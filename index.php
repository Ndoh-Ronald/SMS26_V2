<?php
require_once __DIR__ . '/schooldb.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <meta name="description" content="EXCELLENT BILINGUAL SCHOOL COMPLEX: Offline-First School Management Software for Africa." />
    <meta name="author" content="E.B.S.C." />
    <title>E.B.S.C. • Sign In</title>

    <!-- Google Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-50 via-sky-50 to-emerald-50 font-[Inter] flex flex-col">

    <!-- Header Navigation -->
    <header class="w-full py-4 px-6 flex items-center justify-between bg-white/60 backdrop-blur border-b shadow-sm">
        <h1 class="text-2xl font-semibold text-indigo-600 flex items-center gap-2">
            <i class="fa-solid fa-graduation-cap"></i> EXCELLENT BILINGUAL SCHOOL COMPLEX
        </h1>
        <nav class="hidden md:flex gap-8 text-sm font-medium text-gray-600">
            <a href="#features" class="hover:text-indigo-600 transition">Features</a>
            <a href="#offline" class="hover:text-indigo-600 transition">Offline-First</a>
            <a href="dashboard.php" class="hover:text-indigo-600 transition">Dashboard</a>
            <a href="record_expense.php" class="hover:text-indigo-600 transition">Record Expense</a>
            <a href="#contact" class="hover:text-indigo-600 transition">Contact</a>
        </nav>
        <div class="flex items-center gap-4">
            <a href="login.php" class="text-sm font-semibold text-gray-700 hover:text-indigo-600">Login</a>
            <a href="register.php" class="hidden md:inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium shadow hover:bg-indigo-700 transition">
                <i class="fa-solid fa-user-plus"></i> Register
            </a>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="flex-1 grid place-items-center px-6 py-16 lg:py-24">
        <div class="max-w-3xl text-center">
            <h2 class="text-4xl lg:text-5xl font-extrabold text-gray-800 mb-6 leading-tight">
                Smart, Offline-First School Management<br class="hidden lg:block" />
                <span class="text-indigo-600">Built for African Connectivity</span>
            </h2>
            <p class="text-lg text-gray-600 mb-10">
                Keep your classrooms, finances, and performance data in sync—even when the internet isn’t. 
                E.B.S.C. empowers teachers to capture marks offline and auto-syncs when online.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="register.php" class="inline-flex items-center justify-center px-6 py-3 rounded-lg bg-indigo-600 text-white text-lg font-semibold shadow-lg hover:bg-indigo-700 transition">
                    Get Started
                </a>
                <a href="#features" class="inline-flex items-center justify-center px-6 py-3 rounded-lg border border-indigo-600 text-indigo-600 text-lg font-semibold hover:bg-indigo-50 transition">
                    Explore Features
                </a>
            </div>
        </div>
    </section>

    <!-- Feature Highlights -->
    <section id="features" class="bg-white py-16 px-6">
        <div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-10">
            <div class="p-6 rounded-xl bg-indigo-50/50 border shadow-sm">
                <i class="fa-solid fa-sack-dollar text-3xl text-indigo-600 mb-4"></i>
                <h3 class="text-xl font-semibold mb-2">Finance & Fees</h3>
                <p class="text-gray-600 text-sm">Track tuition payments, generate real-time financial reports, and forecast cash flow in a click.</p>
            </div>
            <div class="p-6 rounded-xl bg-emerald-50/50 border shadow-sm">
                <i class="fa-solid fa-clipboard-check text-3xl text-emerald-600 mb-4"></i>
                <h3 class="text-xl font-semibold mb-2">Offline Mark Entry</h3>
                <p class="text-gray-600 text-sm">Teachers record marks offline; the app syncs securely once connection returns—no double entry.</p>
            </div>
            <div class="p-6 rounded-xl bg-sky-50/50 border shadow-sm">
                <i class="fa-solid fa-chart-line text-3xl text-sky-600 mb-4"></i>
                <h3 class="text-xl font-semibold mb-2">Performance Analytics</h3>
                <p class="text-gray-600 text-sm">Visual dashboards spotlight trends, helping staff intervene early and celebrate success.</p>
            </div>
        </div>
    </section>

    <!-- Offline-First Highlight -->
    <section id="offline" class="py-16 px-6 bg-gradient-to-r from-indigo-100 via-white to-emerald-100">
        <div class="max-w-4xl mx-auto text-center">
            <h2 class="text-3xl font-bold mb-6 text-gray-800">Why Offline-First Matters</h2>
            <p class="text-gray-700 leading-relaxed md:text-lg">
                Many Cameroonian schools have inconsistent internet. Our system uses service workers and IndexedDB to store data locally—even on low-spec Android devices. 
                Once signal returns, secure background sync ensures accuracy across devices.
            </p>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="bg-gray-900 text-gray-200 py-10 px-6">
        <div class="max-w-5xl mx-auto grid sm:grid-cols-2 gap-8">
            <div>
                <h3 class="text-xl font-semibold mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-graduation-cap"></i> EXCELLENT BILINGUAL SCHOOL COMPLEX
                </h3>
                <p class="text-sm mb-4">© <?= date('Y'); ?> E.B.S.C. All rights reserved.</p>
                <p class="text-xs">Douala, Cameroon</p>
            </div>
            <div class="space-y-4">
                <h4 class="font-semibold">Quick Links</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="dashboard.php" class="hover:text-white">Dashboard</a></li>
                    <li><a href="record_expense.php" class="hover:text-white">Record Expense</a></li>
                    <li><a href="login.php" class="hover:text-white">Login</a></li>
                    <li><a href="register.php" class="hover:text-white">Create Account</a></li>
                    <li><a href="mailto:support@ebsc.cm" class="hover:text-white">Email Support</a></li>
                </ul>
            </div>
        </div>
    </footer>
</body>
</html>
