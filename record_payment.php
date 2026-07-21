<?php
session_start();
require_once __DIR__ . '/schooldb.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $amount_paid = $_POST['amount_paid'];
    $narration = $_POST['narration'];

    $stmt = $pdo->prepare("INSERT INTO student_payments (student_id, amount_paid, payment_date, narration) VALUES (?, ?, NOW(), ?)");
    $stmt->bind_param("ids", $student_id, $amount_paid, $narration);
    $stmt->execute();
}
?>

<!-- Payment Form -->
<form method="POST">
    <select name="student_id">
        <?php
        $res = $pdo->query("SELECT students.id, students.name, classes.class_name FROM students JOIN classes ON students.class_id = classes.id");
        while ($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['name']} ({$row['class_name']})</option>";
        }
        ?>
    </select>
    <input type="number" step="0.01" name="amount_paid" placeholder="Amount Paid" required>
    <input type="text" name="narration" placeholder="Narration">
    <button type="submit">Record Payment</button>
</form>
