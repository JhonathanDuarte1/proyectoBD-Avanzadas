FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && printf 'expose_php=Off\nlog_errors=On\ndate.timezone=America/Bogota\n' > "$PHP_INI_DIR/conf.d/investiga.ini"

# Lista explícita: la imagen no recibe credenciales, respaldos ni archivos de la empresa.
COPY app/ /var/www/html/app/
COPY public/ /var/www/html/public/
WORKDIR /var/www/html
EXPOSE 80
