<?php
session_start();
require_once __DIR__ . '/schooldb.php';

$token = $_GET['token'] ?? '';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'] ?? '';

    if ($token && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
            $stmtUpdate->bind_param("ss", $hashed, $token);
            $stmtUpdate->execute();

            $success = "✅ Password updated successfully! <a href='login.php' class='text-blue-600 underline'>Login Now</a>";
        } else {
            $error = "Invalid or expired token.";
        }
    } else {
        $error = "Missing token or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password | EXCELLENT BILINGUAL SCHOOL COMPLEX</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-50 font-[Inter]">
    <form method="POST" class="bg-white p-8 rounded shadow-md w-full max-w-md space-y-4">
        <h2 class="text-xl font-semibold text-indigo-700 text-center">Reset Your Password</h2>

        <?php if ($error): ?><p class="text-red-600"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if ($success): ?><p class="text-green-600"><?= $success ?></p><?php endif; ?>

        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>" />
        <input type="password" name="password" placeholder="New Password" required class="w-full p-3 border rounded" />
        <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700">Reset Password</button>
        <a href="login.php" class="text-sm text-blue-600 hover:underline">← Back to Login</a>
    </form>
</body>
</html>
