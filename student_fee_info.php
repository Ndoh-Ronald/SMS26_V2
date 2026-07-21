<?php
require_once 'db.php';

header('Content-Type: application/json');

$student_id = intval($_GET['student_id'] ?? 0);

if ($student_id > 0) {
    $stmt = $pdo->prepare("SELECT registration_fee FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $student = $res->fetch_assoc();
    $stmt->close();

    if ($student) {
        $feeStmt = $pdo->prepare("SELECT SUM(amount) as total FROM fees WHERE student_id = ?");
        $feeStmt->bind_param("i", $student_id);
        $feeStmt->execute();
        $feeRes = $feeStmt->get_result();
        $feeData = $feeRes->fetch_assoc();
        $feeStmt->close();

        $reg_fee = floatval($student['registration_fee']);
        $paid = floatval($feeData['total'] ?? 0);
        $balance = $reg_fee - $paid;

        echo json_encode([
            'status' => 'ok',
            'registration_fee' => $reg_fee,
            'total_paid' => $paid,
            'balance' => $balance
        ]);
        exit;
    }
}

echo json_encode(['status' => 'error']);
