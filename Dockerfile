# Imagen de la API para desplegar en Render (o cualquier servicio que ejecute contenedores).
#
#   docker build -t pawprints-api .
#   docker run --rm -p 8000:80 --env-file .env pawprints-api      -> http://localhost:8000/api/libros
#
# Etapa 1: descargar las dependencias de Composer (sin las de desarrollo).
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --no-autoloader: el autoload se genera en la etapa final, cuando ya está el código de app/.
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-autoloader --no-scripts


# Etapa 2: PHP + Apache, que es lo que queda en la imagen final.
FROM php:8.4-apache

# Extensiones: curl, mbstring y openssl ya vienen en la imagen oficial.
# pdo_mysql queda instalada para la base de datos (Aiven) de la próxima entrega.
RUN docker-php-ext-install pdo_mysql

# Configuración de PHP para producción: no muestra errores en pantalla ni expone la versión
# (expose_php=Off). La aplicación igual maneja sus errores (ErrorHandler).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apache:
# - mod_rewrite: el .htaccess de public/ manda todas las peticiones a index.php (front controller).
# - El sitio (docker/apache-site.conf) solo expone public/: .env, storage/, vendor/ y el código
#   quedan fuera del alcance de Internet.
# - ServerTokens Prod / ServerSignature Off: Apache no revela su versión (consigna 06).
RUN a2enmod rewrite \
 && sed -ri 's/^ServerTokens .*/ServerTokens Prod/; s/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf
COPY docker/apache-ports.conf /etc/apache2/ports.conf
COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf

# Render indica en la variable PORT en qué puerto tiene que escuchar el servidor (Apache la lee
# al arrancar: "Listen ${PORT}"). 80 si se ejecuta en otro lado.
ENV PORT=80

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .

# Autoload optimizado (mapa de clases) y permisos de escritura para la app (usuario de Apache):
# storage/data (users.json) y storage/logs.
RUN composer dump-autoload --no-dev --optimize --no-interaction \
 && rm /usr/local/bin/composer \
 && chown -R www-data:www-data storage

EXPOSE 80
# El comando de inicio es el de la imagen oficial: apache2-foreground.
