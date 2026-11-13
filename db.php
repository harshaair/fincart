<?php
// db.php - Production & Local Database Configuration
// Supports cloud environment variables (Vercel, TiDB, Railway, Render, AWS) with local fallback

$host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: "localhost");
$user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: "root");
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : "");
$db   = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: "fincart");
$port = getenv('DB_PORT') ? (int)getenv('DB_PORT') : (getenv('MYSQLPORT') ? (int)getenv('MYSQLPORT') : 3306);

$conn = mysqli_init();

if (!$conn) {
    die("Database initialization error.");
}

// Set connection timeout (fail fast on network issues)
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

// Enable TLS/SSL and connection pooling for remote cloud databases (e.g. TiDB Cloud Serverless)
$connect_host = ($host !== 'localhost' && $host !== '127.0.0.1' && !str_starts_with($host, 'p:')) ? ('p:' . $host) : $host;

if ($host !== 'localhost' && $host !== '127.0.0.1') {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
    $connected = @$conn->real_connect($connect_host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL);
} else {
    $connected = @$conn->real_connect($connect_host, $user, $pass, $db, $port);
}

if (!$connected) {
    error_log("Database connection failed: (" . mysqli_connect_errno() . ") " . mysqli_connect_error());
    die("Database connection failed. Please ensure the database server is running and credentials are configured.");
}

$conn->set_charset("utf8mb4");
?>
