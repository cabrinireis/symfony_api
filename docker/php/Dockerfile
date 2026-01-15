FROM php:8.2-fpm

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    zip \
    curl \
    libxml2-dev \
    libxslt1-dev \
    && docker-php-ext-install pdo pdo_pgsql zip dom simplexml xsl

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

WORKDIR /var/www
RUN chown -R www-data:www-data /var/www

# Criar diretórios necessários para processamento de arquivos
RUN mkdir -p /var/www/var/output /var/www/var/uploads \
    && chown -R www-data:www-data /var/www/var \
    && chmod 755 /var/www/var/output /var/www/var/uploads

EXPOSE 9000
