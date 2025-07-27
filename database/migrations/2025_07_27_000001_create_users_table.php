<?php
// Example migration: Create users table
return [
    'up' => function($db) {
        $db->exec('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255), password VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    },
    'down' => function($db) {
        $db->exec('DROP TABLE users');
    }
];
