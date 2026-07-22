<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// ======================================
// AUTHENTICATION
// ======================================
if (
    !isset($_SESSION['username']) ||
    !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])
) {
    exit("Unauthorized Access");
}

// ======================================
// FILTERS
// ======================================

$nameFilter   = trim($_GET['name'] ?? '');
$classFilter  = $_GET['class'] ?? '';
$yearFilter   = $_GET['year'] ?? '';
$termFilter   = $_GET['term'] ?? '';
$startDate    = $_GET['start_date'] ?? '';
$endDate      = $_GET['end_date'] ?? '';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;


// ======================================
// BUILD WHERE CLAUSE
// ======================================

$where = [];
$params = [];

if ($nameFilter != '') {

    $where[] =
        "(s.first_name LIKE :name
        OR s.last_name LIKE :name
        OR CONCAT(s.first_name,' ',s.last_name) LIKE :name)";

    $params[':name'] = "%{$nameFilter}%";
}

if ($classFilter != '') {

    $where[] = "c.id = :class";

    $params[':class'] = $classFilter;
}

if ($yearFilter != '') {

    $where[] = "i.academic_year_id = :year";

    $params[':year'] = $yearFilter;
}

if ($termFilter != '') {

    $where[] = "i.term_id = :term";

    $params[':term'] = $termFilter;
}

if ($startDate != '') {

    $where[] = "DATE(p.payment_date) >= :start";

    $params[':start'] = $startDate;
}

if ($endDate != '') {

    $where[] = "DATE(p.payment_date) <= :end";

    $params[':end'] = $endDate;
}

$whereSql = '';

if (!empty($where)) {

    $whereSql = 'WHERE ' . implode(' AND ', $where);
}


// ======================================
// DASHBOARD TOTALS
// ======================================

// Total Students
$totalStudents = (int)$pdo
    ->query("SELECT COUNT(*) FROM students")
    ->fetchColumn();


// Expected Fees
$totalExpected = (float)$pdo
    ->query("SELECT COALESCE(SUM(amount),0) FROM invoices")
    ->fetchColumn();


// Total Paid
$totalPaid = (float)$pdo
    ->query("SELECT COALESCE(SUM(amount),0) FROM payments")
    ->fetchColumn();


// Outstanding Balance
$totalBalance = $totalExpected - $totalPaid;


// ======================================
// COUNT RECORDS
// ======================================

$countSql = "

SELECT COUNT(DISTINCT s.id)

FROM students s

LEFT JOIN classes c
ON c.id=s.class_id

LEFT JOIN invoices i
ON i.student_id=s.id

LEFT JOIN payments p
ON p.invoice_id=i.id

$whereSql

";

$countStmt = $pdo->prepare($countSql);

foreach ($params as $key => $value) {

    $countStmt->bindValue($key, $value);
}

$countStmt->execute();

$totalRows = (int)$countStmt->fetchColumn();

$totalPages = max(1, ceil($totalRows / $limit));


// ======================================
// MAIN REPORT QUERY
// ======================================

$sql = "

SELECT

s.id AS student_id,

CONCAT(s.first_name,' ',s.last_name) AS student_name,

c.class_name,

COALESCE(SUM(DISTINCT i.amount),0) AS expected_fee,

COALESCE(SUM(p.amount),0) AS amount_paid,

COALESCE(SUM(DISTINCT i.amount),0)
-
COALESCE(SUM(p.amount),0)
AS balance,

MAX(p.payment_date) AS last_payment,

GROUP_CONCAT(
DISTINCT fc.category_name
SEPARATOR ', '
) AS fee_categories

FROM students s

LEFT JOIN classes c
ON c.id=s.class_id

LEFT JOIN invoices i
ON i.student_id=s.id

LEFT JOIN fee_categories fc
ON fc.id=i.fee_category_id

LEFT JOIN payments p
ON p.invoice_id=i.id

$whereSql

GROUP BY
s.id,
student_name,
c.class_name

ORDER BY
student_name ASC

LIMIT :limit OFFSET :offset

";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {

    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$stmt->execute();

$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ======================================
// EXPORT DIRECTORY
// ======================================

$exportDir = __DIR__ . "/exports";

if (!is_dir($exportDir)) {

    mkdir($exportDir, 0777, true);

}


// ======================================
// EXCEL EXPORT
// ======================================

if (
    isset($_GET['export']) &&
    $_GET['export'] == 'excel'
) {

    $filename =
        $exportDir .
        "/financial_report_" .
        date("Y-m-d_H-i-s") .
        ".xls";


    $html = "

    <table border='1'>

    <tr style='background:#4338CA;color:#ffffff;'>

        <th>Student</th>

        <th>Class</th>

        <th>Fee Categories</th>

        <th>Expected Fee</th>

        <th>Amount Paid</th>

        <th>Balance</th>

        <th>Last Payment</th>

    </tr>

    ";


    foreach ($records as $row) {

        $html .= "

        <tr>

            <td>{$row['student_name']}</td>

            <td>{$row['class_name']}</td>

            <td>{$row['fee_categories']}</td>

            <td>{$row['expected_fee']}</td>

            <td>{$row['amount_paid']}</td>

            <td>{$row['balance']}</td>

            <td>{$row['last_payment']}</td>

        </tr>

        ";

    }


    $html .= "

    <tr style='font-weight:bold;background:#E5E7EB;'>

        <td colspan='3'>TOTALS</td>

        <td>{$totalExpected}</td>

        <td>{$totalPaid}</td>

        <td>{$totalBalance}</td>

        <td></td>

    </tr>

    </table>

    ";


    file_put_contents($filename, $html);


    header("Content-Type: application/vnd.ms-excel");

    header(
        "Content-Disposition: attachment; filename=\"" .
        basename($filename) .
        "\""
    );

    readfile($filename);

    exit;

}



// ======================================
// PDF EXPORT
// ======================================

if (
    isset($_GET['export']) &&
    $_GET['export'] == 'pdf'
) {

    require_once __DIR__ . '/fpdf/fpdf.php';


    $filename =
        $exportDir .
        "/financial_report_" .
        date("Y-m-d_H-i-s") .
        ".pdf";


    $pdf = new FPDF('L', 'mm', 'A4');

    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 15);

    $pdf->Cell(
        0,
        10,
        'FINANCIAL REPORT',
        0,
        1,
        'C'
    );

    $pdf->Ln(3);


    $pdf->SetFont('Arial', 'B', 9);

    $pdf->SetFillColor(67,56,202);

    $pdf->SetTextColor(255);

    $pdf->Cell(55,8,'Student',1,0,'C',true);

    $pdf->Cell(35,8,'Class',1,0,'C',true);

    $pdf->Cell(55,8,'Category',1,0,'C',true);

    $pdf->Cell(30,8,'Expected',1,0,'C',true);

    $pdf->Cell(30,8,'Paid',1,0,'C',true);

    $pdf->Cell(30,8,'Balance',1,0,'C',true);

    $pdf->Cell(40,8,'Last Payment',1,1,'C',true);


    $pdf->SetFont('Arial','',8);

    $pdf->SetTextColor(0);


    foreach($records as $row){

        $pdf->Cell(55,7,$row['student_name'],1);

        $pdf->Cell(35,7,$row['class_name'],1);

        $pdf->Cell(55,7,$row['fee_categories'],1);

        $pdf->Cell(30,7,number_format($row['expected_fee'],2),1,0,'R');

        $pdf->Cell(30,7,number_format($row['amount_paid'],2),1,0,'R');

        $pdf->Cell(30,7,number_format($row['balance'],2),1,0,'R');

        $pdf->Cell(40,7,$row['last_payment'],1);

        $pdf->Ln();

    }


    $pdf->SetFont('Arial','B',9);

    $pdf->Cell(145,8,'TOTAL',1);

    $pdf->Cell(30,8,number_format($totalExpected,2),1,0,'R');

    $pdf->Cell(30,8,number_format($totalPaid,2),1,0,'R');

    $pdf->Cell(30,8,number_format($totalBalance,2),1,0,'R');

    $pdf->Cell(40,8,'',1);


    $pdf->Output('D', basename($filename));

    exit;

}

?>
<!DOCTYPE html>
<html lang="en"></html>
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Financial Report</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100">

<div class="max-w-7xl mx-auto p-6">

    <!-- Header -->

    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">

        <div>

            <h1 class="text-3xl font-bold text-indigo-700">

                Financial Report

            </h1>

            <p class="text-gray-600">

                Income, Expected Fees and Outstanding Balances

            </p>

        </div>

        <div class="mt-4 md:mt-0 flex gap-2">

            <a href="dashboard.php"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                Dashboard

            </a>

            <a href="?<?= http_build_query(array_merge($_GET,['export'=>'excel'])) ?>"
               class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">

                Export Excel

            </a>

            <a href="?<?= http_build_query(array_merge($_GET,['export'=>'pdf'])) ?>"
               class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">

                Export PDF

            </a>

        </div>

    </div>


    <!-- FILTERS -->

    <div class="bg-white rounded-lg shadow p-5 mb-6">

        <form method="GET">

            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">

                <div>

                    <label class="block text-sm font-medium mb-1">

                        Student

                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= htmlspecialchars($nameFilter); ?>"
                        placeholder="Search student..."
                        class="w-full border rounded px-3 py-2">

                </div>

                <div>

                    <label class="block text-sm font-medium mb-1">

                        Class

                    </label>

                    <select
                        name="class"
                        class="w-full border rounded px-3 py-2">

                        <option value="">All Classes</option>

                        <?php

                        $classes =
                        $pdo->query(
                        "SELECT id,class_name
                         FROM classes
                         ORDER BY class_name"
                        )->fetchAll(PDO::FETCH_ASSOC);

                        foreach($classes as $class):

                        ?>

                        <option
                        value="<?= $class['id']; ?>"
                        <?= $classFilter==$class['id']?'selected':''; ?>>

                            <?= htmlspecialchars($class['class_name']); ?>

                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div>

                    <label class="block text-sm font-medium mb-1">

                        Academic Year

                    </label>

                    <select
                        name="year"
                        class="w-full border rounded px-3 py-2">

                        <option value="">

                            All Years

                        </option>

                        <?php

                        $years =
                        $pdo->query(
                        "SELECT id,name
                         FROM academic_years
                         ORDER BY id DESC"
                        )->fetchAll(PDO::FETCH_ASSOC);

                        foreach($years as $year):

                        ?>

                        <option
                        value="<?= $year['id']; ?>"
                        <?= $yearFilter==$year['id']?'selected':''; ?>>

                            <?= htmlspecialchars($year['name']); ?>

                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div>

                    <label class="block text-sm font-medium mb-1">

                        Term

                    </label>

                    <select
                        name="term"
                        class="w-full border rounded px-3 py-2">

                        <option value="">

                            All Terms

                        </option>

                        <?php

                        $terms =
                        $pdo->query(
                        "SELECT id,term_name
                         FROM terms
                         ORDER BY id"
                        )->fetchAll(PDO::FETCH_ASSOC);

                        foreach($terms as $term):

                        ?>

                        <option
                        value="<?= $term['id']; ?>"
                        <?= $termFilter==$term['id']?'selected':''; ?>>

                            <?= htmlspecialchars($term['term_name']); ?>

                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div>

                    <label class="block text-sm font-medium mb-1">

                        Start Date

                    </label>

                    <input
                        type="date"
                        name="start_date"
                        value="<?= htmlspecialchars($startDate); ?>"
                        class="w-full border rounded px-3 py-2">

                </div>

                <div>

                    <label class="block text-sm font-medium mb-1">

                        End Date

                    </label>

                    <input
                        type="date"
                        name="end_date"
                        value="<?= htmlspecialchars($endDate); ?>"
                        class="w-full border rounded px-3 py-2">

                </div>

            </div>

            <div class="mt-5">

                <button
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded">

                    Apply Filters

                </button>

            </div>

        </form>

    </div>


    <!-- DASHBOARD CARDS -->

    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">

        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-blue-600">

            <p class="text-gray-500 text-sm">

                Total Students

            </p>

            <h2 class="text-3xl font-bold text-blue-700 mt-2">

                <?= number_format($totalStudents); ?>

            </h2>

        </div>

        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-green-600">

            <p class="text-gray-500 text-sm">

                Expected Fees

            </p>

            <h2 class="text-2xl font-bold text-green-700 mt-2">

                <?= number_format($totalExpected,2); ?>

            </h2>

        </div>

        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-indigo-600">

            <p class="text-gray-500 text-sm">

                Total Collected

            </p>

            <h2 class="text-2xl font-bold text-indigo-700 mt-2">

                <?= number_format($totalPaid,2); ?>

            </h2>

        </div>

        <div class="bg-white shadow rounded-lg p-5 border-l-4 border-red-600">

            <p class="text-gray-500 text-sm">

                Outstanding Balance

            </p>

            <h2 class="text-2xl font-bold text-red-700 mt-2">

                <?= number_format($totalBalance,2); ?>

            </h2>

        </div>

    </div>


    <!-- REPORT TABLE -->
     <div class="bg-white rounded-lg shadow overflow-x-auto">

    <table class="min-w-full divide-y divide-gray-200">

        <thead class="bg-indigo-600 text-white">

            <tr>

                <th class="px-4 py-3 text-left">#</th>

                <th class="px-4 py-3 text-left">Student</th>

                <th class="px-4 py-3 text-left">Class</th>

                <th class="px-4 py-3 text-left">Fee Categories</th>

                <th class="px-4 py-3 text-right">Expected (FCFA)</th>

                <th class="px-4 py-3 text-right">Paid (FCFA)</th>

                <th class="px-4 py-3 text-right">Balance (FCFA)</th>

                <th class="px-4 py-3 text-center">Last Payment</th>

                <th class="px-4 py-3 text-center">Status</th>

            </tr>

        </thead>

        <tbody class="divide-y divide-gray-100 bg-white">

        <?php if(empty($records)): ?>

            <tr>

                <td colspan="9"
                    class="text-center py-10 text-gray-500">

                    No financial records found.

                </td>

            </tr>

        <?php else:

            $sn = $offset + 1;

            foreach($records as $row):

                $expected = (float)$row['expected_fee'];

                $paid = (float)$row['amount_paid'];

                $balance = (float)$row['balance'];

        ?>

            <tr class="hover:bg-gray-50">

                <td class="px-4 py-3">

                    <?= $sn++; ?>

                </td>

                <td class="px-4 py-3 font-medium">

                    <?= htmlspecialchars($row['student_name']); ?>

                </td>

                <td class="px-4 py-3">

                    <?= htmlspecialchars($row['class_name']); ?>

                </td>

                <td class="px-4 py-3">

                    <?= htmlspecialchars($row['fee_categories'] ?: '-'); ?>

                </td>

                <td class="px-4 py-3 text-right">

                    <?= number_format($expected,2); ?>

                </td>

                <td class="px-4 py-3 text-right text-green-700 font-semibold">

                    <?= number_format($paid,2); ?>

                </td>

                <td class="px-4 py-3 text-right">

                    <?php

                    if($balance > 0){

                        echo "<span class='text-red-600 font-bold'>"
                            . number_format($balance,2)
                            . "</span>";

                    }elseif($balance < 0){

                        echo "<span class='text-orange-600 font-bold'>"
                            . number_format(abs($balance),2)
                            . " CR</span>";

                    }else{

                        echo "<span class='text-green-700 font-bold'>0.00</span>";

                    }

                    ?>

                </td>

                <td class="px-4 py-3 text-center">

                    <?= $row['last_payment'] ? htmlspecialchars($row['last_payment']) : '-'; ?>

                </td>

                <td class="px-4 py-3 text-center">

                    <?php if($balance <= 0): ?>

                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">

                            PAID

                        </span>

                    <?php elseif($paid > 0): ?>

                        <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-semibold">

                            PARTIAL

                        </span>

                    <?php else: ?>

                        <span class="px-3 py-1 rounded-full bg-red-100 text-red-700 text-xs font-semibold">

                            UNPAID

                        </span>

                    <?php endif; ?>

                </td>

            </tr>

        <?php

            endforeach;

        endif;

        ?>

        </tbody>

        <tfoot class="bg-gray-100 font-semibold">

            <tr>

                <td colspan="4"
                    class="px-4 py-3 text-right">

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

                <td colspan="2"></td>

            </tr>

        </tfoot>

    </table>

</div>

<!-- PAGINATION -->
 <div class="flex flex-col md:flex-row justify-between items-center mt-6">

    <div class="text-sm text-gray-600 mb-3 md:mb-0">

        Showing

        <strong>

            <?= $totalRows ? ($offset + 1) : 0; ?>

        </strong>

        -

        <strong>

            <?= min($offset + $limit, $totalRows); ?>

        </strong>

        of

        <strong>

            <?= number_format($totalRows); ?>

        </strong>

        record(s)

    </div>

    <div class="flex gap-2 flex-wrap">

        <?php if($page > 1): ?>

            <a
                href="?page=<?= $page-1; ?>
                &name=<?= urlencode($nameFilter); ?>
                &class=<?= urlencode($classFilter); ?>
                &year=<?= urlencode($yearFilter); ?>
                &term=<?= urlencode($termFilter); ?>
                &start_date=<?= urlencode($startDate); ?>
                &end_date=<?= urlencode($endDate); ?>"
                class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">

                Previous

            </a>

        <?php endif; ?>


        <?php for($i=1; $i<=$totalPages; $i++): ?>

            <a
                href="?page=<?= $i; ?>
                &name=<?= urlencode($nameFilter); ?>
                &class=<?= urlencode($classFilter); ?>
                &year=<?= urlencode($yearFilter); ?>
                &term=<?= urlencode($termFilter); ?>
                &start_date=<?= urlencode($startDate); ?>
                &end_date=<?= urlencode($endDate); ?>"

                class="px-4 py-2 rounded

                <?=

                $page==$i

                ?

                'bg-indigo-600 text-white'

                :

                'bg-white border hover:bg-gray-100'

                ?>

                ">

                <?= $i; ?>

            </a>

        <?php endfor; ?>


        <?php if($page < $totalPages): ?>

            <a
                href="?page=<?= $page+1; ?>
                &name=<?= urlencode($nameFilter); ?>
                &class=<?= urlencode($classFilter); ?>
                &year=<?= urlencode($yearFilter); ?>
                &term=<?= urlencode($termFilter); ?>
                &start_date=<?= urlencode($startDate); ?>
                &end_date=<?= urlencode($endDate); ?>"

                class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">

                Next

            </a>

        <?php endif; ?>

    </div>

</div>


<!-- FOOTER -->

<div class="mt-10 border-t pt-6 flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">

    <div>

        <strong>Synergia School Suite</strong>

        <br>

        Financial Reporting Module

    </div>

    <div class="mt-3 md:mt-0">

        Generated on

        <strong>

            <?= date("d M Y h:i A"); ?>

        </strong>

    </div>

</div>

</div>

</body>

</html>