<?php
//read the variables from Railway's environment, fallback to localhost if working locally
define('DB_SERVER', getenv('MYSQLHOST') ?: 'localhost');
define('DB_USERNAME', getenv('MYSQLUSER') ?: 'root');
define('DB_PASSWORD', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'expensetracker_DB');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');

//establish connection with the port number included
$db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

//check connection safety string
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>

