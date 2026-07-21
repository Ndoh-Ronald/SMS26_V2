<?php
session_start();

// Only allow Admin
if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    die("Access denied!");
}

try {
    // Connect to SQLite
    $pdo = new PDO('sqlite:' . __DIR__ . '/schooldb');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Enable foreign key constraints
    $pdo->exec("PRAGMA foreign_keys = ON");

    // Start transaction
    $pdo->beginTransaction();

    // Delete all fees
    $pdo->exec("DELETE FROM fees");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='fees'");

    // Delete all students
    $pdo->exec("DELETE FROM students");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='students'");

    $pdo->commit();

    echo "✅ All students and fees have been reset successfully!";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Reset failed: " . $e->getMessage();
}
?>
