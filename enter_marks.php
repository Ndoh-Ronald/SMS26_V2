<?php
session_start();
require_once __DIR__ . '/schooldb.php';

// Restrict access
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'teacher'])) {
    header("Location: login.php");
    exit;
}

// Get inputs
$class = $_GET['class'] ?? '';
$term = $_GET['term'] ?? '';
$year = $_GET['year'] ?? date('Y');

if (empty($class) || empty($term)) {
    die("Invalid request. Class and term are required.");
}

$username = $_SESSION['username'];
$role = strtolower($_SESSION['role']);

// Get teacher_id from teachers table
$teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE username = ?");
$teacher_stmt->bind_param("s", $username);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();
$teacher_id_row = $teacher_result->fetch_assoc();
$teacher_id = $teacher_id_row['id'] ?? null;

// Fetch subjects taught by this teacher for this class (with JOIN)
$subjects = [];
if ($role === 'teacher') {
    $subject_stmt = $pdo->prepare("
        SELECT s.name AS subject_name 
        FROM teacher_subjects ts
        JOIN subjects s ON ts.subject_id = s.id
        WHERE ts.teacher_id = ? AND ts.class = ?
    ");
    $subject_stmt->bind_param("is", $teacher_id, $class);
    $subject_stmt->execute();
    $subject_result = $subject_stmt->get_result();
    while ($row = $subject_result->fetch_assoc()) {
        $subjects[] = $row['subject_name'];
    }
} else {
    // Admin sees all subjects
    $subject_result = $pdo->query("SELECT name AS subject_name FROM subjects ORDER BY name ASC");
    while ($row = $subject_result->fetch_assoc()) {
        $subjects[] = $row['subject_name'];
    }
}

// Fetch students in the class
$students = [];
$student_stmt = $pdo->prepare("SELECT id, name FROM students WHERE class = ? ORDER BY name ASC");
$student_stmt->bind_param("s", $class);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Marks - <?= htmlspecialchars($class) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">
<div class="max-w-7xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4 text-center">Enter Marks - Class: <?= htmlspecialchars($class) ?> | Term: <?= $term ?> | Year: <?= $year ?></h1>

    <form action="save_marks.php" method="POST">
        <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
        <input type="hidden" name="term" value="<?= htmlspecialchars($term) ?>">
        <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">

        <div class="overflow-auto">
            <table class="table-auto w-full border border-gray-300 mb-6">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="border px-3 py-2">Student Name</th>
                        <?php foreach ($subjects as $subject): ?>
                            <th class="border px-3 py-2"><?= htmlspecialchars($subject) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="border px-3 py-2"><?= htmlspecialchars($student['name']) ?></td>
                            <?php foreach ($subjects as $subject): ?>
                                <td class="border px-3 py-2">
                                    <input type="number" name="marks[<?= $student['id'] ?>][<?= htmlspecialchars($subject) ?>]" min="0" max="100" class="w-20 px-2 py-1 border rounded">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-center">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Save Marks</button>
        </div>
    </form>
</div>
</body>
</html>
