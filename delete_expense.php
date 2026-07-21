<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Only allow Admin and Bursar
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    header("Location: login.php");
    exit;
}

$expense_id = intval($_POST['id'] ?? 0);
$username = $_SESSION['username'];
$role = $_SESSION['role'];

if ($expense_id > 0) {
    // Step 1: Fetch the expense details before deletion
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = :id");
    $stmt->execute([':id' => $expense_id]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($expense) {
        // Step 2: Log the deletion in audit_trail
        $log_stmt = $pdo->prepare("
            INSERT INTO audit_trail (action, performed_by, role, details, timestamp) 
            VALUES (:action, :performed_by, :role, :details, :timestamp)
        ");
        $action = "Delete Expense";
        $details = "Deleted ID: $expense_id, Narration: {$expense['narration']}, Amount: {$expense['amount']}";
        $log_stmt->execute([
            ':action' => $action,
            ':performed_by' => $username,
            ':role' => $role,
            ':details' => $details,
            ':timestamp' => date('Y-m-d H:i:s')
        ]);

        // Step 3: Perform the actual deletion
        $del_stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
        $del_stmt->execute([':id' => $expense_id]);
    }
}

// Step 4: Redirect back to the record_expense page
header("Location: record_expense.php");
exit;
?>
