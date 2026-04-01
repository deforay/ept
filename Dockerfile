FROM php:8.2-apache

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    libzip-dev libxml2-dev libicu-dev libonig-dev libcurl4-openssl-dev \
    libgmp-dev libbz2-dev libldap2-dev \
    cron unzip git curl ca-certificates gnupg \
    libreoffice-writer-nogui \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    gd zip pdo pdo_mysql mysqli mbstring intl \
    xml bcmath bz2 calendar exif gmp ldap opcache soap sockets

# Enable Apache modules
RUN a2enmod rewrite headers expires

# Node.js (LTS) for chart rendering
ENV NODE_VERSION=22.15.0
RUN ARCH=$(dpkg --print-architecture) \
    && if [ "$ARCH" = "amd64" ]; then NODEARCH=x64; elif [ "$ARCH" = "arm64" ]; then NODEARCH=arm64; fi \
    && curl -fsSL "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${NODEARCH}.tar.xz" \
       | tar -xJ -C /usr/local --strip-components=1 \
    && node --version && npm --version

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache vhost - point DocumentRoot to /var/www/ept/public
RUN echo '<VirtualHost *:80>\n\
    ServerName localhost\n\
    DocumentRoot /var/www/ept/public\n\
    <Directory /var/www/ept/public>\n\
        AddDefaultCharset UTF-8\n\
        Options -Indexes -MultiViews +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/ept-error.log\n\
    CustomLog ${APACHE_LOG_DIR}/ept-access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/ept.ini

# Set working directory
WORKDIR /var/www/ept

# Copy application
COPY . .

# Install PHP dependencies
RUN composer install --prefer-dist --no-dev --no-interaction --optimize-autoloader

# Install Node dependencies for chart rendering
RUN npm ci --omit=dev

# Create required directories
RUN mkdir -p application/cache logs downloads backups public/temporary public/uploads

# Set permissions
RUN chown -R www-data:www-data /var/www/ept \
    && chmod -R 775 application/cache logs downloads backups public/temporary public/uploads

# Cron setup (runs crunz scheduler every minute)
RUN echo '* * * * * cd /var/www/ept && ./cron.sh >> /var/log/cron.log 2>&1' \
    | crontab -u www-data -

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh /var/www/ept/cron.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
