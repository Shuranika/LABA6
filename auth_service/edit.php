<?php
$db_url = "http://database_service/index.php";
$id = $_GET['id'] ?? null;
$message = "";
$message_type = "";

if (!$id) {
    header("Location: index.php");
    exit;
}

// --- ОБРАБОТКА ОБНОВЛЕНИЯ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];

    if (strlen($new_password) < 16) {
        http_response_code(418);
        $message = "🫖 Даже при обновлении нужно минимум 16 символов!";
        $message_type = "error";
    } else {
        $new_hash = hash('sha256', $new_password);

        include 'db_connect.php';
        try {
            $stmt = $pdo->prepare("UPDATE tasks SET title = ?, hash = ? WHERE id = ?");
            $stmt->execute([$new_hash, $new_hash, $id]);

        } catch (Exception $e) {
        }


        $update_payload = json_encode([
                'id' => $id,
                'hash' => $new_hash
        ]);

        $ch = curl_init($db_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 || $http_code === 204) {
            header("Location: index.php?update=success");
            exit;
        } else {
            header("Location: index.php?update=success");
            exit;
        }
    }
}

$current_data = json_decode(@file_get_contents($db_url . "?id=" . $id), true);
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Изменить пароль<?php echo $id; ?></title>
    <style>
        /* Копируем стили из index.php */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; padding: 40px; color: #333; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h2 { text-align: center; color: #1a73e8; margin-top: 0; }

        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; }
        .error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        .edit-form { display: flex; flex-direction: column; gap: 15px; }
        label { font-weight: bold; font-size: 14px; color: #555; }
        input { padding: 12px; border: 2px solid #ddd; border-radius: 8px; outline: none; transition: border-color 0.3s; width: 100%; box-sizing: border-box; }
        input:focus { border-color: #1a73e8; }

        .btn-save { padding: 12px; background: #1a73e8; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .btn-save:hover { background: #1557b0; }

        .back-link { display: block; margin-top: 20px; text-align: center; color: #1a73e8; text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }

        .info-box { background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 0px; font-size: 13px; color: #666; border-left: 4px solid #1a73e8; }

        .field { margin: 20px 0; }
        .label { font-weight: bold; color: #666; font-size: 13px; text-transform: uppercase; display: block; margin-bottom: 5px; }
    </style>
</head>

<script>
    const clientId = "client_" + Math.random().toString(36).substr(2, 9);
    window.socket = new WebSocket('ws://localhost:8080');

    function updatePassword() {
        const taskId = document.getElementById('editTaskId').value;
        const newPassword = document.getElementById('editPassInput').value;

        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update',
                id: taskId,
                new_password: newPassword
            })
        })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw err; });
                }
                return response.json();
            })
            .then(data => {
                if (data.type === 'success') {
                    if (window.socket && window.socket.readyState === WebSocket.OPEN) {
                        window.socket.send(JSON.stringify({
                            action: 'refresh',
                            senderId: clientId
                        }));
                        console.log("Сигнал обновления отправлен в сокет");
                    }

                    alert(data.message);
                    window.location.href = 'index.php';
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                alert(err.message || "Ошибка при обновлении");
                console.error(err);
            });
    }
</script>

<body>
<div class="container">
    <h2>🔄 Изменить пароль</h2>

    <div class="info-box">
        Вы редактируете запись <strong>ID #<?php echo $id; ?></strong><br>
    </div>

    <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form id="editForm" onsubmit="event.preventDefault(); updatePassword();" class="edit-form">
        <input type="hidden" id="editTaskId" value="<?php echo htmlspecialchars($id); ?>">

        <div class="field">
            <span class="label">Введите новый пароль: </span>
            <input type="password" id="editPassInput" name="new_password" placeholder="Минимум 16 символов..." required minlength="16" autofocus>
        </div>
        <button type="submit" class="btn-save">Сохранить изменения</button>
    </form>

    <a href="index.php" class="back-link">← Вернуться к списку</a>
</div>

</body>
</html>