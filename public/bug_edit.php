<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$bugId = (int)($_GET['id'] ?? $_POST['bug_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Bugs WHERE bug_id = ?");
$stmt->execute([$bugId]);
$bug = $stmt->fetch();

if (!$bug) {
    setFlash('error', 'Дефект не найден.');
    header('Location: bugs.php');
    exit;
}

// Серверная проверка разграничения прав (п. 12 ТЗ)
if (!canManageBug($pdo, $bug)) {
    setFlash('error', 'У вас нет прав для редактирования этого дефекта.');
    header("Location: bug_view.php?id={$bugId}");
    exit;
}

$projects = $pdo->query("SELECT project_id, code, name FROM Projects ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT user_id, full_name FROM Users ORDER BY full_name")->fetchAll();

$statuses = ['Новый', 'В работе', 'На проверке', 'Закрыт'];
$priorities = ['Низкий', 'Средний', 'Высокий', 'Критический'];

$pageTitle = "Редактирование дефекта #{$bugId}";
$formAction = "bug_edit.php?id={$bugId}";
$projectId = (int)($_POST['project_id'] ?? $bug['project_id']);
$assigneeId = (int)($_POST['assignee_id'] ?? $bug['assignee_id']);
$title = trim($_POST['title'] ?? $bug['title']);
$status = $_POST['status'] ?? $bug['status'];
$priority = $_POST['priority'] ?? $bug['priority'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

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
        $changedBy = (int)$_SESSION['user_id'];
        $historyStmt = $pdo->prepare("
            INSERT INTO BugHistory (bug_id, changed_by, field, old_val, new_val) 
            VALUES (?, ?, ?, ?, ?)
        ");

        $fieldsToTrack = [
            'project_id'  => [(string)$bug['project_id'], (string)$projectId],
            'title'       => [(string)$bug['title'], (string)$title],
            'status'      => [(string)$bug['status'], (string)$status],
            'priority'    => [(string)$bug['priority'], (string)$priority],
            'assignee_id' => [(string)($bug['assignee_id'] ?? 'Не назначен'), (string)($assigneeVal ?? 'Не назначен')],
        ];

        foreach ($fieldsToTrack as $fieldName => [$oldVal, $newVal]) {
            if ($oldVal !== $newVal) {
                $historyStmt->execute([$bugId, $changedBy, $fieldName, $oldVal, $newVal]);
            }
        }

        $update = $pdo->prepare("
            UPDATE Bugs 
            SET project_id = ?, assignee_id = ?, title = ?, status = ?, priority = ? 
            WHERE bug_id = ?
        ");
        $update->execute([$projectId, $assigneeVal, $title, $status, $priority, $bugId]);

        setFlash('success', "Изменения по дефекту #{$bugId} сохранены.");
        header("Location: bug_view.php?id={$bugId}");
        exit;
    }
}

require dirname(__DIR__) . '/views/bugs/form.html';
