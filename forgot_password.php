<?php
session_start();
require_once __DIR__ . '/schooldb.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

        $stmtUpdate = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE username = ?");
        $stmtUpdate->bind_param("sss", $token, $expiry, $email);
        $stmtUpdate->execute();

        // Here you would send email with link: replace with your domain
        $resetLink = "http://localhost/software/pages/reset_password.php?token=$token";
        $success = "Reset link generated: <a href='$resetLink' class='text-blue-600 underline'>Click here to reset</a>";
    } else {
        $error = "No user found with that username.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <form method="POST" class="bg-white p-8 rounded shadow w-full max-w-md space-y-4">
        <h2 class="text-2xl font-bold text-center text-indigo-700">Forgot Password</h2>

        <?php if ($error): ?><p class="text-red-600"><?= $error ?></p><?php endif; ?>
        <?php if ($success): ?><p class="text-green-600"><?= $success ?></p><?php endif; ?>

        <input type="text" name="email" placeholder="Enter username" required class="w-full border p-3 rounded" />
        <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">Send Reset Link</button>
        <a href="login.php" class="text-sm text-blue-600 hover:underline">← Back to Login</a>
    </form>
</body>
</html>
