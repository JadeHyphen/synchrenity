<?php
// Example seeder: Populate users table
return function($db) {
    $db->exec("INSERT INTO users (name, email, password) VALUES ('Admin', 'admin@example.com', 'password_hash')");
};
