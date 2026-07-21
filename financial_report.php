<?php
session_start();
require_once __DIR__ . '/schooldb.php'; // PDO connection

// Only Admin and Bursar
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    exit('Unauthorized access');
}

// ===== Ensure all fees have a date_paid =====
$updateDateStmt = $pdo->prepare("UPDATE fees SET date_paid = :today WHERE date_paid IS NULL OR date_paid = ''");
$updateDateStmt->execute([':today' => date('Y-m-d')]);

// ===== Filters =====
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';
$filters   = "";

// Build WHERE clauses safely
$whereFees = "WHERE 1=1";
$params = [];
if (!empty($startDate)) {
    $whereFees .= " AND DATE(f.date_paid) >= :start";
    $params[':start'] = $startDate;
    $filters .= "&start_date=" . urlencode($startDate);
}
if (!empty($endDate)) {
    $whereFees .= " AND DATE(f.date_paid) <= :end";
    $params[':end'] = $endDate;
    $filters .= "&end_date=" . urlencode($endDate);
}

// ===== Detailed Fees =====
$feeSql = "SELECT f.date_paid AS date, COALESCE(s.name, '-') AS student_name, f.amount, COALESCE(f.narration,'-') AS narration
           FROM fees f
           LEFT JOIN students s ON s.id = f.student_id
           $whereFees
           ORDER BY f.date_paid DESC";
$feeStmt = $pdo->prepare($feeSql);
$feeStmt->execute($params);
$fees = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Fees Summary by Student =====
$summarySql = "SELECT COALESCE(s.name,'-') AS student_name, SUM(f.amount) AS total_amount
               FROM fees f
               LEFT JOIN students s ON s.id = f.student_id
               $whereFees
               GROUP BY f.student_id
               ORDER BY s.name ASC";
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->execute($params);
$feesSummary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Expenses =====
$whereExpenses = "WHERE 1=1";
$expenseParams = [];
if (!empty($startDate)) {
    $whereExpenses .= " AND DATE(date) >= :start";
    $expenseParams[':start'] = $startDate;
}
if (!empty($endDate)) {
    $whereExpenses .= " AND DATE(date) <= :end";
    $expenseParams[':end'] = $endDate;
}

$expenseSql = "SELECT COALESCE(date,'-') AS date, COALESCE(description,'-') AS narration, amount
               FROM expenses $whereExpenses
               ORDER BY date DESC";
$expenseStmt = $pdo->prepare($expenseSql);
$expenseStmt->execute($expenseParams);
$expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Totals =====
$totalFees     = array_sum(array_map('floatval', array_column($fees, 'amount')));
$totalExpenses = array_sum(array_map('floatval', array_column($expenses, 'amount')));
$netBalance    = $totalFees - $totalExpenses;

// ===== Excel Export =====
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=financial_report.xls");

    echo "<h2>Financial Report</h2>";
    echo "<p>Date range: " . ($startDate ?: "Any") . " to " . ($endDate ?: "Any") . "</p>";

    // Detailed Fees
    echo "<h3>Detailed Fees Collected (Income)</h3>";
    echo "<table border='1'><tr><th>Date</th><th>Student</th><th>Narration</th><th>Amount</th></tr>";
    foreach ($fees as $fee) {
        echo "<tr>
                <td>" . htmlspecialchars($fee['date']) . "</td>
                <td>" . htmlspecialchars($fee['student_name']) . "</td>
                <td>" . htmlspecialchars($fee['narration']) . "</td>
                <td>" . number_format($fee['amount'],2) . "</td>
              </tr>";
    }
    echo "<tr><td colspan='3'><strong>Total Fees</strong></td><td><strong>" . number_format($totalFees,2) . "</strong></td></tr>";
    echo "</table><br>";

    // Summary
    echo "<h3>Summary of Fees by Student</h3>";
    echo "<table border='1'><tr><th>Student</th><th>Total Fees Paid</th></tr>";
    foreach ($feesSummary as $summary) {
        echo "<tr>
                <td>" . htmlspecialchars($summary['student_name']) . "</td>
                <td>" . number_format($summary['total_amount'],2) . "</td>
              </tr>";
    }
    echo "</table><br>";

    // Expenses
    echo "<h3>Expenses (Outflow)</h3>";
    echo "<table border='1'><tr><th>Date</th><th>Narration</th><th>Amount</th></tr>";
    foreach ($expenses as $exp) {
        echo "<tr>
                <td>" . htmlspecialchars($exp['date']) . "</td>
                <td>" . htmlspecialchars($exp['narration']) . "</td>
                <td>" . number_format($exp['amount'],2) . "</td>
              </tr>";
    }
    echo "<tr><td colspan='2'><strong>Total Expenses</strong></td><td><strong>" . number_format($totalExpenses,2) . "</strong></td></tr>";
    echo "</table><br>";

    echo "<h3>Net Balance: " . number_format($netBalance,2) . "</h3>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Financial Report | ESBC</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 font-sans">
<h1 class="text-2xl font-bold mb-6 text-indigo-700">Financial Report</h1>

<form method="GET" class="bg-white p-4 rounded shadow mb-6 max-w-lg flex flex-wrap gap-4">
    <div class="flex flex-col">
        <label class="text-gray-700 font-semibold text-sm" for="start_date">Start Date</label>
        <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate) ?>" class="border rounded px-3 py-2">
    </div>
    <div class="flex flex-col">
        <label class="text-gray-700 font-semibold text-sm" for="end_date">End Date</label>
        <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate) ?>" class="border rounded px-3 py-2">
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700 transition">Filter</button>
        <a href="?export=excel<?= $filters ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Export Excel</a>
    </div>
</form>

<section class="bg-white p-4 rounded shadow max-w-5xl mx-auto mb-10">

<h2 class="text-xl font-semibold mb-3 text-indigo-600">Detailed Fees Collected (Income)</h2>
<?php if(empty($fees)): ?>
    <p class="text-gray-600">No fee records found.</p>
<?php else: ?>
<table class="min-w-full border border-gray-300 mb-6 text-sm">
    <thead class="bg-indigo-200 text-indigo-800">
        <tr>
            <th class="border p-2 text-left">Date</th>
            <th class="border p-2 text-left">Student</th>
            <th class="border p-2 text-left">Narration</th>
            <th class="border p-2 text-right">Amount (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($fees as $fee): ?>
        <tr class="border-b hover:bg-indigo-50">
            <td class="border p-2"><?= htmlspecialchars($fee['date']) ?></td>
            <td class="border p-2"><?= htmlspecialchars($fee['student_name']) ?></td>
            <td class="border p-2"><?= htmlspecialchars($fee['narration']) ?></td>
            <td class="border p-2 text-right"><?= number_format($fee['amount'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="bg-indigo-100 font-semibold">
            <td colspan="3" class="border p-2">Total Fees Collected</td>
            <td class="border p-2 text-right"><?= number_format($totalFees,2) ?></td>
        </tr>
    </tbody>
</table>
<?php endif; ?>

<h2 class="text-xl font-semibold mb-3 text-indigo-700">Summary of Fees by Student</h2>
<?php if(empty($feesSummary)): ?>
    <p class="text-gray-600">No summary data found.</p>
<?php else: ?>
<table class="min-w-full border border-gray-300 mb-6 text-sm">
    <thead class="bg-indigo-300 text-indigo-900">
        <tr>
            <th class="border p-2 text-left">Student</th>
            <th class="border p-2 text-right">Total Fees Paid (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($feesSummary as $s): ?>
        <tr class="border-b hover:bg-indigo-100">
            <td class="border p-2"><?= htmlspecialchars($s['student_name']) ?></td>
            <td class="border p-2 text-right"><?= number_format($s['total_amount'],2) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2 class="text-xl font-semibold mb-3 text-red-600">Expenses (Outflow)</h2>
<?php if(empty($expenses)): ?>
    <p class="text-gray-600">No expense records found.</p>
<?php else: ?>
<table class="min-w-full border border-gray-300 mb-6 text-sm">
    <thead class="bg-red-200 text-red-800">
        <tr>
            <th class="border p-2 text-left">Date</th>
            <th class="border p-2 text-left">Narration</th>
            <th class="border p-2 text-right">Amount (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($expenses as $e): ?>
        <tr class="border-b hover:bg-red-50">
            <td class="border p-2"><?= htmlspecialchars($e['date']) ?></td>
            <td class="border p-2"><?= htmlspecialchars($e['narration']) ?></td>
            <td class="border p-2 text-right"><?= number_format($e['amount'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="bg-red-100 font-semibold">
            <td colspan="2" class="border p-2">Total Expenses</td>
            <td class="border p-2 text-right"><?= number_format($totalExpenses,2) ?></td>
        </tr>
    </tbody>
</table>
<?php endif; ?>

<h3 class="text-lg font-bold text-gray-700">Net Balance: <?= number_format($netBalance,2) ?> FCFA</h3>

</section>
<a href="dashboard.php" class="text-indigo-600 hover:underline">&larr; Back to Dashboard</a>
</body>
</html>
