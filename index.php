<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$correct_password_hash = "b3611833086b6fb3e903fbf65b42d8dcda690bce85c4008c1887cf164a4aa3e0";
$error = "";
$access_granted = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pass_input = trim($_POST['password'] ?? '');

    $has_uppercase = preg_match('@[A-Z]@', $pass_input);
    $has_number    = preg_match('@[0-9]@', $pass_input);
    $has_special   = preg_match('@[^\w]@', $pass_input);
    $is_long_enough = strlen($pass_input) >= 16;

    if (!$is_long_enough || !$has_uppercase || !$has_number || !$has_special) {
        $error = "Пароль не соответствует требованиям безопасности!";
    } else {

        if (hash('sha256', $pass_input) === $correct_password_hash) {
            $access_granted = true;

            $db_url = "http://database_service/index.php";
            $payload = json_encode(['hash' => hash('sha256', $pass_input)]);

            $ch = curl_init($db_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);

            $result = curl_exec($ch);

        } else {
            $error = "Неверный пароль!";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход с проверкой сложности</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; display: flex; justify-content: center; padding-top: 50px; }
        .card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1); width: 350px; }
        .requirements { font-size: 0.85em; color: #666; text-align: left; margin-bottom: 15px; background: #f8f9fa; padding: 10px; border-radius: 6px; }
        input[type="password"] { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #0056b3; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; transition: 0.3s; }
        button:hover { background: #004494; }
        .error { color: #d93025; font-size: 14px; margin-bottom: 10px; font-weight: bold; }
        .success { color: #1e8e3e; }
    </style>
</head>
<body>

<div class="card">
    <?php if ($access_granted): ?>
        <h2 class="success">Доступ получен!</h2>
        <p>Вы успешно прошли проверку сложности и хеша.</p>
        <a href="index.php">Выйти</a>
    <?php else: ?>
        <h2>Введите пароль</h2>

        <div class="requirements">
            <strong>Требования к паролю:</strong>
            <ul>
                <li>Не менее 16 символов</li>
                <li>Минимум одна заглавная буква</li>
                <li>Минимум одна цифра</li>
                <li>Минимум один спецсимвол (!@#$%...)</li>
            </ul>
        </div>

        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="password" name="password" placeholder="Ваш надежный пароль" required autofocus>
            <button type="submit">Войти</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>