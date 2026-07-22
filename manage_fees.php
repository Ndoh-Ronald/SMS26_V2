<?php
session_start();

require_once __DIR__ . '/schooldb.php';
require_once __DIR__ . '/fpdf/fpdf.php';

/* ==========================================================
   SECURITY
========================================================== */

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$role = strtolower($_SESSION['role'] ?? '');

if (!in_array($role, ['admin', 'bursar'])) {
    die("Access denied.");
}

/* ==========================================================
   FILTERS
========================================================== */

$nameFilter   = trim($_GET['name'] ?? '');
$classFilter  = trim($_GET['class'] ?? '');
$genderFilter = trim($_GET['gender'] ?? '');

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

/* ==========================================================
   BUILD WHERE CLAUSE
========================================================== */

$where = [];
$params = [];

if ($nameFilter !== '') {
    $where[] = "CONCAT(s.first_name,' ',s.last_name) LIKE :name";
    $params[':name'] = "%{$nameFilter}%";
}

if ($classFilter !== '') {
    $where[] = "s.class_id = :class_id";
    $params[':class_id'] = $classFilter;
}

if ($genderFilter !== '') {
    $where[] = "LOWER(s.gender) = :gender";
    $params[':gender'] = strtolower($genderFilter);
}

$whereSql = "";

if (!empty($where)) {
    $whereSql = " AND " . implode(" AND ", $where);
}

/* ==========================================================
   COUNT RECORDS
========================================================== */

$countSql = "

SELECT COUNT(DISTINCT s.id)

FROM students s

LEFT JOIN classes c
       ON c.id = s.class_id

LEFT JOIN invoices i
       ON i.student_id = s.id

LEFT JOIN payments p
       ON p.invoice_id = i.id

WHERE 1=1
$whereSql

";

$stmtCount = $pdo->prepare($countSql);

foreach ($params as $key => $value) {
    $stmtCount->bindValue($key, $value);
}

$stmtCount->execute();

$totalStudents = (int)$stmtCount->fetchColumn();

$totalPages = max(1, ceil($totalStudents / $limit));

/* ==========================================================
   MAIN QUERY
========================================================== */

$sql = "

SELECT

s.id AS student_id,

CONCAT(s.first_name,' ',s.last_name) AS student_name,

s.guardian_name AS parent_name,

s.guardian_phone AS parent_contact,

c.class_name,

s.gender,

COALESCE(SUM(i.amount),0) AS expected_fee,

COALESCE(SUM(p.amount),0) AS total_amount_paid,

(COALESCE(SUM(i.amount),0) - COALESCE(SUM(p.amount),0)) AS balance,

GROUP_CONCAT(DISTINCT p.narration SEPARATOR ', ') AS combined_narration
FROM students s

LEFT JOIN classes c
       ON c.id = s.class_id

LEFT JOIN invoices i
       ON i.student_id = s.id

LEFT JOIN payments p
       ON p.invoice_id = i.id

WHERE 1=1
$whereSql

GROUP BY

s.id,
s.first_name,
s.last_name,
s.guardian_name,
s.guardian_phone,
c.class_name,
s.gender

ORDER BY

s.first_name ASC,
s.last_name ASC

LIMIT :limit OFFSET :offset

";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==========================================================
   EXPORT DIRECTORY
========================================================== */

$exportDir = __DIR__ . "/export";

if (!is_dir($exportDir)) {

    mkdir($exportDir, 0777, true);

}

/* ==========================================================
   TOTALS
========================================================== */

$totalExpected = 0;
$totalPaid = 0;
$totalBalance = 0;

foreach ($students as $row) {

    $totalExpected += (float)$row['expected_fee'];

    $totalPaid += (float)$row['total_amount_paid'];

    $totalBalance += (float)$row['balance'];

}

/* ==========================================================
   FORMAT VALUES
========================================================== */

foreach ($students as &$student) {

    $student['expected_fee'] =
        (float)$student['expected_fee'];

    $student['total_amount_paid'] =
        (float)$student['total_amount_paid'];

    $student['balance'] =
        (float)$student['balance'];

}

unset($student);

/* ==========================================================
   EXPORT TO EXCEL
========================================================== */

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'excel'
) {

    $filename = $exportDir .
        "/fee_summary_" .
        date("Y-m-d_H-i-s") .
        ".xls";
            $content = "
    <table border='1'>
        <tr style='background:#4F46E5;color:white;'>
            <th>Student</th>
            <th>Parent Name</th>
            <th>Parent Contact</th>
            <th>Class</th>
            <th>Gender</th>
            <th>Expected Fee</th>
            <th>Total Paid</th>
            <th>Balance</th>
            <th>Narration</th>
        </tr>";

    foreach ($students as $row) {

        $content .= "

        <tr>

            <td>" . htmlspecialchars($row['student_name'], ENT_QUOTES) . "</td>

            <td>" . htmlspecialchars($row['parent_name'], ENT_QUOTES) . "</td>

            <td>" . htmlspecialchars($row['parent_contact'], ENT_QUOTES) . "</td>

            <td>" . htmlspecialchars($row['class_name'], ENT_QUOTES) . "</td>

            <td>" . htmlspecialchars($row['gender'], ENT_QUOTES) . "</td>

            <td align='right'>" .
                number_format($row['expected_fee'], 2) .
            "</td>

            <td align='right'>" .
                number_format($row['total_amount_paid'], 2) .
            "</td>

            <td align='right'>" .
                number_format($row['balance'], 2) .
            "</td>

            <td>" .
                htmlspecialchars($row['combined_narration'] ?? '', ENT_QUOTES) .
            "</td>

        </tr>";

    }

    $content .= "

        <tr style='font-weight:bold;background:#F3F4F6;'>

            <td colspan='5'>TOTALS</td>

            <td align='right'>" .
                number_format($totalExpected,2) .
            "</td>

            <td align='right'>" .
                number_format($totalPaid,2) .
            "</td>

            <td align='right'>" .
                number_format($totalBalance,2) .
            "</td>

            <td></td>

        </tr>

    </table>";

    file_put_contents($filename, $content);

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"" . basename($filename) . "\"");
    header("Content-Length: " . filesize($filename));

    readfile($filename);

    exit();

}

/* ==========================================================
   EXPORT TO PDF
========================================================== */

if (
    isset($_GET['export']) &&
    $_GET['export'] === 'pdf'
) {

    $filename = $exportDir .
        "/fee_summary_" .
        date("Y-m-d_H-i-s") .
        ".pdf";

    $pdf = new FPDF('L', 'mm', 'A4');

    $pdf->AddPage();

    $pdf->SetFont('Arial','B',14);

    $pdf->Cell(0,10,'Student Fees Summary Report',0,1,'C');

    $pdf->Ln(4);
        /* ==========================================================
       PDF HEADER
    ========================================================== */

    $pdf->SetFillColor(79, 70, 229);
    $pdf->SetTextColor(255,255,255);
    $pdf->SetFont('Arial','B',9);

    $pdf->Cell(50,8,'Student',1,0,'C',true);
    $pdf->Cell(40,8,'Parent',1,0,'C',true);
    $pdf->Cell(35,8,'Contact',1,0,'C',true);
    $pdf->Cell(28,8,'Class',1,0,'C',true);
    $pdf->Cell(20,8,'Gender',1,0,'C',true);
    $pdf->Cell(30,8,'Expected',1,0,'C',true);
    $pdf->Cell(30,8,'Paid',1,0,'C',true);
    $pdf->Cell(30,8,'Balance',1,1,'C',true);

    /* ==========================================================
       PDF DATA
    ========================================================== */

    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial','',8);

    foreach ($students as $row) {

        $pdf->Cell(50,7,
            substr($row['student_name'],0,28),
            1);

        $pdf->Cell(40,7,
            substr($row['parent_name'],0,22),
            1);

        $pdf->Cell(35,7,
            substr($row['parent_contact'],0,18),
            1);

        $pdf->Cell(28,7,
            $row['class_name'],
            1);

        $pdf->Cell(20,7,
            ucfirst($row['gender']),
            1);

        $pdf->Cell(
            30,
            7,
            number_format($row['expected_fee'],2),
            1,
            0,
            'R'
        );

        $pdf->Cell(
            30,
            7,
            number_format($row['total_amount_paid'],2),
            1,
            0,
            'R'
        );

        $pdf->Cell(
            30,
            7,
            number_format($row['balance'],2),
            1,
            1,
            'R'
        );
    }

    /* ==========================================================
       TOTAL ROW
    ========================================================== */

    $pdf->SetFont('Arial','B',9);

    $pdf->Cell(173,8,'TOTAL',1);

    $pdf->Cell(
        30,
        8,
        number_format($totalExpected,2),
        1,
        0,
        'R'
    );

    $pdf->Cell(
        30,
        8,
        number_format($totalPaid,2),
        1,
        0,
        'R'
    );

    $pdf->Cell(
        30,
        8,
        number_format($totalBalance,2),
        1,
        1,
        'R'
    );

    $pdf->Output('D', basename($filename));

    exit();
}

/* ==========================================================
   HTML STARTS HERE
========================================================== */
?>
<!DOCTYPE html>
<html lang="en">
    <head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Fees</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100">

<div class="max-w-7xl mx-auto py-6 px-4">

    <!-- Page Header -->

    <div class="flex justify-between items-center mb-6">

        <div>

            <h1 class="text-3xl font-bold text-indigo-700">
                Manage Fees Summary
            </h1>

            <p class="text-gray-500">
                View students' invoices, payments and balances.
            </p>

        </div>

        <div class="space-x-2">

            <a href="dashboard.php"
               class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-800">

                Dashboard

            </a>

            <a href="?<?= http_build_query(array_merge($_GET,['export'=>'excel'])) ?>"
               class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">

                Export Excel

            </a>

            <a href="?<?= http_build_query(array_merge($_GET,['export'=>'pdf'])) ?>"
               class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">

                Export PDF

            </a>

        </div>

    </div>

    <!-- Filter Card -->

    <div class="bg-white rounded-lg shadow p-5 mb-6">

        <form method="GET">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                <div>

                    <label class="block text-sm font-medium text-gray-700 mb-1">

                        Student Name

                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($nameFilter) ?>"
                        class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500">

                </div>

                <div>

                    <label class="block text-sm font-medium text-gray-700 mb-1">

                        Class

                    </label>

                    <select
                        name="class"
                        class="w-full border rounded-lg px-3 py-2">

                        <option value="">All Classes</option>

                        <?php

                        $classStmt = $pdo->query("
                            SELECT id,class_name
                            FROM classes
                            ORDER BY class_name
                        ");

                        while($class = $classStmt->fetch(PDO::FETCH_ASSOC)):

                        ?>

                        <option
                            value="<?= $class['id']; ?>"
                            <?= $classFilter==$class['id'] ? 'selected' : ''; ?>>

                            <?= htmlspecialchars($class['class_name']); ?>

                        </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <div>

                    <label class="block text-sm font-medium text-gray-700 mb-1">

                        Gender

                    </label>

                    <select
                        name="gender"
                        class="w-full border rounded-lg px-3 py-2">

                        <option value="">All</option>

                        <option value="male"
                            <?= strtolower($genderFilter)=='male'?'selected':''; ?>>

                            Male

                        </option>

                        <option value="female"
                            <?= strtolower($genderFilter)=='female'?'selected':''; ?>>

                            Female

                        </option>

                    </select>

                </div>

                <div class="flex items-end">

                    <button
                        type="submit"
                        class="w-full bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700">

                        Apply Filters

                    </button>

                </div>

            </div>

        </form>

    </div>

    <!-- Summary Cards -->
         <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">

        <!-- Total Students -->
        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-blue-600">

            <p class="text-sm text-gray-500">
                Total Students
            </p>

            <h2 class="text-3xl font-bold text-blue-700 mt-2">
                <?= number_format($totalStudents); ?>
            </h2>

        </div>

        <!-- Expected Fees -->
        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-green-600">

            <p class="text-sm text-gray-500">
                Expected Fees
            </p>

            <h2 class="text-2xl font-bold text-green-700 mt-2">
                <?= number_format($totalExpected,2); ?>
            </h2>

        </div>

        <!-- Total Paid -->
        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-indigo-600">

            <p class="text-sm text-gray-500">
                Total Paid
            </p>

            <h2 class="text-2xl font-bold text-indigo-700 mt-2">
                <?= number_format($totalPaid,2); ?>
            </h2>

        </div>

        <!-- Outstanding Balance -->
        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-red-600">

            <p class="text-sm text-gray-500">
                Outstanding Balance
            </p>

            <h2 class="text-2xl font-bold text-red-700 mt-2">
                <?= number_format($totalBalance,2); ?>
            </h2>

        </div>

    </div>

    <!-- Students Fee Summary Table -->

    <div class="bg-white rounded-lg shadow overflow-x-auto">

        <table class="min-w-full divide-y divide-gray-200">

            <thead class="bg-indigo-600 text-white">

                <tr>

                    <th class="px-4 py-3 text-left">#</th>

                    <th class="px-4 py-3 text-left">
                        Student
                    </th>

                    <th class="px-4 py-3 text-left">
                        Parent
                    </th>

                    <th class="px-4 py-3 text-left">
                        Contact
                    </th>

                    <th class="px-4 py-3 text-left">
                        Class
                    </th>

                    <th class="px-4 py-3 text-center">
                        Gender
                    </th>

                    <th class="px-4 py-3 text-right">
                        Expected
                    </th>

                    <th class="px-4 py-3 text-right">
                        Paid
                    </th>

                    <th class="px-4 py-3 text-right">
                        Balance
                    </th>

                    <th class="px-4 py-3 text-left">
                        Narration
                    </th>

                    <?php if($role=='admin'): ?>

                    <th class="px-4 py-3 text-center">

                        Actions

                    </th>

                    <?php endif; ?>

                </tr>

            </thead>

            <tbody class="divide-y divide-gray-100 bg-white">

            <?php if(empty($students)): ?>

                <tr>

                    <td colspan="<?= $role=='admin' ? 11 : 10; ?>"
                        class="text-center py-10 text-gray-500">

                        No student records found.

                    </td>

                </tr>

            <?php else:

                $sn = $offset + 1;

                foreach($students as $student):

            ?>
                            <tr class="hover:bg-gray-50 transition">

                    <td class="px-4 py-3">
                        <?= $sn++; ?>
                    </td>

                    <td class="px-4 py-3 font-medium text-gray-800">
                        <?= htmlspecialchars($student['student_name']); ?>
                    </td>

                    <td class="px-4 py-3">
                        <?= htmlspecialchars($student['parent_name']); ?>
                    </td>

                    <td class="px-4 py-3">
                        <?= htmlspecialchars($student['parent_contact']); ?>
                    </td>

                    <td class="px-4 py-3">
                        <?= htmlspecialchars($student['class_name']); ?>
                    </td>

                    <td class="px-4 py-3 text-center">

                        <?php if(strtolower($student['gender'])=='male'): ?>

                            <span class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs">
                                Male
                            </span>

                        <?php else: ?>

                            <span class="px-2 py-1 rounded bg-pink-100 text-pink-700 text-xs">
                                Female
                            </span>

                        <?php endif; ?>

                    </td>

                    <td class="px-4 py-3 text-right font-medium">

                        <?= number_format($student['expected_fee'],2); ?>

                    </td>

                    <td class="px-4 py-3 text-right text-green-700 font-semibold">

                        <?= number_format($student['total_amount_paid'],2); ?>

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?php

                        if($student['balance'] > 0){

                            echo "<span class='text-red-600 font-bold'>"
                                . number_format($student['balance'],2)
                                . "</span>";

                        }elseif($student['balance'] < 0){

                            echo "<span class='text-orange-600 font-bold'>"
                                . number_format($student['balance'],2)
                                . "</span>";

                        }else{

                            echo "<span class='text-green-700 font-bold'>"
                                . number_format($student['balance'],2)
                                . "</span>";

                        }

                        ?>

                    </td>

                    <td class="px-4 py-3">

                        <?= htmlspecialchars($student['combined_narration'] ?? '-'); ?>

                    </td>

                    <?php if($role=='admin'): ?>

                    <td class="px-4 py-3 text-center space-x-2">

                        <a href="edit_fee.php?student_id=<?= $student['student_id']; ?>"
                           class="inline-block px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">

                            Edit

                        </a>

                        <a href="student_statement.php?student_id=<?= $student['student_id']; ?>"
                           class="inline-block px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700">

                            Statement

                        </a>

                    </td>

                    <?php endif; ?>

                </tr>

            <?php

                endforeach;

            endif;

            ?>

            </tbody>

            <tfoot class="bg-gray-100 font-semibold">

                <tr>

                    <td colspan="6" class="px-4 py-3 text-right">

                        GRAND TOTAL

                    </td>

                    <td class="px-4 py-3 text-right text-blue-700">

                        <?= number_format($totalExpected,2); ?>

                    </td>

                    <td class="px-4 py-3 text-right text-green-700">

                        <?= number_format($totalPaid,2); ?>

                    </td>

                    <td class="px-4 py-3 text-right text-red-700">

                        <?= number_format($totalBalance,2); ?>

                    </td>

                    <td colspan="<?= $role=='admin' ? 2 : 1; ?>"></td>

                </tr>

            </tfoot>

        </table>

    </div>

    <!-- Pagination -->
         <div class="flex flex-col md:flex-row justify-between items-center mt-6">

        <div class="text-sm text-gray-600 mb-3 md:mb-0">

            Showing

            <strong>
                <?= count($students) ? ($offset + 1) : 0; ?>
            </strong>

            -

            <strong>
                <?= min($offset + $limit, $totalStudents); ?>
            </strong>

            of

            <strong>
                <?= number_format($totalStudents); ?>
            </strong>

            students

        </div>

        <div class="flex gap-2">

            <?php if($page > 1): ?>

                <a href="?page=<?= $page-1; ?>
                    &name=<?= urlencode($nameFilter); ?>
                    &class=<?= urlencode($classFilter); ?>
                    &gender=<?= urlencode($genderFilter); ?>"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">

                    Previous

                </a>

            <?php endif; ?>

            <?php

            for($i = 1; $i <= $totalPages; $i++):

            ?>

                <a href="?page=<?= $i; ?>
                    &name=<?= urlencode($nameFilter); ?>
                    &class=<?= urlencode($classFilter); ?>
                    &gender=<?= urlencode($genderFilter); ?>"

                    class="px-4 py-2 rounded
                    <?= $i == $page
                        ? 'bg-indigo-600 text-white'
                        : 'bg-white border hover:bg-gray-100'; ?>">

                    <?= $i; ?>

                </a>

            <?php endfor; ?>

            <?php if($page < $totalPages): ?>

                <a href="?page=<?= $page+1; ?>
                    &name=<?= urlencode($nameFilter); ?>
                    &class=<?= urlencode($classFilter); ?>
                    &gender=<?= urlencode($genderFilter); ?>"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">

                    Next

                </a>

            <?php endif; ?>

        </div>

    </div>

    <!-- Footer -->

    <div class="mt-10 border-t pt-5 flex justify-between items-center">

        <p class="text-sm text-gray-500">

            SMS26 School Management System

        </p>

        <p class="text-sm text-gray-500">

            Generated on <?= date('d M Y h:i A'); ?>

        </p>

    </div>

</div>

</body>

</html>