<?php
session_start();
require_once __DIR__ . '/schooldb.php';

$class_id = $_GET['class_id'] ?? '';
$term = $_GET['term'] ?? '3';

if (!$class_id || $term != '3') {
    die("Invalid request. This page is only for 3rd term cumulative reports.");
}

// Get all students in class
$students = $pdo->query("SELECT id, name FROM students WHERE class_id = '$class_id' ORDER BY name ASC");

$annual_averages = [];

while ($student = $students->fetch_assoc()) {
    $student_id = $student['id'];

    // Sum marks for all three terms for the student
    $stmt = $pdo->prepare("
        SELECT subject_id, term, AVG(mark) as avg_mark
        FROM marks
        WHERE student_id = ? AND term IN (1,2,3)
        GROUP BY subject_id, term
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $term_data = [];
    while ($row = $res->fetch_assoc()) {
        $term_data[$row['term']][] = $row['avg_mark'];
    }

    $cumulative_avg = 0;
    $count = 0;
    foreach ([1, 2, 3] as $t) {
        if (!empty($term_data[$t])) {
            $cumulative_avg += array_sum($term_data[$t]);
            $count += count($term_data[$t]);
        }
    }

    $final_avg = $count > 0 ? round($cumulative_avg / $count, 2) : 0;
    $annual_averages[$student_id] = [
        'name' => $student['name'],
        'average' => $final_avg
    ];
    $stmt->close();
}

// Sort by average descending to determine class position
uasort($annual_averages, function($a, $b) {
    return $b['average'] <=> $a['average'];
});

// Assign positions
$position = 1;
foreach ($annual_averages as $id => &$data) {
    $data['position'] = $position++;
}

// Now render results
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cumulative Annual Report</title>
    <style>
        body { font-family: Arial; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #999; padding: 8px; text-align: center; }
        .green { color: green; }
        .red { color: red; }
        .black { color: black; }
    </style>
</head>
<body>
    <h2>Cumulative Annual Report Card - 3rd Term</h2>
    <p><strong>Class:</strong> <?= htmlspecialchars($class_id) ?></p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Annual Average</th>
                <th>Class Position</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($annual_averages as $student_id => $data): 
                $avg = $data['average'];
                $color = $avg >= 15 ? 'green' : ($avg < 10 ? 'red' : 'black');
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($data['name']) ?></td>
                    <td class="<?= $color ?>"><?= $avg ?></td>
                    <td><?= $data['position'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br><br>
    <p><strong>Parent's Signature: ___________________________</strong></p>
    <p><strong>Principal's Signature: _________________________</strong></p>
    <p><strong>Class Master's Signature: ______________________</strong></p>
</body>
</html>
