<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/schooldb.php';

// Access control
if (!isset($_SESSION['username']) || !in_array(strtolower($_SESSION['role']), ['admin', 'bursar'])) {
    header("Location: login.php");
    exit;
}

$role = strtolower($_SESSION['role']);
$error = '';
$success = '';

// Handle expense submission — only Bursar
if ($role === 'bursar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');

    if ($description === '' || $amount <= 0) {
        $error = "Please enter a valid description and amount greater than zero.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO expenses (date, description, amount) VALUES (:date, :description, :amount)");
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':amount', $amount);
        if ($stmt->execute()) {
            $success = "Expense recorded successfully.";
        } else {
            $error = "Failed to record expense.";
        }
    }
}

// Handle deletion — only Admin
if (isset($_GET['delete_id']) && $role === 'admin') {
    $delete_id = intval($_GET['delete_id']);

    $stmtSelect = $pdo->prepare("SELECT id, description FROM expenses WHERE id = :id");
    $stmtSelect->bindParam(':id', $delete_id);
    $stmtSelect->execute();
    $expense = $stmtSelect->fetch(PDO::FETCH_ASSOC);

    if ($expense) {
        $stmtDelete = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
        $stmtDelete->bindParam(':id', $delete_id);
        if ($stmtDelete->execute()) {
            $success = "Expense deleted successfully.";
        } else {
            $error = "Failed to delete expense.";
        }
    } else {
        $error = "Expense record not found for deletion.";
    }
}

// Filter expenses
$filter_start = $_GET['start_date'] ?? '';
$filter_end = $_GET['end_date'] ?? '';
$where = [];
$params = [];

if ($filter_start && $filter_end) {
    $where[] = "date BETWEEN :start AND :end";
    $params[':start'] = $filter_start;
    $params[':end'] = $filter_end;
} elseif ($filter_start) {
    $where[] = "date >= :start";
    $params[':start'] = $filter_start;
} elseif ($filter_end) {
    $where[] = "date <= :end";
    $params[':end'] = $filter_end;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
$stmt = $pdo->prepare("SELECT id, date, description, amount FROM expenses $whereClause ORDER BY date DESC");
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Record & View Expenses</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans min-h-screen p-6">

<h1 class="text-2xl font-bold mb-6">Expenses</h1>

<?php if ($role === 'bursar'): ?>
<section class="mb-6 bg-white p-4 rounded shadow">
<h2 class="text-lg font-semibold mb-2">Record New Expense</h2>
<?php if ($error): ?><p class="text-red-600 mb-2"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="text-green-600 mb-2"><?= htmlspecialchars($success) ?></p><?php endif; ?>

<form method="POST" class="space-y-2">
<input type="text" name="description" placeholder="Description" required class="border p-2 w-full rounded"/>
<input type="number" name="amount" placeholder="Amount (XAF)" step="0.01" min="0.01" required class="border p-2 w-full rounded"/>
<input type="date" name="date" value="<?= date('Y-m-d') ?>" class="border p-2 w-full rounded"/>
<button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Save Expense</button>
</form>
</section>
<?php endif; ?>

<section class="mb-6">
<h2 class="text-lg font-semibold mb-2">Filter Expenses</h2>
<form method="GET" class="flex gap-2 items-end">
<input type="date" name="start_date" value="<?= htmlspecialchars($filter_start) ?>" class="border p-2 rounded"/>
<input type="date" name="end_date" value="<?= htmlspecialchars($filter_end) ?>" class="border p-2 rounded"/>
<button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">Filter</button>
<a href="record_expense.php" class="text-indigo-600 ml-2">Reset</a>
</form>
</section>

<section class="bg-white p-4 rounded shadow">
<h2 class="text-lg font-semibold mb-2">Expense Records</h2>
<?php if (!$expenses): ?>
<p class="text-gray-600 italic">No expenses found.</p>
<?php else: ?>
<table class="w-full border-collapse border" id="expenseTable">
<thead>
<tr class="bg-gray-100">
<th class="border px-2 py-1 text-left">Date</th>
<th class="border px-2 py-1 text-left">Description</th>
<th class="border px-2 py-1 text-right">Amount</th>
<th class="border px-2 py-1 text-center">Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($expenses as $exp): ?>
<tr>
<td class="border px-2 py-1"><?= htmlspecialchars($exp['date']) ?></td>
<td class="border px-2 py-1"><?= htmlspecialchars($exp['description']) ?></td>
<td class="border px-2 py-1 text-right"><?= number_format($exp['amount'],2) ?></td>
<td class="border px-2 py-1 text-center">
<?php if ($role === 'admin'): ?>
<button class="delete-btn text-red-600 font-semibold" data-id="<?= $exp['id'] ?>" data-description="<?= htmlspecialchars($exp['description'], ENT_QUOTES) ?>">Delete</button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<tr class="font-bold">
<td colspan="2" class="text-right border px-2 py-1">Total:</td>
<td class="border px-2 py-1 text-right"><?= number_format(array_sum(array_column($expenses,'amount')),2) ?></td>
<td class="border px-2 py-1"></td>
</tr>
</tbody>
</table>
<?php endif; ?>
</section>

<a href="dashboard.php" class="text-indigo-600 mt-4 inline-block">&larr; Back to Dashboard</a>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
<div class="bg-white rounded shadow-lg max-w-md w-full p-6">
<h3 class="text-xl font-semibold mb-4">Confirm Delete</h3>
<p id="modalText" class="mb-6"></p>
<div class="flex justify-end gap-4">
<button id="cancelDelete" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400 transition">Cancel</button>
<a href="#" id="confirmDelete" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Delete</a>
</div>
</div>
</div>

<script>
// Delete modal
document.querySelectorAll('.delete-btn').forEach(btn => {
btn.addEventListener('click', () => {
const id = btn.dataset.id;
const description = btn.dataset.description;
const modal = document.getElementById('deleteModal');
const modalText = document.getElementById('modalText');
const confirmDelete = document.getElementById('confirmDelete');

modalText.textContent = `Are you sure you want to delete the expense: "${description}"?`;
confirmDelete.href = `record_expense.php?delete_id=${id}`;
modal.classList.remove('hidden');
});
});

document.getElementById('cancelDelete').addEventListener('click', () => {
document.getElementById('deleteModal').classList.add('hidden');
});
</script>

</body>
</html>
