<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bugs.php');
    exit;
}

verifyCsrf();

$bugId = (int)($_POST['bug_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM Bugs WHERE bug_id = ?");
$stmt->execute([$bugId]);
$bug = $stmt->fetch();

if (!$bug) {
    http_response_code(404);
    die('404 Not Found: Дефект не найден.');
}

// Единая серверная проверка прав доступа
if (!canManageBug($pdo, $bug)) {
    http_response_code(403);
    die('403 Forbidden: У вас нет прав для удаления этого дефекта.');
}

$pdo->prepare("DELETE FROM Comments WHERE bug_id = ?")->execute([$bugId]);
$pdo->prepare("DELETE FROM BugHistory WHERE bug_id = ?")->execute([$bugId]);
$pdo->prepare("DELETE FROM Bugs WHERE bug_id = ?")->execute([$bugId]);

setFlash('success', "Дефект #{$bugId} успешно удалён.");
header('Location: bugs.php');
exit;
