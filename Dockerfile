FROM php:8.2-apache

# 1. Instalar dependencias del sistema (añadimos libicu-dev para Laravel)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql intl

# 2. Configurar Apache
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite

# 3. Instalar Composer globalmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Establecer el directorio de trabajo
WORKDIR /var/www/html

# 5. Copiar PRIMERO los archivos de composer para aprovechar la caché de Docker
COPY composer.json composer.lock ./

# 6. Ejecutar composer install (sin scripts para evitar fallos de base de datos en el build)
RUN composer install --no-dev --no-scripts --no-autoloader

# 7. Ahora copiar el resto del código
COPY . .

# 8. Generar el autoloader final
RUN composer dump-autoload --optimize --no-dev

# 9. Permisos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80