<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'bursar'])) {
    exit('Unauthorized');
}

require 'vendor/autoload.php';
require 'db.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$rows = ''; $incTot = 0; $expTot = 0;

// Income Query
$q = $pdo->prepare("SELECT f.date, s.name AS student_name, f.narration, f.amount 
                     FROM fees f LEFT JOIN students s ON s.id = f.student_id 
                     WHERE f.date BETWEEN ? AND ?");
$q->bind_param('ss', $start, $end);
$q->execute();
$res = $q->get_result();
while ($d = $res->fetch_assoc()) {
    $incTot += $d['amount'];
    $rows .= "<tr><td>{$d['date']}</td><td>Income</td><td>{$d['student_name']} - " . htmlspecialchars($d['narration']) . "</td><td>" . number_format($d['amount'], 0, '.', ',') . "</td></tr>";
}
$q->close();

// Expense Query
$q = $pdo->prepare("SELECT date, narration, amount FROM expenses WHERE date BETWEEN ? AND ?");
$q->bind_param('ss', $start, $end);
$q->execute();
$res = $q->get_result();
while ($d = $res->fetch_assoc()) {
    $expTot += $d['amount'];
    $rows .= "<tr><td>{$d['date']}</td><td>Expense</td><td>" . htmlspecialchars($d['narration']) . "</td><td>-" . number_format($d['amount'], 0, '.', ',') . "</td></tr>";
}
$q->close();

$bal = $incTot - $expTot;

$html = "
<h2 style='text-align:center'>Synergia Financial Report</h2>
<p>Period: $start → $end</p>
<table width='100%' border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse;font-size:12px'>
<thead><tr style='background:#f0f0f0'><th>Date</th><th>Type</th><th>Narration</th><th>Amount</th></tr></thead>
<tbody>$rows</tbody>
</table>
<br>
<h4>Total Income: " . number_format($incTot, 0, '.', ',') . " FCFA</h4>
<h4>Total Expense: " . number_format($expTot, 0, '.', ',') . " FCFA</h4>
<h3>Balance: " . number_format($bal, 0, '.', ',') . " FCFA</h3>";

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("financial_report_{$start}_to_{$end}.pdf", ['Attachment' => true]);
exit;
?>
