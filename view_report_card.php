<?php
session_start();
require_once __DIR__ . '/schooldb.php';

$class = $_GET['class'] ?? '';
$term = $_GET['term'] ?? '1st Term';

// Get students in this class
$students = $pdo->query("SELECT id, name FROM students WHERE class = '$class' ORDER BY name");

// Get all subjects
$subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name");
$subjectList = [];
while ($sub = $subjects->fetch_assoc()) {
    $subjectList[$sub['id']] = $sub['name'];
}

// Fetch all marks for this class and term
$marksResult = $pdo->query("
    SELECT m.*, s.name AS student_name 
    FROM marks m 
    JOIN students s ON s.id = m.student_id 
    WHERE s.class = '$class' AND term = '$term'
");

$marksData = [];
$studentTotals = [];
$studentCounts = [];

while ($row = $marksResult->fetch_assoc()) {
    $sid = $row['student_id'];
    $subject = $subjectList[$row['subject_id']];
    $marksData[$sid]['name'] = $row['student_name'];
    $marksData[$sid]['marks'][$subject] = $row['mark'];
    $studentTotals[$sid] = ($studentTotals[$sid] ?? 0) + $row['mark'];
    $studentCounts[$sid] = ($studentCounts[$sid] ?? 0) + 1;
}

// Class average
$classTotal = array_sum($studentTotals);
$classCount = array_sum($studentCounts);
$classAverage = $classCount > 0 ? round($classTotal / $classCount, 2) : 0;

// Compute averages and positions
$averages = [];
foreach ($studentTotals as $sid => $total) {
    $avg = round($total / $studentCounts[$sid], 2);
    $averages[$sid] = $avg;
}
asort($averages);
$positions = array_keys(array_reverse($averages));

?>

<!DOCTYPE html>
<html>
<head>
    <title>Report Card - <?= htmlspecialchars($class) ?> | <?= $term ?></title>
    <style>
        .green { color: green; }
        .black { color: black; }
        .red { color: red; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #999; padding: 6px; text-align: center; }
        h2, h3 { text-align: center; }
    </style>
</head>
<body>
    <h2>SYNERGIA SCHOOL SUITE - REPORT CARD</h2>
    <h3>Class: <?= htmlspecialchars($class) ?> | Term: <?= $term ?> | Class Average: <?= $classAverage ?></h3>

    <?php foreach ($marksData as $sid => $data): ?>
        <h3>Student: <?= htmlspecialchars($data['name']) ?> (ID: <?= $sid ?>)</h3>
        <table>
            <tr>
                <th>Subject</th>
                <th>Mark</th>
            </tr>
            <?php foreach ($data['marks'] as $subject => $mark): ?>
                <?php
                    $color = $mark >= 15 ? "green" : ($mark >= 10 ? "black" : "red");
                ?>
                <tr>
                    <td><?= htmlspecialchars($subject) ?></td>
                    <td class="<?= $color ?>"><?= $mark ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td><strong>Total</strong></td>
                <td><strong><?= $studentTotals[$sid] ?></strong></td>
            </tr>
            <tr>
                <td><strong>Average</strong></td>
                <td><strong><?= round($studentTotals[$sid] / $studentCounts[$sid], 2) ?></strong></td>
            </tr>
            <tr>
                <td><strong>Position</strong></td>
                <td><strong><?= array_search($sid, $positions) + 1 ?></strong></td>
            </tr>
        </table>

        <p style="margin-top: 30px;">
            <strong>Parent's Signature: _________________________</strong><br><br>
            <strong>Class Master: _____________________________</strong><br><br>
            <strong>Principal: ________________________________</strong>
        </p>
        <hr><br>
    <?php endforeach; ?>
</body>
</html>
