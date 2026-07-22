<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/schooldb.php';

/*==========================================================
    AUTHENTICATION
==========================================================*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role     = strtolower($_SESSION['role']);

/*==========================================================
    AUTHORIZATION
==========================================================*/

$allowed_roles = ['admin', 'bursar'];

if (!in_array($role, $allowed_roles)) {
    die("Access Denied! You do not have permission to access this page.");
}

/*==========================================================
    INITIALIZE VARIABLES
==========================================================*/

$success = "";
$error = "";

$receipt_no = "";
$amount = "";
$received_from = "";
$payment_method = "Cash";
$reference_no = "";
$academic_year_id = "";
$term_id = "";
$income_category_id = "";
$income_date = date('Y-m-d');
$description = "";

/*==========================================================
    LOAD ACADEMIC YEARS
==========================================================*/

try {

    $stmt = $pdo->query("
        SELECT *
        FROM academic_years
        ORDER BY id DESC
    ");

    $academic_years = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $academic_years = [];

}

/*==========================================================
    LOAD TERMS
==========================================================*/

try {

    $stmt = $pdo->query("
        SELECT *
        FROM terms
        ORDER BY id ASC
    ");

    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $terms = [];

}

/*==========================================================
    LOAD INCOME CATEGORIES
==========================================================*/

try {

    $stmt = $pdo->query("
        SELECT *
        FROM income_categories
        WHERE active = 1
        ORDER BY category_name ASC
    ");

    $income_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $income_categories = [];

}

/*==========================================================
    GENERATE RECEIPT NUMBER
==========================================================*/

try {

    $stmt = $pdo->query("
        SELECT id
        FROM income
        ORDER BY id DESC
        LIMIT 1
    ");

    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    $next = $last ? ($last['id'] + 1) : 1;

    $receipt_no = "INC-" . date('Y') . "-" . str_pad($next, 6, "0", STR_PAD_LEFT);

} catch (PDOException $e) {

    $receipt_no = "INC-" . date('Y') . "-000001";

}

/*==========================================================
    SAVE RECORD
==========================================================*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $receipt_no = trim($_POST['receipt_no']);
    $income_category_id = intval($_POST['income_category_id']);
    $amount = floatval($_POST['amount']);
    $received_from = trim($_POST['received_from']);
    $payment_method = trim($_POST['payment_method']);
        $reference_no = trim($_POST['reference_no']);
    $academic_year_id = intval($_POST['academic_year_id']);
    $term_id = intval($_POST['term_id']);
    $income_date = $_POST['income_date'];
    $description = trim($_POST['description']);

    /*==========================================================
        VALIDATION
    ==========================================================*/

    if ($income_category_id <= 0) {

        $error = "Please select an income category.";

    } elseif ($amount <= 0) {

        $error = "Amount must be greater than zero.";

    } elseif (empty($received_from)) {

        $error = "Please enter who the money was received from.";

    } elseif (empty($academic_year_id)) {

        $error = "Please select an academic year.";

    } elseif (empty($term_id)) {

        $error = "Please select a term.";

    } elseif (empty($income_date)) {

        $error = "Please select the income date.";

    }

    /*==========================================================
        SAVE TO DATABASE
    ==========================================================*/

    if ($error == "") {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO income
                (
                    receipt_no,
                    income_category_id,
                    amount,
                    received_from,
                    payment_method,
                    reference_no,
                    academic_year_id,
                    term_id,
                    income_date,
                    description,
                    received_by
                )

                VALUES
                (
                    :receipt_no,
                    :income_category_id,
                    :amount,
                    :received_from,
                    :payment_method,
                    :reference_no,
                    :academic_year_id,
                    :term_id,
                    :income_date,
                    :description,
                    :received_by
                )
            ");

            $stmt->execute([

                ':receipt_no'         => $receipt_no,
                ':income_category_id' => $income_category_id,
                ':amount'             => $amount,
                ':received_from'      => $received_from,
                ':payment_method'     => $payment_method,
                ':reference_no'       => $reference_no,
                ':academic_year_id'   => $academic_year_id,
                ':term_id'            => $term_id,
                ':income_date'        => $income_date,
                ':description'        => $description,
                ':received_by'        => $user_id

            ]);

            $income_id = $pdo->lastInsertId();

            $_SESSION['success_message'] =
                "Income recorded successfully.";

            header("Location: print_income_receipt.php?id=" . $income_id);
            exit();

        } catch (PDOException $e) {

            $error = "Database Error: " . $e->getMessage();

        }

    }

}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Record Other Income | SMS26</title>

<script src="https://cdn.tailwindcss.com"></script>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
      rel="stylesheet">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body class="bg-gray-100 font-[Inter]">

<div class="max-w-7xl mx-auto p-6">

    <div class="bg-white rounded-xl shadow-lg p-6">

        <div class="flex justify-between items-center mb-6">
            <div class="mb-6 flex justify-between items-center">

    <a href="dashboard.php"
       class="inline-flex items-center bg-gray-600 hover:bg-gray-700 text-white px-5 py-2 rounded-lg transition">

        <i class="fas fa-arrow-left mr-2"></i>

        Return to Dashboard

    </a>

</div>

            <div>

                <h2 class="text-3xl font-bold text-blue-700">

                    <i class="fas fa-hand-holding-dollar mr-2"></i>

                    Record Other Income

                </h2>

                <p class="text-gray-500">

                    Record all non-fee income received by the school.

                </p>

            </div>

            <a href="income_report.php"
               class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg">

                <i class="fas fa-chart-line mr-2"></i>

                Income Report

            </a>

        </div>
                <?php if (isset($_SESSION['success_message'])): ?>

            <div class="bg-green-100 border border-green-300 text-green-700 p-4 rounded-lg mb-6">

                <?= htmlspecialchars($_SESSION['success_message']); ?>

            </div>

        <?php unset($_SESSION['success_message']); endif; ?>


        <?php if (!empty($error)): ?>

            <div class="bg-red-100 border border-red-300 text-red-700 p-4 rounded-lg mb-6">

                <?= htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <form method="POST" autocomplete="off">

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <!-- Receipt Number -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Receipt Number

                    </label>

                    <input
                        type="text"
                        name="receipt_no"
                        readonly
                        value="<?= htmlspecialchars($receipt_no); ?>"
                        class="w-full border rounded-lg bg-gray-100 p-3">

                </div>


                <!-- Income Category -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Income Category

                    </label>

                    <select
                        name="income_category_id"
                        required
                        class="w-full border rounded-lg p-3">

                        <option value="">Select Category</option>

                        <?php foreach ($income_categories as $category): ?>

                            <option
                                value="<?= $category['id']; ?>"

                                <?= ($income_category_id == $category['id']) ? 'selected' : ''; ?>

                            >

                                <?= htmlspecialchars($category['category_name']); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Amount -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Amount (FCFA)

                    </label>

                    <input
                        type="number"
                        name="amount"
                        min="1"
                        step="0.01"
                        required
                        value="<?= htmlspecialchars($amount); ?>"
                        class="w-full border rounded-lg p-3">

                </div>


                <!-- Received From -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Received From

                    </label>

                    <input
                        type="text"
                        name="received_from"
                        required
                        value="<?= htmlspecialchars($received_from); ?>"
                        class="w-full border rounded-lg p-3">

                </div>


                <!-- Payment Method -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Payment Method

                    </label>

                    <select
                        name="payment_method"
                        required
                        class="w-full border rounded-lg p-3">

                        <?php

                        $methods = [

                            'Cash',
                            'Bank Transfer',
                            'Mobile Money',
                            'Cheque'

                        ];

                        foreach ($methods as $method):

                        ?>

                            <option

                                value="<?= $method; ?>"

                                <?= ($payment_method == $method) ? 'selected' : ''; ?>

                            >

                                <?= $method; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Reference Number -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Reference Number

                    </label>

                    <input
                        type="text"
                        name="reference_no"
                        value="<?= htmlspecialchars($reference_no); ?>"
                        class="w-full border rounded-lg p-3">

                </div>


                <!-- Academic Year -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Academic Year

                    </label>

                    <select
                        name="academic_year_id"
                        required
                        class="w-full border rounded-lg p-3">

                        <option value="">Select Academic Year</option>

                        <?php foreach ($academic_years as $year): ?>

                            <option

                                value="<?= $year['id']; ?>"

                                <?= ($academic_year_id == $year['id']) ? 'selected' : ''; ?>

                            >

                                <?= htmlspecialchars($year['academic_year']); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Term -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Term

                    </label>

                    <select
                        name="term_id"
                        required
                        class="w-full border rounded-lg p-3">

                        <option value="">Select Term</option>

                        <?php foreach ($terms as $term): ?>

                            <option

                                value="<?= $term['id']; ?>"

                                <?= ($term_id == $term['id']) ? 'selected' : ''; ?>

                            >

                                <?= htmlspecialchars($term['term_name']); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>
                                <!-- Income Date -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Income Date

                    </label>

                    <input
                        type="date"
                        name="income_date"
                        required
                        value="<?= htmlspecialchars($income_date); ?>"
                        class="w-full border rounded-lg p-3">

                </div>


                <!-- Recorded By -->

                <div>

                    <label class="block text-sm font-semibold mb-2">

                        Recorded By

                    </label>

                    <input
                        type="text"
                        readonly
                        value="<?= htmlspecialchars($username); ?>"
                        class="w-full border rounded-lg bg-gray-100 p-3">

                </div>

            </div>


            <!-- Description -->

            <div class="mt-6">

                <label class="block text-sm font-semibold mb-2">

                    Description

                </label>

                <textarea
                    name="description"
                    rows="5"
                    class="w-full border rounded-lg p-3"
                    placeholder="Optional description..."><?= htmlspecialchars($description); ?></textarea>

            </div>


            <!-- Transaction Summary -->

            <div class="mt-8">

                <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">

                    <h3 class="text-lg font-bold text-blue-700 mb-4">

                        Transaction Summary

                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">

                        <div>

                            <p class="text-gray-500 text-sm">

                                Receipt Number

                            </p>

                            <p class="font-semibold">

                                <?= htmlspecialchars($receipt_no); ?>

                            </p>

                        </div>

                        <div>

                            <p class="text-gray-500 text-sm">

                                Recorded By

                            </p>

                            <p class="font-semibold">

                                <?= htmlspecialchars($username); ?>

                            </p>

                        </div>

                        <div>

                            <p class="text-gray-500 text-sm">

                                Date

                            </p>

                            <p class="font-semibold">

                                <?= date('d M Y'); ?>

                            </p>

                        </div>

                        <div>

                            <p class="text-gray-500 text-sm">

                                Status

                            </p>

                            <span class="inline-block px-3 py-1 bg-green-100 text-green-700 rounded-full font-semibold">

                                Ready to Save

                            </span>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Action Buttons -->

            <div class="mt-8 flex justify-end gap-4">

                <button
                    type="reset"
                    class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition">

                    <i class="fas fa-eraser mr-2"></i>

                    Clear Form

                </button>

                <button
                    type="submit"
                    onclick="return confirm('Are you sure you want to record this income?');"
                    class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg transition">

                    <i class="fas fa-save mr-2"></i>

                    Save Income

                </button>

            </div>

        </form>

    </div>

</div>


<script>

/*==========================================================
    PREVENT FORM RESUBMISSION
==========================================================*/

if (window.history.replaceState) {

    window.history.replaceState(null, null, window.location.href);

}

</script>

</body>

</html>