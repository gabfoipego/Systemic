FROM php:8.2-apache

# Extensao do MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Instala Composer via installer oficial
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Habilita mod_rewrite e porta 8081 para a Flowgate
RUN a2enmod rewrite && \
    echo "Listen 8081" >> /etc/apache2/ports.conf

# Configuracao dos VirtualHosts (Automax + Flowgate)
COPY apache.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80 8081