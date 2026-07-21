<?php
session_start();

require_once __DIR__ . '/schooldb.php';

if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin','bursar'])) {
    die("Access denied!");
}

$success = '';
$error = '';

// Fetch classes
$classes = [];

try {

    $stmt = $pdo->query("
        SELECT id,class_name
        FROM classes
        ORDER BY class_name ASC
    ");

    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e){

    $error = "Could not fetch classes: ".$e->getMessage();

}

// Search existing student
$searchTerm = trim($_GET['search'] ?? '');
$foundStudent = null;
$lastPayments = [];
if ($searchTerm !== '') {
    try {
        $stmt =$pdo->prepare("SELECT s.*, c.class_name 
                                FROM students s 
                                LEFT JOIN classes c ON s.class_id = c.id
                                WHERE s.id = :id OR s.name LIKE :name
                                LIMIT 1");
        $stmt->execute([
            ':id' => is_numeric($searchTerm) ? intval($searchTerm) : 0,
            ':name' => "%$searchTerm%"
        ]);
        $foundStudent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($foundStudent) {
            // Fetch last fee payments
            $stmt = $pdo->prepare("SELECT narration, amount, term, date_paid FROM fees WHERE student_id = :id ORDER BY date_paid DESC LIMIT 5");
            $stmt->execute([':id' => $foundStudent['id']]);
            $lastPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "❌ Search failed: " . $e->getMessage();
    }
}

// Handle registration or fee submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? null;
    $registration_fee = floatval($_POST['registration_fee'] ?? 0);
    $school_fee = floatval($_POST['school_fee'] ?? 0);
    $term = $_POST['term'] ?? '1st Term';
    $date_paid = date('Y-m-d');

    try {
       $pdo->beginTransaction(); // Start transaction

        if ($student_id) {
            // Existing student: insert fees individually
            if ($registration_fee > 0) {
                $stmt = $pdo->prepare("INSERT INTO fees(student_id, amount, narration, term, date_paid) VALUES(:id,:amount,'Registration Fee',:term,:date_paid)");
                $stmt->execute([':id'=>$student_id, ':amount'=>$registration_fee, ':term'=>$term, ':date_paid'=>$date_paid]);
            }
            if ($school_fee > 0) {
                $stmt = $pdo->prepare("INSERT INTO fees(student_id, amount, narration, term, date_paid) VALUES(:id,:amount,'School Fee',:term,:date_paid)");
                $stmt->execute([':id'=>$student_id, ':amount'=>$school_fee, ':term'=>$term, ':date_paid'=>$date_paid]);
            }
            $success = "✅ Fees recorded for existing student.";
        } else {
            // New student
            $name = trim($_POST['name'] ?? '');
            $gender = strtolower($_POST['gender'] ?? '');
            $class_id = $_POST['class_id'] ?? '';
            $guardian = trim($_POST['guardian'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $age = intval($_POST['age'] ?? 0);

            if ($name && $gender && $class_id && $contact && $age > 0) {
                $stmt = $pdo->prepare("INSERT INTO students(name, gender, class_id, guardian, parent_contact, enrollment_date, age)
                                        VALUES(:name,:gender,:class_id,:guardian,:contact,:enrollment_date,:age)");
                $stmt->execute([
                    ':name'=>$name, ':gender'=>$gender, ':class_id'=>$class_id, 
                    ':guardian'=>$guardian, ':contact'=>$contact, ':enrollment_date'=>date('Y-m-d'), ':age'=>$age
                ]);
                $new_id = $pdo->lastInsertId();

                // Insert fees if any
                if ($registration_fee > 0) {
                    $stmt = $pdo->prepare("INSERT INTO fees(student_id, amount, narration, term, date_paid) VALUES(:id,:amount,'Registration Fee',:term,:date_paid)");
                    $stmt->execute([':id'=>$new_id, ':amount'=>$registration_fee, ':term'=>$term, ':date_paid'=>$date_paid]);
                }
                if ($school_fee > 0) {
                    $stmt = $pdo->prepare("INSERT INTO fees(student_id, amount, narration, term, date_paid) VALUES(:id,:amount,'School Fee',:term,:date_paid)");
                    $stmt->execute([':id'=>$new_id, ':amount'=>$school_fee, ':term'=>$term, ':date_paid'=>$date_paid]);
                }

                $success = "🎉 Student registered and fees recorded successfully.";
            } else {
                $error = "⚠️ Please fill all required fields for new student.";
            }
        }

        $pdo->commit(); // Commit transaction
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "❌ Operation failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register Student / Fees | Synergia</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-sky-50 p-6 font-sans">
<div class="max-w-md mx-auto">

<h2 class="text-xl font-bold mb-4 text-center">Register New Student / Pay Fee</h2>

<?php if($success): ?>
    <div class="bg-green-100 text-green-800 p-3 rounded mb-4 text-center"><?= htmlspecialchars($success) ?></div>
<?php elseif($error): ?>
    <div class="bg-red-100 text-red-800 p-3 rounded mb-4 text-center"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Search existing student -->
<form method="GET" class="mb-4">
    <input type="text" name="search" placeholder="Search student by ID or Name" value="<?= htmlspecialchars($searchTerm) ?>" class="w-full p-2 border rounded">
    <button type="submit" class="mt-2 w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">Search</button>
</form>

<form method="POST" class="bg-white p-6 rounded shadow space-y-3" autocomplete="off">
    <?php if($foundStudent): ?>
        <div class="bg-gray-100 p-3 rounded text-gray-800">
            <p><strong>Name:</strong> <?= htmlspecialchars($foundStudent['name']) ?></p>
            <p><strong>Gender:</strong> <?= htmlspecialchars($foundStudent['gender']) ?></p>
            <p><strong>Class:</strong> <?= htmlspecialchars($foundStudent['class_name'] ?? 'N/A') ?></p>
            <?php if(!empty($lastPayments)): ?>
                <p class="mt-2 font-semibold">Last Payments:</p>
                <ul class="list-disc list-inside text-sm">
                    <?php foreach($lastPayments as $p): ?>
                        <li><?= htmlspecialchars($p['narration']) ?>: <?= number_format($p['amount'],2) ?> (<?= htmlspecialchars($p['term']) ?> on <?= htmlspecialchars($p['date_paid']) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <input type="hidden" name="student_id" value="<?= $foundStudent['id'] ?>">
        <input type="number" name="registration_fee" min="0" step="0.01" placeholder="Registration Fee" class="w-full p-2 border rounded">
        <input type="number" name="school_fee" min="0" step="0.01" placeholder="School Fee Paid" class="w-full p-2 border rounded">
    <?php else: ?>
        <input type="text" name="name" placeholder="Student Name" required class="w-full p-2 border rounded">
        <select name="gender" required class="w-full p-2 border rounded">
            <option value="">Select Gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </select>
        <select name="class_id" required class="w-full p-2 border rounded">
            <option value="">Select Class</option>
            <?php foreach($classes as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="guardian" placeholder="Guardian Name (optional)" class="w-full p-2 border rounded">
        <input type="text" name="contact" placeholder="Parent Contact" required class="w-full p-2 border rounded">
        <input type="number" name="age" min="3" max="25" placeholder="Age" required class="w-full p-2 border rounded">
        <input type="number" name="registration_fee" placeholder="Registration Fee" min="0" step="0.01" class="w-full p-2 border rounded">
        <input type="number" name="school_fee" placeholder="School Fee Paid" min="0" step="0.01" class="w-full p-2 border rounded">
    <?php endif; ?>

    <input type="hidden" name="term" value="1st Term">
    <button type="submit" class="w-full bg-sky-600 text-white py-2 rounded hover:bg-sky-700">Submit</button>
</form>

<div class="mt-4 text-center">
    <a href="dashboard.php" class="text-indigo-600 hover:underline">&larr; Back to Dashboard</a>
</div>

</div>
</body>
</html>
