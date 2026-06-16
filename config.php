
<?php
// Enable explicit internal error reporting for troubleshooting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db_host = getenv('MYSQLHOST') ?: $_ENV['MYSQLHOST'] ?: '127.0.0.1';
    $db_user = getenv('MYSQLUSER') ?: $_ENV['MYSQLUSER'] ?: 'root';
    $db_pass = getenv('MYSQLPASSWORD') ?: $_ENV['MYSQLPASSWORD'] ?: '';
    $db_name = getenv('MYSQLDATABASE') ?: $_ENV['MYSQLDATABASE'] ?: '';
    $db_port = getenv('MYSQLPORT') ?: $_ENV['MYSQLPORT'] ?: '3306';

    $db = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);
} catch (Exception $e) {
    // This stops the 500 white screen and prints out the actual issue
    echo "Database Connection Error: " . $e->getMessage();
    exit();
}
?>
