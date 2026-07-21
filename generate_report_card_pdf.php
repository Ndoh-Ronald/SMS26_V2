<?php
require '../vendor/autoload.php'; // adjust path if needed
require_once '../schooldb.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$student_id = $_GET['student_id'] ?? null;
$term = $_GET['term'] ?? null;
$class = $_GET['class'] ?? null;

if (!$student_id || !$term || !$class) {
    die("Invalid parameters.");
}

// Fetch student info
$studentResult = $mysqli->query("SELECT name, gender, class FROM students WHERE id = $student_id");
$student = $studentResult->fetch_assoc();

// Fetch subjects and marks
$marksResult = $mysqli->query("SELECT subject, mark FROM marks WHERE student_id = $student_id AND term = '$term'");
$subjects = [];
$total = 0;
$count = 0;

while ($row = $marksResult->fetch_assoc()) {
    $subjects[] = $row;
    $total += $row['mark'];
    $count++;
}
$average = $count ? round($total / $count, 2) : 0;

// Class Average
$classAvgResult = $mysqli->query("SELECT AVG(mark) as class_avg FROM marks WHERE class = '$class' AND term = '$term'");
$class_avg = round($classAvgResult->fetch_assoc()['class_avg'], 2);

// Class Position
$positionsResult = $mysqli->query("
    SELECT student_id, AVG(mark) as avg_mark
    FROM marks
    WHERE term = '$term' AND class = '$class'
    GROUP BY student_id
    ORDER BY avg_mark DESC
");

$position = 1;
while ($row = $positionsResult->fetch_assoc()) {
    if ($row['student_id'] == $student_id) break;
    $position++;
}

// For 3rd term: Annual Average & Position
$annual_avg = $annual_pos = '-';
if ($term == '3') {
    $annualQuery = $mysqli->query("
        SELECT student_id, AVG(mark) as annual_avg
        FROM marks
        WHERE class = '$class' AND student_id = $student_id
        GROUP BY student_id
    ");
    $annual_avg = round($annualQuery->fetch_assoc()['annual_avg'], 2);

    $allAnnual = $mysqli->query("
        SELECT student_id, AVG(mark) as avg_mark
        FROM marks
        WHERE term IN ('1', '2', '3') AND class = '$class'
        GROUP BY student_id
        ORDER BY avg_mark DESC
    ");
    $annual_pos = 1;
    while ($row = $allAnnual->fetch_assoc()) {
        if ($row['student_id'] == $student_id) break;
        $annual_pos++;
    }
}

// Generate PDF content
$html = "
<h2 style='text-align:center;'>Synergia Report Card</h2>
<p><strong>Name:</strong> {$student['name']}<br>
<strong>Class:</strong> {$student['class']}<br>
<strong>Term:</strong> {$term}</p>
<table border='1' width='100%' cellspacing='0' cellpadding='5'>
<tr><th>Subject</th><th>Mark</th><th>Grade Color</th></tr>";

foreach ($subjects as $sub) {
    $mark = $sub['mark'];
    $color = ($mark >= 15) ? 'green' : (($mark >= 10) ? 'black' : 'red');
    $html .= "<tr><td>{$sub['subject']}</td><td>$mark</td><td style='color:$color;'>$mark</td></tr>";
}

$html .= "</table><br>
<p><strong>Student Average:</strong> $average<br>
<strong>Class Average:</strong> $class_avg<br>
<strong>Position in Class:</strong> $position</p>";

if ($term == '3') {
    $html .= "<p><strong>Annual Average:</strong> $annual_avg<br>
    <strong>Annual Position:</strong> $annual_pos</p>";
}

$html .= "<br><br>
<p>Parent Signature: _____________________</p>
<p>Class Master: _________________________</p>
<p>Principal: ____________________________</p>";

// Output as PDF
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("report_card_{$student['name']}.pdf", ["Attachment" => false]);
