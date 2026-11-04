<?php
require_once "includes/security.php";
init_secure_session();
include "db.php";

// Basic validation
if (!isset($_POST['name']) || !isset($_POST['email']) || !isset($_POST['password'])) {
    echo "Please fill in all required fields.";
    exit();
}

// Enforce Rate Limiting (5 registrations per 15 minutes per IP)
$rate = check_rate_limit($conn, 'signup', 5, 900);
if (!$rate['allowed']) {
    $minutes = ceil($rate['retry_after'] / 60);
    echo "Too many signup attempts. Please wait {$minutes} minute(s) before trying again.";
    exit();
}

// CSRF validation
if (!empty($_POST['csrf_token'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        echo "Security token validation failed. Please refresh the page.";
        exit();
    }
}

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$rawPassword = $_POST['password'];

if (strlen($name) < 2) {
    echo "Name must be at least 2 characters.";
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Please enter a valid email address.";
    exit();
}

if (strlen($rawPassword) < 8) {
    echo "Password must be at least 8 characters.";
    exit();
}

// Check if email already exists
$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();

if ($checkRes && $checkRes->num_rows > 0) {
    echo "This email is already registered. Please click 'Login' to sign in.";
    exit();
}

$password = password_hash($rawPassword, PASSWORD_DEFAULT);
$role = 'user';

try {
    $sql = "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $password, $role);

    if ($stmt->execute()) {
        clear_rate_limit($conn, 'signup');
        echo "SUCCESS";
    } else {
        echo "Registration could not be completed. Please try again.";
    }
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        echo "This email is already registered. Please click 'Login' to sign in.";
    } else {
        error_log("Signup error: " . $e->getMessage());
        echo "A database error occurred. Please try again later.";
    }
}
?>
