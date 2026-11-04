<?php
/**
 * tests/php/run_all.php
 * Master Backend PHP Test Suite Runner for CI and Local Development
 */

echo "========================================================\n";
echo " FinCart Automated Backend PHP Test Suite\n";
echo "========================================================\n\n";

$suites = [
    __DIR__ . '/test_security.php',
    __DIR__ . '/test_tax_service.php'
];

$all_passed = true;

foreach ($suites as $suite) {
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($suite);
    passthru($cmd, $exit_code);
    if ($exit_code !== 0) {
        $all_passed = false;
        echo "[ERROR] Test suite failed: " . basename($suite) . "\n";
    }
}

if (!$all_passed) {
    echo "\n❌ One or more PHP backend test suites failed.\n";
    exit(1);
}

echo "\n✅ All FinCart PHP backend test suites passed successfully!\n";
exit(0);
