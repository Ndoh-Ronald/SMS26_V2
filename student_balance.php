<?php
session_start();
require_once __DIR__ . '/schooldb.php';

if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    echo "Access denied!";
    exit;
}

$sql = "
    SELECT 
        s.id AS student_id,
        s.name AS student_name,
        s.class AS class_name,
        COALESCE(fc.amount_expected, 0) AS total_expected,
        COALESCE(SUM(fp.amount), 0) AS total_paid,
        GROUP_CONCAT(fp.narration SEPARATOR ', ') AS narrations
    FROM students s
    LEFT JOIN fee_payments fp ON fp.student_id = s.id
    LEFT JOIN fee_config fc ON fc.class = s.class
    GROUP BY s.id, s.name, s.class, fc.amount_expected
    ORDER BY s.class, s.name
";

$result = $pdo->query($sql);
if (!$result) die("Query failed: " . $pdo->error);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Fee Balances</title>
<style>
body { font-family: Arial; padding:20px; background:#f9fafb; }
table { width:100%; border-collapse: collapse; background:#fff; box-shadow:0 0 8px rgba(0,0,0,0.1); }
th, td { border:1px solid #ddd; padding:12px; text-align:center; }
th { background:#4f46e5; color:#fff; }
tr:nth-child(even){ background:#f3f4f6; }
.owe { color:#dc2626; font-weight:bold; }
.cleared { color:#16a34a; font-weight:bold; }
</style>
</head>
<body>

<h2>📘 Student Fee Balances</h2>
<table>
<thead>
<tr>
<th>#</th>
<th>Student Name</th>
<th>Class</th>
<th>Total Expected</th>
<th>Total Paid</th>
<th>Balance</th>
<th>Status</th>
<th>Narration</th>
</tr>
</thead>
<tbody>
<?php
$sn=1;
while($row=$result->fetch_assoc()){
    $balance = $row['total_expected'] - $row['total_paid'];
    $status = ($balance <=0) ? "<span class='cleared'>Cleared</span>" : "<span class='owe'>Owes</span>";
    $narrations = $row['narrations'] ?? '-';
    echo "<tr>
        <td>{$sn}</td>
        <td>".htmlspecialchars($row['student_name'])."</td>
        <td>".htmlspecialchars($row['class_name'])."</td>
        <td>".number_format($row['total_expected'],0,'.',',')."</td>
        <td>".number_format($row['total_paid'],0,'.',',')."</td>
        <td>".number_format(max($balance,0),0,'.',',')."</td>
        <td>{$status}</td>
        <td>".htmlspecialchars($narrations)."</td>
    </tr>";
    $sn++;
}
?>
</tbody>
</table>
</body>
</html>
