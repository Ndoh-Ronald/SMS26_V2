<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Excellent Bilingual School Complex / Excellent École Bilingue Complexe </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" />
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-indigo-50 via-sky-50 to-emerald-50 min-h-screen flex flex-col">

    <!-- Header -->
    <header class="bg-white/70 backdrop-blur border-b shadow-sm px-6 py-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-indigo-600 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap"></i> Excellent Bilingual School Complex
            </span>
            <span class="text-sm text-gray-500 italic">/ Excellent École Bilingue Complexe</span>
        </h1>
        <div class="flex items-center gap-4">
            <a href="index.php" class="text-sm px-3 py-2 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 rounded shadow-sm">
                <i class="fa-solid fa-house mr-1"></i> Home / Accueil
            </a>
            <div class="text-sm text-gray-700">
                Welcome / Bienvenue, <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>
                <span class="text-xs text-gray-500 italic">(<?= htmlspecialchars($_SESSION['role'] ?? 'User'); ?>)</span>
            </div>
        </div>
    </header>

    <!-- Dashboard Main -->
    <main class="flex-1 py-12 px-6 max-w-6xl mx-auto">
        <h2 class="text-2xl font-semibold text-gray-800 mb-8">Quick Actions / Actions Rapides</h2>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

            <!-- Register Student -->
            <a href="register_student.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-user-plus text-indigo-600 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Register Student / Enregistrer un élève</h3>
                <p class="text-sm text-gray-500">Add new students to the system / Ajouter de nouveaux élèves au système.</p>
            </a>

            <!-- Report Card -->
            <a href="select_class_term.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-clipboard-list text-indigo-500 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Report Card / Bulletin Scolaire</h3>
                <p class="text-sm text-gray-500">Generate termly or annual report cards / Générer les bulletins trimestriels ou annuels.</p>
            </a>

            <!-- View Students -->
            <a href="view_students.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-users text-emerald-600 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">View Students / Voir les élèves</h3>
                <p class="text-sm text-gray-500">Browse, edit, or delete student records / Parcourir, modifier ou supprimer les dossiers des élèves.</p>
            </a>

            <!-- Manage Fees -->
            <a href="manage_fees.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-coins text-yellow-500 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Manage Fees / Gérer les frais</h3>
                <p class="text-sm text-gray-500">Record and track tuition payments / Enregistrer et suivre les paiements de frais de scolarité.</p>
            </a>

            <!-- Financial Report -->
            <a href="financial_report.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-chart-pie text-pink-500 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Financial Report / Rapport financier</h3>
                <p class="text-sm text-gray-500">Export summaries and view analytics / Exporter des résumés et voir les analyses.</p>
            </a>

            <!-- Record Expense -->
            <a href="record_expense.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-file-invoice-dollar text-red-500 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Record Expense / Enregistrer une dépense</h3>
                <p class="text-sm text-gray-500">Log school expenses with narration / Enregistrer les dépenses scolaires avec narration.</p>
            </a>

            <!-- Dropouts -->
            <a href="dropouts.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-user-xmark text-red-600 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Dropped Students / Élèves exclus</h3>
                <p class="text-sm text-gray-500">View dismissed or dropout students / Voir les élèves renvoyés ou décrochés.</p>
            </a>

            <!-- Set Class Fee -->
            <a href="set_class_fee.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-sliders text-blue-600 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Set Class Fee / Définir les frais par classe</h3>
                <p class="text-sm text-gray-500">Assign or update fees per class / Assigner ou mettre à jour les frais par classe.</p>
            </a>

            <!-- Fee Status -->
            <a href="fee_status.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-file-circle-check text-indigo-600 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Fee Status / État des frais</h3>
                <p class="text-sm text-gray-500">View payment history per student / Voir l'historique des paiements par élève.</p>
            </a>

            <!-- Logout -->
            <a href="logout.php" class="p-6 bg-white rounded-2xl border shadow hover:shadow-lg transition transform hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-3">
                    <i class="fa-solid fa-right-from-bracket text-gray-600 text-3xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">Logout / Déconnexion</h3>
                <p class="text-sm text-gray-500">Exit your dashboard securely / Quitter le tableau de bord en toute sécurité.</p>
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center text-sm text-gray-500 py-6 border-t mt-6">
        &copy; <?= date('Y'); ?> Excellent Bilingual School Complex / Excellent École Bilingue Complexe — Powered by <strong>STCE</strong> & <strong>IABLE</strong>
    </footer>
</body>
</html>
