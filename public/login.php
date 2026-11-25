<?php
require_once dirname(__DIR__) . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: projects.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }
    if ($password === '') {
        $errors[] = 'Введите пароль.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id, full_name, password_hash FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['user_name'] = $user['full_name'];
            header('Location: projects.php');
            exit;
        } else {
            $errors[] = 'Неверный email или пароль.';
        }
    }
}

require dirname(__DIR__) . '/views/auth/login.html';
