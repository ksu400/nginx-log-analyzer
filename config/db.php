<?php

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'modimio_logs';
$user = getenv('DB_USER') ?: 'yii2';
$pass = getenv('DB_PASS') ?: '';

return [
    'class' => \yii\db\Connection::class,
    'dsn' => "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8mb4',
];
