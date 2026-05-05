# Инструкция FROM задает базовый образ
FROM php:8.2-apache-bullseye
# Установка системных библиотек для IMAP
RUN apt-get update && apt-get install -y \
    libc-client-dev \
    libkrb5-dev \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*
# Конфигурирация и устанавливка расширений IMAP, MySQL
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl
# Инструкция COPY копирует файлы и директории
COPY . /var/www/html/
# Установка расширения PDO MySQL
RUN docker-php-ext-install imap mysqli pdo pdo_mysql sockets
# Права для Apache для чтения файлов
RUN chown -R www-data:www-data /var/www/html/
# Инструкция EXPOSE описывает какие порты прослушивает приложение
EXPOSE 80
EXPOSE 8080




