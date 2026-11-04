<?php
/**
 * api/v1/budget_summary.php
 * RESTful API Endpoint: Real-time Budget Consumption Summary
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/security.php';
init_secure_session();
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../services/BudgetService.php';

use FinCart\Services\BudgetService;

// Ensure authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Please sign in to view budget analytics.'
    ]);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$month_year = isset($_GET['month_year']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month_year'])
    ? $_GET['month_year']
    : date('Y-m');

try {
    $summary = BudgetService::getUserBudgetSummary($conn, $user_id, $month_year);
    echo json_encode([
        'success' => true,
        'data'    => $summary
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to retrieve budget summary: ' . $e->getMessage()
    ]);
}
