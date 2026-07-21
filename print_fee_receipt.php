<?php
session_start();
require_once __DIR__ . '/schooldb.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$student_id = intval($_GET['student_id'] ?? 0);

try {
    if ($student_id <= 0) throw new Exception("Invalid student ID.");

    // Fetch student info including expected fee
    $stmt = $pdo->prepare("
        SELECT s.name, c.class_name, COALESCE(c.standard_fee,0) AS expected_fee
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        WHERE s.id = :id
    ");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student not found.");
    }

    // Fetch fee records
    $stmtFees = $pdo->prepare("
        SELECT narration, amount, 
               COALESCE(date_paid, :now) AS date_paid
        FROM fees 
        WHERE student_id = :id 
        ORDER BY id ASC
    ");
    $stmtFees->execute([
        ':id' => $student_id,
        ':now' => date('Y-m-d H:i:s')
    ]);
    $fees_data = $stmtFees->fetchAll(PDO::FETCH_ASSOC);

    $total_paid = array_sum(array_column($fees_data, 'amount'));
    $balance_left = $student['expected_fee'] - $total_paid;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>School Fee Receipt</title>
<style>
body { font-family: Arial, sans-serif; padding: 40px; background: #f4f4f4; }
.receipt { max-width: 700px; margin: auto; border: 1px solid #ccc; padding: 30px; background: #fff; }
h2, h4 { text-align: center; margin: 0; }
h3 { margin-top: 10px; text-align: center; font-weight: normal; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
.total { font-weight: bold; }
.balance { font-weight: bold; color: red; }
.print-btn { margin-top: 20px; text-align: center; }
</style>
</head>
<body>

<div class="receipt">
<h2>Excellent Bilingual School Complex</h2>
<h3>Excellence Complex Scolaire Bilingue</h3>
<h4>Official / Reçu Officiel des Frais Scolaires</h4>

<p>
<strong>Student Name / Nom de l'élève:</strong> <?= htmlspecialchars($student['name']) ?><br>
<strong>Class / Classe:</strong> <?= htmlspecialchars($student['class_name']) ?><br>
<strong>Student ID / ID Élève:</strong> <?= $student_id ?><br>
<strong>Date Issued / Date d'émission:</strong> <?= date('d M Y') ?><br>
<strong>Printed By / Imprimé Par:</strong> <?= htmlspecialchars($_SESSION['username']) ?>
</p>

<table>
<thead>
<tr>
<th>Narration</th>
<th>Amount (FCFA) / Montant</th>
<th>Date Paid / Date Payée</th>
</tr>
</thead>
<tbody>
<?php foreach ($fees_data as $fee): ?>
<tr>
<td><?= htmlspecialchars($fee['narration']) ?></td>
<td><?= number_format($fee['amount']) ?></td>
<td><?= htmlspecialchars($fee['date_paid']) ?></td>
</tr>
<?php endforeach; ?>
<tr class="total">
<td>Total Paid / Total Payé</td>
<td colspan="2"><?= number_format($total_paid) ?> FCFA</td>
</tr>
<tr class="balance">
<td>Balance Left / Solde Restant</td>
<td colspan="2"><?= number_format($balance_left) ?> FCFA</td>
</tr>
</tbody>
</table>

<div class="print-btn">
<button onclick="window.print()">🖨️ Print / Imprimer</button>
</div>
</div>

</body>
</html>
