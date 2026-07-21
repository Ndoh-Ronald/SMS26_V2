<?php
session_start();

// SQLite connection
require_once __DIR__ . '/schooldb.php';
// Check user role
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin','bursar'])) {
    die("Access denied!");
}

// Class filter
$classFilter = intval($_GET['class'] ?? 0);

// Check for Excel export
$exportExcel = isset($_GET['export']) && $_GET['export'] === 'excel';

// Fetch all classes
$classes = [];
foreach ($pdo->query("SELECT id, class_name FROM classes ORDER BY class_name") as $row) {
    $classes[$row['id']] = $row['class_name'];
}

// Build WHERE clause
$where = '';
$params = [];
if ($classFilter && isset($classes[$classFilter])) {
    $where = "WHERE s.class_id = ?";
    $params[] = $classFilter;
}

// Fetch students with expected fees
$sql = "
    SELECT
        s.id AS student_id,
        s.name AS student_name,
        c.class_name,
        IFNULL(c.standard_fee,0) AS expected_fee,
        COALESCE(SUM(f.amount),0) AS total_paid,
       GROUP_CONCAT(DISTINCT f.narration SEPARATOR ', ') AS narrations
    FROM students s
    LEFT JOIN classes c
        ON c.id = s.class_id
    LEFT JOIN fees f
        ON f.student_id = s.id
    $where
    GROUP BY
        s.id,
        s.name,
        c.class_name,
        c.standard_fee
    ORDER BY
        c.class_name,
        s.name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compute balances
$class_summary = [];
$grand_totals = ['expected'=>0,'paid'=>0,'balance'=>0];

foreach ($students as &$s) {
    $s['expected_fee'] = floatval($s['expected_fee']);
    $s['total_paid'] = floatval($s['total_paid']);
    $s['balance'] = $s['expected_fee'] - $s['total_paid'];
    $s['status'] = ($s['balance'] <= 0) ? "Cleared" : "Owes";

    $class = $s['class_name'] ?? 'Unknown';
    if (!isset($class_summary[$class])) {
        $class_summary[$class] = ['students'=>0,'expected'=>0,'paid'=>0,'balance'=>0];
    }
    $class_summary[$class]['students']++;
    $class_summary[$class]['expected'] += $s['expected_fee'];
    $class_summary[$class]['paid'] += $s['total_paid'];
    $class_summary[$class]['balance'] += $s['balance'];

    $grand_totals['expected'] += $s['expected_fee'];
    $grand_totals['paid'] += $s['total_paid'];
    $grand_totals['balance'] += $s['balance'];
}
unset($s);

// Excel export logic
if ($exportExcel) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=student_fees.xls");

    echo "<table border='1'>";
    echo "<tr><th>#</th><th>Student</th><th>Class</th><th>Expected Fee</th><th>Total Paid</th><th>Balance</th><th>Status</th><th>Narration</th></tr>";
    $sn = 1;
    foreach ($students as $s) {
        $status_label = ($s['balance'] <= 0) ? "Cleared" : "Owes";
        echo "<tr>
            <td>{$sn}</td>
            <td>{$s['student_name']}</td>
            <td>{$s['class_name']}</td>
            <td>{$s['expected_fee']}</td>
            <td>{$s['total_paid']}</td>
            <td>{$s['balance']}</td>
            <td>{$status_label}</td>
            <td>{$s['narrations']}</td>
        </tr>";
        $sn++;
    }
    echo "</table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Fee Status</title>
<style>
body { font-family: Arial,sans-serif; padding:20px; background:#f9fafb; }
table { width:100%; border-collapse:collapse; margin-bottom:30px; background:#fff; box-shadow:0 0 8px rgba(0,0,0,0.1); }
th, td { border:1px solid #ddd; padding:10px; text-align:center; }
th { background:#4f46e5; color:#fff; }
tr:nth-child(even) { background:#f3f4f6; }
.owe { color:#dc2626; font-weight:bold; }
.cleared { color:#16a34a; font-weight:bold; }
.total-row { font-weight:bold; background:#e5e7eb; }
button, select { padding:5px 10px; margin-left:10px; }
</style>
</head>
<body>
<h2>📊 Student Fee Status</h2>

<form method="GET" style="margin-bottom:15px;">
    <label>Filter by Class:</label>
    <select name="class" onchange="this.form.submit()">
        <option value="0">All Classes</option>
        <?php foreach ($classes as $id=>$c): ?>
            <option value="<?= $id ?>" <?= ($classFilter==$id)?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="export" value="excel">Export to Excel</button>
</form>

<h3>Student Records</h3>
<table>
<thead>
<tr>
<th>#</th>
<th>Student</th>
<th>Class</th>
<th>Expected Fee</th>
<th>Total Paid</th>
<th>Balance</th>
<th>Status</th>
<th>Narration</th>
</tr>
</thead>
<tbody>
<?php
if (empty($students)) {
    echo "<tr><td colspan='8'>No records found.</td></tr>";
} else {
    $sn=1;
    foreach ($students as $s) {
        $status_label = ($s['balance'] <= 0) ? "<span class='cleared'>Cleared</span>" : "<span class='owe'>Owes</span>";
        echo "<tr>
            <td>{$sn}</td>
            <td>".htmlspecialchars($s['student_name'])."</td>
            <td>".htmlspecialchars($s['class_name'])."</td>
            <td>".number_format($s['expected_fee'],2)."</td>
            <td>".number_format($s['total_paid'],2)."</td>
            <td>".number_format($s['balance'],2)."</td>
            <td>{$status_label}</td>
            <td>".htmlspecialchars($s['narrations'] ?? '-')."</td>
        </tr>";
        $sn++;
    }
}
?>
<tr class="total-row">
<td colspan="3">Grand Total</td>
<td><?= number_format($grand_totals['expected'],2) ?></td>
<td><?= number_format($grand_totals['paid'],2) ?></td>
<td><?= number_format($grand_totals['balance'],2) ?></td>
<td colspan="2"></td>
</tr>
</tbody>
</table>

<h3>Class Summary</h3>
<table>
<thead>
<tr>
<th>Class</th>
<th># Students</th>
<th>Total Expected</th>
<th>Total Paid</th>
<th>Balance</th>
</tr>
</thead>
<tbody>
<?php foreach($class_summary as $cls=>$cs): ?>
<tr>
<td><?= htmlspecialchars($cls) ?></td>
<td><?= $cs['students'] ?></td>
<td><?= number_format($cs['expected'],2) ?></td>
<td><?= number_format($cs['paid'],2) ?></td>
<td><?= number_format($cs['balance'],2) ?></td>
</tr>
<?php endforeach; ?>
<tr class="total-row">
<td>Grand Total</td>
<td><?= count($students) ?></td>
<td><?= number_format($grand_totals['expected'],2) ?></td>
<td><?= number_format($grand_totals['paid'],2) ?></td>
<td><?= number_format($grand_totals['balance'],2) ?></td>
</tr>
</tbody>
</table>

<a href="dashboard.php">← Back to Dashboard</a>
</body>
</html>
