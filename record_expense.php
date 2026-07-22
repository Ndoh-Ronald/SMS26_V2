<?php

session_start();

require_once __DIR__ . '/schooldb.php';

error_reporting(E_ALL);
ini_set('display_errors',1);


// =====================================
// AUTHENTICATION
// =====================================

if(
    !isset($_SESSION['username']) ||
    !in_array(
        strtolower($_SESSION['role']),
        ['admin','bursar']
    )
){
    header("Location: login.php");
    exit;
}

$role = strtolower($_SESSION['role']);

$success = "";
$error = "";


// =====================================
// FILTERS
// =====================================

$search =
trim($_GET['search'] ?? '');

$startDate =
$_GET['start_date'] ?? '';

$endDate =
$_GET['end_date'] ?? '';

$page =
max(1,(int)($_GET['page'] ?? 1));

$limit = 20;

$offset =
($page-1) * $limit;



// =====================================
// RECORD EXPENSE
// =====================================

if(
    $_SERVER['REQUEST_METHOD']=="POST"
    &&
    $role=="bursar"
){

    $description =
    trim($_POST['description']);

    $amount =
    (float)$_POST['amount'];

    $date =
    $_POST['date'];

    if(
        $description==""
        ||
        $amount<=0
    ){

        $error =
        "Please enter a valid description and amount.";

    }else{

        $sql="

        INSERT INTO expenses
        (
            date,
            description,
            amount,
            created_by
        )

        VALUES
        (
            :date,
            :description,
            :amount,
            :created_by
        )

        ";

        $stmt =
        $pdo->prepare($sql);

        $stmt->bindValue(
            ':date',
            $date
        );

        $stmt->bindValue(
            ':description',
            $description
        );

        $stmt->bindValue(
            ':amount',
            $amount
        );

        $stmt->bindValue(
            ':created_by',
            $_SESSION['user_id'] ?? null,
            PDO::PARAM_INT
        );

        if(
            $stmt->execute()
        ){

            $success =
            "Expense recorded successfully.";

        }else{

            $error =
            "Unable to save expense.";

        }

    }

}



// =====================================
// DELETE EXPENSE
// =====================================

if(

    isset($_GET['delete'])

    &&

    $role=="admin"

){

    $id=(int)$_GET['delete'];

    $stmt=
    $pdo->prepare(

    "DELETE
     FROM expenses
     WHERE id=:id"

    );

    $stmt->bindValue(
        ':id',
        $id,
        PDO::PARAM_INT
    );

    if(
        $stmt->execute()
    ){

        $success =
        "Expense deleted successfully.";

    }else{

        $error =
        "Unable to delete expense.";

    }

}



// =====================================
// BUILD WHERE CLAUSE
// =====================================

$where=[];

$params=[];


if($search!=""){

    $where[]="

    description
    LIKE
    :search

    ";

    $params[':search']="%{$search}%";

}


if($startDate!=""){

    $where[]="

    date>=:start

    ";

    $params[':start']=$startDate;

}


if($endDate!=""){

    $where[]="

    date<=:end

    ";

    $params[':end']=$endDate;

}


$whereSql="";

if(!empty($where)){

    $whereSql=
    "WHERE ".implode(" AND ",$where);

}



// =====================================
// SUMMARY CARDS
// =====================================


// Total Expenses

$totalExpenses=
(float)$pdo
->query(

"SELECT
COALESCE(
SUM(amount),
0
)
FROM expenses"

)
->fetchColumn();


// Number of Expenses

$totalRecords=
(int)$pdo
->query(

"SELECT
COUNT(*)
FROM expenses"

)
->fetchColumn();


// Today's Expenses

$stmtToday=
$pdo->prepare(

"

SELECT

COALESCE(
SUM(amount),
0
)

FROM expenses

WHERE date=CURDATE()

"

);

$stmtToday->execute();

$todayExpenses=
(float)$stmtToday
->fetchColumn();



// Current Month

$stmtMonth=
$pdo->prepare(

"

SELECT

COALESCE(
SUM(amount),
0
)

FROM expenses

WHERE

MONTH(date)=MONTH(CURDATE())

AND

YEAR(date)=YEAR(CURDATE())

"

);

$stmtMonth->execute();

$thisMonth=
(float)$stmtMonth
->fetchColumn();



// =====================================
// COUNT RECORDS
// =====================================

$countSql="

SELECT

COUNT(*)

FROM expenses

$whereSql

";

$countStmt=
$pdo->prepare(
$countSql
);

foreach(
$params
as
$key=>$value
){

$countStmt
->bindValue(
$key,
$value
);

}

$countStmt
->execute();

$totalRows=
$countStmt
->fetchColumn();

$totalPages=
max(
1,
ceil(
$totalRows/$limit
)
);



// =====================================
// FETCH EXPENSES
// =====================================

$sql="

SELECT

*

FROM expenses

$whereSql

ORDER BY

date DESC,

id DESC

LIMIT :limit

OFFSET :offset

";

$stmt=
$pdo->prepare($sql);

foreach(
$params
as
$key=>$value
){

$stmt
->bindValue(
$key,
$value
);

}

$stmt
->bindValue(
':limit',
$limit,
PDO::PARAM_INT
);

$stmt
->bindValue(
':offset',
$offset,
PDO::PARAM_INT
);

$stmt->execute();

$expenses=
$stmt->fetchAll(
PDO::FETCH_ASSOC
);

?>
<?php

// =====================================
// EXPORT DIRECTORY
// =====================================

$exportDir = __DIR__ . "/exports";

if (!is_dir($exportDir)) {
    mkdir($exportDir, 0777, true);
}



// =====================================
// EXPORT TO EXCEL
// =====================================

if (isset($_GET['export']) && $_GET['export'] == "excel") {

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=expenses_" . date('Ymd_His') . ".xls");

    echo "<table border='1'>";

    echo "<tr>
            <th>Date</th>
            <th>Description</th>
            <th>Amount (XAF)</th>
          </tr>";

    $grandTotal = 0;

    foreach ($expenses as $row) {

        $grandTotal += $row['amount'];

        echo "<tr>";

        echo "<td>" . htmlspecialchars($row['date']) . "</td>";

        echo "<td>" . htmlspecialchars($row['description']) . "</td>";

        echo "<td>" . number_format($row['amount'], 2) . "</td>";

        echo "</tr>";

    }

    echo "<tr>";

    echo "<td colspan='2'><strong>Total</strong></td>";

    echo "<td><strong>" . number_format($grandTotal,2) . "</strong></td>";

    echo "</tr>";

    echo "</table>";

    exit;
}




// =====================================
// EXPORT TO PDF
// =====================================

if (
    isset($_GET['export'])
    &&
    $_GET['export'] == "pdf"
) {

    require_once "fpdf/fpdf.php";

    $pdf = new FPDF();

    $pdf->AddPage();

    $pdf->SetFont('Arial','B',16);

    $pdf->Cell(
        190,
        10,
        "Expense Report",
        0,
        1,
        'C'
    );

    $pdf->Ln(5);

    $pdf->SetFont('Arial','B',10);

    $pdf->Cell(35,8,"Date",1);

    $pdf->Cell(105,8,"Description",1);

    $pdf->Cell(50,8,"Amount (XAF)",1,1,'R');

    $pdf->SetFont('Arial','',10);

    $grandTotal = 0;

    foreach($expenses as $row){

        $grandTotal += $row['amount'];

        $pdf->Cell(
            35,
            8,
            $row['date'],
            1
        );

        $pdf->Cell(
            105,
            8,
            substr($row['description'],0,55),
            1
        );

        $pdf->Cell(
            50,
            8,
            number_format($row['amount'],2),
            1,
            1,
            'R'
        );

    }

    $pdf->SetFont('Arial','B',10);

    $pdf->Cell(
        140,
        8,
        "TOTAL",
        1
    );

    $pdf->Cell(
        50,
        8,
        number_format($grandTotal,2),
        1,
        1,
        'R'
    );

    $pdf->Output(
        "D",
        "Expense_Report_" . date('Ymd_His') . ".pdf"
    );

    exit;

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Record Expenses | SMS26</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
rel="stylesheet">

<style>

body{

background:#f3f4f6;

}

.card{

background:white;

border-radius:14px;

box-shadow:0 8px 20px rgba(0,0,0,.08);

}

.table-hover tbody tr:hover{

background:#f8fafc;

}

</style>

</head>

<body class="min-h-screen">

<div class="max-w-7xl mx-auto px-6 py-8">
<!-- ============================= -->
<!-- PAGE HEADER -->
<!-- ============================= -->

<div class="flex flex-col lg:flex-row justify-between items-center mb-8">

    <div>
        <h1 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-wallet text-indigo-600"></i>
            Expense Management
        </h1>

        <p class="text-gray-500 mt-1">
            Record, monitor and manage school operational expenses.
        </p>
    </div>

    <div class="flex gap-3 mt-5 lg:mt-0">

        <a href="dashboard.php"
           class="bg-gray-700 hover:bg-gray-800 text-white px-5 py-3 rounded-lg shadow">

            <i class="fas fa-arrow-left mr-2"></i>
            Dashboard

        </a>

        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'excel'])) ?>"
           class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg shadow">

            <i class="fas fa-file-excel mr-2"></i>
            Excel

        </a>

        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'pdf'])) ?>"
           class="bg-red-600 hover:bg-red-700 text-white px-5 py-3 rounded-lg shadow">

            <i class="fas fa-file-pdf mr-2"></i>
            PDF

        </a>

    </div>

</div>



<!-- ============================= -->
<!-- SUCCESS / ERROR -->
<!-- ============================= -->

<?php if($success): ?>

<div class="bg-green-100 border border-green-300 text-green-700 rounded-lg p-4 mb-6">

    <i class="fas fa-check-circle mr-2"></i>

    <?= htmlspecialchars($success) ?>

</div>

<?php endif; ?>


<?php if($error): ?>

<div class="bg-red-100 border border-red-300 text-red-700 rounded-lg p-4 mb-6">

    <i class="fas fa-times-circle mr-2"></i>

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>




<!-- ============================= -->
<!-- SUMMARY CARDS -->
<!-- ============================= -->

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">

    <div class="card p-6">

        <div class="text-gray-500 text-sm">
            Total Expenses
        </div>

        <div class="text-3xl font-bold text-red-600 mt-2">

            XAF <?= number_format($totalExpenses,2) ?>

        </div>

    </div>


    <div class="card p-6">

        <div class="text-gray-500 text-sm">

            Today's Expenses

        </div>

        <div class="text-3xl font-bold text-indigo-600 mt-2">

            XAF <?= number_format($todayExpenses,2) ?>

        </div>

    </div>


    <div class="card p-6">

        <div class="text-gray-500 text-sm">

            This Month

        </div>

        <div class="text-3xl font-bold text-green-600 mt-2">

            XAF <?= number_format($thisMonth,2) ?>

        </div>

    </div>


    <div class="card p-6">

        <div class="text-gray-500 text-sm">

            Total Records

        </div>

        <div class="text-3xl font-bold text-blue-600 mt-2">

            <?= number_format($totalRecords) ?>

        </div>

    </div>

</div>



<!-- ============================= -->
<!-- RECORD + FILTER -->
<!-- ============================= -->

<div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-8">

<?php if($role=="bursar"): ?>

<div class="card p-6">

<h2 class="text-xl font-bold mb-5">

<i class="fas fa-plus-circle text-indigo-600 mr-2"></i>

Record Expense

</h2>

<form method="POST" class="space-y-4">

<div>

<label class="block text-sm font-medium mb-1">

Description

</label>

<input
type="text"
name="description"
required
class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">

</div>


<div>

<label class="block text-sm font-medium mb-1">

Amount (XAF)

</label>

<input
type="number"
step="0.01"
min="0"
name="amount"
required
class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">

</div>


<div>

<label class="block text-sm font-medium mb-1">

Expense Date

</label>

<input
type="date"
name="date"
value="<?= date('Y-m-d') ?>"
required
class="w-full border rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">

</div>


<button
class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-semibold">

<i class="fas fa-save mr-2"></i>

Save Expense

</button>

</form>

</div>

<?php endif; ?>



<div class="card p-6 xl:col-span-<?= $role=="bursar" ? "2":"3"; ?>">

<h2 class="text-xl font-bold mb-5">

<i class="fas fa-filter text-green-600 mr-2"></i>

Search & Filter

</h2>

<form
method="GET"
class="grid grid-cols-1 md:grid-cols-4 gap-4">

<input
type="text"
name="search"
value="<?= htmlspecialchars($search) ?>"
placeholder="Search description..."
class="border rounded-lg px-4 py-3">

<input
type="date"
name="start_date"
value="<?= htmlspecialchars($startDate) ?>"
class="border rounded-lg px-4 py-3">

<input
type="date"
name="end_date"
value="<?= htmlspecialchars($endDate) ?>"
class="border rounded-lg px-4 py-3">

<div class="flex gap-2">

<button
class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg">

<i class="fas fa-search mr-2"></i>

Search

</button>

<a
href="record_expense.php"
class="flex-1 bg-gray-300 hover:bg-gray-400 rounded-lg flex items-center justify-center">

Reset

</a>

</div>

</form>

</div>

</div>
<!-- ========================================= -->
<!-- EXPENSE RECORDS -->
<!-- ========================================= -->

<div class="card p-6">

    <div class="flex flex-col md:flex-row justify-between items-center mb-6">

        <h2 class="text-2xl font-bold text-gray-800">

            <i class="fas fa-list mr-2 text-indigo-600"></i>

            Expense Records

        </h2>

        <span class="text-gray-500 mt-3 md:mt-0">

            <?= number_format($totalRows) ?> Record(s) Found

        </span>

    </div>


<?php if(empty($expenses)): ?>

<div class="text-center py-16">

    <i class="fas fa-wallet text-6xl text-gray-300 mb-4"></i>

    <h3 class="text-xl font-semibold text-gray-600">

        No Expenses Found

    </h3>

    <p class="text-gray-400 mt-2">

        There are no expenses matching your filters.

    </p>

</div>

<?php else: ?>

<div class="overflow-x-auto">

<table class="min-w-full table-hover">

<thead>

<tr class="bg-indigo-600 text-white">

<th class="px-4 py-3 text-left">

#

</th>

<th class="px-4 py-3 text-left">

Date

</th>

<th class="px-4 py-3 text-left">

Description

</th>

<th class="px-4 py-3 text-right">

Amount (XAF)

</th>

<th class="px-4 py-3 text-center">

Recorded By

</th>

<?php if($role=="admin"): ?>

<th class="px-4 py-3 text-center">

Actions

</th>

<?php endif; ?>

</tr>

</thead>

<tbody>

<?php

$sn = $offset + 1;

$runningTotal = 0;

foreach($expenses as $expense):

$runningTotal += $expense['amount'];

?>

<tr class="border-b">

<td class="px-4 py-3">

<?= $sn++ ?>

</td>

<td class="px-4 py-3 whitespace-nowrap">

<?= date('d M Y',strtotime($expense['date'])) ?>

</td>

<td class="px-4 py-3">

<?= htmlspecialchars($expense['description']) ?>

</td>

<td class="px-4 py-3 text-right font-semibold text-red-600">

<?= number_format($expense['amount'],2) ?>

</td>

<td class="px-4 py-3 text-center">

<?= $expense['created_by'] ?: "-" ?>

</td>

<?php if($role=="admin"): ?>

<td class="px-4 py-3">

<div class="flex justify-center gap-2">

<a
href="edit_expense.php?id=<?= $expense['id'] ?>"
class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg">

<i class="fas fa-edit"></i>

</a>

<button

data-id="<?= $expense['id'] ?>"

data-description="<?= htmlspecialchars($expense['description'],ENT_QUOTES) ?>"

class="deleteBtn bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg">

<i class="fas fa-trash"></i>

</button>

</div>

</td>

<?php endif; ?>

</tr>

<?php endforeach; ?>

</tbody>

<tfoot>

<tr class="bg-gray-100 font-bold">

<td colspan="<?= $role=="admin" ? 3 : 2 ?>" class="px-4 py-4 text-right">

Grand Total

</td>

<td class="px-4 py-4 text-right text-red-700">

XAF <?= number_format($runningTotal,2) ?>

</td>

<td <?= $role=="admin" ? 'colspan="2"' : '' ?>></td>

</tr>

</tfoot>

</table>

</div>

<?php endif; ?>

</div>
<!-- ========================================= -->
<!-- PAGINATION -->
<!-- ========================================= -->

<?php if($totalPages > 1): ?>

<div class="flex justify-center mt-8">

    <nav class="flex flex-wrap gap-2">

        <?php if($page > 1): ?>

            <a
            href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>"
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">

                <i class="fas fa-angle-left"></i>

            </a>

        <?php endif; ?>


        <?php for($i=1;$i<=$totalPages;$i++): ?>

            <a
            href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"
            class="px-4 py-2 rounded-lg <?= $i==$page
            ? 'bg-indigo-600 text-white'
            : 'bg-gray-200 hover:bg-gray-300' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>


        <?php if($page < $totalPages): ?>

            <a
            href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>"
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg">

                <i class="fas fa-angle-right"></i>

            </a>

        <?php endif; ?>

    </nav>

</div>

<?php endif; ?>



<!-- ========================================= -->
<!-- DELETE MODAL -->
<!-- ========================================= -->

<div
id="deleteModal"
class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">

        <div class="p-6">

            <div class="text-center">

                <div class="w-20 h-20 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">

                    <i class="fas fa-trash text-red-600 text-3xl"></i>

                </div>

                <h3 class="text-2xl font-bold text-gray-800">

                    Delete Expense

                </h3>

                <p
                id="deleteMessage"
                class="text-gray-500 mt-3">

                </p>

            </div>

            <div class="flex justify-center gap-4 mt-8">

                <button
                id="cancelDelete"
                class="px-6 py-3 bg-gray-300 hover:bg-gray-400 rounded-lg">

                    Cancel

                </button>

                <a
                id="confirmDelete"
                href="#"
                class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg">

                    Delete

                </a>

            </div>

        </div>

    </div>

</div>



<!-- ========================================= -->
<!-- FOOTER -->
<!-- ========================================= -->

<footer class="mt-12 text-center text-gray-500 text-sm">

    <hr class="mb-6">

    <p>

        SMS26 School Management System

    </p>

    <p class="mt-1">

        Expense Management Module

    </p>

</footer>

</div>



<!-- ========================================= -->
<!-- JAVASCRIPT -->
<!-- ========================================= -->

<script>

const modal =
document.getElementById("deleteModal");

const message =
document.getElementById("deleteMessage");

const confirmDelete =
document.getElementById("confirmDelete");

document.querySelectorAll(".deleteBtn").forEach(button=>{

button.addEventListener("click",function(){

const id =
this.dataset.id;

const description =
this.dataset.description;

message.innerHTML =
`Are you sure you want to delete <strong>${description}</strong>?`;

confirmDelete.href =
`record_expense.php?delete=${id}`;

modal.classList.remove("hidden");

modal.classList.add("flex");

});

});

document
.getElementById("cancelDelete")
.addEventListener("click",function(){

modal.classList.remove("flex");

modal.classList.add("hidden");

});


window.onclick=function(event){

if(event.target===modal){

modal.classList.remove("flex");

modal.classList.add("hidden");

}

};

</script>

</body>

</html>