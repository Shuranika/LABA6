<?php
$db_url = "http://database_service/index.php";
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Получаем данные через cURL (безопаснее для Docker)
$ch = curl_init($db_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$tasks = json_decode($response, true) ?: [];
$current_task = null;

// Ищем нужную задачу по ID в общем списке
foreach ($tasks as $t) {
    if ($t['id'] == $id) {
        $current_task = $t;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Details<?php echo htmlspecialchars($id); ?></title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 40px; display: flex; justify-content: center; }
        .details-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { color: #1a73e8; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .field { margin: 20px 0; }
        .label { font-weight: bold; color: #666; font-size: 13px; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .value { background: #f8f9fa; padding: 12px; border-radius: 6px; display: block; font-family: monospace; border: 1px solid #eee; word-break: break-all; }
        .back-link { display: block; margin-top: 20px; text-align: center; color: #1a73e8; text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="details-card">
    <?php if ($current_task): ?>
        <h2>Пароль пользователя №<?php echo htmlspecialchars($current_task['id']); ?></h2>

        <div class="field">
            <span class="label">ID записи</span>
            <span class="value"><?php echo htmlspecialchars($current_task['id']); ?></span>
        </div>

        <div class="field">
            <span class="label">Полный хеш пароля (SHA-256)</span>
            <span class="value"><?php echo htmlspecialchars($current_task['user_hash']); ?></span>
        </div>

    <?php else: ?>
        <h2 style="color: #dc2626;">Ошибка</h2>
        <p>Задача с таким ID не найдена.</p>
    <?php endif; ?>

    <a href="index.php" class="back-link">← Вернуться к списку</a>
</div>

</body>
</html>