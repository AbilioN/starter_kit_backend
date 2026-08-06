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

# The app's DB user (DB_USERNAME) only has grants on its own single
# database by default (MySQL's MYSQL_DATABASE env var only ever creates
# that one). Database-per-tenant needs it to CREATE/use arbitrary
# databases at runtime (landlord now, tenant_* later via provisioning) -
# grant broadly here since this is a local dev container, not production.
echo "🔑 Concedendo privilégios amplos ao usuário do banco (necessário para multi-tenancy)..."
docker-compose exec db mysql -uroot -p"${DB_PASSWORD:-password}" \
    -e "GRANT ALL PRIVILEGES ON *.* TO '${DB_USERNAME:-starter_kit_backend}'@'%'; FLUSH PRIVILEGES;"

# The landlord DB isn't auto-created by MYSQL_DATABASE (that only covers the
# legacy single-tenant DB_DATABASE) - create it explicitly, then migrate it.
# Tenant DBs are NOT migrated here: they only get created/migrated per-tenant
# via provisioning (see docs/03-multitenancy-plan.md).
echo "🏢 Criando e migrando o banco landlord..."
docker-compose exec db mysql -uroot -p"${DB_PASSWORD:-password}" \
    -e "CREATE DATABASE IF NOT EXISTS starter_kit_landlord;"
docker-compose exec app php artisan migrate --database=landlord --path=database/migrations/landlord --force

echo "👤 Criando o GodAdmin padrão (god@starterkit.test / password123)..."
docker-compose exec app php artisan db:seed --class=GodAdminSeeder --force

docker-compose exec app php artisan storage:link

# No-op if tenant-a/tenant-b haven't been provisioned yet (tenant:provision
# is a separate manual step, see docs/04-local-dev-tenants.md) - safe to
# always run, just re-applies the demo palette to whichever of them exist.
echo "🎨 Aplicando branding padrão aos tenants de demonstração (tenant-a/tenant-b, se existirem)..."
docker-compose exec app php artisan db:seed --class=TenantBrandingSeeder --force

# Instalar dependências do Node.js e construir assets
echo "📦 Instalando dependências do Node.js..."
docker-compose exec app npm install
docker-compose exec app npm run build

echo "✅ Ambiente Docker configurado com sucesso!"
echo "🌐 Acesse: http://localhost:8006"
echo "🛡️  GodAdmin: http://localhost:8006/god/login (god@starterkit.test / password123)"
echo "🗄️ MySQL: localhost:3307"
echo "🔴 Redis: localhost:6379"