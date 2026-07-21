<?php
session_start();

require_once __DIR__ . '/schooldb.php';

// Admin-only access
if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    die("Access denied!");
}

$message = "";
$classes = [];

/*
|--------------------------------------------------------------------------
| DELETE CLASS
|--------------------------------------------------------------------------
*/
if (isset($_GET['delete_id'])) {

    $delete_id = (int)$_GET['delete_id'];

    if ($delete_id > 0) {

        try {

            $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
            $stmt->execute([$delete_id]);

            $message = "
            <div class='message message-success'>
                Class deleted successfully!
            </div>";

        } catch (PDOException $e) {

            $message = "
            <div class='message message-error'>
                " . htmlspecialchars($e->getMessage()) . "
            </div>";

        }

    }

}

/*
|--------------------------------------------------------------------------
| ADD NEW CLASS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $class_name = trim($_POST['class_name'] ?? '');
    $standard_fee = trim($_POST['standard_fee'] ?? '');

    if ($class_name == '') {

        $message = "
        <div class='message message-error'>
            Please enter a class name.
        </div>";

    } elseif (!is_numeric($standard_fee)) {

        $message = "
        <div class='message message-error'>
            Invalid class fee.
        </div>";

    } else {

        try {

            // Check duplicate class
            $check = $pdo->prepare("
                SELECT id
                FROM classes
                WHERE class_name = ?
                LIMIT 1
            ");

            $check->execute([$class_name]);

            if ($check->fetch()) {

                $message = "
                <div class='message message-error'>
                    This class already exists.
                </div>";

            } else {

                $insert = $pdo->prepare("
                    INSERT INTO classes
                    (
                        class_name,
                        standard_fee
                    )
                    VALUES
                    (
                        ?,
                        ?
                    )
                ");

                $insert->execute([
                    $class_name,
                    $standard_fee
                ]);

                $message = "
                <div class='message message-success'>
                    Class added successfully.
                </div>";

            }

        } catch (PDOException $e) {

            $message = "
            <div class='message message-error'>
                " . htmlspecialchars($e->getMessage()) . "
            </div>";

        }

    }

}

/*
|--------------------------------------------------------------------------
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            class_name,
            standard_fee
        FROM classes
        ORDER BY class_name ASC
    ");

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Error fetching classes: " . $e->getMessage());

}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Set Class Fee</title>

    <style>

        body{
            font-family:Arial,sans-serif;
            padding:20px;
            background:#f9f9f9;
        }

        h2,h3{
            color:#333;
        }

        table{
            border-collapse:collapse;
            width:100%;
            margin-top:20px;
        }

        th,
        td{
            border:1px solid #ddd;
            padding:10px;
            text-align:center;
        }

        th{
            background:#4CAF50;
            color:white;
        }

        tr:nth-child(even){
            background:#f2f2f2;
        }

        input[type=text],
        input[type=number]{
            padding:8px;
            margin:5px;
            width:220px;
        }

        button{
            padding:8px 14px;
            cursor:pointer;
        }

        .btn-delete{
            background:#f44336;
            color:white;
            border:none;
        }

        .btn-delete:hover{
            background:#d32f2f;
        }

        .btn-back{
            background:#555;
            color:white;
            padding:10px 15px;
            text-decoration:none;
        }

        .btn-back:hover{
            background:#333;
        }

        .message{
            padding:12px;
            margin-bottom:15px;
            border-radius:5px;
        }

        .message-success{
            background:#d4edda;
            color:#155724;
        }

        .message-error{
            background:#f8d7da;
            color:#721c24;
        }

    </style>

    <script>

        function confirmDelete(id,name){

            if(confirm('Are you sure you want to delete "'+name+'"?')){

                window.location='?delete_id='+id;

            }

        }

    </script>

</head>

<body>

<h2>Manage Classes and Fees</h2>

<?php
if($message!=""){
    echo $message;
}
?>

<form method="POST">

    <label><strong>Class Name</strong></label><br>

    <input
        type="text"
        name="class_name"
        required
        placeholder="Enter Class Name"
    >

    <br><br>

    <label><strong>Standard Fee (FCFA)</strong></label><br>

    <input
        type="number"
        name="standard_fee"
        step="0.01"
        min="0"
        required
        placeholder="Enter Standard Fee"
    >

    <br><br>

    <button type="submit">

        Add Class

    </button>

</form>

<hr>

<h3>Available Classes</h3>

<table>

<thead>

<tr>

<th>#</th>

<th>Class Name</th>

<th>Standard Fee (FCFA)</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php if(empty($classes)): ?>

<tr>

<td colspan="4">

No classes available.

</td>

</tr>

<?php else: ?>

<?php $sn=1; ?>

<?php foreach($classes as $row): ?>

<tr>

<td><?= $sn++ ?></td>

<td><?= htmlspecialchars($row['class_name']) ?></td>

<td><?= number_format($row['standard_fee'],2) ?></td>

<td>

<button

type="button"

class="btn-delete"

onclick="confirmDelete(<?= $row['id'] ?>,'<?= htmlspecialchars(addslashes($row['class_name'])) ?>')"

>

Delete

</button>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

<br><br>

<a href="dashboard.php" class="btn-back">

← Back to Dashboard

</a>

</body>

</html>