<?php
// Simple web helper to generate password_hash() output.
// USAGE: Place this file under your project (tools/) and open in browser:
// http://localhost/fincart/tools/generate_admin_hash_web.php
// IMPORTANT: Delete this file after use.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    $hash = password_hash($pw, PASSWORD_DEFAULT);
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Generate PHP Password Hash</title></head>
<body style="font-family:Arial,sans-serif;padding:20px;">
  <h2>Generate password hash (temporary)</h2>
  <form method="post">
    <label>Enter password: <input name="password" type="text" required></label>
    <button type="submit">Generate</button>
  </form>
  <?php if (!empty($hash)): ?>
    <h3>Hash (copy this into phpMyAdmin INSERT):</h3>
    <textarea rows="3" cols="80" readonly><?php echo htmlspecialchars($hash); ?></textarea>
    <p style="color:#a00">Delete this file after use: <code>tools/generate_admin_hash_web.php</code></p>
  <?php endif; ?>
</body>
</html>
