<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Synchrenity\SynchrenityCore;

class SynchrenityCoreTest extends TestCase
{
    public function testCoreLoads()
    {
        ob_start();
        $core = require __DIR__ . '/../bootstrap/app.php';
        ob_end_clean();
        $this->assertInstanceOf(SynchrenityCore::class, $core);
    }
}
