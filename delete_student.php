<?php
session_start();
require_once __DIR__ . '/schooldb.php';

if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid student ID.");
}

$student_id = intval($_GET['id']);

// Optional: Add audit logging here before deletion

// Delete fees linked to the student first
$stmt = $pdo->prepare("DELETE FROM fees WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->close();

// Delete the student
$stmt2 = $pdo->prepare("DELETE FROM students WHERE id = ?");
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$stmt2->close();

header("Location: view_students.php?msg=deleted");
exit();
?>
