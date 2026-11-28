<?php
require_once __DIR__ . '/config/db.php';

echo "Заполнение базы демонстрационными данными...\n";

$passHash = password_hash('Password123', PASSWORD_DEFAULT);
$usersData = [
    ['Смирнов Алексей Викторович', 'admin@bughunter.local', $passHash, 'Администратор'],
    ['Козлов Дмитрий Сергеевич', 'dev1@bughunter.local', $passHash, 'Разработчик'],
    ['Морозова Елена Игоревна', 'dev2@bughunter.local', $passHash, 'Разработчик'],
    ['Волкова Анна Андреевна', 'qa1@bughunter.local', $passHash, 'Тестировщик'],
];

$userStmt = $pdo->prepare("INSERT OR IGNORE INTO Users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
foreach ($usersData as $u) {
    $userStmt->execute($u);
}

$adminId = (int)$pdo->query("SELECT user_id FROM Users WHERE email = 'admin@bughunter.local'")->fetchColumn();
$dev1Id  = (int)$pdo->query("SELECT user_id FROM Users WHERE email = 'dev1@bughunter.local'")->fetchColumn();
$dev2Id  = (int)$pdo->query("SELECT user_id FROM Users WHERE email = 'dev2@bughunter.local'")->fetchColumn();
$qa1Id   = (int)$pdo->query("SELECT user_id FROM Users WHERE email = 'qa1@bughunter.local'")->fetchColumn();

$projectsData = [
    ['SHOP', 'Интернет-магазин электроники', $dev1Id, $adminId],
    ['API',  'Платёжный шлюз и REST API', $dev2Id, $adminId],
    ['MOB',  'Мобильное приложение клиента', $dev1Id, $adminId],
];

$projStmt = $pdo->prepare("INSERT OR IGNORE INTO Projects (code, name, lead_id, owner_id) VALUES (?, ?, ?, ?)");
foreach ($projectsData as $p) {
    $projStmt->execute($p);
}

$shopId = (int)$pdo->query("SELECT project_id FROM Projects WHERE code = 'SHOP'")->fetchColumn();
$apiId  = (int)$pdo->query("SELECT project_id FROM Projects WHERE code = 'API'")->fetchColumn();
$mobId  = (int)$pdo->query("SELECT project_id FROM Projects WHERE code = 'MOB'")->fetchColumn();

$bugsData = [
    [$shopId, $qa1Id, $dev1Id, 'Ошибка 500 при оформлении заказа с промокодом', 'При вводе валидного промокода корзина падает с внутренней ошибкой сервера.', "1. Добавить товар\n2. Ввести промокод SALE20\n3. Нажать Оформить", 'Применяется скидка 20%', 'Сервер возвращает HTTP 500', 'В работе', 'Критический'],
    [$shopId, $qa1Id, $dev1Id, 'Не отображается превью товара в мобильной версии', 'На экранах шириной менее 375px картинка карточки товара сжимается до 0px.', "1. Открыть каталог с телефона\n2. Посмотреть карточку", 'Изображение масштабируется пропорционально', 'Картинка не видна', 'Новый', 'Средний'],
    [$apiId,  $qa1Id, $dev2Id, 'Утечка токена авторизации в логах вебхука', 'В открытом логе записывается заголовок Authorization: Bearer.', "1. Отправить тестовый платёж\n2. Открыть payment.log", 'Токен замаскирован звёздочками', 'Токен пишется открытым текстом', 'На проверке', 'Критический'],
    [$apiId,  $adminId, $dev2Id, 'Таймаут при запросе истории транзакций за год', 'Запрос без лимита записей выполняется дольше 30 секунд.', "1. Вызвать GET /api/v1/transactions", 'Ответ за < 500 мс с пагинацией', '504 Gateway Timeout', 'В работе', 'Высокий'],
    [$mobId,  $qa1Id, $dev1Id, 'Вылет приложения при загрузке аватара в формате WebP', 'Поддерживаются только JPG и PNG, выбор WebP вызывает краш.', "1. Открыть Профиль\n2. Загрузить .webp файл", 'Уведомление о неподдерживаемом формате', 'Аварийное закрытие приложения', 'Новый', 'Высокий'],
    [$shopId, $qa1Id, null,    'Опечатка в слове «Доставка» в подвале сайта', 'В футере главной страницы написано «Достака».', "1. Прокрутить главную страницу вниз", 'Слово «Доставка»', 'Слово «Достака»', 'Закрыт', 'Низкий'],
    [$mobId,  $qa1Id, $dev2Id, 'Не приходят push-уведомления о смене статуса заказа', 'Firebase возвращает ошибку невалидного токена устройства.', "1. Сменить статус заказа в админке", 'Push доставлен на устройство', 'Уведомление отсутствует', 'В работе', 'Высокий'],
    [$apiId,  $qa1Id, $dev2Id, 'Некорректный HTTP-статус при невалидном JSON', 'Возвращается 200 OK вместо 400 Bad Request.', "1. Отправить битый JSON в POST /orders", 'Код ответа 400', 'Код ответа 200', 'Закрыт', 'Средний'],
    [$shopId, $qa1Id, $dev1Id, 'Дублирование товаров при быстром двойном клике по кнопке «В корзину»', 'Отправляются два параллельных запроса без блокировки кнопки.', "1. Дважды кликнуть «Купить»", 'В корзину добавлена 1 единица товара', 'Добавлено 2 единицы товара', 'На проверке', 'Средний'],
    [$mobId,  $adminId, null,  'Тёмная тема не применяется на экране настроек', 'Фон остаётся белым, текст становится нечитаемым.', "1. Включить тёмную тему\n2. Открыть Настройки", 'Тёмный фон, светлый текст', 'Белый фон, светлый текст', 'Новый', 'Низкий'],
    [$shopId, $qa1Id, $dev1Id, 'Фильтр по цене сбрасывается при переходе на 2-ю страницу', 'Параметр max_price теряется в пагинации каталога.', "1. Задать цену до 5000\n2. Перейти на стр. 2", 'Фильтр сохраняется', 'Показываются все товары', 'В работе', 'Высокий'],
    [$apiId,  $qa1Id, $dev2Id, 'Отсутствует валидация отрицательной суммы возврата', 'Можно передать amount = -100 в метод refund.', "1. Вызвать POST /refund с amount: -100", 'Ошибка валидации 422', 'Запрос проходит успешно', 'Закрыт', 'Критический'],
];

$bugStmt = $pdo->prepare("
    INSERT INTO Bugs (project_id, reporter_id, assignee_id, title, description, steps, expected_result, actual_result, status, priority)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($bugsData as $b) {
    $bugStmt->execute($b);
}

$firstBugId = (int)$pdo->query("SELECT bug_id FROM Bugs ORDER BY bug_id DESC LIMIT 1")->fetchColumn();

$commStmt = $pdo->prepare("INSERT INTO Comments (bug_id, author_id, body) VALUES (?, ?, ?)");
$commStmt->execute([$firstBugId, $dev2Id, 'Проверил логи, проблема воспроизводится при передаче отрицательного значения в JSON.']);
$commStmt->execute([$firstBugId, $qa1Id, 'Подтверждаю, добавила в автотесты проверку граничных значений.']);

echo "Готово! Демонстрационные данные успешно загружены.\n";
echo "Логин администратора: admin@bughunter.local | Пароль: Password123\n";
