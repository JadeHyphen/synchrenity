<?php
// config/api_rate_limits.php
return [
    'default' => [
        'default' => [ 'limit' => 100, 'window' => 60, 'burst' => 20, 'burstWindow' => 10 ]
    ],
    'GET:/api/resource' => [
        'user' => [ 'limit' => 50, 'window' => 60, 'burst' => 10, 'burstWindow' => 10 ],
        'admin' => [ 'limit' => 200, 'window' => 60, 'burst' => 40, 'burstWindow' => 10 ]
    ]
    // Add more endpoint/role configs as needed
];
