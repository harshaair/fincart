<?php
/**
 * services/BudgetService.php
 * Production Budget Analytics and Notification Service
 */

namespace FinCart\Services;

use mysqli;

class BudgetService {
    /**
     * Computes real-time budget usage for a user with O(1) single-query aggregation
     */
    public static function getUserBudgetSummary(mysqli $conn, int $userId, ?string $monthYear = null): array {
        if ($monthYear === null) {
            $monthYear = date('Y-m');
        }

        // 1. Fetch configured budgets
        $bStmt = $conn->prepare("SELECT id, category, limit_amount FROM budgets WHERE user_id = ? ORDER BY category ASC");
        $bStmt->bind_param("i", $userId);
        $bStmt->execute();
        $bRes = $bStmt->get_result();

        $budgets = [];
        $totalLimit = 0.00;
        while ($row = $bRes->fetch_assoc()) {
            $cat = $row['category'];
            $limit = (float)$row['limit_amount'];
            $totalLimit += $limit;
            $budgets[$cat] = [
                'id' => (int)$row['id'],
                'category' => $cat,
                'limit_amount' => $limit,
                'spent' => 0.00,
                'remaining' => $limit,
                'percentage' => 0.00,
                'is_over_budget' => false
            ];
        }

        if (empty($budgets)) {
            return [
                'month_year' => $monthYear,
                'total_limit' => 0.00,
                'total_spent' => 0.00,
                'overall_percentage' => 0.00,
                'items' => []
            ];
        }

        // 2. Fetch monthly expense totals per category in a single O(1) aggregated query
        $sStmt = $conn->prepare("
            SELECT category, SUM(amount) as total_spent 
            FROM transactions 
            WHERE user_id = ? 
              AND type = 'expense' 
              AND DATE_FORMAT(date, '%Y-%m') = ? 
            GROUP BY category
        ");
        $sStmt->bind_param("is", $userId, $monthYear);
        $sStmt->execute();
        $sRes = $sStmt->get_result();

        $totalSpent = 0.00;
        while ($sRow = $sRes->fetch_assoc()) {
            $cat = $sRow['category'];
            $spent = (float)$sRow['total_spent'];
            $totalSpent += $spent;

            if (isset($budgets[$cat])) {
                $limit = $budgets[$cat]['limit_amount'];
                $pct = $limit > 0 ? round(($spent / $limit) * 100, 1) : 0.00;
                $budgets[$cat]['spent'] = $spent;
                $budgets[$cat]['remaining'] = max(0.00, $limit - $spent);
                $budgets[$cat]['percentage'] = $pct;
                $budgets[$cat]['is_over_budget'] = ($spent > $limit);
            }
        }

        $overallPct = $totalLimit > 0 ? round(($totalSpent / $totalLimit) * 100, 1) : 0.00;

        return [
            'month_year' => $monthYear,
            'total_limit' => $totalLimit,
            'total_spent' => $totalSpent,
            'overall_percentage' => $overallPct,
            'items' => array_values($budgets)
        ];
    }
}

