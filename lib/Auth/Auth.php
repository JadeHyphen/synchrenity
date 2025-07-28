<?php
namespace Synchrenity\Auth;

use Synchrenity\Support\SynchrenityFacade;

class Auth extends SynchrenityFacade {
    protected static function getServiceName() {
        return 'auth';
    }
}
