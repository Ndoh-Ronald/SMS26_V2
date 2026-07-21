<?php
session_start();
require_once __DIR__ . '/schooldb.php';

if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['log_id']) || !is_numeric($_GET['log_id'])) {
    die("Invalid log ID.");
}

$log_id = intval($_GET['log_id']);

// Fetch log entry
$stmt = $pdo->prepare("SELECT * FROM fee_change_logs WHERE id = ?");
$stmt->bind_param("i", $log_id);
$stmt->execute();
$log_result = $stmt->get_result();
$log = $log_result->fetch_assoc();
$stmt->close();

if (!$log) {
    die("Log entry not found.");
}

// Fetch current fee
$stmt = $pdo->prepare("SELECT * FROM fees WHERE id = ?");
$stmt->bind_param("i", $log['fee_id']);
$stmt->execute();
$fee_result = $stmt->get_result();
$current_fee = $fee_result->fetch_assoc();
$stmt->close();

if (!$current_fee) {
    die("Original fee record not found.");
}

// Save current values as new log entry (for audit trail before restore)
$log_restore = $pdo->prepare("INSERT INTO fee_change_logs (
    fee_id, student_id, old_amount, new_amount, old_narration, new_narration, old_date, new_date, changed_by
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$log_restore->bind_param(
    "iiddsssss",
    $log['fee_id'],
    $current_fee['student_id'],
    $current_fee['amount'],
    $log['old_amount'],
    $current_fee['narration'],
    $log['old_narration'],
    $current_fee['date'],
    $log['old_date'],
    $_SESSION['username']
);
$log_restore->execute();
$log_restore->close();

// Restore old values to the `fees` table
$update = $pdo->prepare("UPDATE fees SET amount = ?, narration = ?, date = ? WHERE id = ?");
$update->bind_param("dssi", $log['old_amount'], $log['old_narration'], $log['old_date'], $log['fee_id']);
$update->execute();

if ($update->affected_rows >= 0) {
    $update->close();
    header("Location: view_fee_logs.php?message=Fee restored successfully");
    exit();
} else {
    $update->close();
    die("Failed to restore fee record.");
}
?>
