<?php
session_start();
require_once __DIR__ . '/schooldb.php';

$success='';
$error='';
$valid_roles=['admin','bursar','teacher','principal'];

function logActivity(PDO $pdo,string $activity):void{
    try{
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            activity TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmt=$pdo->prepare("INSERT INTO activity_logs(activity) VALUES(:a)");
        $stmt->execute([':a'=>$activity]);
    }catch(Exception $e){}
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $username=trim($_POST['username']??'');
    $email=trim($_POST['email']??'');
    $password=$_POST['password']??'';
    $role=$_POST['role']??'';

    if(strlen($username)<3){
        $error='Username must be at least 3 characters.';
    }elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        $error='Please enter a valid email address.';
    }elseif(strlen($password)<6){
        $error='Password must be at least 6 characters.';
    }elseif(!in_array($role,$valid_roles,true)){
        $error='Invalid role selected.';
    }else{
        try{
            $pdo->beginTransaction();
            $check=$pdo->prepare("SELECT id FROM users WHERE username=:u OR email=:e LIMIT 1");
            $check->execute([':u'=>$username,':e'=>$email]);
            if($check->fetch()){
                throw new Exception('Username or Email already exists.');
            }
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $ins=$pdo->prepare("INSERT INTO users(username,email,password,role)
            VALUES(:u,:e,:p,:r)");
            $ins->execute([
                ':u'=>$username,
                ':e'=>$email,
                ':p'=>$hash,
                ':r'=>$role
            ]);
            logActivity($pdo,"Registered user: $username ($role)");
            $pdo->commit();
            $success="✅ Account created successfully. <a href='login.php' class='underline text-indigo-600'>Login now</a>";
            $_POST=[];
        }catch(Exception $ex){
            if($pdo->inTransaction()) $pdo->rollBack();
            $error=$ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Register | EXCELLENT BILINGUAL SCHOOL COMPLEX</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-indigo-50 via-sky-50 to-emerald-50 font-[Inter] flex items-center justify-center min-h-screen">
<main class="w-full max-w-md bg-white p-8 rounded-2xl shadow-xl border">
<section class="text-center mb-6">
<h2 class="text-3xl font-extrabold text-indigo-600 flex justify-center items-center gap-2"><i class="fa-solid fa-graduation-cap"></i> Register</h2>
<p class="text-sm text-gray-500">Create a user for EXCELLENT BILINGUAL SCHOOL COMPLEX</p>
</section>
<?php if($success): ?><div class="bg-green-100 text-green-700 p-3 rounded text-sm mb-4 text-center"><?= $success ?></div><?php endif;?>
<?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded text-sm mb-4 text-center">⚠️ <?= htmlspecialchars($error) ?></div><?php endif;?>
<form method="POST" class="space-y-4">
<input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($_POST['username']??'') ?>" required class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-300">
<input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($_POST['email']??'') ?>" required class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-300">
<input type="password" name="password" placeholder="Password" required class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-indigo-300">
<select name="role" required class="w-full px-4 py-3 border rounded-lg bg-white focus:ring-2 focus:ring-indigo-300">
<option value="">Select Role</option>
<?php foreach($valid_roles as $r): ?>
<option value="<?= $r ?>" <?= (($_POST['role']??'')===$r)?'selected':''; ?>><?= ucfirst($r) ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">Create Account</button>
</form>
<p class="text-sm text-center mt-6">Already registered? <a href="login.php" class="text-indigo-600 hover:underline">Login here</a></p>
</main>
</body>
</html>
