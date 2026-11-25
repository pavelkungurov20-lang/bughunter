<?php
require_once dirname(__DIR__) . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: projects.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Введите ФИО.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный формат email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Пароль должен содержать минимум 8 символов.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'Пользователь с таким email уже зарегистрирован.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO Users (full_name, email, password_hash) VALUES (?, ?, ?)");
            $insert->execute([$fullName, $email, $hash]);

            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['user_name'] = $fullName;
            header('Location: index.php');
            exit;
        }
    }
}

require dirname(__DIR__) . '/views/auth/register.html';
