<?php
// Simple script to create the database if it doesn't exist.
require_once 'config.php';

// config.php currently attempts to connect to DB name; we'll create a temporary mysqli without db to run CREATE DATABASE.
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

$mysqli = new mysqli($host, $user, $pass);
if ($mysqli->connect_error) {
    echo 'Connection to MySQL failed: ' . $mysqli->connect_error;
    exit;
}

if ($mysqli->query("CREATE DATABASE IF NOT EXISTS `" . $mysqli->real_escape_string($dbname) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    echo "Database '$dbname' is present or created successfully.<br>";
    echo "Run <a href=\"setup_database.php\">setup_database.php</a> next to create tables.<br>";
} else {
    echo 'Failed to create database: ' . $mysqli->error;
}
$mysqli->close();
?>