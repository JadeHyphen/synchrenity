<?php
namespace Synchrenity\Admin;

class SynchrenityAdminPanel {
    public function showDashboard($core = null) {
        // Fast, secure admin dashboard logic
        $auditStats = $core ? $core->audit()->dashboard() : [];
        return [
            'message' => 'Admin dashboard loaded.',
            'audit' => $auditStats
        ];
    }
}
