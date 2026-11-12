<?php
require_once __DIR__ . '/../includes/security.php';
init_secure_session();
include '../db.php';

// Check if professional is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['professional', 'professional_approved'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get the professional profile
$stmt = $conn->prepare("SELECT * FROM professionals WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$prof = $stmt->get_result()->fetch_assoc();

if (!$prof) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $name = trim($_POST['name'] ?? '');
    $professional_type = trim($_POST['professional_type'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = intval($_POST['experience_years'] ?? 0);
    $specialization = trim($_POST['specialization'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $consultation_fee = floatval($_POST['consultation_fee'] ?? 0);
    $languages = trim($_POST['languages'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Validate required fields
    if (empty($name) || empty($professional_type)) {
        $error = 'Name and Professional Type are required';
    } elseif ($consultation_fee <= 0) {
        $error = 'Consultation fee must be greater than 0';
    } else {
        // Update professional profile
        $stmt = $conn->prepare("
            UPDATE professionals 
            SET name = ?, professional_type = ?, qualification = ?, experience_years = ?, 
                specialization = ?, city = ?, state = ?, phone = ?, consultation_fee = ?, 
                languages = ?, bio = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sssissssdsi', 
            $name, $professional_type, $qualification, $experience_years,
            $specialization, $city, $state, $phone, $consultation_fee,
            $languages, $bio, $prof['id']
        );

        if ($stmt->execute()) {
            $message = 'Profile updated successfully!';
            // Refresh the profile data
            $prof['name'] = $name;
            $prof['professional_type'] = $professional_type;
            $prof['qualification'] = $qualification;
            $prof['experience_years'] = $experience_years;
            $prof['specialization'] = $specialization;
            $prof['city'] = $city;
            $prof['state'] = $state;
            $prof['phone'] = $phone;
            $prof['consultation_fee'] = $consultation_fee;
            $prof['languages'] = $languages;
            $prof['bio'] = $bio;
        } else {
            $error = 'Failed to update profile: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Profile - Professional Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .edit-container { max-width: 700px; margin: 30px auto; padding: 20px; }
        .form-group { margin: 20px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 14px;
            font-family: Arial, sans-serif;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-row .form-group { margin: 0; }
        .button-group { display: flex; gap: 10px; margin-top: 30px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #545b62; }
        .message { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .message-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; }
    </style>
</head>
<body>
<header class="site-header">
    <div class="logo"><h1>Edit Professional Profile</h1></div>
    <div class="header-actions">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
</header>

<div class="edit-container">
    <?php if ($message): ?>
        <div class="message message-success">✓ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="message message-error">✗ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <?php echo csrf_field(); ?>
        <div class="form-row">
            <div class="form-group">
                <label for="name">Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($prof['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="professional_type">Professional Type *</label>
                <select id="professional_type" name="professional_type" required>
                    <option value="">Select Type</option>
                    <option value="CA" <?php echo $prof['professional_type'] === 'CA' ? 'selected' : ''; ?>>Chartered Accountant (CA)</option>
                    <option value="Tax Consultant" <?php echo $prof['professional_type'] === 'Tax Consultant' ? 'selected' : ''; ?>>Tax Consultant</option>
                    <option value="Financial Advisor" <?php echo $prof['professional_type'] === 'Financial Advisor' ? 'selected' : ''; ?>>Financial Advisor</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="qualification">Qualification</label>
                <input type="text" id="qualification" name="qualification" value="<?php echo htmlspecialchars($prof['qualification'] ?? ''); ?>" placeholder="e.g., CA, CPA, B.Com">
            </div>
            <div class="form-group">
                <label for="experience_years">Experience (Years)</label>
                <input type="number" id="experience_years" name="experience_years" value="<?php echo $prof['experience_years'] ?? 0; ?>" min="0">
            </div>
        </div>

        <div class="form-group">
            <label for="specialization">Specialization</label>
            <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($prof['specialization'] ?? ''); ?>" placeholder="e.g., Tax Planning, Audit, Investment">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($prof['city'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="state">State</label>
                <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($prof['state'] ?? ''); ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($prof['phone'] ?? ''); ?>" placeholder="10-digit phone number">
            </div>
            <div class="form-group">
                <label for="consultation_fee">Consultation Fee (₹) *</label>
                <input type="number" id="consultation_fee" name="consultation_fee" value="<?php echo $prof['consultation_fee'] ?? 0; ?>" step="0.01" min="0" required>
            </div>
        </div>

        <div class="form-group">
            <label for="languages">Languages</label>
            <input type="text" id="languages" name="languages" value="<?php echo htmlspecialchars($prof['languages'] ?? ''); ?>" placeholder="e.g., English, Hindi, Marathi">
        </div>

        <div class="form-group">
            <label for="bio">Biography</label>
            <textarea id="bio" name="bio" placeholder="Tell clients about your experience, approach, and expertise..."><?php echo htmlspecialchars($prof['bio'] ?? ''); ?></textarea>
        </div>

        <div class="button-group">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="dashboard.php" class="btn btn-secondary" style="text-decoration: none; display: inline-block;">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>
