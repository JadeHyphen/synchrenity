<?php

declare(strict_types=1);
// Synchrenity Policy base and manager

namespace Synchrenity\Security;

class SynchrenityPolicy
{
    /**
     * Called before any ability check. Override to grant/deny all abilities for a user.
     * Return true/false to short-circuit, or null to continue.
     */
    public function before($user, $ability, ...$args)
    {
        return null;
    }

    /**
     * Called after an ability check. Override for audit/logging.
     */
    public function after($user, $ability, $result, ...$args)
    {
        // Optionally log or audit
        return $result;
    }
}

class SynchrenityPolicyManager
{
    // Robustness: ensure all new properties are defined
    protected $cacheEnabled      = true;
    protected $abilityAliases    = [];
    protected $fallbackPolicy    = null;
    protected $context           = [];
    protected $policyVersions    = [];
    protected $eventHooks        = [ 'before' => [], 'after' => [], 'audit' => [] ];
    protected $policies          = [];
    protected $policyGroups      = [];
    protected $auditTrail        = [];
    protected $snapshots         = [];
    protected $importExportHooks = [ 'import' => [], 'export' => [] ];
    protected $healthStats       = [ 'checks' => 0, 'authorizations' => 0, 'denials' => 0, 'errors' => 0 ];
    protected $userResolver;
    protected $cache = [];
    protected $denyCallback;
    protected $auditCallback;

    public function __construct(callable $userResolver = null)
    {
        $this->userResolver = $userResolver;
    }

    // Policy cache control
    // Policy groups
    public function group($group, array $models)
    {
        $this->policyGroups[$group] = $models;
    }
    public function getGroup($group)
    {
        return $this->policyGroups[$group] ?? [];
    }

    // Dynamic policy loading
    public function loadPolicy($model, $file)
    {
        require_once $file;
    }

    // Per-ability deny/allow hooks
    protected $abilityHooks = [ 'allow' => [], 'deny' => [] ];
    public function onAllow($ability, $cb)
    {
        $this->abilityHooks['allow'][$ability][] = $cb;
    }
    public function onDenyAbility($ability, $cb)
    {
        $this->abilityHooks['deny'][$ability][] = $cb;
    }

    // Policy audit trail
    public function auditTrail()
    {
        return $this->auditTrail;
    }

    // Policy snapshot/restore
    public function snapshot($name = 'default')
    {
        $this->snapshots[$name] = [ 'policies' => $this->policies, 'cache' => $this->cache ];
    }
    public function restore($name = 'default')
    {
        if (isset($this->snapshots[$name])) {
            $this->policies = $this->snapshots[$name]['policies'];
            $this->cache    = $this->snapshots[$name]['cache'];
        }
    }

    // Import/export hooks
    public function onImport($cb)
    {
        $this->importExportHooks['import'][] = $cb;
    }
    public function onExport($cb)
    {
        $this->importExportHooks['export'][] = $cb;
    }
    public function enableCache($on = true)
    {
        $this->cacheEnabled = $on;
    }
    public function clearCache()
    {
        $this->cache = [];
    }

    // Ability aliasing
    public function alias($alias, $ability)
    {
        $this->abilityAliases[$alias] = $ability;
    }

    // Fallback policy
    public function setFallbackPolicy($policyClass)
    {
        $this->fallbackPolicy = $policyClass;
    }

    // Context injection
    public function setContext(array $ctx)
    {
        $this->context = $ctx;
    }
    public function getContext()
    {
        return $this->context;
    }

    // Policy versioning
    public function setPolicyVersion($model, $version)
    {
        $this->policyVersions[$model] = $version;
    }
    public function getPolicyVersion($model)
    {
        return $this->policyVersions[$model] ?? null;
    }

    // Policy event hooks
    public function on($event, $cb)
    {
        if (isset($this->eventHooks[$event])) {
            $this->eventHooks[$event][] = $cb;
        }
    }
    protected function trigger($event, ...$args)
    {
        if (isset($this->eventHooks[$event])) {
            foreach ($this->eventHooks[$event] as $cb) {
                call_user_func_array($cb, $args);
            }
        }
    }

    // Policy import/export
    public function export($file)
    {
        foreach ($this->importExportHooks['export'] as $cb) {
            call_user_func($cb, $this);
        }
        file_put_contents($file, serialize([$this->policies, $this->policyVersions]));
    }
    public function import($file)
    {
        if (file_exists($file)) {
            list($this->policies, $this->policyVersions) = unserialize(file_get_contents($file));

            foreach ($this->importExportHooks['import'] as $cb) {
                call_user_func($cb, $this);
            }
        }
    }

    // Health check
    public function healthCheck()
    {
        $this->healthStats['checks']++;

        return is_array($this->policies) && is_array($this->cache);
    }
    public function healthStats()
    {
        return $this->healthStats;
    }

    /**
     * Register a policy for a model/class.
     * Policy class must be named {ModelName}Policy by convention.
     */
    public function register($model, $policyClass = null)
    {
        if ($policyClass === null) {
            // Guess policy class name by convention
            if (is_object($model)) {
                $model = get_class($model);
            }
            $base        = is_string($model) ? (substr(strrchr($model, '\\'), 1) ?: $model) : $model;
            $policyClass = $base . 'Policy';

            if (class_exists('App\\Policies\\' . $policyClass)) {
                $policyClass = 'App\\Policies\\' . $policyClass;
            } elseif (class_exists('Synchrenity\\Policies\\' . $policyClass)) {
                $policyClass = 'Synchrenity\\Policies\\' . $policyClass;
            }
        }
        $this->policies[$model] = $policyClass;
    }

    // Policy discovery (list all registered policies)
    public function allPolicies()
    {
        return $this->policies;
    }

    /**
     * Register multiple policies at once.
     * Accepts [ModelClass => PolicyClass] or [ModelClass] (uses convention).
     */
    public function registerMany(array $map)
    {
        foreach ($map as $model => $policy) {
            if (is_int($model)) {
                $this->register($policy);
            } else {
                $this->register($model, $policy);
            }
        }
    }

    /**
     * Set a callback to resolve the current user.
     */
    public function setUserResolver(callable $resolver)
    {
        $this->userResolver = $resolver;
    }

    /**
     * Set a callback to run on denied authorization.
     */
    public function onDeny(callable $callback)
    {
        $this->denyCallback = $callback;
    }

    /**
     * Set a callback to run for auditing all checks.
     */
    public function onAudit(callable $callback)
    {
        $this->auditCallback = $callback;
    }

    /**
     * Get the policy instance for a model/class.
     */
    public function getPolicy($model)
    {
        if ($this->cacheEnabled && isset($this->cache[$model])) {
            return $this->cache[$model];
        }

        if (!isset($this->policies[$model])) {
            if ($this->fallbackPolicy) {
                $policy = new $this->fallbackPolicy();

                if ($this->cacheEnabled) {
                    $this->cache[$model] = $policy;
                }

                return $policy;
            }

            return null;
        }
        $policy = new $this->policies[$model]();

        if ($this->cacheEnabled) {
            $this->cache[$model] = $policy;
        }

        return $policy;
    }

    /**
     * Authorize an ability for the current user and model/args.
     */
    public function authorize($ability, $model, ...$args)
    {
        $this->healthStats['authorizations']++;
        $user   = $this->resolveUser();
        $policy = $this->getPolicy($model);

        if (!$policy) {
            $this->healthStats['errors']++;
            $this->audit('policy.missing', $user, $ability, $model, $args, false);

            throw new \Exception("No policy registered for model: $model");
        }

        // Ability aliasing
        if (isset($this->abilityAliases[$ability])) {
            $ability = $this->abilityAliases[$ability];
        }

        // Context injection
        if (method_exists($policy, 'setContext')) {
            $policy->setContext($this->context);
        }
        // Before event hook
        $this->trigger('before', $user, $ability, $model, $args);

        // Before hook
        if (method_exists($policy, 'before')) {
            $result = $policy->before($user, $ability, ...$args);

            if (!is_null($result)) {
                $this->audit('policy.before', $user, $ability, $model, $args, $result);
                $this->trigger('after', $user, $ability, $model, $args, $result);
                $this->auditTrail[] = [ 'event' => 'before', 'user' => $user, 'ability' => $ability, 'model' => $model, 'args' => $args, 'result' => $result, 'time' => time() ];

                return $this->after($policy, $user, $ability, $result, ...$args);
            }
        }

        if (!method_exists($policy, $ability)) {
            $this->healthStats['errors']++;
            $this->audit('policy.ability_missing', $user, $ability, $model, $args, false);

            throw new \Exception("Policy does not define ability: $ability");
        }
        $result = (bool) $policy->$ability($user, ...$args);
        $result = $this->after($policy, $user, $ability, $result, ...$args);
        $this->audit('policy.checked', $user, $ability, $model, $args, $result);
        $this->trigger('after', $user, $ability, $model, $args, $result);
        $this->auditTrail[] = [ 'event' => 'checked', 'user' => $user, 'ability' => $ability, 'model' => $model, 'args' => $args, 'result' => $result, 'time' => time() ];

        // Per-ability allow/deny hooks
        if ($result && isset($this->abilityHooks['allow'][$ability])) {
            foreach ($this->abilityHooks['allow'][$ability] as $cb) {
                call_user_func($cb, $user, $ability, $model, $args);
            }
        }

        if (!$result) {
            $this->healthStats['denials']++;

            if (isset($this->abilityHooks['deny'][$ability])) {
                foreach ($this->abilityHooks['deny'][$ability] as $cb) {
                    call_user_func($cb, $user, $ability, $model, $args);
                }
            }

            if ($this->denyCallback) {
                call_user_func($this->denyCallback, $user, $ability, $model, $args);
            }
        }

        return $result;
    }

    /**
     * Check if the user is authorized for the ability (alias for authorize).
     */
    public function allows($ability, $model, ...$args)
    {
        return $this->authorize($ability, $model, ...$args);
    }

    /**
     * Check if the user is NOT authorized for the ability.
     */
    public function denies($ability, $model, ...$args)
    {
        return !$this->authorize($ability, $model, ...$args);
    }

    /**
     * Run the after hook on the policy.
     */
    protected function after($policy, $user, $ability, $result, ...$args)
    {
        if (method_exists($policy, 'after')) {
            return $policy->after($user, $ability, $result, ...$args);
        }

        return $result;
    }

    /**
     * Audit all checks if an audit callback is set.
     */
    protected function audit($event, $user, $ability, $model, $args, $result)
    {
        if ($this->auditCallback) {
            call_user_func($this->auditCallback, [
                'event'     => $event,
                'user'      => $user,
                'ability'   => $ability,
                'model'     => $model,
                'args'      => $args,
                'result'    => $result,
                'timestamp' => time(),
                'ip'        => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        }
    }

    /**
     * Resolve the current user.
     */
    protected function resolveUser()
    {
        if ($this->userResolver) {
            return call_user_func($this->userResolver);
        }

        // Default: try $_SESSION['user']
        return $_SESSION['user'] ?? null;
    }
}
