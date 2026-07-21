<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Restrict to logged-in users (Admin or Teacher)
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'teacher'])) {
    header("Location: login.php");
    exit;
}

// Fetch distinct classes from students table
$classes = [];
$class_query = $pdo->query("SELECT DISTINCT class FROM students ORDER BY class ASC");
while ($row = $class_query->fetch_assoc()) {
    $classes[] = $row['class'];
}

// Default year
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Class and Term - Report Card</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
<div class="max-w-xl mx-auto mt-12 bg-white p-8 rounded shadow">
    <h1 class="text-2xl font-bold mb-6 text-center">Generate Report Card</h1>

    <form action="enter_marks.php" method="GET">
        <div class="mb-4">
            <label class="block text-gray-700 mb-1">Select Class:</label>
            <select name="class" required class="w-full border rounded px-3 py-2">
                <option value="">-- Choose Class --</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?= htmlspecialchars($class) ?>"><?= htmlspecialchars($class) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 mb-1">Select Term:</label>
            <select name="term" required class="w-full border rounded px-3 py-2">
                <option value="">-- Choose Term --</option>
                <option value="1">1st Term</option>
                <option value="2">2nd Term</option>
                <option value="3">3rd Term</option>
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 mb-1">Academic Year (optional):</label>
            <input type="text" name="year" placeholder="<?= $current_year ?>" class="w-full border rounded px-3 py-2" value="<?= $current_year ?>">
        </div>

        <div class="text-center">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                Proceed to Enter/View Marks
            </button>
        </div>
    </form>
</div>
</body>
</html>
