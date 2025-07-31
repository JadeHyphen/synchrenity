# Synchrenity Atlas ORM

Synchrenity Atlas is an ultra-secure, powerful, and extensible ORM that combines ActiveRecord and DataMapper patterns with relationships, query building, transactions, events, validation, and advanced security features.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Model Definition](#model-definition)
- [Query Building](#query-building)
- [Relationships](#relationships)
- [Transactions](#transactions)
- [Events & Hooks](#events--hooks)
- [Validation](#validation)
- [Security Features](#security-features)
- [Caching](#caching)
- [Advanced Features](#advanced-features)

## Overview

Atlas provides:

- **Hybrid ORM**: Combines ActiveRecord simplicity with DataMapper flexibility
- **Query Builder**: Fluent, SQL-injection-safe query construction
- **Relationships**: One-to-one, one-to-many, many-to-many, polymorphic
- **Security**: Field encryption, access control, audit logging
- **Validation**: Built-in and custom validation rules
- **Events**: Model lifecycle hooks and custom events
- **Transactions**: Nested transactions with rollback support
- **Caching**: Query and model caching with invalidation
- **Multi-database**: Support for multiple database connections

## Quick Start

```php
use Synchrenity\Atlas\SynchrenityAtlas;

// Initialize connection
$pdo = new PDO('mysql:host=localhost;dbname=app', $username, $password);
$atlas = new SynchrenityAtlas($pdo);

// Basic operations
$user = $atlas->table('users')->find(1);
$users = $atlas->table('users')->where('active', true)->get();

// Create new record
$user = $atlas->table('users')->create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Update record
$user->update(['name' => 'Jane Doe']);

// Delete record
$user->delete();
```

## Model Definition

### Basic Model

```php
use Synchrenity\Atlas\SynchrenityAtlas;

class User extends SynchrenityAtlas
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $timestamps = true;
    
    // Define relationships
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
    
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}
```

### Advanced Model Configuration

```php
class User extends SynchrenityAtlas
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $timestamps = true;
    protected $softDelete = true;
    protected $auditEnabled = true;
    protected $cacheEnabled = true;
    
    // Field casting
    protected $casts = [
        'active' => 'boolean',
        'settings' => 'json',
        'created_at' => 'datetime',
        'metadata' => 'array'
    ];
    
    // Encrypted fields
    protected $encrypted = ['ssn', 'credit_card'];
    
    // Validation rules
    protected $validationRules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'age' => 'integer|min:18|max:120'
    ];
    
    // Access control
    protected $accessRules = [
        'read' => ['admin', 'user'],
        'write' => ['admin'],
        'delete' => ['admin']
    ];
}
```

## Query Building

### Basic Queries

```php
// Find by ID
$user = User::find(1);

// Find by attributes
$user = User::where('email', 'john@example.com')->first();

// Multiple conditions
$users = User::where('active', true)
             ->where('age', '>', 18)
             ->where('city', 'like', '%New York%')
             ->get();

// Order and limit
$users = User::orderBy('created_at', 'desc')
             ->limit(10)
             ->offset(20)
             ->get();
```

### Advanced Queries

```php
// Complex WHERE clauses
$users = User::where(function($query) {
    $query->where('age', '>', 25)
          ->orWhere('verified', true);
})->where('active', true)->get();

// Joins
$users = User::join('profiles', 'users.id', '=', 'profiles.user_id')
             ->select('users.*', 'profiles.bio')
             ->where('profiles.verified', true)
             ->get();

// Subqueries
$users = User::whereIn('id', function($query) {
    $query->select('user_id')
          ->from('orders')
          ->where('total', '>', 1000);
})->get();

// Raw queries (use with caution)
$users = User::whereRaw('YEAR(created_at) = ?', [2024])->get();
```

### Aggregations

```php
// Count
$userCount = User::count();
$activeUsers = User::where('active', true)->count();

// Other aggregations
$totalAge = User::sum('age');
$avgAge = User::avg('age');
$maxAge = User::max('age');
$minAge = User::min('age');

// Group by
$usersByCity = User::select('city', 'COUNT(*) as count')
                   ->groupBy('city')
                   ->having('count', '>', 10)
                   ->get();
```

## Relationships

### One-to-One

```php
class User extends SynchrenityAtlas
{
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}

class Profile extends SynchrenityAtlas
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Usage
$user = User::find(1);
$profile = $user->profile; // Lazy loading
$user = User::with('profile')->find(1); // Eager loading
```

### One-to-Many

```php
class User extends SynchrenityAtlas
{
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

class Post extends SynchrenityAtlas
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts; // Get all posts
$activePosts = $user->posts()->where('published', true)->get();
```

### Many-to-Many

```php
class User extends SynchrenityAtlas
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
}

class Role extends SynchrenityAtlas
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id');
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles; // Get all roles
$user->roles()->attach($roleId); // Add role
$user->roles()->detach($roleId); // Remove role
$user->roles()->sync([$role1, $role2]); // Sync roles
```

### Polymorphic Relationships

```php
class Comment extends SynchrenityAtlas
{
    public function commentable()
    {
        return $this->morphTo();
    }
}

class Post extends SynchrenityAtlas
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Video extends SynchrenityAtlas
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
```

## Transactions

### Basic Transactions

```php
// Simple transaction
$atlas->transaction(function() {
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
    $profile = Profile::create(['user_id' => $user->id, 'bio' => 'Developer']);
});

// Manual transaction control
$atlas->beginTransaction();
try {
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
    $profile = Profile::create(['user_id' => $user->id, 'bio' => 'Developer']);
    $atlas->commit();
} catch (\Exception $e) {
    $atlas->rollback();
    throw $e;
}
```

### Nested Transactions

```php
$atlas->transaction(function() {
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
    
    // Nested transaction
    $atlas->transaction(function() use ($user) {
        $profile = Profile::create(['user_id' => $user->id, 'bio' => 'Developer']);
        
        if (!$profile->validate()) {
            throw new \Exception('Profile validation failed');
        }
    });
    
    $user->sendWelcomeEmail();
});
```

### Savepoints

```php
$atlas->beginTransaction();

try {
    $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
    
    $savepoint = $atlas->savepoint('user_created');
    
    try {
        $profile = Profile::create(['user_id' => $user->id, 'bio' => 'Developer']);
    } catch (\Exception $e) {
        $atlas->rollbackToSavepoint($savepoint);
        // Continue with user creation without profile
    }
    
    $atlas->commit();
} catch (\Exception $e) {
    $atlas->rollback();
    throw $e;
}
```

## Events & Hooks

### Model Events

```php
class User extends SynchrenityAtlas
{
    protected function onBeforeCreate($attributes)
    {
        // Hash password before saving
        if (isset($attributes['password'])) {
            $attributes['password'] = password_hash($attributes['password'], PASSWORD_DEFAULT);
        }
        return $attributes;
    }
    
    protected function onAfterCreate($user)
    {
        // Send welcome email
        $this->sendWelcomeEmail($user);
        
        // Create default profile
        Profile::create(['user_id' => $user->id]);
    }
    
    protected function onBeforeUpdate($attributes, $user)
    {
        // Log changes
        $this->logChanges($user, $attributes);
        return $attributes;
    }
    
    protected function onAfterDelete($user)
    {
        // Clean up related data
        $user->profile()->delete();
        $user->posts()->delete();
    }
}
```

### Global Events

```php
// Register global event listeners
User::addEventListener('creating', function($user) {
    // Validate user data
    if (!$user->validate()) {
        throw new ValidationException($user->getErrors());
    }
});

User::addEventListener('created', function($user) {
    // Log user creation
    Log::info('User created', ['user_id' => $user->id]);
});

User::addEventListener('updating', function($user) {
    // Audit trail
    AuditLog::create([
        'model' => 'User',
        'model_id' => $user->id,
        'changes' => $user->getDirty()
    ]);
});
```

### Custom Events

```php
class User extends SynchrenityAtlas
{
    public function promoteToAdmin()
    {
        $this->fireEvent('promoting_to_admin', $this);
        
        $this->update(['role' => 'admin']);
        
        $this->fireEvent('promoted_to_admin', $this);
    }
}

// Listen for custom events
User::addEventListener('promoted_to_admin', function($user) {
    // Send notification
    Notification::send($user, new AdminPromotionNotification());
    
    // Update permissions cache
    PermissionCache::clearUser($user->id);
});
```

## Validation

### Built-in Validation

```php
class User extends SynchrenityAtlas
{
    protected $validationRules = [
        'name' => 'required|string|min:2|max:255',
        'email' => 'required|email|unique:users,email',
        'age' => 'integer|min:18|max:120',
        'phone' => 'phone|nullable',
        'website' => 'url|nullable'
    ];
    
    protected $validationMessages = [
        'name.required' => 'Name is required',
        'email.unique' => 'Email already exists',
        'age.min' => 'Must be at least 18 years old'
    ];
}

// Usage
$user = new User([
    'name' => 'John',
    'email' => 'invalid-email'
]);

if (!$user->validate()) {
    $errors = $user->getErrors();
    // Handle validation errors
}
```

### Custom Validation Rules

```php
class User extends SynchrenityAtlas
{
    protected $validationRules = [
        'username' => 'required|username_format',
        'password' => 'required|strong_password'
    ];
    
    protected function validateUsernameFormat($value)
    {
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $value);
    }
    
    protected function validateStrongPassword($value)
    {
        return strlen($value) >= 8 && 
               preg_match('/[A-Z]/', $value) && 
               preg_match('/[a-z]/', $value) && 
               preg_match('/[0-9]/', $value);
    }
}
```

### Conditional Validation

```php
class User extends SynchrenityAtlas
{
    protected function getValidationRules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email'
        ];
        
        // Add unique constraint for updates
        if ($this->exists) {
            $rules['email'] .= '|unique:users,email,' . $this->id;
        } else {
            $rules['email'] .= '|unique:users,email';
        }
        
        // Require password for new users
        if (!$this->exists) {
            $rules['password'] = 'required|min:8';
        }
        
        return $rules;
    }
}
```

## Security Features

### Field Encryption

```php
class User extends SynchrenityAtlas
{
    protected $encrypted = ['ssn', 'credit_card', 'sensitive_data'];
    
    // Encryption is automatic
    public function setSsn($value)
    {
        $this->attributes['ssn'] = $value; // Will be encrypted
    }
    
    public function getSsn()
    {
        return $this->attributes['ssn']; // Will be decrypted
    }
}

// Usage
$user = new User();
$user->ssn = '123-45-6789'; // Stored encrypted
echo $user->ssn; // Returns decrypted value
```

### Access Control

```php
class User extends SynchrenityAtlas
{
    protected $accessRules = [
        'read' => ['admin', 'user', 'owner'],
        'write' => ['admin', 'owner'],
        'delete' => ['admin']
    ];
    
    protected function canRead($user)
    {
        return $user->hasRole(['admin', 'user']) || $user->id === $this->id;
    }
    
    protected function canWrite($user)
    {
        return $user->hasRole('admin') || $user->id === $this->id;
    }
    
    protected function canDelete($user)
    {
        return $user->hasRole('admin');
    }
}

// Usage with current user context
$currentUser = Auth::user();
$user = User::find(1);

if ($user->canRead($currentUser)) {
    // Allow read access
}

if ($user->canWrite($currentUser)) {
    // Allow write access
}
```

### Audit Logging

```php
class User extends SynchrenityAtlas
{
    protected $auditEnabled = true;
    protected $auditFields = ['name', 'email', 'role']; // Only audit specific fields
    
    // Custom audit data
    protected function getAuditData()
    {
        return [
            'user_id' => Auth::id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];
    }
}

// View audit trail
$auditTrail = $user->getAuditTrail();
foreach ($auditTrail as $entry) {
    echo "Changed {$entry->field} from {$entry->old_value} to {$entry->new_value}";
}
```

## Caching

### Query Caching

```php
class User extends SynchrenityAtlas
{
    protected $cacheEnabled = true;
    protected $cacheTtl = 3600; // 1 hour
    
    // Cache specific queries
    public static function getActiveUsers()
    {
        return static::where('active', true)
                     ->cache('active_users', 1800) // 30 minutes
                     ->get();
    }
}

// Manual cache control
$users = User::where('role', 'admin')->remember(3600)->get();
User::forgetCache('active_users');
User::flushCache(); // Clear all cache for this model
```

### Model Caching

```php
class User extends SynchrenityAtlas
{
    protected $cacheEnabled = true;
    
    // Cached find
    public static function findCached($id)
    {
        return static::cache("user:$id", 3600, function() use ($id) {
            return static::find($id);
        });
    }
}

// Usage
$user = User::findCached(1); // Cached for 1 hour
$user->clearCache(); // Clear this model's cache
```

### Cache Invalidation

```php
class User extends SynchrenityAtlas
{
    protected function onAfterSave()
    {
        // Invalidate related caches
        $this->clearCache();
        Cache::forget('active_users');
        Cache::tags(['users', "user:{$this->id}"])->flush();
    }
}
```

## Advanced Features

### Soft Deletes

```php
class User extends SynchrenityAtlas
{
    protected $softDelete = true;
    protected $deletedAtColumn = 'deleted_at';
}

// Usage
$user = User::find(1);
$user->delete(); // Soft delete (sets deleted_at)
$user->restore(); // Restore soft deleted
$user->forceDelete(); // Permanent delete

// Query soft deleted
$allUsers = User::withTrashed()->get();
$deletedUsers = User::onlyTrashed()->get();
```

### Scopes

```php
class User extends SynchrenityAtlas
{
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
    
    public function scopeAdults($query)
    {
        return $query->where('age', '>=', 18);
    }
    
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }
}

// Usage
$activeUsers = User::active()->get();
$activeAdults = User::active()->adults()->get();
$admins = User::byRole('admin')->get();
```

### Mutators and Accessors

```php
class User extends SynchrenityAtlas
{
    // Mutator (set)
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = ucfirst(strtolower($value));
    }
    
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }
    
    // Accessor (get)
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    public function getAvatarUrlAttribute()
    {
        return "https://gravatar.com/avatar/" . md5($this->email);
    }
}

// Usage
$user = new User();
$user->name = 'JOHN DOE'; // Stored as "John doe"
$user->password = 'secret'; // Stored as hash

echo $user->full_name; // "John Doe"
echo $user->avatar_url; // Gravatar URL
```

### Collections

```php
// Atlas returns collections for multiple results
$users = User::where('active', true)->get();

// Collection methods
$activeUsers = $users->where('active', true);
$userNames = $users->pluck('name');
$groupedByRole = $users->groupBy('role');
$averageAge = $users->avg('age');

// Transform collection
$transformed = $users->map(function($user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email
    ];
});

// Filter collection
$admins = $users->filter(function($user) {
    return $user->role === 'admin';
});
```

## Best Practices

1. **Use relationships** instead of manual joins when possible
2. **Enable query caching** for frequently accessed data
3. **Implement validation** at the model level
4. **Use transactions** for related operations
5. **Enable audit logging** for sensitive models
6. **Encrypt sensitive fields** automatically
7. **Use scopes** for common query patterns
8. **Implement soft deletes** for important data
9. **Use eager loading** to avoid N+1 queries
10. **Follow naming conventions** for tables and columns