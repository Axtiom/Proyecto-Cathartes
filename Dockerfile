# Cathartes — imagen web: Apache + PHP 8.3 con las 4 apps y los 2 frontends.
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"   # display_errors = Off

COPY docker/cathartes.conf /etc/apache2/conf-available/cathartes.conf
RUN a2enconf cathartes

COPY . /var/www/html

# Carpeta de fotos de incidencias: fuera de public/, escribible solo por Apache.
RUN mkdir -p /var/www/html/storage/uploads/incidencias \
 && chown -R www-data:www-data /var/www/html/storage

HEALTHCHECK --interval=30s --timeout=5s --retries=5 \
  CMD curl -fsS http://localhost/api-gestion/public/health || exit 1
