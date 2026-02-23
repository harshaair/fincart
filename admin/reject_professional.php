<?php
include '../auth_admin.php';
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['application_id'])) {
    header('Location: requests.php');
    exit();
}

$application_id = intval($_POST['application_id']);
$admin_id = $_SESSION['user_id'];
$rejection_reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'No reason provided';

try {
    $conn->begin_transaction();

    // Fetch application details
    $stmt = $conn->prepare("SELECT user_id FROM professional_applications WHERE id = ?");
    $stmt->bind_param('i', $application_id);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();

    if (!$app) {
        throw new Exception('Application not found');
    }

    $user_id = $app['user_id'];

    // Update application status to rejected with reason
    $stmt = $conn->prepare("UPDATE professional_applications SET status = 'rejected', rejection_reason = ?, reviewed_by_admin = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param('sii', $rejection_reason, $admin_id, $application_id);
    $stmt->execute();

    // Update user role back to 'user'
    $stmt = $conn->prepare("UPDATE users SET role = 'user' WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    // Log the action in system_logs
    $action_type = 'reject';
    $entity_type = 'professional_application';
    $description = 'Rejected professional application from user_id ' . $user_id . ' - Reason: ' . $rejection_reason;
    $stmt = $conn->prepare("INSERT INTO system_logs (admin_id, action_type, entity_type, entity_id, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt->bind_param('issiis', $admin_id, $action_type, $entity_type, $application_id, $description, $ip);
    $stmt->execute();

    $conn->commit();
    header('Location: requests.php?success=Application+rejected');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header('Location: requests.php?error=' . urlencode($e->getMessage()));
    exit();
}
?>
