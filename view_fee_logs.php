<?php
session_start();
require_once __DIR__ . '/schooldb.php';

if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Filter parameters
$student_id = $_GET['student_id'] ?? '';
$admin = $_GET['admin'] ?? '';
$date = $_GET['date'] ?? '';
$message = $_GET['message'] ?? '';

$sql = "SELECT l.*, s.name AS student_name FROM fee_change_logs l
        LEFT JOIN students s ON s.id = l.student_id WHERE 1=1";
$params = [];
$types = "";

if ($student_id) {
    $sql .= " AND l.student_id = ?";
    $params[] = $student_id;
    $types .= "i";
}
if ($admin) {
    $sql .= " AND l.changed_by = ?";
    $params[] = $admin;
    $types .= "s";
}
if ($date) {
    $sql .= " AND DATE(l.change_date) = ?";
    $params[] = $date;
    $types .= "s";
}

$stmt = $pdo->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Change Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded shadow">
        <h2 class="text-2xl font-bold mb-4 text-indigo-700">Fee Change Logs</h2>

        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="GET" class="mb-6 flex gap-4 flex-wrap">
            <input type="text" name="student_id" placeholder="Student ID" value="<?= htmlspecialchars($student_id) ?>"
                   class="p-2 border rounded w-40">
            <input type="text" name="admin" placeholder="Admin Username" value="<?= htmlspecialchars($admin) ?>"
                   class="p-2 border rounded w-40">
            <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="p-2 border rounded w-40">
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Filter</button>
            <a href="view_fee_logs.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Reset</a>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left border border-collapse">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border p-2">Student</th>
                        <th class="border p-2">Changed By</th>
                        <th class="border p-2">Old Amount</th>
                        <th class="border p-2">New Amount</th>
                        <th class="border p-2">Old Narration</th>
                        <th class="border p-2">New Narration</th>
                        <th class="border p-2">Old Date</th>
                        <th class="border p-2">New Date</th>
                        <th class="border p-2">Changed On</th>
                        <th class="border p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border p-2"><?= htmlspecialchars($log['student_name']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['changed_by']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['old_amount']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['new_amount']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['old_narration']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['new_narration']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['old_date']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['new_date']) ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['change_date']) ?></td>
                            <td class="border p-2 text-center">
                                <a href="restore_fee.php?log_id=<?= $log['id'] ?>"
                                   onclick="return confirm('Are you sure you want to restore this fee?')"
                                   class="text-blue-600 hover:underline text-sm">Restore</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($logs->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center p-4 text-gray-500">No logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
