<?php
ob_start();
session_start();
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

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$data = $input ?: $_POST;

$isPostman = (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Postman') !== false);

// --- 1. ЛОГИКА УДАЛЕНИЯ ---
if (isset($_GET['delete_id'])) {
    include 'db_connect.php';
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);

    $ch = curl_init($db_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $_GET['delete_id']]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_exec($ch);
    curl_close($ch);

    header("Location: index.php");
    exit;
}

require_once 'db_connect.php';
// --- 2. ОБРАБОТКА ВВОДА ---
if ($method === 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $password = $data['new_password'] ?? $data['password'] ?? $_POST['password'] ?? null;
    $id = $data['id'] ?? null;
    $action = $data['action'] ?? null;

    if (!$password || strlen($password) < 16) {
        http_response_code(418);
        echo json_encode(["message" => "🫖 Пароль слишком короткий (мин. 16 символов)!", "type" => "error"]);
        exit;
    }

    $hash = hash('sha256', $password);

    try {
        if ($id && $action === 'update') {
            // --- ЛОГИКА ОБНОВЛЕНИЯ ---
            $check = $pdo->prepare("SELECT id FROM tasks WHERE hash = ? AND id != ?");
            $check->execute([$hash, $id]);
            if ($check->fetch()) {
                http_response_code(409);
                echo json_encode(["message" => "⚠️ Этот пароль уже используется в другой записи!", "type" => "error"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, hash = ? WHERE id = ?");
            $stmt->execute([$hash, $hash, $id]);
            $res_message = "✅ Данные успешно изменены!";
        } else {
            // --- ЛОГИКА ДОБАВЛЕНИЯ ---
            $check = $pdo->prepare("SELECT id FROM tasks WHERE hash = ?");
            $check->execute([$hash]);
            if ($check->fetch()) {
                http_response_code(409);
                echo json_encode(["message" => "⚠️ Этот пароль уже зарегистрирован!", "type" => "error"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO tasks (title, hash) VALUES (?, ?)");
            $stmt->execute([$hash, $hash]);
            $res_message = "✅ Пароль успешно добавлен!";
        }

        $ch = curl_init($db_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $id ? "PUT" : "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['id' => $id, 'hash' => $hash]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);

        echo json_encode(["message" => $res_message, "type" => "success"]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "❌ Ошибка БД: " . $e->getMessage(), "type" => "error"]);
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

/* if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $message = "✅ Письмо успешно отправлено!";
    $message_type = "success";
} elseif (isset($_GET['status']) && $_GET['status'] === 'error') {
    $message = "❌ Ошибка при отправке почты";
    $message_type = "error";
} */

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

    <?php if (isset($_SESSION['flash_msg'])): ?>
        <div class="alert <?php echo $_SESSION['flash_type']; ?>">
            <?php
            echo $_SESSION['flash_msg'];
            unset($_SESSION['flash_msg']);
            unset($_SESSION['flash_type']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($message) && !isset($_SESSION['mail_status'])): ?>
        <div class="alert <?php echo ($message_type === 'success') ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form id="regForm" onsubmit="event.preventDefault(); registerPassword();">
        <input type="password" id="passInput" placeholder="Минимум 16 символов..." required>
        <button type="submit">Добавить</button>
    </form>


    <table>
        <thead>
        <tr>
            <th style="width: 15%;">ID</th>
            <th style="width: 35%;">Действие</th>
            <th style="width: 50%; text-align: center;">Отправить Email</th>
        </tr>
        </thead>
        <tbody>
        <?php include 'get_tasks_partial.php'; ?>
        </tbody>
    </table>
</div>

<script>
    const clientId = "client_" + Math.random().toString(36).substr(2, 9);
    window.socket = new WebSocket('ws://localhost:8080');

    socket.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            if (data.senderId === clientId) return;

            if (data.action === 'delete') {
                const row = document.getElementById('task-' + data.taskId);
                if (row) row.remove();
            }
            else {
                updateTableData();
            }
        } catch (e) { console.error("Ошибка:", e); }
    };


    function registerPassword() {
        const password = document.getElementById('passInput').value;

        if (password.length < 16) {
            alert("Пароль слишком короткий!");
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                password: password
            })
        })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json().then(data => {
                        if (!response.ok) {
                            alert(data.message || "Ошибка сервера");
                        } else {
                            alert(data.message);
                            document.getElementById('passInput').value = '';
                            sendRefreshSignal();
                        }
                    });
                } else {
                    return response.text().then(text => {
                        console.error("Сервер вернул не JSON:", text);
                        alert("Ошибка на стороне сервера. Проверьте консоль.");
                    });
                }
            })
            .catch(err => {
                console.error("Ошибка запроса:", err);
                alert("Не удалось связаться с сервером.");
            });
    }

    function sendRefreshSignal() {
        if (window.socket && window.socket.readyState === WebSocket.OPEN) {
            window.socket.send(JSON.stringify({
                action: 'add',
                senderId: clientId
            }));
        }
        updateTableData();
    }

    function deleteAndRefresh(taskId) {
        if (!confirm('Удалить запись #' + taskId + '?')) return;

        const row = document.getElementById('task-' + taskId);
        if (row) row.style.opacity = '0.5';

        fetch('?delete_id=' + taskId)
            .then(response => {
                if (response.ok) {
                    if (row) row.remove();

                    alert("✅ Запись #" + taskId + " успешно удалена!");

                    if (window.socket && window.socket.readyState === WebSocket.OPEN) {
                        window.socket.send(JSON.stringify({
                            action: 'delete',
                            taskId: taskId,
                            senderId: clientId
                        }));
                    }
                } else {
                    alert("❌ Ошибка при удалении");
                    if (row) row.style.opacity = '1';
                }
            })
            .catch(err => {
                console.error("Ошибка:", err);
                if (row) row.style.opacity = '1';
            });
    }
    function updateTableData() {
        fetch('get_tasks_partial.php')
            .then(response => response.text())
            .then(html => {
                const tbody = document.querySelector('table tbody');
                if (tbody) {
                    tbody.innerHTML = html;
                    console.log("Таблица обновлена синхронно");
                }
            });
    }
</script>

</body>
</html>