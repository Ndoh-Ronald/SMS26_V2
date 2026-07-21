<?php
session_start();

// Include the PDO connection
require_once __DIR__ . '/schooldb.php';

$error = '';
$success = '';

// Show success message after registration
if (isset($_GET['registered'])) {
    $success = "✅ Registration successful! You can now login.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username !== '' && $password !== '') {

        try {

            // Get user together with role name
            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.password,
                    u.active,
                    r.name AS role
                FROM users u
                LEFT JOIN roles r
                    ON u.role_id = r.id
                WHERE u.username = ?
                   OR u.email = ?
                LIMIT 1
            ");

            $stmt->execute([$username, $username]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                // Check if account is active
                if (isset($user['active']) && !$user['active']) {

                    $error = "Your account has been disabled.";

                } elseif (password_verify($password, $user['password'])) {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = strtolower($user['role']);

                    header("Location: dashboard.php");
                    exit;

                } else {

                    $error = "Incorrect password.";

                }

            } else {

                $error = "Invalid username/email or password.";

            }

        } catch (PDOException $e) {

            $error = "Database Error: " . $e->getMessage();

        }

    } else {

        $error = "Please fill in both fields.";

    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | EXCELLENT BILINGUAL SCHOOL COMPLEX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="bg-gradient-to-br from-indigo-50 via-sky-50 to-blue-50 font-[Inter] flex items-center justify-center min-h-screen">

<form method="POST" class="bg-white shadow-xl p-8 rounded-xl w-full max-w-md space-y-6 border">

    <div class="text-center">
        <h2 class="text-3xl font-bold text-blue-600 flex justify-center items-center gap-2">
            <i class="fa-solid fa-graduation-cap"></i> Login
        </h2>

        <p class="text-gray-500 text-sm">
            EXCELLENT BILINGUAL SCHOOL COMPLEX
        </p>
    </div>

    <?php if ($success): ?>
        <p class="bg-green-100 text-green-700 text-sm p-2 rounded text-center">
            <?= htmlspecialchars($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="bg-red-100 text-red-700 text-sm p-2 rounded text-center">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <div class="space-y-4">

        <input
            type="text"
            name="username"
            placeholder="Username or Email"
            required
            autofocus
            class="w-full px-4 py-3 border rounded focus:outline-none focus:ring-2 focus:ring-blue-300">

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
            class="w-full px-4 py-3 border rounded focus:outline-none focus:ring-2 focus:ring-blue-300">

    </div>

    <button
        type="submit"
        class="w-full bg-blue-600 text-white py-3 rounded font-semibold hover:bg-blue-700 transition">

        Sign In

    </button>

    <div class="text-sm text-center text-gray-600">

        <a href="forgot_password.php"
           class="text-blue-600 hover:underline">

            Forgot Password?

        </a>

    </div>

    <p class="text-sm text-center text-gray-600">

        Don't have an account?

        <a href="register.php"
           class="text-blue-600 hover:underline">

            Register here

        </a>

    </p>

</form>

</body>
</html>