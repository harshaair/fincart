<?php
require_once __DIR__ . '/../includes/security.php';
init_secure_session();
include '../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../become_professional.php');
    exit();
}

enforce_csrf();

$conn->begin_transaction();

try {
    $logged_in = isset($_SESSION['user_id']);
    
    if ($logged_in) {
        // User is already logged in
        // Check if they're trying to apply with a DIFFERENT email
        $submitted_email = trim($_POST['email'] ?? '');
        
        // Get current user's email
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $current_user = $stmt->get_result()->fetch_assoc();
        $current_email = $current_user['email'] ?? '';
        
        // If emails don't match, prevent application with different email
        if (strtolower($submitted_email) !== strtolower($current_email)) {
            throw new Exception('You are logged in with a different email (' . htmlspecialchars($current_email) . '). Please logout first if you want to apply with a different email address.');
        }
        
        // Check if this user is ALREADY approved
        $stmt = $conn->prepare("SELECT id FROM professionals WHERE user_id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('You are already an approved professional. Please access the professional dashboard.');
        }
        
        // Use current user's ID
        $user_id = $_SESSION['user_id'];
    } else {
        // User is NOT logged in - validate registration fields
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($full_name) || empty($email) || empty($password)) {
            throw new Exception('Missing required account information.');
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('An account with this email already exists. Please login first before applying.');
        }
        
        // Create new user with professional_pending role
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'professional_pending';
        $verified = 0;
        
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, verified) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssi', $full_name, $email, $password_hash, $role, $verified);
        $stmt->execute();
        
        $user_id = $conn->insert_id;
        
        // Auto-login the newly created user
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $_SESSION['role'] = $role;
    }
    
    // Now create the application record
    $professional_type = trim($_POST['professional_type'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $consultation_fee = floatval($_POST['consultation_fee'] ?? 0);
    
    if (empty($professional_type)) {
        throw new Exception('Professional type is required.');
    }
    
    // Check if an application already exists for this user
    $stmt = $conn->prepare("SELECT id, status FROM professional_applications WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        if ($existing['status'] === 'pending') {
            throw new Exception('You already have a pending application. Please wait for admin approval.');
        } elseif ($existing['status'] === 'approved') {
            throw new Exception('Your application has already been approved.');
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO professional_applications (user_id, professional_type, bio, consultation_fee, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param('issd', $user_id, $professional_type, $bio, $consultation_fee);
    $stmt->execute();
    
    $conn->commit();
    header('Location: ../become_professional.php?success=' . urlencode('Your application has been submitted and is pending review by an admin.'));
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    header('Location: ../become_professional.php?error=' . urlencode($e->getMessage()));
    exit();
}
