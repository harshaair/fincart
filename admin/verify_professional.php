<?php
include '../auth_admin.php';
include '../db.php';
enforce_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['application_id']) || !isset($_POST['user_id'])) {
    header('Location: requests.php');
    exit();
}

$application_id = intval($_POST['application_id']);
$user_id = intval($_POST['user_id']);
$admin_id = $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    // Fetch application details
    $stmt = $conn->prepare("SELECT * FROM professional_applications WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $application_id, $user_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if (!$app) {
        throw new Exception('Application not found');
    }

    // Get user name for professional name field
    $userStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $userStmt->bind_param('i', $user_id);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $name = $user['full_name'] ?? 'Professional User';

    // Create professional record from application
    $stmt = $conn->prepare("INSERT INTO professionals (user_id, professional_type, name, qualification, experience_years, specialization, bio, phone, city, state, languages, consultation_fee, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, 'active', NOW())");
    $stmt->bind_param('issssissssd', $user_id, $app['professional_type'], $name, $app['qualification'], $app['experience_years'], $app['specialization'], $app['bio'], $app['phone'], $app['city'], $app['state'], $app['consultation_fee']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create professional profile: ' . $stmt->error);
    }

    // Update application status to approved
    $stmt = $conn->prepare("UPDATE professional_applications SET status = 'approved', reviewed_by_admin = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $admin_id, $application_id);
    $stmt->execute();

    // Update user role to 'professional'
    $stmt = $conn->prepare("UPDATE users SET role = 'professional' WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    // Log the action in system_logs
    $action_type = 'approve';
    $entity_type = 'professional_application';
    $description = 'Approved professional application from user_id ' . $user_id . ' (' . $app['professional_type'] . ')';
    $stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action_type, entity_type, entity_id, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt->bind_param('issiis', $admin_id, $action_type, $entity_type, $application_id, $description, $ip);
    $stmt->execute();

    $conn->commit();
    header('Location: requests.php?success=Professional+approved');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header('Location: requests.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
