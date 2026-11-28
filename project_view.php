<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$projectId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, u.full_name AS owner_name 
    FROM Projects p 
    LEFT JOIN Users u ON p.owner_id = u.user_id 
    WHERE p.project_id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    setFlash('error', 'Запрашиваемый проект не найден.');
    header('Location: projects.php');
    exit;
}

$bugsStmt = $pdo->prepare("
    SELECT b.bug_id, b.title, b.status, b.priority, u.full_name AS assignee_name
    FROM Bugs b
    LEFT JOIN Users u ON b.assignee_id = u.user_id
    WHERE b.project_id = ?
    ORDER BY b.bug_id DESC
");
$bugsStmt->execute([$projectId]);
$bugs = $bugsStmt->fetchAll();
$flash = getFlash();

require dirname(__DIR__) . '/views/projects/view.html';
