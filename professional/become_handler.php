<?php
session_start();
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../become_professional.php');
    exit();
}

$conn->begin_transaction();

try {
    $logged_in = isset($_SESSION['user_id']);
    if ($logged_in) {
        $user_id = $_SESSION['user_id'];
    } else {
        // Create a new user account
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$full_name || !$email || !$password) {
            throw new Exception('Missing required account information.');
        }

        // Check for existing user with same email
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        $res = $chk->get_result();
        if ($res && $res->num_rows > 0) {
            throw new Exception('Email already registered. Please login and apply from your account.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO users (full_name, email, password, role, created_at) VALUES (?, ?, ?, 'professional_pending', NOW())");
        $ins->bind_param('sss', $full_name, $email, $hash);
        if (!$ins->execute()) {
            throw new Exception('Failed to create user account.');
        }
        $user_id = $conn->insert_id;
        // Log the user in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $full_name;
        $_SESSION['role'] = 'professional_pending';
    }

    // Prepare professional data
    $professional_type = $_POST['professional_type'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $experience_years = $_POST['experience_years'] ? intval($_POST['experience_years']) : NULL;
    $specialization = $_POST['specialization'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $consultation_fee = isset($_POST['consultation_fee']) && $_POST['consultation_fee'] !== '' ? floatval($_POST['consultation_fee']) : NULL;

    $name = $logged_in ? $_SESSION['user_name'] : ($full_name ?? '');

    $stmt = $conn->prepare("INSERT INTO professionals (user_id, professional_type, name, qualification, experience_years, specialization, bio, phone, city, state, consultation_fee, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->bind_param('isssisssssd', $user_id, $professional_type, $name, $qualification, $experience_years, $specialization, $bio, $phone, $city, $state, $consultation_fee);
    if (!$stmt->execute()) {
        throw new Exception('Failed to submit professional application.');
    }

    // Ensure user role is set to professional_pending (if logged in it may be 'user')
    $upd = $conn->prepare("UPDATE users SET role = 'professional_pending' WHERE id = ?");
    $upd->bind_param('i', $user_id);
    $upd->execute();

    $conn->commit();

    header('Location: ../dashboard.php');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $msg = urlencode($e->getMessage());
    header('Location: ../become_professional.php?error=' . $msg);
    exit();
}
