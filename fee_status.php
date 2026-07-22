<?php
session_start();

require_once __DIR__ . '/schooldb.php';

/*=========================================================
| AUTHENTICATION
=========================================================*/

if (
    !isset($_SESSION['username']) ||
    !isset($_SESSION['role']) ||
    !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])
) {
    die("Access Denied!");
}

/*=========================================================
| FILTERS
=========================================================*/

$classFilter = isset($_GET['class'])
    ? (int) $_GET['class']
    : 0;

$exportExcel = (
    isset($_GET['export']) &&
    $_GET['export'] === 'excel'
);

/*=========================================================
| LOAD CLASSES
=========================================================*/

$classes = [];

$classQuery = $pdo->query("
    SELECT
        id,
        class_name
    FROM classes
    ORDER BY class_name
");

while ($row = $classQuery->fetch(PDO::FETCH_ASSOC)) {

    $classes[$row['id']] = $row['class_name'];

}

/*=========================================================
| WHERE CLAUSE
=========================================================*/

$where = "";
$params = [];

if ($classFilter > 0) {

    $where = "WHERE s.class_id = ?";

    $params[] = $classFilter;

}

/*=========================================================
| STUDENT FEE STATUS
=========================================================*/

$sql = "

SELECT

    s.id,

    s.admission_no,

    CONCAT(
        s.first_name,
        ' ',
        s.last_name
    ) AS student_name,

    c.class_name,

    COALESCE(inv.expected_fee,0) AS expected_fee,

    COALESCE(pay.total_paid,0) AS total_paid,

    pay.narrations

FROM students s

LEFT JOIN classes c
ON c.id = s.class_id

LEFT JOIN (

    SELECT

        student_id,

        SUM(amount) AS expected_fee

    FROM invoices

    GROUP BY student_id

) inv

ON inv.student_id = s.id

LEFT JOIN (

    SELECT

        i.student_id,

        SUM(p.amount) AS total_paid,

        GROUP_CONCAT(
            DISTINCT p.narration
            SEPARATOR ', '
        ) AS narrations

    FROM invoices i

    LEFT JOIN payments p
    ON p.invoice_id = i.id

    GROUP BY i.student_id

) pay

ON pay.student_id = s.id

$where

ORDER BY

    c.class_name,

    s.first_name,

    s.last_name

";

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*=========================================================
| CALCULATE TOTALS
=========================================================*/

$class_summary = [];

$grand_totals = [

    'expected' => 0,

    'paid' => 0,

    'balance' => 0

];

foreach ($students as &$student) {

    $student['expected_fee'] = (float) $student['expected_fee'];

    $student['total_paid'] = (float) $student['total_paid'];

    $student['balance'] =
        $student['expected_fee']
        -
        $student['total_paid'];

    $student['status'] =
        ($student['balance'] <= 0)
        ? 'Cleared'
        : 'Owes';

    $class = $student['class_name'] ?? 'Unknown';

    if (!isset($class_summary[$class])) {

        $class_summary[$class] = [

            'students' => 0,

            'expected' => 0,

            'paid' => 0,

            'balance' => 0

        ];

    }

    $class_summary[$class]['students']++;

    $class_summary[$class]['expected'] += $student['expected_fee'];

    $class_summary[$class]['paid'] += $student['total_paid'];

    $class_summary[$class]['balance'] += $student['balance'];

    $grand_totals['expected'] += $student['expected_fee'];

    $grand_totals['paid'] += $student['total_paid'];

    $grand_totals['balance'] += $student['balance'];

}

unset($student);

/*=========================================================
| EXPORT TO EXCEL
=========================================================*/

if ($exportExcel) {

    header("Content-Type: application/vnd.ms-excel");

    header("Content-Disposition: attachment; filename=fee_status.xls");

    echo "<table border='1'>";

    echo "
    <tr>
        <th>#</th>
        <th>Admission No</th>
        <th>Student</th>
        <th>Class</th>
        <th>Expected Fee</th>
        <th>Total Paid</th>
        <th>Balance</th>
        <th>Status</th>
        <th>Narration</th>
    </tr>";

    $sn = 1;

    foreach ($students as $student) {

        echo "<tr>

            <td>{$sn}</td>

            <td>{$student['admission_no']}</td>

            <td>{$student['student_name']}</td>

            <td>{$student['class_name']}</td>

            <td>{$student['expected_fee']}</td>

            <td>{$student['total_paid']}</td>

            <td>{$student['balance']}</td>

            <td>{$student['status']}</td>

            <td>{$student['narrations']}</td>

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

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Fee Status</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100">

<div class="max-w-7xl mx-auto p-6">

    <!-- Header -->

    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">

        <div>

            <h1 class="text-3xl font-bold text-gray-800">
                Student Fee Status
            </h1>

            <p class="text-gray-500 mt-1">
                View expected fees, payments and outstanding balances.
            </p>

        </div>

        <div class="mt-4 md:mt-0">

            <a href="dashboard.php"
               class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg">

                ← Dashboard

            </a>

        </div>

    </div>

    <!-- Statistics -->

    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">

        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-blue-600">

            <h3 class="text-gray-500 text-sm">
                Expected Fees
            </h3>

            <p class="text-2xl font-bold text-blue-700">

                FCFA <?= number_format($grand_totals['expected'],2) ?>

            </p>

        </div>

        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-green-600">

            <h3 class="text-gray-500 text-sm">
                Total Paid
            </h3>

            <p class="text-2xl font-bold text-green-700">

                FCFA <?= number_format($grand_totals['paid'],2) ?>

            </p>

        </div>

        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-red-600">

            <h3 class="text-gray-500 text-sm">
                Outstanding
            </h3>

            <p class="text-2xl font-bold text-red-700">

                FCFA <?= number_format($grand_totals['balance'],2) ?>

            </p>

        </div>

        <div class="bg-white rounded-xl shadow p-5 border-l-4 border-purple-600">

            <h3 class="text-gray-500 text-sm">
                Students
            </h3>

            <p class="text-2xl font-bold text-purple-700">

                <?= count($students) ?>

            </p>

        </div>

    </div>

    <!-- Filter -->

    <div class="bg-white shadow rounded-xl p-5 mb-6">

        <form method="GET"
              class="grid grid-cols-1 md:grid-cols-4 gap-4">

            <div>

                <label class="block text-sm font-medium text-gray-700 mb-2">

                    Filter by Class

                </label>

                <select
                    name="class"
                    class="w-full border rounded-lg px-3 py-2">

                    <option value="0">

                        All Classes

                    </option>

                    <?php foreach($classes as $id=>$class): ?>

                        <option
                            value="<?= $id ?>"
                            <?= ($classFilter==$id)?'selected':'' ?>>

                            <?= htmlspecialchars($class) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="flex items-end">

                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">

                    Filter

                </button>

            </div>

            <div class="flex items-end">

                <a
                    href="fee_status.php?export=excel<?= $classFilter ? '&class='.$classFilter : '' ?>"
                    class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg">

                    Export Excel

                </a>

            </div>

            <div class="flex items-end">

                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search Student..."
                    class="w-full border rounded-lg px-3 py-2">

            </div>

        </form>

    </div>
    <!-- Student Fee Status -->

<div class="bg-white rounded-xl shadow overflow-hidden mb-8">

    <div class="px-6 py-4 border-b">

        <h2 class="text-xl font-semibold text-gray-800">
            Student Fee Records
        </h2>

    </div>

    <div class="overflow-x-auto">

        <table
            id="studentsTable"
            class="min-w-full divide-y divide-gray-200">

            <thead class="bg-gray-100">

                <tr>

                    <th class="px-4 py-3 text-left">#</th>

                    <th class="px-4 py-3 text-left">
                        Admission No
                    </th>

                    <th class="px-4 py-3 text-left">
                        Student
                    </th>

                    <th class="px-4 py-3 text-left">
                        Class
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

                    <th class="px-4 py-3 text-center">
                        Status
                    </th>

                    <th class="px-4 py-3 text-left">
                        Narration
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-gray-200 bg-white">

            <?php

            if (empty($students)) {

                echo "

                <tr>

                    <td colspan='9'

                        class='text-center py-10 text-gray-500'>

                        No student records found.

                    </td>

                </tr>

                ";

            } else {

                $sn = 1;

                foreach ($students as $student):

                ?>

                <tr class="hover:bg-gray-50">

                    <td class="px-4 py-3">

                        <?= $sn++ ?>

                    </td>

                    <td class="px-4 py-3">

                        <?= htmlspecialchars($student['admission_no']) ?>

                    </td>

                    <td class="px-4 py-3 font-medium">

                        <?= htmlspecialchars($student['student_name']) ?>

                    </td>

                    <td class="px-4 py-3">

                        <?= htmlspecialchars($student['class_name']) ?>

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?= number_format($student['expected_fee'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-right text-green-700 font-semibold">

                        <?= number_format($student['total_paid'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-right font-semibold">

                        <?= number_format($student['balance'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-center">

                        <?php if($student['balance']<=0): ?>

                            <span
                                class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">

                                Cleared

                            </span>

                        <?php else: ?>

                            <span
                                class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm font-semibold">

                                Owes

                            </span>

                        <?php endif; ?>

                    </td>

                    <td class="px-4 py-3">

                        <?= htmlspecialchars($student['narrations'] ?? '-') ?>

                    </td>

                </tr>

                <?php endforeach; } ?>

            </tbody>

            <tfoot class="bg-gray-100 font-bold">

                <tr>

                    <td colspan="4"
                        class="px-4 py-3">

                        GRAND TOTAL

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?= number_format($grand_totals['expected'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?= number_format($grand_totals['paid'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?= number_format($grand_totals['balance'],2) ?>

                    </td>

                    <td colspan="2"></td>

                </tr>

            </tfoot>

        </table>

    </div>

</div>
<!-- =========================================================
     CLASS SUMMARY
========================================================= -->

<div class="bg-white rounded-xl shadow overflow-hidden mb-8">

    <div class="px-6 py-4 border-b">

        <h2 class="text-xl font-semibold text-gray-800">
            Class Fee Summary
        </h2>

        <p class="text-sm text-gray-500 mt-1">
            Summary of expected fees, collections and outstanding balances by class.
        </p>

    </div>

    <div class="overflow-x-auto">

        <table class="min-w-full divide-y divide-gray-200">

            <thead class="bg-gray-100">

                <tr>

                    <th class="px-4 py-3 text-left">
                        Class
                    </th>

                    <th class="px-4 py-3 text-center">
                        Students
                    </th>

                    <th class="px-4 py-3 text-right">
                        Expected Fees
                    </th>

                    <th class="px-4 py-3 text-right">
                        Amount Paid
                    </th>

                    <th class="px-4 py-3 text-right">
                        Outstanding
                    </th>

                    <th class="px-4 py-3 text-center">
                        Collection %
                    </th>

                </tr>

            </thead>

            <tbody class="divide-y divide-gray-200 bg-white">

            <?php if(empty($class_summary)): ?>

                <tr>

                    <td colspan="6"
                        class="text-center py-10 text-gray-500">

                        No class summary available.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach($class_summary as $class=>$summary): ?>

                    <?php

                    $percentage = 0;

                    if($summary['expected'] > 0){

                        $percentage =
                            ($summary['paid'] / $summary['expected']) * 100;

                    }

                    ?>

                    <tr class="hover:bg-gray-50">

                        <td class="px-4 py-3 font-medium">

                            <?= htmlspecialchars($class) ?>

                        </td>

                        <td class="px-4 py-3 text-center">

                            <?= $summary['students'] ?>

                        </td>

                        <td class="px-4 py-3 text-right">

                            <?= number_format($summary['expected'],2) ?>

                        </td>

                        <td class="px-4 py-3 text-right text-green-700 font-semibold">

                            <?= number_format($summary['paid'],2) ?>

                        </td>

                        <td class="px-4 py-3 text-right text-red-700 font-semibold">

                            <?= number_format($summary['balance'],2) ?>

                        </td>

                        <td class="px-4 py-3 text-center">

                            <?php

                            if($percentage >= 100){

                                $badge =
                                "bg-green-100 text-green-700";

                            }elseif($percentage >= 75){

                                $badge =
                                "bg-blue-100 text-blue-700";

                            }elseif($percentage >= 50){

                                $badge =
                                "bg-yellow-100 text-yellow-700";

                            }else{

                                $badge =
                                "bg-red-100 text-red-700";

                            }

                            ?>

                            <span class="<?= $badge ?> px-3 py-1 rounded-full text-sm font-semibold">

                                <?= number_format($percentage,1) ?>%

                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

            <tfoot class="bg-gray-100 font-bold">

                <?php

                $overallPercentage = 0;

                if($grand_totals['expected'] > 0){

                    $overallPercentage =
                    ($grand_totals['paid'] / $grand_totals['expected']) * 100;

                }

                ?>

                <tr>

                    <td class="px-4 py-3">
                        GRAND TOTAL
                    </td>

                    <td class="px-4 py-3 text-center">

                        <?= count($students) ?>

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?= number_format($grand_totals['expected'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?= number_format($grand_totals['paid'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-right">

                        <?= number_format($grand_totals['balance'],2) ?>

                    </td>

                    <td class="px-4 py-3 text-center">

                        <?= number_format($overallPercentage,1) ?>%

                    </td>

                </tr>

            </tfoot>

        </table>

    </div>

</div><!-- =========================================================
     PAGE ACTIONS
========================================================= -->

<div class="flex flex-wrap gap-3 justify-end mb-6">

    <button
        onclick="window.print()"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg">

        🖨 Print Report

    </button>

    <a href="dashboard.php"
       class="bg-gray-700 hover:bg-gray-800 text-white px-5 py-2 rounded-lg">

        ← Dashboard

    </a>

</div>

<!-- =========================================================
     FOOTER
========================================================= -->

<div class="text-center text-gray-500 text-sm mt-10 mb-6">

    <hr class="mb-4">

    <p>

        SMS26 School Management System

    </p>

    <p>

        Student Fee Status Report

    </p>

</div>

</div>

<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

const searchInput = document.getElementById("searchInput");

if(searchInput){

    searchInput.addEventListener("keyup", function(){

        let filter = this.value.toLowerCase();

        let rows = document.querySelectorAll("#studentsTable tbody tr");

        rows.forEach(function(row){

            let text = row.innerText.toLowerCase();

            row.style.display =
                text.includes(filter)
                ? ""
                : "none";

        });

    });

}

window.onload = function(){

    if(searchInput){

        searchInput.focus();

    }

};

</script>

<style>

@media print{

    body{

        background:white !important;

    }

    button,
    a,
    input,
    select{

        display:none !important;

    }

    .shadow{

        box-shadow:none !important;

    }

    .rounded-xl{

        border-radius:0 !important;

    }

    .mb-6{

        margin-bottom:10px !important;

    }

}

</style>

</body>
</html>