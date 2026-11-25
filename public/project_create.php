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
$projectId = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$assigneeId = (int)($_POST['assignee_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$steps = trim($_POST['steps'] ?? '');
$expectedResult = trim($_POST['expected_result'] ?? '');
$actualResult = trim($_POST['actual_result'] ?? '');
$status = $_POST['status'] ?? 'Новый';
$priority = $_POST['priority'] ?? 'Средний';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($projectId <= 0) {
        $errors[] = 'Необходимо выбрать проект.';
    } else {
        $projCheck = $pdo->prepare("SELECT project_id FROM Projects WHERE project_id = ?");
        $projCheck->execute([$projectId]);
        if (!$projCheck->fetch()) {
            $errors[] = 'Выбран несуществующий проект.';
        }
    }

    if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
        $errors[] = 'Заголовок дефекта должен содержать от 3 до 200 символов.';
    }
    if (mb_strlen($description) < 5) {
        $errors[] = 'Подробное описание должно содержать минимум 5 символов.';
    }
    if (mb_strlen($steps) < 3) {
        $errors[] = 'Укажите шаги воспроизведения ошибки.';
    }
    if (mb_strlen($expectedResult) < 3) {
        $errors[] = 'Укажите ожидаемый результат.';
    }
    if (mb_strlen($actualResult) < 3) {
        $errors[] = 'Укажите фактический результат.';
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
            INSERT INTO Bugs (
                project_id, reporter_id, assignee_id, title, 
                description, steps, expected_result, actual_result, 
                status, priority
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $reporterId,
            $assigneeVal,
            $title,
            $description,
            $steps,
            $expectedResult,
            $actualResult,
            $status,
            $priority
        ]);
        $newBugId = (int)$pdo->lastInsertId();

        setFlash('success', "Дефект #{$newBugId} успешно зарегистрирован.");
        header("Location: bug_view.php?id={$newBugId}");
        exit;
    }
}

require dirname(__DIR__) . '/views/bugs/form.html';
