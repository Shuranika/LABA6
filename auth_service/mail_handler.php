<?php
require 'libs/Exception.php';
require 'libs/PHPMailer.php';
require 'libs/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$config = require 'config.php';

if (isset($_POST['send_email'])) {
    $mail = new PHPMailer(true);
    try {
        // Настройки сервера
        $mail->isSMTP();
        $mail->Host       = $config['smtp']['host'];
        $mail->Port       = $config['smtp']['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp']['user'];
        $mail->Password   = $config['smtp']['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet    = 'UTF-8';

        // Подготовка данных
        $custom_text = isset($_POST['custom_message']) ? $_POST['custom_message'] : '';
        $task_hash   = $_POST['task_hash'];
        $task_id     = $_POST['task_id'];

        // Формирование письма
        $mail->setFrom($config['smtp']['user'], 'ToDo Manager');
        $mail->addAddress($_POST['target_email']);

        $mail->Subject = "Данные из ToDo Manager (Задача #" . $task_id . ")";

        $mail->Body    = "Привет! \n\n" .
            "Сообщение: " . $custom_text . "\n\n" .
            "Технический хеш задачи: " . $task_hash . "\n\n" .
            "С уважением, Ваш ToDo сервис.";

        // Отправка
        $mail->send();

        header("Location: index.php?status=success");
        exit();

    } catch (Exception $e) {
        header("Location: index.php?status=error");
        exit();
    }
}