<?php
include '../auth_professional.php';
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: apply.php');
    exit();
}

$uid = $_SESSION['user_id'];
$professional_type = $_POST['professional_type'] ?? '';
$qualification = $_POST['qualification'] ?? '';
$experience_years = $_POST['experience_years'] ? intval($_POST['experience_years']) : null;
$specialization = $_POST['specialization'] ?? '';
$bio = $_POST['bio'] ?? '';
$phone = $_POST['phone'] ?? '';
$city = $_POST['city'] ?? '';
$state = $_POST['state'] ?? '';
$consultation_fee = $_POST['consultation_fee'] ? floatval($_POST['consultation_fee']) : null;

// Insert into professionals table with status 'pending'
$stmt = $conn->prepare("INSERT INTO professionals (user_id, professional_type, name, qualification, experience_years, specialization, bio, phone, city, state, consultation_fee, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
$name = $_SESSION['user_name'];
$stmt->bind_param('isssisssssd', $uid, $professional_type, $name, $qualification, $experience_years, $specialization, $bio, $phone, $city, $state, $consultation_fee);
$stmt->execute();

// Update users.role to professional_pending
$upd = $conn->prepare("UPDATE users SET role = 'professional_pending' WHERE id = ?");
$upd->bind_param('i', $uid);
$upd->execute();

header('Location: dashboard.php');
exit();
