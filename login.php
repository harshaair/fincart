<?php
session_start();
include "db.php";

// Check if form was submitted with data
if (!isset($_POST['email']) || !isset($_POST['password']) || empty($_POST['email']) || empty($_POST['password'])) {
    echo "<script>
            alert('Please enter your email and password.');
            window.location.href = 'login.html';
          </script>";
    exit();
}

$email = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];

        header("Location: dashboard.php");
        exit();

    } else {
        // Wrong password popup + redirect
        echo "<script>
                alert('Incorrect password. Please try again.');
                window.location.href = 'login.html';
              </script>";
        exit();
    }

} else {
    // Email not found popup + redirect
    echo "<script>
            alert('Email not found. Please check your email.');
            window.location.href = 'login.html';
          </script>";
    exit();
}
?>
