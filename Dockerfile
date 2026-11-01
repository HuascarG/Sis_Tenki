FROM php:8.2-apache

# 1. Instalar dependencias del sistema y extensiones requeridas para TENKI
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    mariadb-client \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 2. Configurar y compilar extensiones PHP (GD, PDO MySQL, etc.)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql

# 3. Habilitar mod_rewrite y mod_headers de Apache para .htaccess
RUN a2enmod rewrite headers

# 4. Copiar los archivos del proyecto al directorio web de Apache
COPY . /var/www/html/

# 5. Ajustar permisos para la carpeta de subidas
RUN mkdir -p /var/www/html/Assets/uploads/productos \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/Assets/uploads

# 6. Exponer puerto HTTP estándar
EXPOSE 80

CMD ["apache2-foreground"]
