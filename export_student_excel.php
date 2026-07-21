<?php
require_once __DIR__ . '/schooldb.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=student_list.xls");

echo "Name\tClass\tGender\tDate of Birth\tContact\n";

$query = "SELECT * FROM students ORDER BY name ASC";
$result = $pdo->query($query);

while ($row = $result->fetch_assoc()) {
    echo "{$row['name']}\t{$row['class']}\t{$row['gender']}\t{$row['dob']}\t{$row['contact']}\n";
}
exit;
