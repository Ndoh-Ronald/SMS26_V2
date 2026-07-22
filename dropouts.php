<?php
session_start();

require_once 'schooldb.php';

// ===============================
// AUTHENTICATION
// ===============================
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

$error = '';
$success = '';


// ===============================
// PAGINATION
// ===============================

$limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page'])
    ? (int)$_GET['page']
    : 1;

$page = max($page, 1);

$offset = ($page - 1) * $limit;


// ===============================
// SEARCH
// ===============================

$search = trim($_GET['search'] ?? '');

$where = '';
$params = [];

if ($search != '') {

    $where = "
    WHERE
        s.admission_no LIKE ?
        OR CONCAT(s.first_name,' ',s.last_name) LIKE ?
        OR c.class_name LIKE ?
        OR d.status LIKE ?
        OR d.remarks LIKE ?
    ";

    $keyword = "%{$search}%";

    $params = [
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ];
}


// ===============================
// DELETE RECORD
// ===============================

if (
    isset($_GET['delete']) &&
    is_numeric($_GET['delete']) &&
    $role == 'admin'
) {

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT student_id
            FROM dropouts
            WHERE id=?
        ");

        $stmt->execute([$_GET['delete']]);

        $studentId = $stmt->fetchColumn();

        if ($studentId) {

            $pdo->prepare("
                UPDATE students
                SET active=1
                WHERE id=?
            ")->execute([$studentId]);

        }

        $pdo->prepare("
            DELETE FROM dropouts
            WHERE id=?
        ")->execute([$_GET['delete']]);

        $pdo->commit();

        $success = "Record deleted successfully.";

    } catch(Exception $e){

        $pdo->rollBack();

        $error = $e->getMessage();

    }

}



// ===============================
// REINSTATE STUDENT
// ===============================

if (
    isset($_GET['reinstate']) &&
    is_numeric($_GET['reinstate']) &&
    $role=='admin'
){

    try{

        $pdo->beginTransaction();

        $stmt=$pdo->prepare("
            SELECT student_id
            FROM dropouts
            WHERE id=?
        ");

        $stmt->execute([$_GET['reinstate']]);

        $studentId=$stmt->fetchColumn();

        if($studentId){

            $pdo->prepare("
                UPDATE students
                SET active=1
                WHERE id=?
            ")->execute([$studentId]);

            $success="Student reinstated successfully.";

        }else{

            $error="Student not found.";

        }

        $pdo->commit();

    }catch(Exception $e){

        $pdo->rollBack();

        $error=$e->getMessage();

    }

}



// ===============================
// SAVE / UPDATE
// ===============================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $id            = $_POST['id'] ?? 0;
    $student_id    = $_POST['student_id'];
    $dropout_date  = $_POST['dropout_date'];
    $status        = $_POST['status'];
    $fees_paid     = $_POST['fees_paid'];
    $remarks       = trim($_POST['remarks']);

    try {

        if ($id == 0) {

            $stmt = $pdo->prepare("
                INSERT INTO dropouts
                (
                    student_id,
                    dropout_date,
                    status,
                    fees_paid,
                    remarks,
                    recorded_by,
                    recorded_at
                )
                VALUES
                (?,?,?,?,?,?,NOW())
            ");

            $stmt->execute([

                $student_id,
                $dropout_date,
                $status,
                $fees_paid,
                $remarks,
                $userId

            ]);

            $pdo->prepare("
                UPDATE students
                SET active=0
                WHERE id=?
            ")->execute([$student_id]);

            $success="Record saved successfully.";

        } else {

            $stmt = $pdo->prepare("
                UPDATE dropouts
                SET

                    student_id=?,
                    dropout_date=?,
                    status=?,
                    fees_paid=?,
                    remarks=?

                WHERE id=?
            ");

            $stmt->execute([

                $student_id,
                $dropout_date,
                $status,
                $fees_paid,
                $remarks,
                $id

            ]);

            $success="Record updated successfully.";

        }

    } catch(Exception $e){

        $error=$e->getMessage();

    }

}



// ===============================
// EDIT RECORD
// ===============================

$edit = null;

if (
    isset($_GET['edit']) &&
    is_numeric($_GET['edit'])
){

    $stmt=$pdo->prepare("
        SELECT *
        FROM dropouts
        WHERE id=?
    ");

    $stmt->execute([$_GET['edit']]);

    $edit=$stmt->fetch(PDO::FETCH_ASSOC);

}



// ===============================
// DASHBOARD STATISTICS
// ===============================

// Total Records
$totalRecords = $pdo
->query("
SELECT COUNT(*)
FROM dropouts
")
->fetchColumn();


// Today's Records

$todayRecords = $pdo
->query("
SELECT COUNT(*)
FROM dropouts
WHERE DATE(dropout_date)=CURDATE()
")
->fetchColumn();


// This Month

$thisMonth = $pdo
->query("
SELECT COUNT(*)
FROM dropouts
WHERE
MONTH(dropout_date)=MONTH(CURDATE())
AND YEAR(dropout_date)=YEAR(CURDATE())
")
->fetchColumn();


// Total Fees

$totalFees = $pdo
->query("
SELECT IFNULL(SUM(fees_paid),0)
FROM dropouts
")
->fetchColumn();




// ===============================
// STUDENT DROPDOWN
// ===============================

$stmt=$pdo->query("

SELECT

s.id,

s.admission_no,

CONCAT(
s.first_name,
' ',
s.last_name
) AS student_name,

c.class_name

FROM students s

LEFT JOIN classes c
ON s.class_id=c.id

WHERE s.active=1

ORDER BY
s.first_name,
s.last_name

");

$students=$stmt->fetchAll(PDO::FETCH_ASSOC);




// ===============================
// TOTAL FILTERED RECORDS
// ===============================

$stmt=$pdo->prepare("

SELECT COUNT(*)

FROM dropouts d

LEFT JOIN students s
ON d.student_id=s.id

LEFT JOIN classes c
ON s.class_id=c.id

$where

");

$stmt->execute($params);

$totalFiltered=$stmt->fetchColumn();

$totalPages=ceil($totalFiltered/$limit);




// ===============================
// FETCH RECORDS
// ===============================

$sql="

SELECT

d.*,

s.admission_no,

CONCAT(
s.first_name,
' ',
s.last_name
) AS student_name,

c.class_name,

u.username

FROM dropouts d

LEFT JOIN students s
ON d.student_id=s.id

LEFT JOIN classes c
ON s.class_id=c.id

LEFT JOIN users u
ON d.recorded_by=u.id

$where

ORDER BY d.dropout_date DESC

LIMIT $limit OFFSET $offset

";

$stmt=$pdo->prepare($sql);

$stmt->execute($params);

$records=$stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>SMS26 | Dropout Management</title>

<script src="https://cdn.tailwindcss.com"></script>

<link
rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{

    background:#f3f4f6;

}

.table-hover:hover{

    background:#f9fafb;

}

</style>

</head>

<body>

<div class="max-w-7xl mx-auto p-6">


<!-- ===========================
        PAGE HEADER
=========================== -->

<div class="bg-white rounded-xl shadow mb-6">

<div class="flex flex-col lg:flex-row lg:justify-between lg:items-center p-6">

<div>

<h1 class="text-3xl font-bold text-indigo-700">

<i class="fas fa-user-slash mr-2"></i>

Dropout & Dismissal Management

</h1>

<p class="text-gray-600 mt-2">

Manage students who have dropped out or were dismissed.

</p>

</div>


<div class="mt-5 lg:mt-0 flex flex-wrap gap-3">

<a
href="dashboard.php"
class="bg-gray-600 hover:bg-gray-700 text-white px-5 py-3 rounded-lg">

<i class="fas fa-arrow-left mr-2"></i>

Dashboard

</a>

<a
href="?export=excel"
class="bg-green-600 hover:bg-green-700 text-white px-5 py-3 rounded-lg">

<i class="fas fa-file-excel mr-2"></i>

Excel

</a>

<a
href="?export=pdf"
class="bg-red-600 hover:bg-red-700 text-white px-5 py-3 rounded-lg">

<i class="fas fa-file-pdf mr-2"></i>

PDF

</a>

</div>

</div>

</div>


<?php
/* ==========================================================
   SUCCESS / ERROR ALERTS
========================================================== */
?>

<?php if(!empty($success)): ?>

<div
id="successAlert"
class="bg-green-100 border-l-4 border-green-600 text-green-800 rounded-lg p-4 mb-6 shadow">

<div class="flex items-center">

<i class="fas fa-check-circle text-2xl mr-3"></i>

<div>

<strong>Success</strong>

<p>

<?= htmlspecialchars($success) ?>

</p>

</div>

</div>

</div>

<?php endif; ?>


<?php if(!empty($error)): ?>

<div
id="errorAlert"
class="bg-red-100 border-l-4 border-red-600 text-red-800 rounded-lg p-4 mb-6 shadow">

<div class="flex items-center">

<i class="fas fa-triangle-exclamation text-2xl mr-3"></i>

<div>

<strong>Error</strong>

<p>

<?= htmlspecialchars($error) ?>

</p>

</div>

</div>

</div>

<?php endif; ?>


<?php
/* ==========================================================
   EXPORTS
========================================================== */
?>

<?php

if(isset($_GET['export'])){

    $stmt=$pdo->query("

    SELECT

    s.admission_no,

    CONCAT(
    s.first_name,
    ' ',
    s.last_name
    ) AS student_name,

    c.class_name,

    d.dropout_date,

    d.status,

    d.fees_paid,

    d.remarks,

    u.username,

    d.recorded_at

    FROM dropouts d

    LEFT JOIN students s
    ON d.student_id=s.id

    LEFT JOIN classes c
    ON s.class_id=c.id

    LEFT JOIN users u
    ON d.recorded_by=u.id

    ORDER BY d.dropout_date DESC

    ");

    $exportRows=$stmt->fetchAll(PDO::FETCH_ASSOC);

}

?>
<!-- =====================================================
     DASHBOARD CARDS
===================================================== -->

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">

    <!-- Total Records -->
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-gray-500 text-sm">Total Records</p>
                <h2 class="text-3xl font-bold text-indigo-700 mt-2">
                    <?= number_format($totalRecords) ?>
                </h2>
            </div>

            <div class="bg-indigo-100 p-4 rounded-full">
                <i class="fas fa-users-slash text-indigo-700 text-2xl"></i>
            </div>
        </div>
    </div>

    <!-- Today -->
    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex justify-between items-center">

            <div>
                <p class="text-gray-500 text-sm">Today's Records</p>

                <h2 class="text-3xl font-bold text-red-600 mt-2">
                    <?= number_format($todayRecords) ?>
                </h2>
            </div>

            <div class="bg-red-100 p-4 rounded-full">
                <i class="fas fa-calendar-day text-red-600 text-2xl"></i>
            </div>

        </div>
    </div>

    <!-- Month -->

    <div class="bg-white rounded-xl shadow p-6">

        <div class="flex justify-between items-center">

            <div>

                <p class="text-gray-500 text-sm">

                    This Month

                </p>

                <h2 class="text-3xl font-bold text-blue-600 mt-2">

                    <?= number_format($thisMonth) ?>

                </h2>

            </div>

            <div class="bg-blue-100 p-4 rounded-full">

                <i class="fas fa-calendar-alt text-blue-600 text-2xl"></i>

            </div>

        </div>

    </div>

    <!-- Fees -->

    <div class="bg-white rounded-xl shadow p-6">

        <div class="flex justify-between items-center">

            <div>

                <p class="text-gray-500 text-sm">

                    Fees Paid

                </p>

                <h2 class="text-2xl font-bold text-green-700 mt-2">

                    XAF <?= number_format($totalFees,2) ?>

                </h2>

            </div>

            <div class="bg-green-100 p-4 rounded-full">

                <i class="fas fa-money-bill-wave text-green-700 text-2xl"></i>

            </div>

        </div>

    </div>

</div>



<!-- =====================================================
     SEARCH PANEL
===================================================== -->

<div class="bg-white rounded-xl shadow p-6 mb-8">

<form method="GET">

<div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

<div class="lg:col-span-4">

<input

type="text"

name="search"

value="<?= htmlspecialchars($search) ?>"

placeholder="Search admission number, student name, class, status or remarks..."

class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none">

</div>

<div class="flex gap-2">

<button

type="submit"

class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-5 py-3">

<i class="fas fa-search mr-2"></i>

Search

</button>

<a

href="dropouts.php"

class="flex-1 bg-gray-600 hover:bg-gray-700 text-white rounded-lg px-5 py-3 text-center">

Reset

</a>

</div>

</div>

</form>

</div>



<!-- =====================================================
     ADD / EDIT FORM
===================================================== -->

<div class="bg-white rounded-xl shadow p-6 mb-8">

<h2 class="text-2xl font-bold text-indigo-700 mb-6">

<?php if($edit): ?>

<i class="fas fa-edit mr-2"></i>

Edit Record

<?php else: ?>

<i class="fas fa-plus-circle mr-2"></i>

Record New Dropout / Dismissal

<?php endif; ?>

</h2>



<form method="POST">

<input
type="hidden"
name="id"
value="<?= $edit['id'] ?? 0 ?>">


<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

<!-- Student -->

<div>

<label class="block font-semibold mb-2">

Student

</label>

<select

name="student_id"

required

class="w-full border border-gray-300 rounded-lg px-4 py-3">

<option value="">

-- Select Student --

</option>

<?php foreach($students as $student): ?>

<option

value="<?= $student['id'] ?>"

<?= (($edit['student_id'] ?? '')==$student['id']) ? 'selected':'' ?>

>

<?= htmlspecialchars(

$student['admission_no']

." - "

.$student['student_name']

." ("

.$student['class_name']

.")"

) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<!-- Date -->

<div>

<label class="block font-semibold mb-2">

Dropout Date

</label>

<input

type="date"

name="dropout_date"

required

value="<?= $edit['dropout_date'] ?? date('Y-m-d') ?>"

class="w-full border border-gray-300 rounded-lg px-4 py-3">

</div>


<!-- Status -->

<div>

<label class="block font-semibold mb-2">

Status

</label>

<select

name="status"

required

class="w-full border border-gray-300 rounded-lg px-4 py-3">

<option value="">Select Status</option>

<option value="Dropout"
<?= (($edit['status'] ?? '')=='Dropout')?'selected':'' ?>>

Dropout

</option>

<option value="Dismissal"
<?= (($edit['status'] ?? '')=='Dismissal')?'selected':'' ?>>

Dismissal

</option>

</select>

</div>



<!-- Fees Paid -->

<div>

<label class="block font-semibold mb-2">

Fees Paid (XAF)

</label>

<input

type="number"

name="fees_paid"

step="0.01"

min="0"

value="<?= $edit['fees_paid'] ?? 0 ?>"

class="w-full border border-gray-300 rounded-lg px-4 py-3">

</div>



<!-- Remarks -->

<div class="lg:col-span-2">

<label class="block font-semibold mb-2">

Remarks

</label>

<textarea

name="remarks"

rows="4"

class="w-full border border-gray-300 rounded-lg px-4 py-3"

placeholder="Reason for dropout or dismissal"><?= htmlspecialchars($edit['remarks'] ?? '') ?></textarea>

</div>

</div>



<div class="mt-8 flex gap-3">

<button

type="submit"

class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-3 rounded-lg">

<?php if($edit): ?>

<i class="fas fa-save mr-2"></i>

Update Record

<?php else: ?>

<i class="fas fa-plus-circle mr-2"></i>

Save Record

<?php endif; ?>

</button>

<?php if($edit): ?>

<a

href="dropouts.php"

class="bg-gray-600 hover:bg-gray-700 text-white px-8 py-3 rounded-lg">

Cancel

</a>

<?php endif; ?>

</div>

</form>

</div>
<!-- =====================================================
     DROPOUT RECORDS TABLE
===================================================== -->

<div class="bg-white rounded-xl shadow overflow-hidden">

    <div class="flex flex-col md:flex-row md:justify-between md:items-center px-6 py-4 border-b">

        <h2 class="text-xl font-bold text-indigo-700">

            <i class="fas fa-table mr-2"></i>

            Dropout Records

        </h2>

        <span class="text-gray-500 mt-2 md:mt-0">

            <?= number_format($totalFiltered) ?> Record(s)

        </span>

    </div>

    <div class="overflow-x-auto">

        <table class="min-w-full divide-y divide-gray-200">

            <thead class="bg-indigo-600 text-white">

                <tr>

                    <th class="px-4 py-3 text-left">#</th>

                    <th class="px-4 py-3 text-left">Admission No.</th>

                    <th class="px-4 py-3 text-left">Student Name</th>

                    <th class="px-4 py-3 text-left">Class</th>

                    <th class="px-4 py-3 text-left">Dropout Date</th>

                    <th class="px-4 py-3 text-center">Status</th>

                    <th class="px-4 py-3 text-right">Fees Paid</th>

                    <th class="px-4 py-3 text-left">Remarks</th>

                    <th class="px-4 py-3 text-center">Recorded By</th>

                    <th class="px-4 py-3 text-center">Actions</th>

                </tr>

            </thead>

            <tbody class="bg-white divide-y divide-gray-100">

            <?php if(empty($records)): ?>

                <tr>

                    <td colspan="10" class="text-center py-12 text-gray-500">

                        <i class="fas fa-folder-open text-5xl mb-4"></i>

                        <p>No dropout records found.</p>

                    </td>

                </tr>

            <?php else: ?>

            <?php foreach($records as $index => $row): ?>

            <tr class="hover:bg-gray-50">

                <td class="px-4 py-3">

                    <?= $offset + $index + 1 ?>

                </td>

                <td class="px-4 py-3 font-semibold">

                    <?= htmlspecialchars($row['admission_no']) ?>

                </td>

                <td class="px-4 py-3">

                    <?= htmlspecialchars($row['student_name']) ?>

                </td>

                <td class="px-4 py-3">

                    <?= htmlspecialchars($row['class_name']) ?>

                </td>

                <td class="px-4 py-3">

                    <?= date('d M Y', strtotime($row['dropout_date'])) ?>

                </td>

                <td class="px-4 py-3 text-center">

                    <?php if($row['status']=="Dismissal"): ?>

                        <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-semibold">

                            Dismissal

                        </span>

                    <?php else: ?>

                        <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-semibold">

                            Dropout

                        </span>

                    <?php endif; ?>

                </td>

                <td class="px-4 py-3 text-right font-semibold text-green-700">

                    <?= number_format($row['fees_paid'],2) ?>

                </td>

                <td class="px-4 py-3">

                    <?= htmlspecialchars($row['remarks']) ?>

                </td>

                <td class="px-4 py-3 text-center">

                    <?= htmlspecialchars($row['username']) ?>

                </td>

                <td class="px-4 py-3">

                    <div class="flex justify-center gap-2">

                        <!-- Edit -->

                        <a

                        href="?edit=<?= $row['id'] ?>"

                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg"

                        title="Edit">

                            <i class="fas fa-edit"></i>

                        </a>

                        <!-- Reinstate -->

                        <?php if($role=="admin"): ?>

                        <a

                        href="?reinstate=<?= $row['id'] ?>"

                        onclick="return confirm('Reinstate this student?');"

                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg"

                        title="Reinstate Student">

                            <i class="fas fa-user-check"></i>

                        </a>

                        <!-- Delete -->

                        <a

                        href="?delete=<?= $row['id'] ?>"

                        onclick="return confirm('Delete this record permanently?');"

                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg"

                        title="Delete">

                            <i class="fas fa-trash"></i>

                        </a>

                        <?php endif; ?>

                    </div>

                </td>

            </tr>

            <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =====================================================
     PAGINATION
===================================================== -->

<?php if($totalPages > 1): ?>

<div class="flex flex-col md:flex-row md:justify-between md:items-center mt-8">

    <div class="text-gray-600 mb-4 md:mb-0">

        Showing

        <strong><?= $offset + 1 ?></strong>

        -

        <strong><?= min($offset + count($records), $totalFiltered) ?></strong>

        of

        <strong><?= number_format($totalFiltered) ?></strong>

        record(s)

    </div>

    <div class="flex flex-wrap gap-2">

        <?php for($i=1;$i<=$totalPages;$i++): ?>

        <a

        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"

        class="px-4 py-2 rounded-lg border

        <?= $page==$i

        ? 'bg-indigo-600 text-white border-indigo-600'

        : 'bg-white hover:bg-gray-100'

        ?>">

            <?= $i ?>

        </a>

        <?php endfor; ?>

    </div>

</div>

<?php endif; ?>
<!-- =====================================================
     PAGE FOOTER
===================================================== -->

<footer class="mt-10">

    <div class="bg-white rounded-xl shadow p-5">

        <div class="flex flex-col md:flex-row md:justify-between md:items-center">

            <div>

                <h3 class="font-semibold text-gray-700">

                    SMS26 School Management System

                </h3>

                <p class="text-sm text-gray-500 mt-1">

                    Dropout & Dismissal Management Module

                </p>

            </div>

            <div class="mt-4 md:mt-0 text-sm text-gray-500">

                © <?= date('Y') ?> SMS26

            </div>

        </div>

    </div>

</footer>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

// Auto-hide alerts
window.addEventListener("load", function(){

    setTimeout(function(){

        let success=document.getElementById("successAlert");

        if(success){

            success.style.transition="0.5s";

            success.style.opacity="0";

            setTimeout(function(){

                success.remove();

            },500);

        }

        let error=document.getElementById("errorAlert");

        if(error){

            error.style.transition="0.5s";

            error.style.opacity="0";

            setTimeout(function(){

                error.remove();

            },500);

        }

    },4000);

});



// Highlight current row

document.querySelectorAll("tbody tr").forEach(function(row){

    row.addEventListener("mouseenter",function(){

        row.classList.add("bg-gray-50");

    });

    row.addEventListener("mouseleave",function(){

        row.classList.remove("bg-gray-50");

    });

});



// Confirm Delete

document.querySelectorAll("a[href*='delete=']").forEach(function(btn){

    btn.addEventListener("click",function(e){

        if(!confirm("Delete this dropout record permanently?")){

            e.preventDefault();

        }

    });

});



// Confirm Reinstate

document.querySelectorAll("a[href*='reinstate=']").forEach(function(btn){

    btn.addEventListener("click",function(e){

        if(!confirm("Reinstate this student?")){

            e.preventDefault();

        }

    });

});



// Prevent double form submission

const form=document.querySelector("form");

if(form){

    form.addEventListener("submit",function(){

        let btn=form.querySelector("button[type='submit']");

        if(btn){

            btn.disabled=true;

            btn.innerHTML="<i class='fas fa-spinner fa-spin mr-2'></i>Processing...";

        }

    });

}

</script>

</body>
</html>