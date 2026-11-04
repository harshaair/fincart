<?php
/**
 * api/v1/tax_calculator.php
 * RESTful API Endpoint: Dual-Regime Indian Income Tax Comparison
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../services/TaxService.php';
use FinCart\Services\TaxService;

// Read JSON input or GET/POST params
$raw_input = file_get_contents('php://input');
$json_data = json_decode($raw_input, true) ?? [];

$gross_income = isset($json_data['gross_income']) ? (float)$json_data['gross_income'] : (float)($_REQUEST['gross_income'] ?? 0);
$deductions = isset($json_data['deductions']) ? (float)$json_data['deductions'] : (float)($_REQUEST['deductions'] ?? 0);
$is_salaried = isset($json_data['is_salaried']) ? (bool)$json_data['is_salaried'] : (isset($_REQUEST['is_salaried']) ? (bool)$_REQUEST['is_salaried'] : true);

if ($gross_income < 0 || $deductions < 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Gross income and deductions must be non-negative values.'
    ]);
    exit();
}

try {
    $result = TaxService::compareRegimes($gross_income, $deductions, $is_salaried);
    echo json_encode([
        'success' => true,
        'data'    => $result
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Calculation error: ' . $e->getMessage()
    ]);
}
