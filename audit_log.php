<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Admin only
if (!isset($_SESSION['username']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch logs from activity_log table
$result = $pdo->query("SELECT * FROM activity_log ORDER BY timestamp DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | Synergia</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen p-6 font-sans">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">System Activity Logs</h1>

        <div class="overflow-auto bg-white shadow-md rounded-lg">
            <table class="min-w-full table-auto">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="p-3 text-left">User</th>
                        <th class="p-3 text-left">Role</th>
                        <th class="p-3 text-left">Action</th>
                        <th class="p-3 text-left">Student ID</th>
                        <th class="p-3 text-left">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($log = $result->fetch_assoc()): ?>
                            <tr class="border-b hover:bg-gray-100">
                                <td class="p-3"><?= htmlspecialchars($log['username']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($log['role']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($log['action']) ?></td>
                                <td class="p-3"><?= htmlspecialchars($log['student_id'] ?? '-') ?></td>
                                <td class="p-3"><?= htmlspecialchars($log['timestamp']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="p-4 text-center text-gray-500">No activity logs found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="mt-4">
            <a href="dashboard.php" class="text-red-600 underline">← Back to Dashboard</a>
        </p>
    </div>
</body>
</html>
