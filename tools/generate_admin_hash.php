<?php
// Usage: php generate_admin_hash.php YourStrongPassword
if (PHP_SAPI !== 'cli') {
    echo "Run from CLI: php generate_admin_hash.php YourStrongPassword\n";
    exit(1);
}
if ($argc < 2) {
    echo "Usage: php generate_admin_hash.php <password>\n";
    exit(1);
}
$password = $argv[1];
echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
