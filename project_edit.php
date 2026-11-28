<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$bugId = (int)($_GET['id'] ?? $_POST['bug_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Bugs WHERE bug_id = ?");
$stmt->execute([$bugId]);
$bug = $stmt->fetch();

if (!$bug) {
    http_response_code(404);
    die('404 Not Found: Запрашиваемый дефект не найден.');
}

// Защита от IDOR: проверка прав на уровне сервера (GET и POST)
if (!canManageBug($pdo, $bug)) {
    http_response_code(403);
    die('403 Forbidden: У вас нет прав для редактирования этого дефекта.');
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
$description = trim($_POST['description'] ?? ($bug['description'] ?? ''));
$steps = trim($_POST['steps'] ?? ($bug['steps'] ?? ''));
$expectedResult = trim($_POST['expected_result'] ?? ($bug['expected_result'] ?? ''));
$actualResult = trim($_POST['actual_result'] ?? ($bug['actual_result'] ?? ''));
$status = $_POST['status'] ?? $bug['status'];
$priority = $_POST['priority'] ?? $bug['priority'];
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
        $changedBy = (int)$_SESSION['user_id'];
        $historyStmt = $pdo->prepare("
            INSERT INTO BugHistory (bug_id, changed_by, field, old_val, new_val) 
            VALUES (?, ?, ?, ?, ?)
        ");

        $fieldsToTrack = [
            'project_id'      => [(string)$bug['project_id'], (string)$projectId],
            'title'           => [(string)$bug['title'], (string)$title],
            'description'     => [(string)($bug['description'] ?? ''), (string)$description],
            'steps'           => [(string)($bug['steps'] ?? ''), (string)$steps],
            'expected_result' => [(string)($bug['expected_result'] ?? ''), (string)$expectedResult],
            'actual_result'   => [(string)($bug['actual_result'] ?? ''), (string)$actualResult],
            'status'          => [(string)$bug['status'], (string)$status],
            'priority'        => [(string)$bug['priority'], (string)$priority],
            'assignee_id'     => [(string)($bug['assignee_id'] ?? 'Не назначен'), (string)($assigneeVal ?? 'Не назначен')],
        ];

        foreach ($fieldsToTrack as $fieldName => [$oldVal, $newVal]) {
            if ($oldVal !== $newVal) {
                $historyStmt->execute([$bugId, $changedBy, $fieldName, $oldVal, $newVal]);
            }
        }

        $update = $pdo->prepare("
            UPDATE Bugs 
            SET project_id = ?, assignee_id = ?, title = ?, description = ?, 
                steps = ?, expected_result = ?, actual_result = ?, 
                status = ?, priority = ? 
            WHERE bug_id = ?
        ");
        $update->execute([
            $projectId,
            $assigneeVal,
            $title,
            $description,
            $steps,
            $expectedResult,
            $actualResult,
            $status,
            $priority,
            $bugId
        ]);

        setFlash('success', "Изменения по дефекту #{$bugId} сохранены.");
        header("Location: bug_view.php?id={$bugId}");
        exit;
    }
}

require dirname(__DIR__) . '/views/bugs/form.html';
