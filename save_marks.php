<?php
require_once '../schooldb.php';

$class = $_POST['class'] ?? '';
$term = $_POST['term'] ?? '';
$year = $_POST['year'] ?? date('Y');
$student_ids = $_POST['student_ids'] ?? [];
$marks = $_POST['marks'] ?? [];

foreach ($student_ids as $student_id) {
    foreach ($marks[$student_id] as $subject => $mark) {
        if ($mark !== '') {
            $stmt = $mysqli->prepare("INSERT INTO marks (student_id, class, term, year, subject, mark) 
                                      VALUES (?, ?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE mark = VALUES(mark)");
            $stmt->bind_param("issssi", $student_id, $class, $term, $year, $subject, $mark);
            $stmt->execute();
            $stmt->close();
        }
    }
}

echo "<script>alert('Marks saved successfully.'); window.location.href='select_class_term.php';</script>";
exit;
