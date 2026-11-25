<?php
ob_start();
session_start();

$dbFile = dirname(__DIR__) . '/database/bughunter.db';

try {
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Автоматическое добавление новых полей ТЗ в таблицу Bugs при их отсутствии
    $cols = array_column($pdo->query("PRAGMA table_info(Bugs)")->fetchAll(), 'name');
    $requiredCols = [
        'description'     => "TEXT NOT NULL DEFAULT ''",
        'steps'           => "TEXT NOT NULL DEFAULT ''",
        'expected_result' => "TEXT NOT NULL DEFAULT ''",
        'actual_result'   => "TEXT NOT NULL DEFAULT ''",
    ];
    foreach ($requiredCols as $colName => $colDef) {
        if (!in_array($colName, $cols, true)) {
            $pdo->exec("ALTER TABLE Bugs ADD COLUMN {$colName} {$colDef}");
        }
    }
} catch (PDOException $e) {
    error_log("DB Connection/Migration Error: " . $e->getMessage());
    http_response_code(500);
    die("Ошибка подключения к базе данных. Обратитесь к администратору.");
}

function requireAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('403 Forbidden: Недействительный CSRF-токен.');
    }
}

function canManageProject(PDO $pdo, array $project): bool {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId === 0) {
        return false;
    }
    if ((int)$project['owner_id'] === $userId) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT role FROM Users WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() === 'Администратор';
}

function canManageBug(PDO $pdo, array $bug): bool {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId === 0) {
        return false;
    }

    if ((int)$bug['reporter_id'] === $userId || (int)($bug['assignee_id'] ?? 0) === $userId) {
        return true;
    }

    $projStmt = $pdo->prepare("SELECT owner_id FROM Projects WHERE project_id = ?");
    $projStmt->execute([(int)$bug['project_id']]);
    if ((int)$projStmt->fetchColumn() === $userId) {
        return true;
    }

    $roleStmt = $pdo->prepare("SELECT role FROM Users WHERE user_id = ?");
    $roleStmt->execute([$userId]);
    return $roleStmt->fetchColumn() === 'Администратор';
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
