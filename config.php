<?php
// Railway provides these variables automatically when you link the services
$db_host = getenv('MYSQLHOST') ?: '127.0.0.1';
$db_user = getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: '';
$db_name = getenv('MYSQL_DATABASE') ?: 'railway'; // Default to your DB name
$db_port = getenv('MYSQLPORT') ?: '3306';

// Establish the connection
$db = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if (!$db) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
