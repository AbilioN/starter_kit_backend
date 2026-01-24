#!/bin/bash

echo "🧪 Executando testes do sistema de login..."

echo ""
echo "📋 Testes Unitários (Domain):"
./vendor/bin/phpunit --testsuite=Unit --filter="Domain"

echo ""
echo "📋 Testes Unitários (Infrastructure):"
./vendor/bin/phpunit --testsuite=Unit --filter="Infrastructure"

echo ""
echo "📋 Testes Unitários (Application):"
./vendor/bin/phpunit --testsuite=Unit --filter="Application"

echo ""
echo "📋 Testes de Integração:"
./vendor/bin/phpunit --testsuite=Integration

echo ""
echo "📋 Testes de Feature (End-to-End):"
./vendor/bin/phpunit --testsuite=Feature

echo ""
echo "📋 Todos os testes:"
./vendor/bin/phpunit

echo ""
echo "✅ Testes concluídos!" 