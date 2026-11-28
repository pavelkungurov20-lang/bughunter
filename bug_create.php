<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$projects = $pdo->query("SELECT project_id, code, name FROM Projects ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT user_id, full_name FROM Users ORDER BY full_name")->fetchAll();

if (empty($projects)) {
    setFlash('error', 'Сначала создайте хотя бы один проект.');
    header('Location: projects.php');
    exit;
}

$statuses = ['Новый', 'В работе', 'На проверке', 'Закрыт'];
$priorities = ['Низкий', 'Средний', 'Высокий', 'Критический'];

$pageTitle = 'Регистрация нового дефекта';
$formAction = 'bug_create.php';
$projectId = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? $projects['project_id']);
$assigneeId = (int)($_POST['assignee_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$status = $_POST['status'] ?? 'Новый';
$priority = $_POST['priority'] ?? 'Средний';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Проверка существования проекта
    $projCheck = $pdo->prepare("SELECT project_id FROM Projects WHERE project_id = ?");
    $projCheck->execute([$projectId]);
    if (!$projCheck->fetch()) {
        $errors[] = 'Выбран несуществующий проект.';
    }

    if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
        $errors[] = 'Заголовок дефекта должен содержать от 3 до 200 символов.';
    }
    if (!in_array($status, $statuses, true)) {
        $errors[] = 'Некорректный статус.';
    }
    if (!in_array($priority, $priorities, true)) {
        $errors[] = 'Некорректный приоритет.';
    }

    $assigneeVal = $assigneeId > 0 ? $assigneeId : null;
    if ($assigneeVal !== null) {
        $userCheck = $pdo->prepare("SELECT user_id FROM Users WHERE user_id = ?");
        $userCheck->execute([$assigneeVal]);
        if (!$userCheck->fetch()) {
            $errors[] = 'Выбран несуществующий исполнитель.';
        }
    }

    if (empty($errors)) {
        $reporterId = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare("
            INSERT INTO Bugs (project_id, reporter_id, assignee_id, title, status, priority) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$projectId, $reporterId, $assigneeVal, $title, $status, $priority]);
        $newBugId = (int)$pdo->lastInsertId();

        setFlash('success', "Дефект #{$newBugId} успешно добавлен.");
        header("Location: bug_view.php?id={$newBugId}");
        exit;
    }
}

require dirname(__DIR__) . '/views/bugs/form.html';
