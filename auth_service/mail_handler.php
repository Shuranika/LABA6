<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'libs/Exception.php';
require 'libs/PHPMailer.php';
require 'libs/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$config = require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $mail = new PHPMailer(true);

    try {
        // --- НАСТРОЙКИ SMTP ---
        $mail->isSMTP();
        $mail->Host       = $config['smtp']['host'];
        $mail->Port       = $config['smtp']['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp']['user'];
        $mail->Password   = $config['smtp']['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Для порта 465
        $mail->CharSet    = 'UTF-8';

        // --- ПОЛУЧЕНИЕ ДАННЫХ ИЗ ФОРМЫ ---
        $target_email = $_POST['email'];
        $custom_text  = $_POST['message_text'] ?? '';
        $task_id      = $_POST['task_id'] ?? 'N/A';

        // --- ПОЛУЧЕНИЕ ХЕША ИЗ БАЗЫ ДАННЫХ ---
        include 'db_connect.php';
        $stmt = $pdo->prepare("SELECT hash FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        $task_hash = $task ? $task['hash'] : '[Хеш не найден]';

        // --- ФОРМИРОВАНИЕ ПИСЬМА ---
        $mail->setFrom($config['smtp']['user'], 'ToDo Manager');
        $mail->addAddress($target_email);

        $mail->Subject = "Данные из ToDo Manager (Задача #" . $task_id . ")";

        $mail->Body = "Привет! \n\n" .
            "Сообщение: " . $custom_text . "\n\n" .
            "Технический хеш задачи: " . $task_hash . "\n\n" .
            "С уважением, Ваш ToDo сервис.";

        // --- ОТПРАВКА ---
        $mail->send();

        $_SESSION['mail_status'] = 'success';
        header("Location: index.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['mail_status'] = 'error';
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}