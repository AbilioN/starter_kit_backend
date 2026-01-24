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
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan storage:link

# Instalar dependências do Node.js e construir assets
echo "📦 Instalando dependências do Node.js..."
docker-compose exec app npm install
docker-compose exec app npm run build

echo "✅ Ambiente Docker configurado com sucesso!"
echo "🌐 Acesse: http://localhost:8006"
echo "🗄️ MySQL: localhost:3307"
echo "🔴 Redis: localhost:6379" 