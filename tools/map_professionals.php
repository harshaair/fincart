<?php
// Usage:
//  php map_professionals.php —dry-run   (shows proposed mappings)
//  php map_professionals.php              (executes mappings after confirmation)

if (PHP_SAPI !== 'cli') {
    echo "Run from CLI: php map_professionals.php [—dry-run]\n";
    exit(1);
}

require __DIR__ . '/../db.php';

$dry = in_array('—dry-run', $argv, true);

$res = $conn->query("SELECT id, name, email, status FROM professionals WHERE user_id IS NULL OR user_id = 0");
$toUpdate = [];
while ($p = $res->fetch_assoc()) {
    $profId = $p['id'];
    $foundUserId = null;
    // 1) Try match by email if present
    if (!empty($p['email'])) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $p['email']);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r) $foundUserId = $r['id'];
    }
    // 2) Try exact name match
    if (!$foundUserId) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? LIMIT 1");
        $stmt->bind_param('s', $p['name']);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r) $foundUserId = $r['id'];
    }
    // 3) Try LIKE name match (first match)
    if (!$foundUserId && !empty($p['name'])) {
        $like = '%' . $p['name'] . '%';
        $stmt = $conn->prepare("SELECT id FROM users WHERE full_name LIKE ? LIMIT 1");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r) $foundUserId = $r['id'];
    }

    if ($foundUserId) {
        $toUpdate[] = [
            'prof_id' => $profId,
            'user_id' => $foundUserId,
            'prof_name' => $p['name'],
            'prof_email' => $p['email'],
            'status' => $p['status']
        ];
    }
}

if (empty($toUpdate)) {
    echo "No candidate mappings found.\n";
    exit(0);
}

echo "Proposed mappings (professional_id -> user_id):\n";
foreach ($toUpdate as $m) {
    echo sprintf("%d (%s / %s)  ->  %d\n", $m['prof_id'], $m['prof_name'], $m['prof_email'], $m['user_id']);
}

if ($dry) {
    echo "\nDry-run only. No changes made.\n";
    exit(0);
}

// Confirm
echo "\nApply these mappings? Type 'yes' to continue: ";
$handle = fopen('php://stdin', 'r');
$line = trim(fgets($handle));
if (strtolower($line) !== 'yes') {
    echo "Aborted.\n";
    exit(0);
}

$pStmt = $conn->prepare("UPDATE professionals SET user_id = ? WHERE id = ?");
$uStmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
foreach ($toUpdate as $m) {
    $profId = $m['prof_id'];
    $userId = $m['user_id'];
    $status = $m['status'];

    // set professionals.user_id
    $pStmt->bind_param('ii', $userId, $profId);
    $pStmt->execute();

    // set users.role based on professional status
    $role = ($status === 'active') ? 'professional' : 'professional_pending';
    $uStmt->bind_param('si', $role, $userId);
    $uStmt->execute();

    echo sprintf("Updated professional %d -> user %d (role=%s)\n", $profId, $userId, $role);
}

echo "Done.\n";
