<?php
session_start();
include "db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: booking.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$professional_id = intval($_POST['professional_id'] ?? 0);
$appointment_date = $_POST['appointment_date'] ?? '';
$appointment_time = $_POST['appointment_time'] ?? '';
$consultation_type = $_POST['consultation_type'] ?? '';
$consultation_mode = $_POST['consultation_mode'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$professional_id || !$appointment_date || !$appointment_time || !$consultation_type || !$consultation_mode) {
    header('Location: booking.php?professional_id=' . $professional_id . '&error=Missing+required+fields');
    exit();
}

try {
    $conn->begin_transaction();

    // Get professional details
    $stmt = $conn->prepare("SELECT p.*, u.email as prof_email, u.full_name as prof_name FROM professionals p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.status = 'active'");
    $stmt->bind_param('i', $professional_id);
    $stmt->execute();
    $prof = $stmt->get_result()->fetch_assoc();

    if (!$prof) {
        throw new Exception('Professional not found');
    }

    // Get user details
    $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Check if slot is already booked
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE professional_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed')");
    $stmt->bind_param('iss', $professional_id, $appointment_date, $appointment_time);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This time slot is already booked. Please select another time.');
    }

    // Validate time is within business hours (9 AM to 6 PM)
    $time_parts = explode(':', $appointment_time);
    $hour = intval($time_parts[0]);
    if ($hour < 9 || $hour >= 18) {
        throw new Exception('Please select a time between 9 AM and 6 PM');
    }

    // Insert appointment
    $stmt = $conn->prepare("INSERT INTO appointments (professional_id, user_id, appointment_date, appointment_time, consultation_type, consultation_mode, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('iisssss', $professional_id, $user_id, $appointment_date, $appointment_time, $consultation_type, $consultation_mode, $notes);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to book appointment');
    }

    $appointment_id = $conn->insert_id;

    // Send email to user
    $user_subject = "Appointment Booking Confirmation - FinCart";
    $user_message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Appointment Booking Confirmation</h2>
        <p>Dear " . htmlspecialchars($user['full_name']) . ",</p>
        
        <p>Your appointment has been successfully booked! Here are the details:</p>
        
        <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
            <tr style='background-color: #f0f0f0;'>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Professional:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($prof['name']) . " (" . htmlspecialchars($prof['professional_type']) . ")</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Date:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . date('d-M-Y', strtotime($appointment_date)) . "</td>
            </tr>
            <tr style='background-color: #f0f0f0;'>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Time:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . date('h:i A', strtotime($appointment_time)) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Type:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($consultation_type) . "</td>
            </tr>
            <tr style='background-color: #f0f0f0;'>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Mode:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($consultation_mode) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Fee:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>₹" . number_format($prof['consultation_fee'], 2) . "</td>
            </tr>
            <tr style='background-color: #f0f0f0;'>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Status:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong style='color: #ff9800;'>Pending Acceptance</strong></td>
            </tr>
        </table>
        
        <p>Your appointment is pending acceptance from the professional. You will receive an email notification once they accept or reject your booking.</p>
        
        " . (!empty($notes) ? "<p><strong>Your Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>" : "") . "
        
        <p style='color: #666; font-size: 13px; margin-top: 30px;'>
            Best regards,<br>
            FinCart Team
        </p>
    </body>
    </html>";

    $user_headers = "MIME-Version: 1.0\r\n";
    $user_headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $user_headers .= "From: noreply@fincart.local\r\n";

    @mail($user['email'], $user_subject, $user_message, $user_headers);

    // Send email to professional
    if ($prof['prof_email']) {
        $prof_subject = "New Appointment Booking Request - FinCart";
        $prof_message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>New Appointment Booking Request</h2>
            <p>Dear " . htmlspecialchars($prof['prof_name'] ?? $prof['name']) . ",</p>
            
            <p>You have received a new appointment booking request:</p>
            
            <table style='border-collapse: collapse; width: 100%; margin: 20px 0;'>
                <tr style='background-color: #f0f0f0;'>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Client Name:</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($user['full_name']) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Client Email:</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($user['email']) . "</td>
                </tr>
                <tr style='background-color: #f0f0f0;'>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Requested Date:</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . date('d-M-Y', strtotime($appointment_date)) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Requested Time:</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . date('h:i A', strtotime($appointment_time)) . "</td>
                </tr>
                <tr style='background-color: #f0f0f0;'>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Type:</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($consultation_type) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd;'><strong>Preferred Mode:</strong></td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($consultation_mode) . "</td>
                </tr>
            </table>
            
            " . (!empty($notes) ? "<p><strong>Client Notes:</strong><br>" . nl2br(htmlspecialchars($notes)) . "</p>" : "") . "
            
            <p>Please login to your <a href='" . $_SERVER['HTTP_HOST'] . "/professional/dashboard.php'>Professional Dashboard</a> to accept or reject this booking.</p>
            
            <p style='color: #666; font-size: 13px; margin-top: 30px;'>
                Best regards,<br>
                FinCart Team
            </p>
        </body>
        </html>";

        $prof_headers = "MIME-Version: 1.0\r\n";
        $prof_headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $prof_headers .= "From: noreply@fincart.local\r\n";

        @mail($prof['prof_email'], $prof_subject, $prof_message, $prof_headers);
    }

    $conn->commit();

    // Redirect to success page
    header('Location: booking_confirmation.php?appointment_id=' . $appointment_id);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header('Location: booking.php?professional_id=' . $professional_id . '&error=' . urlencode($e->getMessage()));
    exit();
}
?>
