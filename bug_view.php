<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$bugId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT 
        b.*,
        p.code AS project_code,
        p.name AS project_name,
        p.owner_id AS project_owner_id,
        rep.full_name AS reporter_name,
        ass.full_name AS assignee_name
    FROM Bugs b
    JOIN Projects p ON b.project_id = p.project_id
    LEFT JOIN Users rep ON b.reporter_id = rep.user_id
    LEFT JOIN Users ass ON b.assignee_id = ass.user_id
    WHERE b.bug_id = ?
");
$stmt->execute([$bugId]);
$bug = $stmt->fetch();

if (!$bug) {
    http_response_code(404);
    die('404 Not Found: Запрашиваемый дефект не найден.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $body = trim($_POST['body'] ?? '');

    if ($body === '') {
        setFlash('error', 'Текст комментария не может быть пустым.');
    } else {
        $ins = $pdo->prepare("INSERT INTO Comments (bug_id, author_id, body) VALUES (?, ?, ?)");
        $ins->execute([$bugId, (int)$_SESSION['user_id'], $body]);
        setFlash('success', 'Комментарий добавлен.');
    }
    header("Location: bug_view.php?id={$bugId}");
    exit;
}

$commStmt = $pdo->prepare("
    SELECT c.*, u.full_name AS author_name 
    FROM Comments c 
    LEFT JOIN Users u ON c.author_id = u.user_id 
    WHERE c.bug_id = ? 
    ORDER BY c.comment_id ASC
");
$commStmt->execute([$bugId]);
$comments = $commStmt->fetchAll();

$histStmt = $pdo->prepare("
    SELECT h.*, u.full_name AS user_name 
    FROM BugHistory h 
    LEFT JOIN Users u ON h.changed_by = u.user_id 
    WHERE h.bug_id = ? 
    ORDER BY h.history_id DESC
");
$histStmt->execute([$bugId]);
$history = $histStmt->fetchAll();

$flash = getFlash();
require dirname(__DIR__) . '/views/bugs/view.html';
