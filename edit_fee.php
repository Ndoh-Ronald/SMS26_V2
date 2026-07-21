<?php
session_start();
require_once __DIR__ . '/schooldb.php';

if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_GET['student_id'] ?? '';
if (!$student_id) {
    echo "Invalid student ID.";
    exit;
}

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fee_id'], $_POST['amount'], $_POST['narration'], $_POST['date'])) {
    $fee_id = intval($_POST['fee_id']);
    $amount = floatval($_POST['amount']);
    $narration = trim($_POST['narration']);
    $date = $_POST['date'];

    try {
        $stmt = $pdo->prepare("UPDATE fees 
                                SET amount = :amount, narration = :narration, date = :date 
                                WHERE id = :id");
        $stmt->execute([
            ':amount'    => $amount,
            ':narration' => $narration,
            ':date'      => $date,
            ':id'        => $fee_id
        ]);
        $message = "Change saved successfully.";
    } catch (PDOException $e) {
        $message = "Error saving changes: " . $e->getMessage();
    }
}

// Fetch all fees for this student
$feeStmt = $pdo->prepare("SELECT id, amount, narration, date FROM fees WHERE student_id = :student_id");
$feeStmt->execute([':student_id' => $student_id]);
$fees = $feeStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch student info
$studentStmt = $pdo->prepare("SELECT name FROM students WHERE id = :id");
$studentStmt->execute([':id' => $student_id]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "Student not found.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Fees for <?= htmlspecialchars($student['name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 font-sans max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6 text-indigo-700">Edit Fees for <?= htmlspecialchars($student['name']) ?></h1>

    <?php if ($message): ?>
        <div class="mb-4 p-4 bg-green-200 text-green-800 rounded"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (empty($fees)): ?>
        <p class="text-gray-700">No fee records found for this student.</p>
    <?php else: ?>
        <form method="POST" class="space-y-6 bg-white p-6 rounded shadow">
            <?php foreach ($fees as $fee): ?>
                <div class="border rounded p-4 bg-gray-50">
                    <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>" />
                    
                    <label class="block mb-1 font-semibold text-gray-700">Amount</label>
                    <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($fee['amount']) ?>" required
                        class="w-full px-3 py-2 border rounded" />
                    
                    <label class="block mt-3 mb-1 font-semibold text-gray-700">Narration</label>
                    <input type="text" name="narration" value="<?= htmlspecialchars($fee['narration']) ?>" required
                        class="w-full px-3 py-2 border rounded" />

                    <label class="block mt-3 mb-1 font-semibold text-gray-700">Date</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($fee['date']) ?>" required
                        class="w-full px-3 py-2 border rounded" />
                </div>
            <?php endforeach; ?>

            <div class="flex justify-between items-center mt-6">
                <a href="manage_fees.php" class="text-indigo-600 hover:underline">&larr; Back to Manage Fees</a>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Save Changes</button>
            </div>
        </form>
    <?php endif; ?>
</body>
</html>
