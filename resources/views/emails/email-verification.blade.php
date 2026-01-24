<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificação de Email - dashboard_addresses</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .code {
            background: #fff;
            border: 2px dashed #667eea;
            padding: 20px;
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin: 20px 0;
            border-radius: 8px;
            letter-spacing: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏠 dashboard_addresses</h1>
        <p>Verificação de Email</p>
    </div>
    
    <div class="content">
        <h2>Olá, {{ $userName }}!</h2>
        
        <p>Obrigado por se registrar no dashboard_addresses! Para completar seu cadastro, use o código de verificação abaixo:</p>
        
        <div class="code">
            {{ $code }}
        </div>
        
        <div class="warning">
            ⚠️ <strong>Atenção:</strong> Este código expira em {{ $expiresIn }}. Se não conseguir usar agora, você pode solicitar um novo código.
        </div>
        
        <p>Se você não solicitou este código, pode ignorar este email com segurança.</p>
        
        <p>Atenciosamente,<br>
        <strong>Equipe dashboard_addresses</strong></p>
    </div>
    
    <div class="footer">
        <p>Este é um email automático, não responda a esta mensagem.</p>
        <p>&copy; {{ date('Y') }} dashboard_addresses. Todos os direitos reservados.</p>
    </div>
</body>
</html> 