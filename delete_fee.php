<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Only Admin can delete
if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Validate fee ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "No valid fee ID provided.";
    exit();
}

$id = intval($_GET['id']);

try {
    // Optional: Add audit logging here before deletion
    $stmt = $pdo->prepare("DELETE FROM fees WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        header("Location: manage_fee.php?deleted=1");
        exit();
    } else {
        echo "Failed to delete fee or fee not found.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
