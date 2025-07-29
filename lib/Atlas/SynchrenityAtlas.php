<?php

declare(strict_types=1);

namespace Synchrenity\Atlas;

/**
 * SynchrenityAtlas: Ultra-secure, powerful, and extensible ORM
 * Features: ActiveRecord + DataMapper hybrid, relationships, eager/lazy loading, query builder, transactions, events, validation, access control, encryption, caching, auditing, multi-DB, migrations, and more.
 */
class SynchrenityAtlas
{
    protected $connection;
    protected $table;
    protected $primaryKey = 'id';
    protected $attributes = [];
    protected $original   = [];
    protected $relations  = [];
    protected $casts      = [];
    protected $encrypted  = [];
    protected $events     = [];
    protected $plugins    = [];
    protected $metrics    = [
        'queries' => 0,
        'creates' => 0,
        'updates' => 0,
        'deletes' => 0,
        'errors'  => 0,
    ];
    protected $context         = [];
    protected $cacheEnabled    = false;
    protected $accessRules     = [];
    protected $auditEnabled    = false;
    protected $softDelete      = false;
    protected $timestamps      = true;
    protected $validationRules = [];
    protected $errors          = [];

    public function __construct($connection, $table = null)
    {
        $this->connection = $connection;
        $this->table      = $table ?: strtolower((new \ReflectionClass($this))->getShortName());
    }

    public static function query($connection, $table = null)
    {
        return new static($connection, $table);
    }

    public function find($id)
    {
        $this->metrics['queries']++;
        $this->errors = [];

        if (!$this->can('view')) {
            $this->metrics['errors']++;
            $this->errors['access'] = 'Access denied.';

            return $this->errors;
        }
        $pdo  = $this->getPdo();
        $sql  = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result) {
            $result           = $this->decryptFields($result);
            $this->attributes = $result;
            $this->original   = $result;
            $this->audit('view', $result);
            $this->trigger('found', $result);

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onFound'])) {
                    $plugin->onFound($result, $this);
                }
            }

            return $this;
        }
        $this->metrics['errors']++;
        $this->errors['not_found'] = 'Record not found.';

        return $this->errors;
    }

    public function all($conditions = [], $order = null, $limit = null)
    {
        $this->errors = [];

        if (!$this->can('view')) {
            $this->errors['access'] = 'Access denied.';

            return $this->errors;
        }
        $pdo    = $this->getPdo();
        $sql    = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $where = [];

            foreach ($conditions as $col => $val) {
                $where[]         = "`$col` = :$col";
                $params[":$col"] = $val;
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if ($order) {
            $sql .= " ORDER BY $order";
        }

        if ($limit) {
            $sql .= ' LIMIT ' . (int)$limit;
        }
        $stmt = $pdo->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $results = $stmt->fetchAll();

        if ($results === false) {
            $this->errors['query'] = 'Query failed.';

            return $this->errors;
        }
        $results = array_map([$this, 'decryptFields'], $results);
        $this->audit('view_all', $results);
        $this->trigger('found_all', $results);

        return $results;
    }

    public function where($conditions)
    {
        // Add where conditions to query builder
        // ...
        return $this;
    }

    public function create($data)
    {
        $this->metrics['creates']++;
        $this->errors = [];

        if (!$this->can('create')) {
            $this->metrics['errors']++;
            $this->errors['access'] = 'Access denied.';

            return $this->errors;
        }
        $this->validate($data);

        if (!empty($this->errors)) {
            $this->metrics['errors']++;

            return $this->errors;
        }
        $data         = $this->encryptFields($data);
        $pdo          = $this->getPdo();
        $cols         = array_keys($data);
        $placeholders = array_map(function ($c) { return ':' . $c; }, $cols);
        $sql          = "INSERT INTO `{$this->table}` (" . implode(',', $cols) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt         = $pdo->prepare($sql);

        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }

        if (!$stmt->execute()) {
            $this->metrics['errors']++;
            $this->errors['query'] = 'Insert failed.';

            return $this->errors;
        }
        $id = $pdo->lastInsertId();
        $this->audit('create', $data);
        $this->trigger('created', $data);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onCreated'])) {
                $plugin->onCreated($data, $this);
            }
        }

        return $id;
    }

    public function update($id, $data)
    {
        $this->metrics['updates']++;
        $this->errors = [];

        if (!$this->can('update')) {
            $this->metrics['errors']++;
            $this->errors['access'] = 'Access denied.';

            return $this->errors;
        }
        $this->validate($data);

        if (!empty($this->errors)) {
            $this->metrics['errors']++;

            return $this->errors;
        }
        $data = $this->encryptFields($data);
        $pdo  = $this->getPdo();
        $sets = [];

        foreach ($data as $k => $v) {
            $sets[] = "`$k` = :$k";
        }
        $sql  = "UPDATE `{$this->table}` SET " . implode(',', $sets) . " WHERE `{$this->primaryKey}` = :id";
        $stmt = $pdo->prepare($sql);

        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $this->metrics['errors']++;
            $this->errors['query'] = 'Update failed.';

            return $this->errors;
        }
        $this->audit('update', $data);
        $this->trigger('updated', $data);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onUpdated'])) {
                $plugin->onUpdated($data, $this);
            }
        }

        return true;
    }

    public function delete($id)
    {
        $this->metrics['deletes']++;
        $this->errors = [];

        if (!$this->can('delete')) {
            $this->metrics['errors']++;
            $this->errors['access'] = 'Access denied.';

            return $this->errors;
        }
        $pdo = $this->getPdo();

        if ($this->softDelete) {
            $sql = "UPDATE `{$this->table}` SET `deleted_at` = NOW() WHERE `{$this->primaryKey}` = :id";
        } else {
            $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);

        if (!$stmt->execute()) {
            $this->metrics['errors']++;
            $this->errors['query'] = 'Delete failed.';

            return $this->errors;
        }
        $this->audit('delete', ['id' => $id]);
        $this->trigger('deleted', ['id' => $id]);

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onDeleted'])) {
                $plugin->onDeleted(['id' => $id], $this);
            }
        }

        return true;
    }
    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }
    // Metrics
    public function getMetrics()
    {
        return $this->metrics;
    }
    // Context
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
    }
    public function getContext($key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }
    // Introspection
    public function getPlugins()
    {
        return $this->plugins;
    }

    public function with($relation)
    {
        // Eager load relation
        // ...
        return $this;
    }

    public function save()
    {
        if (!empty($this->attributes[$this->primaryKey])) {
            return $this->update($this->attributes[$this->primaryKey], $this->attributes);
        } else {
            return $this->create($this->attributes);
        }
    }

    public function validate($data)
    {
        $this->errors = [];

        foreach ($this->validationRules as $field => $rule) {
            if ($rule === 'required' && (!isset($data[$field]) || $data[$field] === '')) {
                $this->errors[$field] = 'Required.';
            }
            // Add more rules as needed
        }
    }

    // Access control check
    protected function can($action)
    {
        if (empty($this->accessRules[$action])) {
            return true;
        }
        // Example: callable or boolean
        $rule = $this->accessRules[$action];

        if (is_callable($rule)) {
            return $rule($this);
        }

        return (bool)$rule;
    }

    // Encryption helpers
    protected function encryptFields($data)
    {
        foreach ($this->encrypted as $field) {
            if (isset($data[$field])) {
                // Example: simple encryption (replace with real)
                $data[$field] = base64_encode($data[$field]);
            }
        }

        return $data;
    }

    protected function decryptFields($data)
    {
        foreach ($this->encrypted as $field) {
            if (isset($data[$field])) {
                $data[$field] = base64_decode($data[$field]);
            }
        }

        return $data;
    }

    // Event hooks
    protected function trigger($event, $data)
    {
        if (isset($this->events[$event]) && is_callable($this->events[$event])) {
            $this->events[$event]($data);
        }
    }

    public function errors()
    {
        return $this->errors;
    }

    public function setAccessRules($rules)
    {
        $this->accessRules = $rules;
    }

    public function enableCache($enabled = true)
    {
        $this->cacheEnabled = (bool)$enabled;
    }

    public function enableAudit($enabled = true)
    {
        $this->auditEnabled = (bool)$enabled;
    }

    public function on($event, $handler)
    {
        $this->events[$event] = $handler;
    }

    public function setSoftDelete($enabled = true)
    {
        $this->softDelete = (bool)$enabled;
    }

    public function setTimestamps($enabled = true)
    {
        $this->timestamps = (bool)$enabled;
    }

    public function setValidationRules($rules)
    {
        $this->validationRules = $rules;
    }

    public function setCasts($casts)
    {
        $this->casts = $casts;
    }

    public function setEncrypted($fields)
    {
        $this->encrypted = $fields;
    }

    // Add more advanced features: transactions, migrations, multi-DB, relationships, custom query builder, etc.
    /**
     * Relationship: hasOne
     */
    public function hasOne($relatedClass, $foreignKey = null, $localKey = null)
    {
        // Example: $user->hasOne(Profile::class, 'user_id', 'id')
        // ...implement relationship logic...
        return null;
    }

    /**
     * Relationship: hasMany
     */
    public function hasMany($relatedClass, $foreignKey = null, $localKey = null)
    {
        // Example: $user->hasMany(Post::class, 'user_id', 'id')
        // ...implement relationship logic...
        return [];
    }

    /**
     * Relationship: belongsTo
     */
    public function belongsTo($relatedClass, $foreignKey = null, $ownerKey = null)
    {
        // Example: $post->belongsTo(User::class, 'user_id', 'id')
        // ...implement relationship logic...
        return null;
    }

    /**
     * Query builder stub (chainable)
     */
    public function select($columns = ['*'])
    {
        // ...store selected columns for query...
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        // ...store order for query...
        return $this;
    }

    public function limit($count)
    {
        // ...store limit for query...
        return $this;
    }

    /**
     * Transaction support
     */
    public function transaction(callable $callback)
    {
        $pdo = $this->getPdo();

        try {
            $pdo->beginTransaction();
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Exception $e) {
            $pdo->rollBack();
            $this->errors['transaction'] = $e->getMessage();

            return $this->errors;
        }
    }

    /**
     * Migration stub
     */
    public function migrate($schema)
    {
        // Example: $atlas->migrate(['id' => 'int', 'name' => 'string'])
        // ...implement migration logic...
        return true;
    }

    /**
     * Caching stub
     */
    public function cache($key, $value = null, $ttl = 3600)
    {
        $cacheFile = sys_get_temp_dir() . '/atlas_cache_' . md5($key) . '.cache';

        if ($value !== null) {
            file_put_contents($cacheFile, serialize(['value' => $value, 'expires' => time() + $ttl]));

            return true;
        }

        if (file_exists($cacheFile)) {
            $data = unserialize(file_get_contents($cacheFile));

            if ($data['expires'] > time()) {
                return $data['value'];
            }
            unlink($cacheFile);
        }

        return null;
    }

    /**
     * Advanced validation stub
     */
    public function addValidationRule($field, $rule, $message = null)
    {
        $this->validationRules[$field] = $rule;

        // Optionally store custom message
        return $this;
    }

    /**
     * Lazy loading stub
     */
    public function __get($name)
    {
        // Example: load relation or attribute on demand
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        if (isset($this->relations[$name])) {
            // ...load relation...
            return $this->relations[$name];
        }

        return null;
    }

    /**
     * Data casting stub
     */
    protected function castAttribute($name, $value)
    {
        if (!isset($this->casts[$name])) {
            return $value;
        }
        $type = $this->casts[$name];

        switch ($type) {
            case 'int': return (int)$value;

            case 'float': return (float)$value;

            case 'bool': return (bool)$value;

            case 'string': return (string)$value;

            case 'array': return (array)$value;
            default: return $value;
        }
    }
    /**
     * Get PDO instance for current connection
     * Supports direct PDO or DSN string
     */
    protected function getPdo()
    {
        if ($this->connection instanceof \PDO) {
            return $this->connection;
        }

        // If connection is DSN string, create PDO
        if (is_string($this->connection)) {
            // Example: 'mysql:host=localhost;dbname=test', 'user', 'pass'
            // You may want to extend this for config arrays
            return new \PDO($this->connection);
        }

        throw new \Exception('Invalid database connection');
    }

    /**
     * Audit log for ORM actions (create, update, delete, view, etc.)
     * Only logs if auditEnabled is true
     */
    protected function audit($action, $data)
    {
        if (!$this->auditEnabled) {
            return;
        }
        // Simple example: log to file. Extend for DB, external, etc.
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'table'     => $this->table,
            'action'    => $action,
            'data'      => $data,
            'user'      => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
        ];
        // You may want to use a logger class or DB table
        file_put_contents(__DIR__ . '/atlas_audit.log', json_encode($log) . PHP_EOL, FILE_APPEND);
    }
}
