<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Restrict access
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    header("Location: login.php");
    exit;
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Class filter
$classFilter = $_GET['class'] ?? '';

// Total students for pagination
$countQuery = $mysqli->prepare("SELECT COUNT(DISTINCT s.id) FROM students s WHERE (? = '' OR s.class = ?)");
$countQuery->bind_param("ss", $classFilter, $classFilter);
$countQuery->execute();
$countQuery->bind_result($totalStudents);
$countQuery->fetch();
$countQuery->close();

$totalPages = ceil($totalStudents / $limit);

// Get class list for filter
$classes = $mysqli->query("SELECT DISTINCT class FROM students ORDER BY class");

// Main report query
$stmt = $mysqli->prepare("
    SELECT 
        s.id AS student_id,
        s.name,
        s.class,
        COALESCE(SUM(f.amount), 0) AS total_paid,
        COALESCE(cf.standard_fee, 0) AS expected_fee,
        COALESCE(cf.standard_fee, 0) - COALESCE(SUM(f.amount), 0) AS balance
    FROM students s
    LEFT JOIN fees f ON s.id = f.student_id
    LEFT JOIN class_fee cf ON TRIM(LOWER(s.class)) = TRIM(LOWER(cf.class_name))
    WHERE (? = '' OR s.class = ?)
    GROUP BY s.id, s.name, s.class, cf.standard_fee
    ORDER BY s.class, s.name
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ssii", $classFilter, $classFilter, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Grand Totals
$totalQuery = $mysqli->prepare("
    SELECT 
        COALESCE(SUM(f.amount), 0) AS total_collected,
        COALESCE(SUM(cf.standard_fee), 0) AS total_expected
    FROM students s
    LEFT JOIN fees f ON s.id = f.student_id
    LEFT JOIN class_fee cf ON TRIM(LOWER(s.class)) = TRIM(LOWER(cf.class_name))
    WHERE (? = '' OR s.class = ?)
");
$totalQuery->bind_param("ss", $classFilter, $classFilter);
$totalQuery->execute();
$totalQuery->bind_result($grandCollected, $grandExpected);
$totalQuery->fetch();
$totalQuery->close();

$grandBalance = $grandExpected - $grandCollected;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fee Report by Class</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #aaa; padding: 8px; }
        th { background-color: #f4f4f4; }
        .pagination a {
            padding: 5px 10px;
            margin: 0 2px;
            border: 1px solid #ccc;
            text-decoration: none;
        }
    </style>
</head>
<body>

<h2>Class-wise Fee Report</h2>

<form method="get">
    <label for="class">Filter by Class:</label>
    <select name="class" onchange="this.form.submit()">
        <option value="">All Classes</option>
        <?php while ($c = $classes->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($c['class']) ?>" <?= $classFilter == $c['class'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['class']) ?>
            </option>
        <?php endwhile; ?>
    </select>
</form>

<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Class</th>
            <th>Total Paid</th>
            <th>Expected Fee</th>
            <th>Balance</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['class']) ?></td>
            <td><?= number_format($row['total_paid']) ?></td>
            <td><?= number_format($row['expected_fee']) ?></td>
            <td style="color: <?= $row['balance'] > 0 ? 'red' : 'green' ?>">
                <?= number_format($row['balance']) ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<h4>Grand Totals<?= $classFilter ? " for " . htmlspecialchars($classFilter) : "" ?></h4>
<ul>
    <li><strong>Total Expected:</strong> <?= number_format($grandExpected) ?> FCFA</li>
    <li><strong>Total Paid:</strong> <?= number_format($grandCollected) ?> FCFA</li>
    <li><strong>Total Outstanding:</strong> <?= number_format($grandBalance) ?> FCFA</li>
</ul>

<div class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?class=<?= urlencode($classFilter) ?>&page=<?= $i ?>" <?= $i == $page ? 'style="font-weight:bold;"' : '' ?>>
            <?= $i ?>
        </a>
    <?php endfor; ?>
</div>

</body>
</html>
