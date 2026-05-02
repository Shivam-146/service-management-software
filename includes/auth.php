<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTech() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'tech' || $_SESSION['role'] === 'technician');
}

function checkAccess($role = null) {
    if (!isLoggedIn()) {
        header("Location: /cctv/index.php");
        exit();
    }
    
    if ($role === 'admin' && !isAdmin()) {
        header("Location: /cctv/index.php"); // or some unauthorized page
        exit();
    }

    if (($role === 'tech' || $role === 'technician') && !isTech()) {
        header("Location: /cctv/index.php");
        exit();
    }
}
?>
