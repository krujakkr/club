<?php
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher') {
    logActivity($_SESSION['user_id'], 'teacher', 'logout', 'ครูออกจากระบบ');
}

session_unset();
session_destroy();
redirect('login.php');