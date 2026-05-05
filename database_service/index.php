<?php
header("Content-Type: application/json");

// 1. Настройки подключения к MySQL
$host = 'mysql_db';      // Имя контейнера, которое вы указали в --name
$db   = 'todo_db';       // Имя базы из -e MYSQL_DATABASE
$user = 'root';          // Пользователь по умолчанию
$pass = 'root_pass';     // Пароль из -e MYSQL_ROOT_PASSWORD

try {
    // Подключаемся к базе данных
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 2. Автоматическое создание таблицы при первом запуске
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_hash VARCHAR(64) NOT NULL UNIQUE,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Получение одной задачи по ID
                $stmt = $pdo->prepare("SELECT * FROM user_tasks WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                $result = $stmt->fetch();
                echo json_encode($result ?: ["error" => "ID not found"]);
            } else {
                // Получение всех задач
                $stmt = $pdo->query("SELECT * FROM user_tasks");
                echo json_encode($stmt->fetchAll());
            }
            break;

        case 'PUT':
            // Проверяем наличие ID и хотя бы одного поля для обновления (хеш или статус)
            if (isset($input['id']) && (isset($input['hash']) || isset($input['status']))) {
                $id = (int)$input['id'];

                // Получаем текущие данные, чтобы не затереть их, если пришло только одно поле
                $stmt = $pdo->prepare("SELECT user_hash, status FROM user_tasks WHERE id = ?");
                $stmt->execute([$id]);
                $current = $stmt->fetch();

                if ($current) {
                    $new_hash = isset($input['hash']) ? trim($input['hash']) : $current['user_hash'];
                    $new_status = isset($input['status']) ? $input['status'] : $current['status'];

                    try {
                        $update = $pdo->prepare("UPDATE user_tasks SET user_hash = ?, status = ? WHERE id = ?");
                        $update->execute([$new_hash, $new_status, $id]);
                        echo json_encode(["status" => "updated"]);
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Если новый хеш совпал с уже существующим в другой строке
                            http_response_code(409);
                            echo json_encode(["error" => "Duplicate hash", "message" => "Этот пароль уже занят."]);
                        } else { throw $e; }
                    }
                } else {
                    http_response_code(404);
                    echo json_encode(["error" => "ID not found"]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["error" => "Missing ID or data to update"]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['hash'])) {
                $hash = $input['hash'];

                // Проверка на дубликат (чтобы вернуть 409)
                $check = $pdo->prepare("SELECT id FROM user_tasks WHERE user_hash = ?");
                $check->execute([$hash]);
                if ($check->fetch()) {
                    http_response_code(409);
                    echo json_encode(["error" => "Duplicate hash"]);
                    exit;
                }

                // Вставка новой записи
                $stmt = $pdo->prepare("INSERT INTO user_tasks (user_hash) VALUES (?)");
                if ($stmt->execute([$hash])) {
                    http_response_code(201); // Успешно создано
                    echo json_encode(["status" => "created", "id" => $pdo->lastInsertId()]);
                } else {
                    http_response_code(500);
                    echo json_encode(["error" => "DB Error"]);
                }
            } else {
                http_response_code(400);
                echo json_encode(["error" => "No hash provided"]);
            }
            break;

        case 'DELETE':
            if (isset($input['id'])) {
                $stmt = $pdo->prepare("DELETE FROM user_tasks WHERE id = ?");
                $stmt->execute([(int)$input['id']]);

                if ($stmt->rowCount() > 0) {
                    echo json_encode(["status" => "deleted"]);
                } else {
                    http_response_code(404);
                    echo json_encode(["error" => "ID not found"]);
                }
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed"]);
    }

} catch (PDOException $e) {
    // Если база еще не загрузилась или данные неверны
    http_response_code(500);
    echo json_encode([
        "error" => "DB Error",
        "details" => $e->getMessage()
    ]);
}
