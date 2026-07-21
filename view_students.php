<?php
session_start();
require_once __DIR__ . '/schooldb.php'; // Make sure this path is correct

// Only logged-in users
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Admin-only delete
if (isset($_GET['delete_id']) && strtolower($_SESSION['role']) === 'admin') {
    $delete_id = intval($_GET['delete_id']);
    if ($delete_id > 0) {
        // Delete fees first
        $stmt = $pdo->prepare("DELETE FROM fees WHERE student_id = :id");
        $stmt->execute([':id' => $delete_id]);

        // Delete student
        $stmt2 = $pdo->prepare("DELETE FROM students WHERE id = :id");
        $stmt2->execute([':id' => $delete_id]);

        header("Location: view_students.php?msg=deleted");
        exit;
    }
}

// Filters
$name = $_GET['name'] ?? '';
$class = $_GET['class'] ?? '';
$gender = $_GET['gender'] ?? '';
$age = $_GET['age'] ?? '';
$from = $_GET['from_date'] ?? '';
$to = $_GET['to_date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

// Build dynamic WHERE clause
if ($name !== '') {
    $where[] = "s.name LIKE :name";
    $params[':name'] = "%$name%";
}
if ($class !== '') {
    $where[] = "c.class_name = :class";
    $params[':class'] = $class;
}
if ($gender !== '') {
    $where[] = "s.gender = :gender";
    $params[':gender'] = $gender;
}
if ($age !== '') {
    $where[] = "s.age = :age";
    $params[':age'] = intval($age);
}
if ($from !== '') {
    $where[] = "s.enrollment_date >= :from";
    $params[':from'] = $from;
}
if ($to !== '') {
    $where[] = "s.enrollment_date <= :to";
    $params[':to'] = $to;
}

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

// Count total students
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM students s LEFT JOIN classes c ON s.class_id = c.id $whereSQL");
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

// Fetch students with pagination
$sql = "SELECT s.*, c.class_name 
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        $whereSQL
        ORDER BY s.id ASC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind parameters
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch classes for dropdown
$classes = $pdo->query("SELECT class_name FROM classes ORDER BY class_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Prepare filter query string for export links
$filterQuery = http_build_query(array_filter([
    'name' => $name,
    'class' => $class,
    'gender' => $gender,
    'age' => $age,
    'from_date' => $from,
    'to_date' => $to
]));

// Export
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $exportSQL = "SELECT s.*, c.class_name 
                  FROM students s
                  LEFT JOIN classes c ON s.class_id = c.id
                  $whereSQL
                  ORDER BY s.id ASC";
    $exportStmt = $pdo->prepare($exportSQL);
    foreach ($params as $key => $val) {
        $exportStmt->bindValue($key, $val);
    }
    $exportStmt->execute();
    $exportStudents = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($exportType === 'excel') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=students.xls");
        echo "<table border='1'><tr><th>#</th><th>Name</th><th>Class</th><th>Gender</th><th>Age</th><th>Enrollment Date</th></tr>";
        $sn_export = 1;
        foreach ($exportStudents as $s) {
            echo "<tr><td>{$sn_export}</td><td>{$s['name']}</td><td>{$s['class_name']}</td><td>{$s['gender']}</td><td>{$s['age']}</td><td>{$s['enrollment_date']}</td></tr>";
            $sn_export++;
        }
        echo "</table>";
        exit;
    } elseif ($exportType === 'pdf') {
        header("Content-Type: text/html");
        header("Content-Disposition: attachment; filename=students.pdf");
        echo "<h2>Students Report</h2><table border='1'><tr><th>#</th><th>Name</th><th>Class</th><th>Gender</th><th>Age</th><th>Enrollment Date</th></tr>";
        $sn_export = 1;
        foreach ($exportStudents as $s) {
            echo "<tr><td>{$sn_export}</td><td>{$s['name']}</td><td>{$s['class_name']}</td><td>{$s['gender']}</td><td>{$s['age']}</td><td>{$s['enrollment_date']}</td></tr>";
            $sn_export++;
        }
        echo "</table>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Students</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<h1 class="text-2xl font-bold text-indigo-700 mb-4">Students</h1>

<?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <p class="bg-green-200 text-green-800 p-2 rounded mb-4">Student deleted successfully!</p>
<?php endif; ?>

<form method="GET" class="flex flex-wrap gap-4 items-end bg-white p-4 rounded shadow mb-6">
    <div>
        <label class="text-sm">Name</label>
        <input name="name" value="<?= htmlspecialchars($name) ?>" class="px-3 py-2 border rounded" />
    </div>
    <div>
        <label class="text-sm">Class</label>
        <select name="class" class="px-3 py-2 border rounded">
            <option value="">All</option>
            <?php foreach ($classes as $c): 
                $selected = $class === $c['class_name'] ? 'selected' : '';
            ?>
            <option value="<?= htmlspecialchars($c['class_name']) ?>" <?= $selected ?>><?= htmlspecialchars($c['class_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="text-sm">Gender</label>
        <select name="gender" class="px-3 py-2 border rounded">
            <option value="">All</option>
            <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
    </div>
    <div>
        <label class="text-sm">Age</label>
        <input type="number" name="age" value="<?= htmlspecialchars($age) ?>" class="px-3 py-2 border rounded" />
    </div>
    <div>
        <label class="text-sm">From</label>
        <input type="date" name="from_date" value="<?= htmlspecialchars($from) ?>" class="px-3 py-2 border rounded" />
    </div>
    <div>
        <label class="text-sm">To</label>
        <input type="date" name="to_date" value="<?= htmlspecialchars($to) ?>" class="px-3 py-2 border rounded" />
    </div>
    <button class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Filter</button>
    <a href="?export=pdf&<?= $filterQuery ?>" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Export PDF</a>
    <a href="?export=excel&<?= $filterQuery ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">Export Excel</a>
</form>

<p class="mb-2 text-gray-700 font-semibold">Total Students: <?= $total ?></p>

<div class="overflow-x-auto bg-white rounded shadow">
<table class="min-w-full text-sm">
    <thead class="bg-indigo-200 text-indigo-800">
        <tr>
            <th class="p-3 text-left">#</th>
            <th class="p-3 text-left">Name</th>
            <th class="p-3 text-left">Class</th>
            <th class="p-3 text-left">Gender</th>
            <th class="p-3 text-left">Age</th>
            <th class="p-3 text-left">Enrollment Date</th>
            <th class="p-3 text-left">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php $sn = ($page - 1) * $limit + 1; ?>
        <?php foreach ($students as $s): ?>
        <tr class="border-b hover:bg-gray-50">
            <td class="p-3"><?= $sn ?></td>
            <td class="p-3"><?= htmlspecialchars($s['name']) ?></td>
            <td class="p-3"><?= htmlspecialchars($s['class_name']) ?></td>
            <td class="p-3"><?= htmlspecialchars($s['gender']) ?></td>
            <td class="p-3"><?= $s['age'] ?></td>
            <td class="p-3"><?= $s['enrollment_date'] ?></td>
            <td class="p-3 space-x-2">
                <a href="edit_student.php?id=<?= $s['id'] ?>" class="text-blue-600 hover:underline">Edit</a>
                <?php if (strtolower($_SESSION['role']) === 'admin'): ?>
                <a href="?delete_id=<?= $s['id'] ?>" onclick="return confirm('Are you sure?')" class="text-red-600 hover:underline">Delete</a>
                <?php endif; ?>
                <a href="print_fee_receipt.php?student_id=<?= $s['id'] ?>" target="_blank" class="text-green-600 hover:underline">Print Receipt</a>
            </td>
        </tr>
        <?php $sn++; endforeach; ?>
    </tbody>
</table>
</div>

<div class="mt-4 flex justify-center gap-2">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>&<?= $filterQuery ?>" class="px-3 py-1 border <?= $i == $page ? 'bg-indigo-600 text-white' : 'bg-white text-indigo-700' ?> rounded"><?= $i ?></a>
    <?php endfor; ?>
</div>

<a href="dashboard.php" class="block mt-6 text-indigo-600 hover:underline">&larr; Back to Dashboard</a>

</body>
</html>
