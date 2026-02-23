<?php
// Temporary web checker: verifies a plain password against stored DB hash for a user.
// Usage: http://localhost/fincart/tools/check_password.php
// Delete after use.
include __DIR__ . '/../db.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pw = $_POST['password'] ?? '';
    $stmt = $conn->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if (!$res) {
        $message = 'No user with that email.';
    } else {
        $hash = $res['password'];
        if (password_verify($pw, $hash)) $message = 'MATCH: password is correct.';
        else $message = 'NO MATCH: incorrect password.';
    }
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Check Password</title></head><body style="font-family:Arial,sans-serif;padding:20px;">
  <h2>Verify password for a user (temporary)</h2>
  <form method="post">
    <label>Email: <input name="email" required></label><br><br>
    <label>Password: <input name="password" type="password" required></label><br><br>
    <button type="submit">Verify</button>
  </form>
  <?php if ($message): ?>
    <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
    <p style="color:#a00">Delete this file after use: tools/check_password.php</p>
  <?php endif; ?>
</body></html>
