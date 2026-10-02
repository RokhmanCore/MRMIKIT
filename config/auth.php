<?php
require_once __DIR__ . '/config.php';
function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}
