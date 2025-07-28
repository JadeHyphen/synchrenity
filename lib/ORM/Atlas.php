<?php
namespace Synchrenity\ORM;

use Synchrenity\Support\SynchrenityFacade;

class Atlas extends SynchrenityFacade {
    protected static function getServiceName() {
        return 'atlas';
    }
}
