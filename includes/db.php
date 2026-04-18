<?php
// includes/db.php

require_once __DIR__ . '/../config.php';

function getDBConnection(): mysqli
{
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }

    // Important for Myanmar Unicode
    $conn->set_charset('utf8mb4');

    return $conn;
}
?>