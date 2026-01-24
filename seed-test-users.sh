#!/bin/bash

echo "🌱 Executando seeder de usuários de teste..."

# Executar o seeder
docker-compose exec app php artisan db:seed --class=TestUserSeeder

echo ""
echo "✅ Usuários de teste criados!"
echo ""
echo "📋 Credenciais para teste:"
echo "📧 Email: user@email.com"
echo "🔑 Senha: password123"
echo ""
echo "🌐 Endpoint de login: http://localhost:8006/api/login"
echo ""
echo "📝 Exemplo de requisição:"
echo "curl -X POST http://localhost:8006/api/login \\"
echo "  -H 'Content-Type: application/json' \\"
echo "  -d '{\"email\":\"user@email.com\",\"password\":\"password123\"}'" 