#!/bin/bash

# Script para aguardar o banco de dados estar pronto
echo "Aguardando banco de dados estar disponível..."

# Aguarda até 60 segundos
for i in {1..60}; do
    # Usa PHP para verificar a conexão com o banco
    if php -r "
        try {
            \$pdo = new PDO('mysql:host=db;dbname=${DB_DATABASE:-dashboard_addresses}', '${DB_USERNAME:-dashboard_addresses}', '${DB_PASSWORD:-password}');
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo 'Database connection successful';
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " > /dev/null 2>&1; then
        echo "Banco de dados está pronto!"
        break
    fi
    
    if [ $i -eq 60 ]; then
        echo "Timeout: Banco de dados não ficou disponível em 60 segundos"
        exit 1
    fi
    
    echo "Tentativa $i/60: Aguardando banco..."
    sleep 1
done

# Executa o comando passado como argumento
exec "$@"
