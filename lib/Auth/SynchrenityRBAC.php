<?php

declare(strict_types=1);

namespace Synchrenity\Auth;

class SynchrenityRBAC
{
    protected array $userRoles       = [];
    protected array $rolePermissions = [];
    protected array $roleHierarchy   = [];
    protected $auditTrail;
    protected array $plugins = [];
    protected array $events  = [];
    protected array $metrics = [
        'checks' => 0,
        'grants' => 0,
        'denies' => 0,
        'errors' => 0,
    ];
    protected array $context = [];

    public function setAuditTrail($auditTrail): void
    {
        $this->auditTrail = $auditTrail;
    }

    // Assign a role to a user
    public function assignRole($userId, string $role): void
    {
        if (empty($role)) {
            throw new \InvalidArgumentException('Role cannot be empty');
        }

        if (!isset($this->userRoles[$userId])) {
            $this->userRoles[$userId] = [];
        }

        if (!in_array($role, $this->userRoles[$userId])) {
            $this->userRoles[$userId][] = $role;
            $this->audit('assign_role', $userId, ['role' => $role]);
            $this->triggerEvent('assign_role', compact('userId', 'role'));

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onAssignRole'])) {
                    $plugin->onAssignRole($userId, $role, $this);
                }
            }
        }
    }

    // Remove a role from a user
    public function removeRole($userId, string $role): void
    {
        if (isset($this->userRoles[$userId])) {
            $idx = array_search($role, $this->userRoles[$userId]);

            if ($idx !== false) {
                unset($this->userRoles[$userId][$idx]);
                $this->userRoles[$userId] = array_values($this->userRoles[$userId]);
                $this->audit('remove_role', $userId, ['role' => $role]);
                $this->triggerEvent('remove_role', compact('userId', 'role'));

                foreach ($this->plugins as $plugin) {
                    if (is_callable([$plugin, 'onRemoveRole'])) {
                        $plugin->onRemoveRole($userId, $role, $this);
                    }
                }
            }
        }
    }

    // Define permissions for a role
    public function setRolePermissions($role, array $permissions)
    {
        $this->rolePermissions[$role] = $permissions;
        $this->audit('set_role_permissions', null, ['role' => $role, 'permissions' => $permissions]);
        $this->triggerEvent('set_role_permissions', compact('role', 'permissions'));

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onSetRolePermissions'])) {
                $plugin->onSetRolePermissions($role, $permissions, $this);
            }
        }
    }

    // Add a permission to a role
    public function addPermission($role, $permission)
    {
        if (!isset($this->rolePermissions[$role])) {
            $this->rolePermissions[$role] = [];
        }

        if (!in_array($permission, $this->rolePermissions[$role])) {
            $this->rolePermissions[$role][] = $permission;
            $this->audit('add_permission', null, ['role' => $role, 'permission' => $permission]);
            $this->triggerEvent('add_permission', compact('role', 'permission'));

            foreach ($this->plugins as $plugin) {
                if (is_callable([$plugin, 'onAddPermission'])) {
                    $plugin->onAddPermission($role, $permission, $this);
                }
            }
        }
    }

    // Remove a permission from a role
    public function removePermission($role, $permission)
    {
        if (isset($this->rolePermissions[$role])) {
            $idx = array_search($permission, $this->rolePermissions[$role]);

            if ($idx !== false) {
                unset($this->rolePermissions[$role][$idx]);
                $this->rolePermissions[$role] = array_values($this->rolePermissions[$role]);
                $this->audit('remove_permission', null, ['role' => $role, 'permission' => $permission]);
                $this->triggerEvent('remove_permission', compact('role', 'permission'));

                foreach ($this->plugins as $plugin) {
                    if (is_callable([$plugin, 'onRemovePermission'])) {
                        $plugin->onRemovePermission($role, $permission, $this);
                    }
                }
            }
        }
    }

    // Set role hierarchy (role inheritance)
    public function setRoleHierarchy($role, array $inherits)
    {
        $this->roleHierarchy[$role] = $inherits;
        $this->audit('set_role_hierarchy', null, ['role' => $role, 'inherits' => $inherits]);
    }

    // Check if a user has a permission (including inherited roles)
    public function check($userId, $permission, $context = [])
    {
        $this->metrics['checks']++;
        $roles = $this->getUserRoles($userId);

        foreach ($roles as $role) {
            if ($this->hasPermission($role, $permission, $context)) {
                $this->metrics['grants']++;
                $this->triggerEvent('grant', compact('userId', 'role', 'permission', 'context'));

                foreach ($this->plugins as $plugin) {
                    if (is_callable([$plugin, 'onGrant'])) {
                        $plugin->onGrant($userId, $role, $permission, $context, $this);
                    }
                }

                return true;
            }
        }
        $this->metrics['denies']++;
        $this->triggerEvent('deny', compact('userId', 'permission', 'context'));

        foreach ($this->plugins as $plugin) {
            if (is_callable([$plugin, 'onDeny'])) {
                $plugin->onDeny($userId, $permission, $context, $this);
            }
        }

        return false;
    }

    // Get all roles for a user (including inherited)
    public function getUserRoles($userId)
    {
        $roles    = $this->userRoles[$userId] ?? [];
        $allRoles = $roles;

        foreach ($roles as $role) {
            $allRoles = array_merge($allRoles, $this->getInheritedRoles($role));
        }

        return array_unique($allRoles);
    }

    // Get inherited roles recursively
    protected function getInheritedRoles($role)
    {
        $inherited = $this->roleHierarchy[$role] ?? [];
        $all       = $inherited;

        foreach ($inherited as $r) {
            $all = array_merge($all, $this->getInheritedRoles($r));
        }

        return $all;
    }

    // Check if a role has a permission (including inherited roles)
    protected function hasPermission($role, $permission, $context = [])
    {
        $perms = $this->rolePermissions[$role] ?? [];

        // Wildcard support
        foreach ($perms as $perm) {
            if ($this->permissionMatch($perm, $permission, $context)) {
                return true;
            }
        }

        foreach ($this->roleHierarchy[$role] ?? [] as $parent) {
            if ($this->hasPermission($parent, $permission, $context)) {
                return true;
            }
        }

        return false;
    }

    // Permission match with wildcards, conditions, context
    protected function permissionMatch($perm, $permission, $context)
    {
        if ($perm === $permission) {
            return true;
        }

        if (strpos($perm, '*') !== false) {
            $pattern = '/^' . str_replace(['*', '.'], ['.*', '\.'], preg_quote($perm, '/')) . '$/i';

            if (preg_match($pattern, $permission)) {
                return true;
            }
        }

        // Time-based, context-aware, deny/allow, etc. (stub for extensibility)
        // Example: if (isset($context['time']) && $perm === 'admin:night' && $context['time'] === 'night') return true;
        return false;
    }
    // Plugin system
    public function registerPlugin($plugin)
    {
        $this->plugins[] = $plugin;
    }
    // Event system
    public function on($event, callable $cb)
    {
        $this->events[$event][] = $cb;
    }
    protected function triggerEvent($event, $data = null)
    {
        foreach ($this->events[$event] ?? [] as $cb) {
            call_user_func($cb, $data, $this);
        }
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
    public function getEvents()
    {
        return $this->events;
    }
    // Search roles/permissions
    public function searchRoles($query)
    {
        return array_values(array_filter(array_keys($this->rolePermissions), fn ($r) => stripos($r, $query) !== false));
    }
    public function searchPermissions($query)
    {
        $all = $this->listPermissions();

        return array_values(array_filter($all, fn ($p) => stripos($p, $query) !== false));
    }

    // List all permissions for a user
    public function getUserPermissions($userId)
    {
        $roles = $this->getUserRoles($userId);
        $perms = [];

        foreach ($roles as $role) {
            $perms = array_merge($perms, $this->rolePermissions[$role] ?? []);
        }

        return array_unique($perms);
    }

    // List all roles
    public function listRoles()
    {
        return array_keys($this->rolePermissions);
    }

    // List all permissions
    public function listPermissions()
    {
        $all = [];

        foreach ($this->rolePermissions as $perms) {
            $all = array_merge($all, $perms);
        }

        return array_unique($all);
    }

    // Audit helper
    protected function audit($action, $userId = null, $meta = [])
    {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
