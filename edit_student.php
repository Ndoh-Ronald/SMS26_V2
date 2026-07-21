<?php
session_start();
require_once __DIR__ . '/schooldb.php'; // PDO connection

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Get the student ID from GET parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid student ID.");
}

$student_id = intval($_GET['id']);
$errors = [];
$success = false;

// Fetch existing student data using PDO
try {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->execute([':id' => $student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        die("Student not found.");
    }
} catch (PDOException $e) {
    die("Error fetching student: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $class = $_POST['class'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $age = intval($_POST['age'] ?? 0);
    $enrollment_date = $_POST['enrollment_date'] ?? '';

    // Validate inputs
    if ($name === '') $errors[] = "Name is required.";
    if (!in_array($class, array_map(fn($i) => "GRADE $i", range(1,12)))) $errors[] = "Invalid class selected.";
    if ($gender !== 'Male' && $gender !== 'Female') $errors[] = "Gender must be Male or Female.";
    if ($age < 1 || $age > 100) $errors[] = "Age must be between 1 and 100.";
    if ($enrollment_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $enrollment_date)) $errors[] = "Invalid enrollment date.";

    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE students 
                SET name = :name, class = :class, gender = :gender, age = :age, enrollment_date = :enrollment_date
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':name' => $name,
                ':class' => $class,
                ':gender' => $gender,
                ':age' => $age,
                ':enrollment_date' => $enrollment_date,
                ':id' => $student_id
            ]);
            $success = true;

            // Refresh student data after update
            $student['name'] = $name;
            $student['class'] = $class;
            $student['gender'] = $gender;
            $student['age'] = $age;
            $student['enrollment_date'] = $enrollment_date;

        } catch (PDOException $e) {
            $errors[] = "Failed to update student: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Student</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6 font-sans max-w-xl mx-auto">
<h1 class="text-2xl font-bold text-indigo-700 mb-6">Edit Student #<?= htmlspecialchars($student_id) ?></h1>

<?php if ($success): ?>
    <div class="mb-4 p-4 bg-green-200 text-green-800 rounded">
        Student updated successfully.
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="mb-4 p-4 bg-red-200 text-red-800 rounded">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST" class="bg-white p-6 rounded shadow space-y-4">
    <div>
        <label class="block mb-1 font-semibold" for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($student['name']) ?>" required
               class="w-full px-3 py-2 border rounded" />
    </div>

    <div>
        <label class="block mb-1 font-semibold" for="class">Class</label>
        <select id="class" name="class" required class="w-full px-3 py-2 border rounded">
            <option value="">Select class</option>
            <?php
            for ($i = 1; $i <= 12; $i++):
                $grade = "GRADE $i";
                $selected = ($student['class'] === $grade) ? 'selected' : '';
            ?>
                <option value="<?= $grade ?>" <?= $selected ?>><?= $grade ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div>
        <label class="block mb-1 font-semibold" for="gender">Gender</label>
        <select id="gender" name="gender" required class="w-full px-3 py-2 border rounded">
            <option value="">Select gender</option>
            <option value="Male" <?= $student['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $student['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
    </div>

    <div>
        <label class="block mb-1 font-semibold" for="age">Age</label>
        <input type="number" id="age" name="age" min="1" max="100" value="<?= htmlspecialchars($student['age']) ?>" required
               class="w-full px-3 py-2 border rounded" />
    </div>

    <div>
        <label class="block mb-1 font-semibold" for="enrollment_date">Enrollment Date</label>
        <input type="date" id="enrollment_date" name="enrollment_date" value="<?= htmlspecialchars($student['enrollment_date']) ?>" required
               class="w-full px-3 py-2 border rounded" />
    </div>

    <div class="flex justify-between items-center">
        <a href="view_students.php" class="text-indigo-600 hover:underline">← Back to Students</a>
        <button type="submit" class="bg-indigo-600 text-white px-5 py-2 rounded hover:bg-indigo-700 transition">Update Student</button>
    </div>
</form>
</body>
</html>
