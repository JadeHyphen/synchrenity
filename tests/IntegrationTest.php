<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function testDatabaseConnection()
    {
        if (getenv('CI')) {
            $this->markTestSkipped('Skipping DB test in CI');

            return;
        }

        try {
            $db = new \PDO('mysql:host=localhost;dbname=synchrenity', 'root', 'secret');
            $this->assertInstanceOf(\PDO::class, $db);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Skipping DB test: ' . $e->getMessage());
        }
    }
}
