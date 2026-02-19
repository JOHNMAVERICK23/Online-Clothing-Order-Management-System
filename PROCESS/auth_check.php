<?php
// PROCESS/auth_check.php
// I-include ito sa simula ng bawat protected PHP page

session_start();

function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: ../login.html');
        exit;
    }
}

function requireStaff() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.html');
        exit;
    }
    // Both admin and staff can access staff pages
}
?>