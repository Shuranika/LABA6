<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$db_url = "http://database_service/index.php";
$method = $_SERVER['REQUEST_METHOD'];
$message = "";
$message_type = "";
$config = require 'config.php';

// БЕЗОПАСНАЯ ПРОВЕРКА ПОЧТЫ (IMAP)
$mail_count = 0;
if (isset($config['imap']) && is_array($config['imap'])) {
    $mbox = @imap_open($config['imap']['host'], $config['imap']['user'], $config['imap']['pass']);

    if ($mbox) {
        $check = imap_check($mbox);
        $mail_count = $check->Nmsgs;
        imap_close($mbox);
    } else {
        $message = "⚠️ Не удалось подключиться к IMAP";
        $message_type = "error";
    }
} else {
    $message = "❌ Ошибка: Секция 'imap' не найдена в config.php";
    $message_type = "error";
}

// Читаем входные данные (JSON или POST)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$data = $input ?: $_POST;

$isPostman = (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Postman') !== false);

// --- 1. ЛОГИКА УДАЛЕНИЯ (БЕЗ сломанного PHP-сокета) ---
if (isset($_GET['delete_id'])) {
    $ch = curl_init($db_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $_GET['delete_id']]));
    curl_exec($ch);
    curl_close($ch);

    if ($isPostman) {
        header("Content-Type: application/json");
        echo json_encode(["message" => "Deleted successfully"]);
        exit;
    }

    header("Location: index.php");
    exit;
}

// --- 2. ОБРАБОТКА ВВОДА (POST - Регистрация) ---
if ($method === 'POST' && isset($data['password']) && !isset($data['action'])) {
    $password = $data['password'];

    if (strlen($password) < 16) {
        http_response_code(418);
        $message = "🫖 Слишком короткий пароль для регистрации!";
        $message_type = "error";
    } else {
        $hash = hash('sha256', $password);
        $ch = curl_init($db_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['hash' => $hash]));
        $result = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 201) {
            $message = "✅ Надежный пароль успешно сохранен в базе.";
            $message_type = "success";
        } elseif ($http_code === 409) {
            $message = "⚠️ Этот пароль уже был зарегистрирован ранее.";
            $message_type = "error";
        } else {
            $message = "❌ Ошибка базы данных (Код: $http_code)";
            $message_type = "error";
        }

        if ($isPostman) {
            header("Content-Type: application/json");
            echo json_encode(["message" => $message, "type" => $message_type]);
            exit;
        }
    }
}

if (($method === 'PUT') || ($method === 'POST' && isset($data['action']) && $data['action'] === 'update')) {
    $id = $data['id'] ?? null;
    $new_password = $data['new_password'] ?? null;

    if (!$id || !$new_password || strlen($new_password) < 16) {
        http_response_code(418);
        $message = "🫖 Ошибка данных или пароль короче 16 символов!";
        $message_type = "error";
    } else {
        $new_hash = hash('sha256', $new_password);
        $ch = curl_init($db_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $id, 'hash' => $new_hash]));
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $message = "✅ Хеш успешно изменен!";
            $message_type = "success";
        } else {
            $message = "❌ Ошибка обновления (Код: $http_code)";
            $message_type = "error";
        }
    }

    if ($isPostman) {
        header("Content-Type: application/json");
        echo json_encode(["message" => $message, "type" => $message_type]);
        exit;
    }
}

// --- 4. ПОЛУЧЕНИЕ СПИСКА ДЛЯ ТАБЛИЦЫ ---
$ch_get = curl_init($db_url);
curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_get, CURLOPT_TIMEOUT, 5);
$all_tasks_raw = curl_exec($ch_get);
$get_status = curl_getinfo($ch_get, CURLINFO_HTTP_CODE);
curl_close($ch_get);

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = "✅ Письмо успешно отправлено!";
    $message_type = "success";
} elseif (isset($_GET['status']) && $_GET['status'] === 'error') {
    $message = "❌ Ошибка при отправке почты";
    $message_type = "error";
}

if ($all_tasks_raw === false || $get_status !== 200) {
    $tasks = [];
    if (empty($message)) {
        $message = "❌ Ошибка связи с базой данных (HTTP: $get_status)";
        $message_type = "error";
    }
} else {
    $tasks = json_decode($all_tasks_raw, true) ?: [];
}

if ($method === 'GET' && $isPostman) {
    header("Content-Type: application/json");
    echo $all_tasks_raw;
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Registration page</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 40px; color: #333; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h2 { text-align: center; color: #1a73e8; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500; }
        .error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        form { display: flex; gap: 10px; margin-bottom: 30px; }
        input { flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 8px; outline: none; transition: border-color 0.3s; }
        input:focus { border-color: #1a73e8; }
        button { padding: 12px 20px; background: #1a73e8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #eee; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .btn-delete { color: #dc2626; text-decoration: none; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <h2>🔐 Регистрация</h2>

    <?php if (!empty($message)): ?>
        <div class="alert <?php echo ($message_type === 'success') ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" onsubmit="sendRefreshSignal()">
        <input type="password" name="password" placeholder="Минимум 16 символов..." required>
        <button type="submit">Добавить</button>
    </form>

    <table>
        <thead>
        <tr>
            <th style="width: 10%;">ID</th>
            <th style="width: 30%;">Действие</th>
            <th style="width: 60%; text-align: center;">Отправить Email</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($tasks)): ?>
            <tr><td colspan="3" style="text-align:center;">Список пуст или база недоступна</td></tr>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td>
                        <a href="view.php?id=<?php echo $task['id']; ?>"
                           style="color: #1a73e8; font-weight: bold; text-decoration: none;">
                            #<?php echo htmlspecialchars($task['id']); ?>
                        </a>
                    </td>

                    <td style="white-space: nowrap;">
                        <a href="edit.php?id=<?php echo $task['id']; ?>"
                           style="color: #1a73e8; text-decoration: none;" onclick="sendRefreshSignal()">Изменить</a>
                        <span style="color: #ccc; margin: 0 5px;">|</span>
                        <a href="#"
                           class="btn-delete"
                           onclick="event.preventDefault(); deleteAndRefresh(<?php echo $task['id']; ?>);">Удалить</a>
                    </td>

                    <td style="text-align: right; padding: 10px;">
                        <form action="mail_handler.php" method="POST" style="display:flex; flex-direction:column; gap:8px; margin:0; width: 220px; margin-left: auto;">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <input type="hidden" name="task_hash" value="<?= $task['user_hash'] ?>">

                            <input type="email" name="target_email" placeholder="Кому (Email)..." required
                                   style="padding: 8px; border: 2px solid #ddd; border-radius: 8px; font-size: 12px;">

                            <textarea name="custom_message" placeholder="Ваше сообщение..."
                                      style="padding: 8px; border: 2px solid #ddd; border-radius: 8px; font-size: 12px; resize: none; height: 50px; font-family: inherit;"></textarea>

                            <button type="submit" name="send_email"
                                    style="padding: 8px; background: #1a73e8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: background 0.3s;">
                                Отправить письмо
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    const clientId = "client_" + Math.random().toString(36).substr(2, 9);

    window.socket = new WebSocket('ws://localhost:8080');

    window.socket.onopen = function() {
        console.log("Соединение установлено. ID клиента: " + clientId);
    };

    window.socket.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            if (data.action === 'refresh' && data.senderId !== clientId) {
                console.log("Получен сигнал от другого окна. Обновляюсь без кэша...");
                window.location.href = window.location.pathname + '?t=' + Date.now();
            }
        } catch (e) {
            console.error("Ошибка при получении данных:", e);
        }
    };

    window.socket.onerror = function(error) {
        console.log("Сервер сокетов недоступен (порт 8080 отключен).");
    };

    function sendRefreshSignal() {
        if (window.socket && window.socket.readyState === WebSocket.OPEN) {
            window.socket.send(JSON.stringify({
                action: 'refresh',
                senderId: clientId
            }));
            console.log("Сигнал отправлен на сервер сокетов");
        }
        return true;
    }

    function deleteAndRefresh(taskId) {
        if (!confirm('Удалить запись # ' + taskId + '?')) {
            return;
        }

        fetch('?delete_id=' + taskId)
            .then(response => {
                console.log("Запись удалена из базы.");
                if (window.socket && window.socket.readyState === WebSocket.OPEN) {
                    window.socket.send(JSON.stringify({
                        action: 'refresh',
                        senderId: clientId
                    }));
                }
                setTimeout(() => {
                    window.location.href = window.location.pathname + '?t=' + Date.now();
                }, 150);
            })
            .catch(error => {
                console.error("Ошибка при удалении:", error);
            });
    }
</script>

</body>
</html>