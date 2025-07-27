<?php
namespace Tests;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    public function testDatabaseConnection()
    {
        $db = new \PDO('mysql:host=localhost;dbname=synchrenity', 'root', 'secret');
        $this->assertInstanceOf(\PDO::class, $db);
    }
}
