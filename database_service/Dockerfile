# Инструкция FROM задает базовый образ
FROM php:8.2-apache
# Инструкция COPY копирует файлы и директории
COPY . /var/www/html/
# Установка расширения PDO MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql
# Инструкция EXPOSE описывает какие порты прослушивает приложение
EXPOSE 80

