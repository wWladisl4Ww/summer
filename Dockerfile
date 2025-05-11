# Используем образ PHP с Apache
FROM php:8.2-apache

# Устанавливаем необходимые зависимости для PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql

# Настраиваем PHP для отключения уведомлений
RUN echo "error_reporting = E_ERROR | E_PARSE" >> /usr/local/etc/php/php.ini
