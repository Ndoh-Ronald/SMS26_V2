<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Only Admin or Bursar can access
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    header("Location: login.php");
    exit;
}

$result = $pdo->query("
    SELECT fees.id, students.name AS student_name, fees.amount, fees.date_paid 
    FROM fees 
    JOIN students ON students.id = fees.student_id 
    ORDER BY fees.date_paid DESC
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Fees | Synergia</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <h1 class="text-2xl font-bold text-center mb-6">All Fee Records</h1>
    <table class="min-w-full bg-white shadow rounded-lg overflow-hidden">
        <thead class="bg-indigo-600 text-white">
            <tr>
                <th class="p-3 text-left">Student</th>
                <th class="p-3 text-left">Amount (FCFA)</th>
                <th class="p-3 text-left">Date Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="border-b hover:bg-indigo-50">
                    <td class="p-3"><?= htmlspecialchars($row['student_name']) ?></td>
                    <td class="p-3"><?= number_format($row['amount']) ?></td>
                    <td class="p-3"><?= $row['date_paid'] ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
