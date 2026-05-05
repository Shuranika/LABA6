<?php
$db_url = "http://database_service/index.php";
$id = $_GET['id'] ?? null;
$message = "";
$message_type = "";

if (!$id) {
    header("Location: index.php");
    exit;
}

// --- ОБРАБОТКА ОБНОВЛЕНИЯ (PUT через POST формы) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_password = $_POST['new_password'];

    // 1. Проверка на "Чайника" и длину
    if (strlen($new_password) < 16) {
        http_response_code(418);
        $message = "🫖 I'm a teapot. Даже при обновлении нужно минимум 16 символов!";
        $message_type = "error";

        if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Postman') !== false) {
            header("Content-Type: application/json");
            echo json_encode(["error" => "Insecure update", "message" => $message]);
            exit;
        }
    } else {
        // 2. Подготовка данных для Database Service
        $new_hash = hash('sha256', $new_password);
        $update_payload = json_encode([
                'id' => $id, // ID берем из GET-параметра страницы
                'hash' => $new_hash
        ]);

        $ch = curl_init($db_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Превращаем запрос в PUT
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_payload);

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 3. Анализ ответа от базы и редирект
        if ($http_code === 200) {
            // Если успех — лучше сразу уйти на главную с флагом успеха
            header("Location: index.php?update=success");
            exit;
        } elseif ($http_code === 409) {
            $message = "⚠️ Ошибка: такой пароль уже кем-то используется!";
            $message_type = "error";
        } else {
            $message = "❌ Ошибка базы данных (Код: $http_code).";
            $message_type = "error";
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
<body>

<div class="container">
    <h2>🔄 Изменить пароль</h2>

    <div class="info-box">
        Вы редактируете запись <strong>ID #<?php echo $id; ?></strong><br>
    </div>

    <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" class="edit-form">
        <div class="field">
            <span class="label">Введите новый пароль: </span>
            <input type="password" name="new_password" placeholder="Минимум 16 символов..." required minlength="16" autofocus>
        </div>
        <button type="submit" class="btn-save">Сохранить изменения</button>
    </form>

    <a href="index.php" class="back-link">← Вернуться к списку</a>
</div>

</body>
</html>