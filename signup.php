<?php
include "db.php";

// Basic validation
if (!isset($_POST['name']) || !isset($_POST['email']) || !isset($_POST['password'])) {
    echo "Please fill in all required fields.";
    exit();
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
