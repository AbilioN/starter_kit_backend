FROM php:8.2-fpm

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    supervisor \
    # Backups (5.3): mysqldump/mysql are NOT in php:8.2-fpm. Adding them here
    # means app, horizon and scheduler must be REBUILT, not just restarted —
    # `docker compose restart` keeps running the old image and every backup
    # fails with "mysqldump not available".
    default-mysql-client \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Instalar Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Criar usuário para a aplicação
RUN useradd -G www-data,root -u 1000 -d /home/akerfeels akerfeels
RUN mkdir -p /home/akerfeels/.composer && \
    chown -R akerfeels:akerfeels /home/akerfeels

# Definir diretório de trabalho
WORKDIR /var/www

# Copiar arquivos do projeto
COPY . /var/www

# Definir permissões antes de instalar dependências
RUN chown -R akerfeels:akerfeels /var/www

# Mudar para o usuário akerfeels antes de instalar dependências
USER akerfeels

# Copiar .env.example para .env se não existir
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Instalar dependências do PHP — composer update regenerates the lock file
# when composer.json and composer.lock drift out of sync (e.g. laravel/horizon).
RUN composer update --no-interaction --optimize-autoloader

# Instalar dependências do Node.js
RUN npm install

# Construir assets
RUN npm run build

# Definir permissões finais
USER root
RUN chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Voltar para o usuário akerfeels
USER akerfeels

# Expor porta 9000
EXPOSE 9000

# Comando padrão
CMD ["php-fpm"] 