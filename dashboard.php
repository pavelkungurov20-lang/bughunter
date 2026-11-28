<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

// 1. Общая сводка по дефектам (агрегация через COUNT и SUM CASE WHEN)
$summary = $pdo->query("
    SELECT 
        COUNT(*) AS total_bugs,
        COALESCE(SUM(CASE WHEN status = 'Новый' THEN 1 ELSE 0 END), 0) AS new_bugs,
        COALESCE(SUM(CASE WHEN status = 'В работе' THEN 1 ELSE 0 END), 0) AS in_progress_bugs,
        COALESCE(SUM(CASE WHEN status = 'На проверке' THEN 1 ELSE 0 END), 0) AS review_bugs,
        COALESCE(SUM(CASE WHEN status = 'Закрыт' THEN 1 ELSE 0 END), 0) AS closed_bugs,
        COALESCE(SUM(CASE WHEN priority = 'Критический' AND status != 'Закрыт' THEN 1 ELSE 0 END), 0) AS active_critical
    FROM Bugs
")->fetch();

// 2. Распределение дефектов по проектам (LEFT JOIN + COUNT + GROUP BY)
$byProject = $pdo->query("
    SELECT 
        p.project_id,
        p.code,
        p.name,
        COUNT(b.bug_id) AS total_count,
        COALESCE(SUM(CASE WHEN b.status != 'Закрыт' THEN 1 ELSE 0 END), 0) AS open_count,
        COALESCE(SUM(CASE WHEN b.status = 'Закрыт' THEN 1 ELSE 0 END), 0) AS closed_count
    FROM Projects p
    LEFT JOIN Bugs b ON p.project_id = b.project_id
    GROUP BY p.project_id
    ORDER BY open_count DESC, total_count DESC
")->fetchAll();

// 3. Нагрузка на исполнителей (GROUP BY)
$byAssignee = $pdo->query("
    SELECT 
        COALESCE(u.full_name, 'Не назначен') AS assignee_name,
        COUNT(b.bug_id) AS total_assigned,
        COALESCE(SUM(CASE WHEN b.status != 'Закрыт' THEN 1 ELSE 0 END), 0) AS active_assigned
    FROM Bugs b
    LEFT JOIN Users u ON b.assignee_id = u.user_id
    GROUP BY b.assignee_id
    ORDER BY active_assigned DESC
")->fetchAll();

// 4. Распределение по приоритетам (GROUP BY)
$byPriority = $pdo->query("
    SELECT priority, COUNT(*) AS cnt
    FROM Bugs
    GROUP BY priority
    ORDER BY cnt DESC
")->fetchAll();

$flash = getFlash();
require dirname(__DIR__) . '/views/dashboard.html';
