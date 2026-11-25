<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$stmt = $pdo->query("
    SELECT 
        b.bug_id,
        b.title,
        b.status,
        b.priority,
        b.project_id,
        b.reporter_id,
        p.code AS project_code,
        p.name AS project_name,
        p.owner_id AS project_owner_id,
        rep.full_name AS reporter_name,
        ass.full_name AS assignee_name
    FROM Bugs b
    JOIN Projects p ON b.project_id = p.project_id
    LEFT JOIN Users rep ON b.reporter_id = rep.user_id
    LEFT JOIN Users ass ON b.assignee_id = ass.user_id
    ORDER BY b.bug_id DESC
");
$bugs = $stmt->fetchAll();
$flash = getFlash();

require dirname(__DIR__) . '/views/bugs/bugs.html';
