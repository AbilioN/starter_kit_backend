#!/bin/bash

echo "🚀 Configurando ambiente Docker para dashboard_addresses..."

# Copiar arquivo de ambiente
if [ ! -f .env ]; then
    echo "📝 Copiando arquivo de ambiente..."
    cp env.docker .env
fi

# Construir e iniciar containers
echo "🔨 Construindo containers..."
docker-compose up -d --build

# Aguardar MySQL estar pronto
echo "⏳ Aguardando MySQL estar pronto..."
sleep 30

# Executar comandos do Laravel
echo "⚙️ Configurando Laravel..."
docker-compose exec app composer install
docker-compose exec app php artisan key:generate

# The landlord DB isn't auto-created by MYSQL_DATABASE (that only covers the
# legacy single-tenant DB_DATABASE) - create it explicitly, then migrate it.
# Tenant DBs are NOT migrated here: they only get created/migrated per-tenant
# via provisioning (see docs/03-multitenancy-plan.md).
echo "🏢 Criando e migrando o banco landlord..."
docker-compose exec db mysql -uroot -p"${DB_PASSWORD:-password}" \
    -e "CREATE DATABASE IF NOT EXISTS starter_kit_landlord;"
docker-compose exec app php artisan migrate --database=landlord --path=database/migrations/landlord --force

docker-compose exec app php artisan storage:link

# Instalar dependências do Node.js e construir assets
echo "📦 Instalando dependências do Node.js..."
docker-compose exec app npm install
docker-compose exec app npm run build

echo "✅ Ambiente Docker configurado com sucesso!"
echo "🌐 Acesse: http://localhost:8006"
echo "🗄️ MySQL: localhost:3307"
echo "🔴 Redis: localhost:6379" 