<?php
require_once __DIR__ . '/db.php';
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function currentUser() {
    return $_SESSION ?? [];
}

function hasRole($roles) {
    if (!is_array($roles)) $roles = [$roles];
    return in_array($_SESSION['role'] ?? '', $roles);
}

function redirect($url, $msg = '', $type = 'success') {
    if ($msg) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
    }
    header("Location: $url");
    exit;
}

function flash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $cls = $f['type'] === 'success' ? 'alert-success' : ($f['type'] === 'error' ? 'alert-error' : 'alert-warning');
        echo "<div class='alert $cls'><span>{$f['msg']}</span><button onclick='this.parentElement.remove()'>×</button></div>";
    }
}

function sanitize($val) {
    return htmlspecialchars(trim($val ?? ''), ENT_QUOTES, 'UTF-8');
}

function e($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
