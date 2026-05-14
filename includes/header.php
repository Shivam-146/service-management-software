<?php
require_once __DIR__ . '/auth.php';
$isAdminPath = str_contains($_SERVER['REQUEST_URI'], '/admin/');
$isTechPath = str_contains($_SERVER['REQUEST_URI'], '/tech/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">

<div class="flex min-h-screen">
    <?php if ($isAdminPath && isAdmin() && !isset($_GET['print'])): ?>
    <!-- Admin Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex-shrink-0 fixed h-full transition-all duration-300 z-50">
        <div class="p-6">
            <h1 class="text-xl font-bold tracking-tight flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                CCTV Admin
            </h1>
        </div>
        <nav class="mt-4 px-3 space-y-1">
            <a href="/cctv/admin/index.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'index.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="/cctv/admin/complaints.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'complaints.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                Service Desk
            </a>
            <a href="/cctv/admin/amc_tracking.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'amc_tracking.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                AMC Tracking
            </a>
            <a href="/cctv/admin/customers.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'customers.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                Customers
            </a>
            <a href="/cctv/admin/stock/index.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'stock/') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Stock Management
            </a>
            <a href="/cctv/admin/billing.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'billing.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Billing
            </a>
            <a href="/cctv/admin/accounts/index.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'accounts/') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Accounts
            </a>
            <a href="/cctv/admin/reports.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'reports.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                Reports
            </a>
            <a href="/cctv/admin/technician_report.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'technician_report.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                Tech Reports
            </a>
            <a href="/cctv/admin/settings.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'settings.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
            </a>
            <a href="/cctv/admin/company_profile.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'company_profile.php') ? 'bg-slate-800 border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Company Profile
            </a>
            <div class="pt-10">
                <a href="/cctv/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-red-900/50 text-red-400 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Logout
                </a>
            </div>
        </nav>
    </aside>
    <?php elseif ($isTechPath && isTech() && !isset($_GET['print'])): ?>
    <!-- Tech Sidebar -->
    <aside class="w-20 md:w-64 bg-slate-900 text-white flex-shrink-0 fixed h-full transition-all duration-300 z-50">
        <div class="p-4 md:p-6 text-center md:text-left">
            <h1 class="text-xl font-bold tracking-tight hidden md:flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                CCTV Tech
            </h1>
            <div class="md:hidden flex justify-center">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
            </div>
        </div>
        <nav class="mt-4 px-2 md:px-3 space-y-1">
            <a href="/cctv/tech/dashboard.php" class="flex flex-col md:flex-row items-center gap-1 md:gap-3 px-2 md:px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'dashboard.php') ? 'bg-slate-800 border-b-2 md:border-b-0 md:border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                <span class="text-[10px] md:text-sm">My Tasks</span>
            </a>
            <a href="/cctv/tech/history.php" class="flex flex-col md:flex-row items-center gap-1 md:gap-3 px-2 md:px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'history.php') ? 'bg-slate-800 border-b-2 md:border-b-0 md:border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-[10px] md:text-sm">Job History</span>
            </a>
            <a href="/cctv/tech/billing.php" class="flex flex-col md:flex-row items-center gap-1 md:gap-3 px-2 md:px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'billing.php') ? 'bg-slate-800 border-b-2 md:border-b-0 md:border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span class="text-[10px] md:text-sm">Billing</span>
            </a>
            <a href="/cctv/tech/inventory.php" class="flex flex-col md:flex-row items-center gap-1 md:gap-3 px-2 md:px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'inventory.php') ? 'bg-slate-800 border-b-2 md:border-b-0 md:border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span class="text-[10px] md:text-sm">Inventory</span>
            </a>
            <a href="/cctv/tech/profile.php" class="flex flex-col md:flex-row items-center gap-1 md:gap-3 px-2 md:px-4 py-3 rounded-lg hover:bg-slate-800 transition <?php echo str_contains($_SERVER['PHP_SELF'], 'profile.php') ? 'bg-slate-800 border-b-2 md:border-b-0 md:border-l-4 border-indigo-500' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span class="text-[10px] md:text-sm">Profile</span>
            </a>
            <div class="pt-10 flex justify-center md:block">
                <a href="/cctv/logout.php" class="flex flex-col md:flex-row items-center gap-1 md:gap-3 px-2 md:px-4 py-3 rounded-lg hover:bg-red-900/50 text-red-400 transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span class="text-[10px] md:text-sm">Logout</span>
                </a>
            </div>
        </nav>
    </aside>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main class="flex-1 <?php echo (($isAdminPath && isAdmin()) || ($isTechPath && isTech())) && !isset($_GET['print']) ? 'ml-20 md:ml-64' : ''; ?> p-4 md:p-8">
