<?php
session_start();
require_once __DIR__ . '/schooldb.php'; // Make sure $pdo is a PDO instance

// Access control
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';
$action = $_GET['action'] ?? '';

// Helper function to mark student inactive
function markStudentInactive($conn, $studentId) {
    $stmt = $pdo->prepare("UPDATE students SET active = 0 WHERE id = ?");
    $stmt->execute([$studentId]);
}

// Fetch all active students for dropdown
$studentsStmt = $pdo->prepare("
    SELECT s.id, s.name, c.class_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.active = 1
    ORDER BY s.name ASC
");
$studentsStmt->execute();
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $delStmt = $pdo->prepare("DELETE FROM dropouts WHERE id = ?");
    if ($delStmt->execute([$id])) {
        $success = "Record deleted successfully.";
    } else {
        $error = "Delete failed.";
    }
}

// Handle Edit fetch
$editRecord = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editId = intval($_GET['id']);
    $editStmt = $pdo->prepare("SELECT * FROM dropouts WHERE id = ?");
    $editStmt->execute([$editId]);
    $editRecord = $editStmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $studentId = intval($_POST['student_id'] ?? 0);
    $dropoutDate = $_POST['dropout_date'] ?? '';
    $status = $_POST['status'] ?? '';
    $feesPaid = floatval($_POST['fees_paid'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($studentId <= 0 || !$dropoutDate || !in_array($status, ['Dropout', 'Dismissal'])) {
        $error = "Please fill in all required fields correctly.";
    } else {
        if ($id > 0) {
            // Update
            $updStmt = $pdo->prepare("
                UPDATE dropouts SET student_id = ?, dropout_date = ?, status = ?, fees_paid = ?, remarks = ? 
                WHERE id = ?
            ");
            if ($updStmt->execute([$studentId, $dropoutDate, $status, $feesPaid, $remarks, $id])) {
                $success = "Record updated successfully.";
                markStudentInactive($conn, $studentId);
            } else {
                $error = "Update failed.";
            }
        } else {
            // Insert
            $insStmt = $pdo->prepare("
                INSERT INTO dropouts (student_id, dropout_date, status, fees_paid, remarks) 
                VALUES (?, ?, ?, ?, ?)
            ");
            if ($insStmt->execute([$studentId, $dropoutDate, $status, $feesPaid, $remarks])) {
                $success = "Record saved successfully.";
                markStudentInactive($conn, $studentId);
            } else {
                $error = "Insert failed.";
            }
        }
    }
}

// Fetch all records for display
$recordsStmt = $pdo->prepare("
    SELECT d.id, s.name AS student_name, c.class_name, d.dropout_date, d.status, d.fees_paid, d.remarks, d.recorded_at
    FROM dropouts d
    JOIN students s ON s.id = d.student_id
    LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY d.recorded_at DESC
");
$recordsStmt->execute();
$records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Dropouts / Dismissals</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-6 font-sans">
<h1 class="text-2xl font-bold text-indigo-700 mb-6">
    <?= $editRecord ? "Edit Dropout/Dismissal" : "Add Dropout / Dismissal" ?>
</h1>

<?php if ($error): ?>
    <p class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error ?? '') ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success ?? '') ?></p>
<?php endif; ?>

<form method="POST" class="bg-white p-6 rounded shadow max-w-xl mb-8">
    <input type="hidden" name="id" value="<?= $editRecord['id'] ?? 0 ?>" />
    <div class="mb-4">
        <label class="block font-semibold mb-1">Student</label>
        <select name="student_id" required class="w-full border rounded px-3 py-2">
            <option value="">-- Select Student --</option>
            <?php foreach ($students as $stu): ?>
                <option value="<?= $stu['id'] ?>" <?= (isset($editRecord) && $editRecord['student_id'] == $stu['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($stu['name'] . " (" . ($stu['class_name'] ?? '-') . ")") ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-4">
        <label class="block font-semibold mb-1">Date of Dropout/Dismissal</label>
        <input type="date" name="dropout_date" required max="<?= date('Y-m-d') ?>" 
               class="w-full border rounded px-3 py-2" 
               value="<?= htmlspecialchars($editRecord['dropout_date'] ?? '') ?>" />
    </div>

    <div class="mb-4">
        <label class="block font-semibold mb-1">Status</label>
        <select name="status" required class="w-full border rounded px-3 py-2">
            <option value="">-- Select Status --</option>
            <option value="Dropout" <?= (isset($editRecord) && $editRecord['status'] === 'Dropout') ? 'selected' : '' ?>>Dropout</option>
            <option value="Dismissal" <?= (isset($editRecord) && $editRecord['status'] === 'Dismissal') ? 'selected' : '' ?>>Dismissal</option>
        </select>
    </div>

    <div class="mb-4">
        <label class="block font-semibold mb-1">Fees Paid (XAF)</label>
        <input type="number" min="0" step="0.01" name="fees_paid" required 
               class="w-full border rounded px-3 py-2" 
               value="<?= htmlspecialchars($editRecord['fees_paid'] ?? 0) ?>" />
    </div>

    <div class="mb-4">
        <label class="block font-semibold mb-1">Remarks</label>
        <textarea name="remarks" rows="3" class="w-full border rounded px-3 py-2"><?= htmlspecialchars($editRecord['remarks'] ?? '') ?></textarea>
    </div>

    <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700">
        <?= $editRecord ? "Update Record" : "Save Record" ?>
    </button>
    <?php if ($editRecord): ?>
        <a href="dropouts.php" class="ml-4 text-red-600 hover:underline">Cancel Edit</a>
    <?php endif; ?>
</form>

<h2 class="text-xl font-semibold text-indigo-700 mb-4">All Dropout / Dismissal Records</h2>

<div class="overflow-x-auto bg-white rounded shadow max-w-6xl">
    <table class="min-w-full text-sm">
        <thead class="bg-indigo-200 text-indigo-800">
            <tr>
                <th class="p-3 text-left">Student</th>
                <th class="p-3 text-left">Class</th>
                <th class="p-3 text-left">Date</th>
                <th class="p-3 text-left">Status</th>
                <th class="p-3 text-right">Fees Paid</th>
                <th class="p-3 text-left">Remarks</th>
                <th class="p-3 text-left">Recorded At</th>
                <th class="p-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr><td colspan="8" class="p-4 text-center text-gray-500">No records found.</td></tr>
            <?php else: ?>
                <?php foreach ($records as $rec): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3"><?= htmlspecialchars($rec['student_name'] ?? '') ?></td>
                        <td class="p-3"><?= htmlspecialchars($rec['class_name'] ?? '-') ?></td>
                        <td class="p-3"><?= htmlspecialchars($rec['dropout_date'] ?? '') ?></td>
                        <td class="p-3"><?= htmlspecialchars($rec['status'] ?? '') ?></td>
                        <td class="p-3 text-right"><?= number_format($rec['fees_paid'] ?? 0, 2) ?></td>
                        <td class="p-3"><?= htmlspecialchars($rec['remarks'] ?? '') ?></td>
                        <td class="p-3"><?= htmlspecialchars($rec['recorded_at'] ?? '') ?></td>
                        <td class="p-3">
                            <a href="?action=edit&id=<?= $rec['id'] ?>" class="text-blue-600 hover:underline mr-2">Edit</a>
                            <a href="?action=delete&id=<?= $rec['id'] ?>" onclick="return confirm('Are you sure you want to delete this record?')" class="text-red-600 hover:underline">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<a href="dashboard.php" class="inline-block mt-6 text-indigo-600 hover:underline">&larr; Back to Dashboard</a>
</body>
</html>
