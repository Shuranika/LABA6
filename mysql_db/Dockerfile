# Инструкция FROM задает базовый образ
FROM mysql:8.0
# Все .sql файлы в этой папке Docker выполнит сам при первом старте
COPY ./init.sql /docker-entrypoint-initdb.d/

