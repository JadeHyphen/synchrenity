<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Synchrenity\SynchrenityCore;

class SynchrenityCoreTest extends TestCase
{
    public function testCoreLoads()
    {
        $core = require __DIR__ . '/../bootstrap/app.php';
        $this->assertInstanceOf(SynchrenityCore::class, $core);
    }
}
