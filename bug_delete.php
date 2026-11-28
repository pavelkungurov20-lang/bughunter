<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bugs.php');
    exit;
}

verifyCsrf();

$bugId = (int)($_POST['bug_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT b.*, p.owner_id AS project_owner_id 
    FROM Bugs b 
    JOIN Projects p ON b.project_id = p.project_id 
    WHERE b.bug_id = ?
");
$stmt->execute([$bugId]);
$bug = $stmt->fetch();

if (!$bug) {
    setFlash('error', 'Дефект не найден.');
    header('Location: bugs.php');
    exit;
}

// Удалять может автор бага, владелец проекта или Администратор
$isReporter = (int)$bug['reporter_id'] === (int)$_SESSION['user_id'];
if (!$isReporter && !canManageProject($pdo, ['owner_id' => $bug['project_owner_id']])) {
    setFlash('error', 'У вас нет прав для удаления этого дефекта.');
    header('Location: bugs.php');
    exit;
}

// Удаляем связанные комментарии и историю изменений перед удалением самого бага
$pdo->prepare("DELETE FROM Comments WHERE bug_id = ?")->execute([$bugId]);
$pdo->prepare("DELETE FROM BugHistory WHERE bug_id = ?")->execute([$bugId]);
$pdo->prepare("DELETE FROM Bugs WHERE bug_id = ?")->execute([$bugId]);

setFlash('success', "Дефект #{$bugId} удалён.");
header('Location: bugs.php');
exit;
