<?php
session_start();
require_once __DIR__ . '/schooldb.php';
require_once __DIR__ . '/../vendor/fpdf/fpdf.php'; // path to fpdf.php

if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin','bursar'])) {
    exit("Access denied!");
}

$classFilter = intval($_GET['class'] ?? 0);

// Fetch students (same query)
$where = "WHERE 1=1";
if($classFilter > 0){
    $where .= " AND s.class_id = $classFilter";
}

$sql = "
    SELECT 
        s.name AS student_name,
        c.class_name,
        IFNULL(c.standard_fee,0) AS expected_fee,
        COALESCE(SUM(f.amount),0) AS total_paid,
        GROUP_CONCAT(f.narration SEPARATOR ', ') AS narrations
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN fees f ON f.student_id = s.id
    $where
    GROUP BY s.id, s.name, c.class_name, c.standard_fee
    ORDER BY c.class_name, s.name
";

$result = $pdo->query($sql);
if (!$result) die("Query failed: ".$pdo->error);

// PDF generation
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial','B',12);

// Header
$pdf->Cell(0,10,"Student Fee Status / État des frais des élèves",0,1,'C');
$pdf->Ln(5);

// Table header
$pdf->SetFont('Arial','B',10);
$header = ['Student', 'Class', 'Expected', 'Paid', 'Balance', 'Narrations'];
foreach($header as $col){
    $pdf->Cell(30,7,$col,1);
}
$pdf->Ln();

// Table data
$pdf->SetFont('Arial','',10);
while($row = $result->fetch_assoc()){
    $balance = $row['expected_fee'] - $row['total_paid'];
    $pdf->Cell(30,6,$row['student_name'],1);
    $pdf->Cell(30,6,$row['class_name'],1);
    $pdf->Cell(30,6,number_format($row['expected_fee'],2),1);
    $pdf->Cell(30,6,number_format($row['total_paid'],2),1);
    $pdf->Cell(30,6,number_format($balance,2),1);
    $pdf->Cell(40,6,$row['narrations'],1);
    $pdf->Ln();
}

$pdf->Output('D', 'student_fees.pdf');
exit;
