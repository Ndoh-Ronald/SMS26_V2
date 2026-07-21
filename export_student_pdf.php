<?php
require_once __DIR__ . '/schooldb.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../vendor/autoload.php';

// Setup Dompdf options
//$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);

//$dompdf = new Dompdf($options);

// Fetch student data
$query = "SELECT * FROM students ORDER BY name ASC";
$result = $pdo->query($query);

// Start building the HTML
$html = '<h2 style="text-align:center;">Student List</h2>';
$html .= '<table border="1" cellspacing="0" cellpadding="6" width="100%">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Class</th>
                    <th>Gender</th>
                    <th>Date of Birth</th>
                    <th>Contact</th>
                </tr>
            </thead>
            <tbody>';

while ($row = $result->fetch_assoc()) {
    $html .= '<tr>
                <td>' . htmlspecialchars($row['name']) . '</td>
                <td>' . htmlspecialchars($row['class']) . '</td>
                <td>' . htmlspecialchars($row['gender']) . '</td>
                <td>' . htmlspecialchars($row['dob']) . '</td>
                <td>' . htmlspecialchars($row['contact']) . '</td>
              </tr>';
}

$html .= '</tbody></table>';

// Load HTML into Dompdf
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("student_list.pdf", ["Attachment" => false]); // false = view in browser
exit;
