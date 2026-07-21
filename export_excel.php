<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Only Admin and Bursar can access
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    exit("Access denied!");
}

// Optional date filters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// --- STEP 1: Fix old expenses with empty narration ---
$fixStmt = $pdo->prepare("UPDATE expenses SET narration = description WHERE narration IS NULL OR narration = ''");
$fixStmt->execute();

// --- STEP 2: Fetch Fees ---
$whereFees = "WHERE 1=1";
$paramsFees = [];
if ($startDate !== '') {
    $whereFees .= " AND f.date >= :start";
    $paramsFees[':start'] = $startDate;
}
if ($endDate !== '') {
    $whereFees .= " AND f.date <= :end";
    $paramsFees[':end'] = $endDate;
}

$feeSql = "
    SELECT f.date, s.name AS student_name, f.amount, f.narration
    FROM fees f
    LEFT JOIN students s ON s.id = f.student_id
    $whereFees
    ORDER BY f.date DESC
";
$feeStmt = $pdo->prepare($feeSql);
$feeStmt->execute($paramsFees);
$fees = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

// --- STEP 3: Fetch Expenses ---
$whereExpenses = "WHERE 1=1";
$paramsExp = [];
if ($startDate !== '') {
    $whereExpenses .= " AND date >= :start";
    $paramsExp[':start'] = $startDate;
}
if ($endDate !== '') {
    $whereExpenses .= " AND date <= :end";
    $paramsExp[':end'] = $endDate;
}

$expSql = "
    SELECT date, amount, description, narration
    FROM expenses
    $whereExpenses
    ORDER BY date DESC
";
$expStmt = $pdo->prepare($expSql);
$expStmt->execute($paramsExp);
$expenses = $expStmt->fetchAll(PDO::FETCH_ASSOC);

// --- STEP 4: Calculate Totals ---
$totalFees = array_sum(array_column($fees, 'amount'));
$totalExpenses = array_sum(array_column($expenses, 'amount'));
$netBalance = $totalFees - $totalExpenses;

// --- STEP 5: Export CSV ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=financial_report.csv');

$output = fopen('php://output', 'w');

// Fees Section
fputcsv($output, ['--- Fees Collected (Income) ---']);
fputcsv($output, ['Date', 'Student', 'Narration', 'Amount (FCFA)']);
foreach ($fees as $f) {
    fputcsv($output, [
        $f['date'],
        $f['student_name'],
        $f['narration'],
        $f['amount']
    ]);
}
fputcsv($output, ['Total Fees', '', '', $totalFees]);
fputcsv($output, []);

// Expenses Section
fputcsv($output, ['--- Expenses (Outflow) ---']);
fputcsv($output, ['Date', 'Description', 'Narration', 'Amount (FCFA)']);
foreach ($expenses as $e) {
    fputcsv($output, [
        $e['date'],
        $e['description'],
        $e['narration'],
        $e['amount']
    ]);
}
fputcsv($output, ['Total Expenses', '', '', $totalExpenses]);
fputcsv($output, []);

// Net Balance
fputcsv($output, ['Net Balance', '', '', $netBalance]);

fclose($output);
exit;
