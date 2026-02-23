<?php
// Simple one-time admin creation/promote script. Restricted to local access.
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    echo "Access denied. Run this from the server machine.";
    exit();
}

include '../db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';

    if (empty($email) || empty($password)) {
        $message = 'Email and password are required.';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            // Promote existing user to admin
            $row = $res->fetch_assoc();
            $uid = (int)$row['id'];

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ?, role = 'admin', full_name = ? WHERE id = ?");
            $upd->bind_param('ssi', $hash, $full_name, $uid);
            if ($upd->execute()) {
                $message = 'Existing user promoted to admin.';
            } else {
                $message = 'Failed to promote user: ' . $conn->error;
            }
        } else {
            // Create new admin user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')");
            $ins->bind_param('sss', $full_name, $email, $hash);
            if ($ins->execute()) {
                $message = 'New admin user created.';
            } else {
                $message = 'Failed to create user: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Admin</title>
    <style>body{font-family:Arial,Helvetica,sans-serif;max-width:720px;margin:40px auto;padding:12px}label{display:block;margin-top:8px}input{padding:8px;width:100%;box-sizing:border-box}button{margin-top:12px;padding:8px 12px}</style>
</head>
<body>
    <h1>Create / Promote Admin</h1>
    <?php if ($message): ?>
        <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
    <?php endif; ?>
    <form method="post">
        <label>Full name (optional)
            <input name="full_name" value="" placeholder="Admin Name">
        </label>
        <label>Email
            <input name="email" required placeholder="admin@example.com">
        </label>
        <label>Password
            <input name="password" type="password" required placeholder="Strong password">
        </label>
        <button type="submit">Create / Promote Admin</button>
    </form>
    <p>After creating the admin, log in via <a href="/fincart/login.html">login page</a>.</p>
</body>
</html>
