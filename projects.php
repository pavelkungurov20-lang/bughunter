<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

$search = trim($_GET['q'] ?? '');
$where = [];
$params = [];

if ($search !== '') {
    $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search, 'UTF-8'));
    $where[] = "(MB_LOWER(p.code) LIKE ? ESCAPE '\\' OR MB_LOWER(p.name) LIKE ? ESCAPE '\\')";
    $params[] = "%{$escapedSearch}%";
    $params[] = "%{$escapedSearch}%";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Пагинация
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Projects p {$whereSql}");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "
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
    {$whereSql}
    GROUP BY p.project_id
    ORDER BY p.project_id DESC
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $val) {
    $stmt->bindValue($paramIndex++, $val, PDO::PARAM_STR);
}
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();

$projects = $stmt->fetchAll();
$flash = getFlash();

require dirname(__DIR__) . '/views/projects/projects.html';
