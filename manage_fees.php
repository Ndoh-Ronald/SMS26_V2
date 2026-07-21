<?php
session_start();
require_once __DIR__ . '/schooldb.php';
require_once __DIR__ . '/fpdf/fpdf.php';

if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    header("Location: login.php");
    exit();
}

$role = strtolower($_SESSION['role']);
$nameFilter = $_GET['name'] ?? '';
$classFilter = $_GET['class'] ?? '';
$genderFilter = $_GET['gender'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Base SQL
$sqlBase = "
    FROM students s
    LEFT JOIN fees f ON f.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE 1=1
";

$params = [];

// Filters
if ($nameFilter !== "") {
    $sqlBase .= " AND s.name LIKE :name";
    $params[':name'] = "%$nameFilter%";
}
if ($classFilter !== "") {
    $sqlBase .= " AND s.class_id = :class_id";
    $params[':class_id'] = $classFilter;
}
if ($genderFilter !== "") {
    $sqlBase .= " AND s.gender = :gender";
    $params[':gender'] = $genderFilter;
}

// Count total students
$countSql = "SELECT COUNT(DISTINCT s.id) " . $sqlBase;
$stmtCount = $pdo->prepare($countSql);
foreach ($params as $key => &$val) {
    $stmtCount->bindParam($key, $val);
}
$stmtCount->execute();
$total = $stmtCount->fetchColumn();
$totalPages = ceil($total / $limit);

// Fetch student fee summary
$sql = "SELECT 
        s.id AS student_id,
        s.name AS student_name,
        s.guardian AS parent_name,
        s.parent_contact,
        c.class_name AS class_name,
        s.gender,
        COALESCE(c.standard_fee,0) AS expected_fee,
        COALESCE(SUM(f.amount),0) AS total_amount_paid,
        COALESCE(c.standard_fee,0) - COALESCE(SUM(f.amount),0) AS balance,
        GROUP_CONCAT(DISTINCT f.narration) AS combined_narration
    $sqlBase
    GROUP BY s.id, s.name, s.guardian, s.parent_contact, c.class_name, s.gender, c.standard_fee
    ORDER BY s.name ASC
    LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => &$val) {
    $stmt->bindParam($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* -------------------------
   EXPORT SECTION
-------------------------- */
$exportDir = __DIR__ . "/export";
if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}

// Export Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = $exportDir . "/fee_summary_" . date("Y-m-d_H-i-s") . ".xls";

    $content = "<table border='1'><tr>
        <th>Student</th><th>Parent Name</th><th>Parent Contact</th><th>Class</th><th>Gender</th><th>Expected Fee</th>
        <th>Total Paid</th><th>Balance</th><th>Narration</th>
    </tr>";

    foreach ($students as $row) {
        $content .= "<tr>
            <td>".htmlspecialchars($row['student_name'] ?? '', ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['parent_name'] ?? '', ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['parent_contact'] ?? '', ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['class_name'] ?? '', ENT_QUOTES)."</td>
            <td>".htmlspecialchars($row['gender'] ?? '', ENT_QUOTES)."</td>
            <td>".number_format($row['expected_fee'],2)."</td>
            <td>".number_format($row['total_amount_paid'],2)."</td>
            <td>".number_format($row['balance'],2)."</td>
            <td>".htmlspecialchars($row['combined_narration'] ?? '', ENT_QUOTES)."</td>
        </tr>";
    }
    $content .= "</table>";

    file_put_contents($filename, $content);

    // Auto-open Excel (Windows)
    pclose(popen("start excel \"" . $filename . "\"", "r"));
    header("Location: manage_fees.php");
    exit;
}

// Export PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $filename = $exportDir . "/fee_summary_" . date("Y-m-d_H-i-s") . ".pdf";

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Fees Summary Report', 0, 1, 'C');
    $pdf->Ln(5);

    // Table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(35, 8, 'Student', 1);
    $pdf->Cell(30, 8, 'Parent', 1);
    $pdf->Cell(25, 8, 'Contact', 1);
    $pdf->Cell(20, 8, 'Class', 1);
    $pdf->Cell(15, 8, 'Gender', 1);
    $pdf->Cell(25, 8, 'Expected', 1, 0, 'R');
    $pdf->Cell(25, 8, 'Paid', 1, 0, 'R');
    $pdf->Cell(25, 8, 'Balance', 1, 0, 'R');
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 9);
    foreach ($students as $row) {
        $pdf->Cell(35, 8, $row['student_name'], 1);
        $pdf->Cell(30, 8, $row['parent_name'], 1);
        $pdf->Cell(25, 8, $row['parent_contact'], 1);
        $pdf->Cell(20, 8, $row['class_name'], 1);
        $pdf->Cell(15, 8, $row['gender'], 1);
        $pdf->Cell(25, 8, number_format($row['expected_fee'],2), 1, 0, 'R');
        $pdf->Cell(25, 8, number_format($row['total_amount_paid'],2), 1, 0, 'R');
        $pdf->Cell(25, 8, number_format($row['balance'],2), 1, 0, 'R');
        $pdf->Ln();
    }

    $pdf->Output('F', $filename);

    // Auto-open in default PDF viewer
    pclose(popen("start \"\" \"" . $filename . "\"", "r"));
    header("Location: manage_fees.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Fees Summary</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 font-sans">

<h1 class="text-2xl font-bold mb-4 text-indigo-700">Manage Fees (Summary View)</h1>

<form method="GET" class="bg-white p-4 rounded shadow mb-6 flex flex-wrap gap-4 items-end">
    <div class="flex flex-col">
        <label class="text-sm text-gray-600">Student Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($nameFilter, ENT_QUOTES) ?>" class="px-3 py-2 border rounded" />
    </div>
    <div class="flex flex-col">
        <label class="text-sm text-gray-600">Class</label>
        <select name="class" class="px-3 py-2 border rounded">
            <option value="">All</option>
            <?php 
            $classes = $pdo->query("SELECT id, class_name FROM classes ORDER BY class_name ASC");
            while ($c = $classes->fetch(PDO::FETCH_ASSOC)):
                $selected = ($classFilter == $c['id']) ? 'selected' : '';
                echo "<option value='".htmlspecialchars($c['id'], ENT_QUOTES)."' $selected>".htmlspecialchars($c['class_name'], ENT_QUOTES)."</option>";
            endwhile;
            ?>
        </select>
    </div>
    <div class="flex flex-col">
        <label class="text-sm text-gray-600">Gender</label>
        <select name="gender" class="px-3 py-2 border rounded">
            <option value="">All</option>
            <option value="male" <?= strtolower($genderFilter) === 'male' ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= strtolower($genderFilter) === 'female' ? 'selected' : '' ?>>Female</option>
        </select>
    </div>
    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Filter</button>
    
    <!-- Export Buttons -->
    <a href="?export=excel<?= ($nameFilter?'&name='.urlencode($nameFilter):'').($classFilter?'&class='.urlencode($classFilter):'').($genderFilter?'&gender='.urlencode($genderFilter):'') ?>" 
       class="ml-auto bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Export Excel</a>
    <a href="?export=pdf<?= ($nameFilter?'&name='.urlencode($nameFilter):'').($classFilter?'&class='.urlencode($classFilter):'').($genderFilter?'&gender='.urlencode($genderFilter):'') ?>" 
       class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Export PDF</a>
</form>

<!-- Table -->
<div class="overflow-x-auto bg-white rounded shadow">
<table class="min-w-full text-sm">
<thead class="bg-indigo-200 text-indigo-800">
<tr>
    <th class="p-3 text-left">#</th>
    <th class="p-3 text-left">Student</th>
    <th class="p-3 text-left">Parent Name</th>
    <th class="p-3 text-left">Parent Contact</th>
    <th class="p-3 text-left">Class</th>
    <th class="p-3 text-left">Gender</th>
    <th class="p-3 text-right">Expected Fee</th>
    <th class="p-3 text-right">Total Paid</th>
    <th class="p-3 text-right">Balance</th>
    <th class="p-3 text-left">Narration</th>
    <?php if ($role === 'admin'): ?>
    <th class="p-3 text-center">Action</th>
    <?php endif; ?>
</tr>
</thead>
<tbody>
<?php if (empty($students)): ?>
<tr><td colspan="11" class="p-4 text-center text-gray-500">No fee summary found.</td></tr>
<?php else: $index = $offset + 1; ?>
<?php foreach ($students as $stu): ?>
<tr class="border-b hover:bg-gray-50">
    <td class="p-3"><?= $index++ ?></td>
    <td class="p-3"><?= htmlspecialchars($stu['student_name'] ?? '', ENT_QUOTES) ?></td>
    <td class="p-3"><?= htmlspecialchars($stu['parent_name'] ?? '-', ENT_QUOTES) ?></td>
    <td class="p-3"><?= htmlspecialchars($stu['parent_contact'] ?? '-', ENT_QUOTES) ?></td>
    <td class="p-3"><?= htmlspecialchars($stu['class_name'] ?? '-', ENT_QUOTES) ?></td>
    <td class="p-3"><?= htmlspecialchars($stu['gender'] ?? '-', ENT_QUOTES) ?></td>
    <td class="p-3 text-right"><?= number_format($stu['expected_fee'], 2) ?></td>
    <td class="p-3 text-right"><?= number_format($stu['total_amount_paid'], 2) ?></td>
    <td class="p-3 text-right text-red-700 font-semibold"><?= number_format($stu['balance'], 2) ?></td>
    <td class="p-3"><?= htmlspecialchars($stu['combined_narration'] ?? '', ENT_QUOTES) ?></td>
    <?php if ($role === 'admin'): ?>
    <td class="p-3 text-center">
        <a href="edit_fee.php?student_id=<?= htmlspecialchars($stu['student_id'], ENT_QUOTES) ?>" class="text-blue-600 hover:underline">Edit</a>
    </td>
    <?php endif; ?>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<div class="mt-4 flex justify-center gap-2">
<?php
$filterParams = ($nameFilter?'&name='.urlencode($nameFilter):'') . ($classFilter?'&class='.urlencode($classFilter):'') . ($genderFilter?'&gender='.urlencode($genderFilter):'');
for ($i = 1; $i <= $totalPages; $i++):
?>
    <a href="?page=<?= $i . $filterParams ?>" class="px-3 py-1 border <?= $i==$page?'bg-indigo-600 text-white':'bg-white text-indigo-700' ?> rounded"><?= $i ?></a>
<?php endfor; ?>
</div>

<a href="dashboard.php" class="text-indigo-600 hover:underline mt-4 inline-block">&larr; Back to Dashboard</a>
</body>
</html>
