<?php
/**
 * statement_pdf.php
 * Official FinCart Financial Statement & Audit PDF Generator
 */
require_once "includes/security.php";
require_auth();
include "db.php";

$user_id = (int)$_SESSION['user_id'];

// Get user profile
$u_stmt = $conn->prepare("SELECT full_name, email, created_at FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$user_info = $u_stmt->get_result()->fetch_assoc() ?: [
    'full_name'  => $_SESSION['user_name'] ?? 'FinCart Customer',
    'email'      => $_SESSION['user_email'] ?? 'customer@fincart.local',
    'created_at' => date('Y-m-d')
];

// Filters
$filter_type = trim($_GET['type'] ?? 'all');
$filter_category = trim($_GET['category'] ?? 'all');
$filter_month = trim($_GET['month'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

$where_clause = "WHERE user_id = ?";
$params = [$user_id];
$param_types = "i";

if ($filter_type !== 'all' && in_array($filter_type, ['income', 'expense'])) {
    $where_clause .= " AND type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if ($filter_category !== 'all' && !empty($filter_category)) {
    $where_clause .= " AND category = ?";
    $params[] = $filter_category;
    $param_types .= "s";
}

if (!empty($filter_month)) {
    $where_clause .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
    $params[] = $filter_month;
    $param_types .= "s";
} elseif (!empty($start_date) && !empty($end_date)) {
    $where_clause .= " AND date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= "ss";
}

// Fetch all matching transactions
$sql = "SELECT * FROM transactions $where_clause ORDER BY date ASC, id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate metrics
$total_income = 0.0;
$total_expense = 0.0;
$category_totals = [];

foreach ($transactions as $t) {
    $amt = (float)$t['amount'];
    $cat = $t['category'] ?: 'Uncategorized';
    if ($t['type'] === 'income') {
        $total_income += $amt;
        if (!isset($category_totals[$cat])) $category_totals[$cat] = ['income' => 0.0, 'expense' => 0.0, 'count' => 0];
        $category_totals[$cat]['income'] += $amt;
        $category_totals[$cat]['count']++;
    } else {
        $total_expense += $amt;
        if (!isset($category_totals[$cat])) $category_totals[$cat] = ['income' => 0.0, 'expense' => 0.0, 'count' => 0];
        $category_totals[$cat]['expense'] += $amt;
        $category_totals[$cat]['count']++;
    }
}

$net_balance = $total_income - $total_expense;
$statement_id = "FC-STMT-" . strtoupper(dechex($user_id)) . "-" . date('YmdHis');
$period_label = !empty($filter_month) ? date('F Y', strtotime($filter_month . '-01')) : (!empty($start_date) ? "$start_date to $end_date" : "All Recorded Historical Data");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Statement - <?php echo htmlspecialchars($user_info['full_name']); ?> - FinCart</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <!-- html2pdf for Direct PDF Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            padding: 30px 15px;
            font-size: 13.5px;
            line-height: 1.5;
        }
        .action-toolbar {
            max-width: 960px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #1a1a1a;
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13.5px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-gold {
            background: #d4af37;
            color: #111;
        }
        .btn-gold:hover {
            background: #e6be44;
        }
        .btn-outline-white {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .btn-outline-white:hover {
            background: rgba(255,255,255,0.1);
        }
        .document-container {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            padding: 45px 50px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid #e5e7eb;
        }
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 25px;
            border-bottom: 2px solid #1a1a1a;
            margin-bottom: 30px;
        }
        .brand-logo {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: -0.5px;
        }
        .brand-tagline {
            color: #d4af37;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .meta-box {
            text-align: right;
            font-size: 12.5px;
            color: #6b7280;
        }
        .meta-box strong {
            color: #111827;
        }
        .client-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            background: #fafaf9;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 18px 22px;
            margin-bottom: 28px;
        }
        .info-col h4 {
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 4px;
        }
        .info-col p {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-box {
            padding: 16px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #e5e7eb;
        }
        .stat-box.inflow {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }
        .stat-box.outflow {
            border-left: 4px solid #ef4444;
            background: #fef2f2;
        }
        .stat-box.net {
            border-left: 4px solid #d4af37;
            background: #fdfbf7;
        }
        .stat-box-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }
        .stat-box-val {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
        }
        .section-heading {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .statement-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 30px;
        }
        .statement-table th {
            background: #f3f4f6;
            color: #374151;
            font-weight: 600;
            text-align: left;
            padding: 10px 12px;
            border-bottom: 2px solid #e5e7eb;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.04em;
        }
        .statement-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
        }
        .statement-table tr:nth-child(even) {
            background: #fafafa;
        }
        .text-right {
            text-align: right;
        }
        .credit-text {
            color: #059669;
            font-weight: 600;
        }
        .debit-text {
            color: #dc2626;
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-income {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-expense {
            background: #fee2e2;
            color: #991b1b;
        }
        .doc-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #9ca3af;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .action-toolbar {
                display: none !important;
            }
            .document-container {
                box-shadow: none;
                border: none;
                padding: 20px 0;
                max-width: 100%;
            }
            .statement-table {
                page-break-inside: auto;
            }
            .statement-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Interactive Export Toolbar -->
    <div class="action-toolbar">
        <div>
            <strong>📄 FinCart Statement Generator</strong>
            <span style="font-size: 12px; color: #9ca3af; margin-left: 10px;">Filter: <?php echo htmlspecialchars($period_label); ?> • <?php echo count($transactions); ?> records</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="btn-action btn-gold">
                🖨️ Print / Save as PDF
            </button>
            <button onclick="downloadAsPDF()" id="pdfDownloadBtn" class="btn-action btn-outline-white">
                📥 Direct PDF Download
            </button>
            <a href="transactions.php" class="btn-action btn-outline-white">
                ✖ Close
            </a>
        </div>
    </div>

    <!-- Printable & Exportable Statement Document -->
    <div class="document-container" id="printableStatement">
        <!-- Header -->
        <div class="doc-header">
            <div>
                <div class="brand-logo">FinCart</div>
                <div class="brand-tagline">Financial Intelligence & Audit System</div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">FinCart Technologies Inc. • Licensed Personal Finance & Tax Portal</div>
            </div>
            <div class="meta-box">
                <div>Statement ID: <strong><?php echo htmlspecialchars($statement_id); ?></strong></div>
                <div>Generated: <strong><?php echo date('d-M-Y H:i:s T'); ?></strong></div>
                <div>Filter Scope: <strong><?php echo htmlspecialchars($period_label); ?></strong></div>
                <div>Status: <strong style="color: #10b981;">✓ Verified Active Ledger</strong></div>
            </div>
        </div>

        <!-- Client & Account Summary -->
        <div class="client-info-grid">
            <div class="info-col">
                <h4>Account Holder</h4>
                <p><?php echo htmlspecialchars($user_info['full_name']); ?></p>
                <div style="font-size: 12.5px; color: #6b7280;"><?php echo htmlspecialchars($user_info['email']); ?></div>
            </div>
            <div class="info-col">
                <h4>Ledger Summary Period</h4>
                <p><?php echo htmlspecialchars($period_label); ?></p>
                <div style="font-size: 12.5px; color: #6b7280;">Currency: <strong>INR (₹)</strong> • Jurisdiction: Republic of India</div>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="stats-summary">
            <div class="stat-box inflow">
                <div class="stat-box-label">Total Inflow (Credits)</div>
                <div class="stat-box-val" style="color: #059669;">+₹<?php echo number_format($total_income, 2); ?></div>
            </div>
            <div class="stat-box outflow">
                <div class="stat-box-label">Total Outflow (Debits)</div>
                <div class="stat-box-val" style="color: #dc2626;">-₹<?php echo number_format($total_expense, 2); ?></div>
            </div>
            <div class="stat-box net">
                <div class="stat-box-label">Net Liquidity Variance</div>
                <div class="stat-box-val" style="color: <?php echo $net_balance >= 0 ? '#111827' : '#dc2626'; ?>;">
                    <?php echo $net_balance >= 0 ? '+' : ''; ?>₹<?php echo number_format($net_balance, 2); ?>
                </div>
            </div>
        </div>

        <!-- Category Distribution Breakdown -->
        <?php if (!empty($category_totals)): ?>
        <div class="section-heading">
            <span>Category Spending & Inflow Breakdown</span>
            <span style="font-size: 12px; color: #6b7280; font-weight: normal;"><?php echo count($category_totals); ?> Active Categories</span>
        </div>
        <table class="statement-table">
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="text-right">Transactions</th>
                    <th class="text-right">Credits (₹)</th>
                    <th class="text-right">Debits (₹)</th>
                    <th class="text-right">Net Flow (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($category_totals as $cat_name => $c_data): 
                    $cat_net = $c_data['income'] - $c_data['expense'];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($cat_name); ?></strong></td>
                    <td class="text-right"><?php echo (int)$c_data['count']; ?></td>
                    <td class="text-right credit-text"><?php echo $c_data['income'] > 0 ? '+₹' . number_format($c_data['income'], 2) : '—'; ?></td>
                    <td class="text-right debit-text"><?php echo $c_data['expense'] > 0 ? '-₹' . number_format($c_data['expense'], 2) : '—'; ?></td>
                    <td class="text-right" style="font-weight: 700; color: <?php echo $cat_net >= 0 ? '#059669' : '#dc2626'; ?>;">
                        <?php echo $cat_net >= 0 ? '+' : ''; ?>₹<?php echo number_format($cat_net, 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Detailed Itemized Ledger -->
        <div class="section-heading">
            <span>Itemized Transaction Ledger</span>
            <span style="font-size: 12px; color: #6b7280; font-weight: normal;"><?php echo count($transactions); ?> Total Items</span>
        </div>

        <?php if (!empty($transactions)): ?>
        <table class="statement-table">
            <thead>
                <tr>
                    <th style="width: 55px;">Ref #</th>
                    <th style="width: 95px;">Date</th>
                    <th style="width: 90px;">Nature</th>
                    <th>Category</th>
                    <th>Description / Merchant</th>
                    <th class="text-right" style="width: 120px;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td style="color: #6b7280;">#<?php echo (int)$tx['id']; ?></td>
                    <td><?php echo date('d-M-Y', strtotime($tx['date'])); ?></td>
                    <td>
                        <?php if ($tx['type'] === 'income'): ?>
                            <span class="badge badge-income">↑ Credit</span>
                        <?php else: ?>
                            <span class="badge badge-expense">↓ Debit</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($tx['category'] ?: 'General'); ?></strong></td>
                    <td style="color: #4b5563;"><?php echo htmlspecialchars($tx['description'] ?: '—'); ?></td>
                    <td class="text-right">
                        <strong class="<?php echo $tx['type'] === 'income' ? 'credit-text' : 'debit-text'; ?>">
                            <?php echo $tx['type'] === 'income' ? '+' : '-'; ?>₹<?php echo number_format((float)$tx['amount'], 2); ?>
                        </strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f9fafb; font-weight: 700;">
                    <td colspan="5" class="text-right" style="padding: 12px;">Total Net Cash Position:</td>
                    <td class="text-right" style="padding: 12px; font-size: 14.5px; color: <?php echo $net_balance >= 0 ? '#059669' : '#dc2626'; ?>;">
                        <?php echo $net_balance >= 0 ? '+' : ''; ?>₹<?php echo number_format($net_balance, 2); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 40px 20px; background: #fafaf9; border-radius: 8px; border: 1px dashed #d1d5db; color: #6b7280; margin-bottom: 25px;">
            No transaction records found matching the specified filters (<?php echo htmlspecialchars($period_label); ?>).
        </div>
        <?php endif; ?>

        <!-- Disclaimer & Audit Footer -->
        <div class="doc-footer">
            <div>
                <strong>FinCart Audit Certificate</strong> • Generated for authorized account holder reference.
            </div>
            <div>
                Page 1 of 1 • Cryptographic Hash: <?php echo substr(hash('sha256', $statement_id . $user_id . $total_income), 0, 16); ?>
            </div>
        </div>
    </div>

    <script>
    function downloadAsPDF() {
        const btn = document.getElementById('pdfDownloadBtn');
        const element = document.getElementById('printableStatement');
        if (!element || !window.html2pdf) {
            window.print();
            return;
        }

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Generating PDF...';

        const opt = {
            margin:       [10, 10, 10, 10],
            filename:     'FinCart_Statement_<?php echo date("Ymd_His"); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(function() {
            btn.disabled = false;
            btn.innerHTML = '✓ Downloaded!';
            setTimeout(function() {
                btn.innerHTML = originalText;
            }, 3000);
        }).catch(function(err) {
            console.error('PDF generation error, falling back to window.print():', err);
            btn.disabled = false;
            btn.innerHTML = originalText;
            window.print();
        });
    }
    </script>
</body>
</html>
