<?php
namespace Synchrenity\Auth;

class SynchrenityRBAC {
    protected $userRoles = [];
    protected $rolePermissions = [];
    protected $roleHierarchy = [];
    protected $auditTrail;

    public function setAuditTrail($auditTrail) {
        $this->auditTrail = $auditTrail;
    }

    // Assign a role to a user
    public function assignRole($userId, $role) {
        if (!isset($this->userRoles[$userId])) {
            $this->userRoles[$userId] = [];
        }
        if (!in_array($role, $this->userRoles[$userId])) {
            $this->userRoles[$userId][] = $role;
            $this->audit('assign_role', $userId, ['role' => $role]);
        }
    }

    // Remove a role from a user
    public function removeRole($userId, $role) {
        if (isset($this->userRoles[$userId])) {
            $idx = array_search($role, $this->userRoles[$userId]);
            if ($idx !== false) {
                unset($this->userRoles[$userId][$idx]);
                $this->userRoles[$userId] = array_values($this->userRoles[$userId]);
                $this->audit('remove_role', $userId, ['role' => $role]);
            }
        }
    }

    // Define permissions for a role
    public function setRolePermissions($role, array $permissions) {
        $this->rolePermissions[$role] = $permissions;
        $this->audit('set_role_permissions', null, ['role' => $role, 'permissions' => $permissions]);
    }

    // Add a permission to a role
    public function addPermission($role, $permission) {
        if (!isset($this->rolePermissions[$role])) {
            $this->rolePermissions[$role] = [];
        }
        if (!in_array($permission, $this->rolePermissions[$role])) {
            $this->rolePermissions[$role][] = $permission;
            $this->audit('add_permission', null, ['role' => $role, 'permission' => $permission]);
        }
    }

    // Remove a permission from a role
    public function removePermission($role, $permission) {
        if (isset($this->rolePermissions[$role])) {
            $idx = array_search($permission, $this->rolePermissions[$role]);
            if ($idx !== false) {
                unset($this->rolePermissions[$role][$idx]);
                $this->rolePermissions[$role] = array_values($this->rolePermissions[$role]);
                $this->audit('remove_permission', null, ['role' => $role, 'permission' => $permission]);
            }
        }
    }

    // Set role hierarchy (role inheritance)
    public function setRoleHierarchy($role, array $inherits) {
        $this->roleHierarchy[$role] = $inherits;
        $this->audit('set_role_hierarchy', null, ['role' => $role, 'inherits' => $inherits]);
    }

    // Check if a user has a permission (including inherited roles)
    public function check($userId, $permission) {
        $roles = $this->getUserRoles($userId);
        foreach ($roles as $role) {
            if ($this->hasPermission($role, $permission)) {
                return true;
            }
        }
        return false;
    }

    // Get all roles for a user (including inherited)
    public function getUserRoles($userId) {
        $roles = $this->userRoles[$userId] ?? [];
        $allRoles = $roles;
        foreach ($roles as $role) {
            $allRoles = array_merge($allRoles, $this->getInheritedRoles($role));
        }
        return array_unique($allRoles);
    }

    // Get inherited roles recursively
    protected function getInheritedRoles($role) {
        $inherited = $this->roleHierarchy[$role] ?? [];
        $all = $inherited;
        foreach ($inherited as $r) {
            $all = array_merge($all, $this->getInheritedRoles($r));
        }
        return $all;
    }

    // Check if a role has a permission (including inherited roles)
    protected function hasPermission($role, $permission) {
        $perms = $this->rolePermissions[$role] ?? [];
        if (in_array($permission, $perms)) return true;
        foreach ($this->roleHierarchy[$role] ?? [] as $parent) {
            if ($this->hasPermission($parent, $permission)) return true;
        }
        return false;
    }

    // List all permissions for a user
    public function getUserPermissions($userId) {
        $roles = $this->getUserRoles($userId);
        $perms = [];
        foreach ($roles as $role) {
            $perms = array_merge($perms, $this->rolePermissions[$role] ?? []);
        }
        return array_unique($perms);
    }

    // List all roles
    public function listRoles() {
        return array_keys($this->rolePermissions);
    }

    // List all permissions
    public function listPermissions() {
        $all = [];
        foreach ($this->rolePermissions as $perms) {
            $all = array_merge($all, $perms);
        }
        return array_unique($all);
    }

    // Audit helper
    protected function audit($action, $userId = null, $meta = []) {
        if ($this->auditTrail) {
            $this->auditTrail->log($action, [], $userId, $meta);
        }
    }
}
