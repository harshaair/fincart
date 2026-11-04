<?php
// db.php - Production & Local Database Configuration
// Supports cloud environment variables (Vercel, Railway, Render, AWS) with local fallback

$host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: "localhost");
$user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: "root");
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : "");
$db   = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: "fincart");
$port = getenv('DB_PORT') ? (int)getenv('DB_PORT') : (getenv('MYSQLPORT') ? (int)getenv('MYSQLPORT') : 3306);

// Initialize mysqli with optional SSL support for cloud databases (Aiven, TiDB, PlanetScale)
$conn = mysqli_init();

if (!$conn) {
    die("Database initialization error.");
}

// Set connection timeout (critical for serverless / cloud latency)
$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

$connected = @$conn->real_connect($host, $user, $pass, $db, $port);

if (!$connected) {
    error_log("Database connection failed: (" . mysqli_connect_errno() . ") " . mysqli_connect_error());
    // In production, avoid leaking credentials; show a clean error message
    die("Database connection failed. Please ensure the database server is running and credentials are configured.");
}

$conn->set_charset("utf8mb4");
?>
