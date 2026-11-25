<?php
require_once dirname(__DIR__) . '/config/db.php';
requireAuth();

// Параметры поиска и фильтрации
$search = trim($_GET['q'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterPriority = $_GET['priority'] ?? '';

$statuses = ['Новый', 'В работе', 'На проверке', 'Закрыт'];
$priorities = ['Низкий', 'Средний', 'Высокий', 'Критический'];

// Формируем условия WHERE
$where = [];
$params = [];

if ($search !== '') {
    // Экранируем символы % и _ для безопасного LIKE
    $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    $where[] = "(b.title LIKE ? ESCAPE '\\' OR b.description LIKE ? ESCAPE '\\')";
    $params[] = "%{$escapedSearch}%";
    $params[] = "%{$escapedSearch}%";
}

if (in_array($filterStatus, $statuses, true)) {
    $where[] = "b.status = ?";
    $params[] = $filterStatus;
}

if (in_array($filterPriority, $priorities, true)) {
    $where[] = "b.priority = ?";
    $params[] = $filterPriority;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Настройки пагинации
$perPage = 10; // количество записей на одной странице
$page = max(1, (int)($_GET['page'] ?? 1));

// Подсчёт общего количества записей с учётом фильтров
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Bugs b {$whereSql}");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Основной запрос с LIMIT и OFFSET
$sql = "
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
    {$whereSql}
    ORDER BY b.bug_id DESC
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

$bugs = $stmt->fetchAll();
$flash = getFlash();

require dirname(__DIR__) . '/views/bugs/bugs.html';
