<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$stmt = $pdo->query("
    SELECT 
        p.project_id,
        p.code,
        p.name,
        p.owner_id,
        u.full_name AS owner_name,
        COUNT(b.bug_id) AS bugs_count
    FROM Projects p
    LEFT JOIN Users u ON p.owner_id = u.user_id
    LEFT JOIN Bugs b ON p.project_id = b.project_id
    GROUP BY p.project_id
    ORDER BY p.project_id DESC
");
$projects = $stmt->fetchAll();
$flash = getFlash();

require dirname(__DIR__) . '/views/projects/projects.html';
