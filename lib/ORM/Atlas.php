<?php

declare(strict_types=1);

namespace Synchrenity\ORM;

use Synchrenity\Support\SynchrenityFacade;

/**
 * Atlas ORM Facade
 *
 * Provides robust, extensible static access to the Atlas ORM service.
 * Supports macros, event hooks, and context via SynchrenityFacade.
 */
class Atlas extends SynchrenityFacade
{
    /**
     * Get the service name for the Atlas ORM.
     * @return string
     */
    protected static function getServiceName(): string
    {
        return 'atlas';
    }
}
