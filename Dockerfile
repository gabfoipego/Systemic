FROM php:8.2-apache

# Extensão do MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilita mod_rewrite
RUN a2enmod rewrite

# Configuração do VirtualHost — DocumentRoot na raiz do frontend
# FallbackResource substitui o .htaccess para redirecionar tudo ao index.php
COPY apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80
