FROM php:8.3-apache

WORKDIR /var/www/html

# Extensiones típicas
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
  && docker-php-ext-install pdo pdo_mysql zip \
  && rm -rf /var/lib/apt/lists/*

# Instalar Composer (sin multi-stage)
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
  && rm composer-setup.php

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Cache de dependencias
COPY composer.json composer.lock* ./
RUN composer update --no-dev --prefer-dist --no-interaction --no-progress
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# Código al final
COPY . .

# Apache config típica
RUN a2enmod rewrite

EXPOSE 80
CMD ["apache2-foreground"]