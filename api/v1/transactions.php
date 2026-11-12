<?php
/**
 * api/v1/transactions.php
 * RESTful API Endpoint: Paginated Transactions CRUD
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/security.php';
init_secure_session();
require_once __DIR__ . '/../../db.php';

// Ensure authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Please sign in to access transactions.'
    ]);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$method = strtoupper($_SERVER['REQUEST_METHOD']);

if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $type_filter = trim($_GET['type'] ?? '');
    $cat_filter = trim($_GET['category'] ?? '');

    $where = ["user_id = ?"];
    $params = [$user_id];
    $types = "i";

    if (!empty($type_filter) && in_array($type_filter, ['income', 'expense'], true)) {
        $where[] = "type = ?";
        $params[] = $type_filter;
        $types .= "s";
    }

    if (!empty($cat_filter)) {
        $where[] = "category = ?";
        $params[] = $cat_filter;
        $types .= "s";
    }

    $where_sql = implode(' AND ', $where);

    // Get total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE $where_sql");
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_count = (int)$count_stmt->get_result()->fetch_assoc()['total'];

    // Get paginated rows
    $sql = "SELECT id, type, category, amount, description, date, created_at 
            FROM transactions 
            WHERE $where_sql 
            ORDER BY date DESC, id DESC 
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['amount'] = (float)$row['amount'];
        $items[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $items,
            'pagination'   => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total_items'  => $total_count,
                'total_pages'  => ceil($total_count / $limit)
            ]
        ]
    ], JSON_PRETTY_PRINT);
    exit();
}

if ($method === 'POST') {
    enforce_csrf();
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?? $_POST;

    $type = trim($payload['type'] ?? '');
    $category = trim($payload['category'] ?? '');
    $amount = (float)($payload['amount'] ?? 0);
    $date = trim($payload['date'] ?? date('Y-m-d'));
    $desc = trim($payload['description'] ?? '');

    if (!in_array($type, ['income', 'expense'], true) || empty($category) || $amount <= 0 || $amount > 99999999.99 || empty($date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid input. Please provide valid type, category, amount (0.01 to 99999999.99), and date.'
        ]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, category, amount, description, date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdss", $user_id, $type, $category, $amount, $desc, $date);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Transaction created successfully',
            'transaction_id' => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit();
}

if ($method === 'PUT') {
    enforce_csrf();
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?? [];

    $transaction_id = (int)($payload['id'] ?? ($payload['transaction_id'] ?? 0));
    $type = trim($payload['type'] ?? '');
    $category = trim($payload['category'] ?? '');
    $amount = (float)($payload['amount'] ?? 0);
    $date = trim($payload['date'] ?? '');
    $desc = trim($payload['description'] ?? '');

    if ($transaction_id <= 0 || !in_array($type, ['income', 'expense'], true) || empty($category) || $amount <= 0 || $amount > 99999999.99 || empty($date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid input. Please provide valid id, type, category, amount (0.01 to 99999999.99), and date.'
        ]);
        exit();
    }

    $stmt = $conn->prepare("UPDATE transactions SET type = ?, category = ?, amount = ?, description = ?, date = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssdssii", $type, $category, $amount, $desc, $date, $transaction_id, $user_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
            echo json_encode(['success' => true, 'message' => 'Transaction updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Transaction not found or unauthorized']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit();
}

if ($method === 'DELETE') {
    enforce_csrf();
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?? [];
    $transaction_id = (int)($payload['id'] ?? ($payload['transaction_id'] ?? ($_GET['id'] ?? 0)));

    if ($transaction_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid transaction id required']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $transaction_id, $user_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Transaction not found or unauthorized']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

