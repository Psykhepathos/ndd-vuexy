<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao {{ $appName }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #7367f0;
        }
        .header h1 {
            color: #7367f0;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin-bottom: 30px;
        }
        .content p {
            margin: 15px 0;
        }
        .highlight {
            background-color: #f8f7fa;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #7367f0;
            margin: 20px 0;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            background-color: #7367f0;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #5e50ee;
        }
        .link-fallback {
            background-color: #f8f7fa;
            padding: 15px;
            border-radius: 6px;
            word-break: break-all;
            font-size: 12px;
            color: #666;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #888;
            font-size: 12px;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            font-size: 14px;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $appName }}</h1>
        </div>

        <div class="content">
            <p>Ola, <strong>{{ $userName }}</strong>!</p>

            <p>Sua conta foi criada com sucesso no sistema <strong>{{ $appName }}</strong>.</p>

            <div class="info-box">
                <strong>Seus dados de acesso:</strong><br>
                E-mail: <code>{{ $userEmail }}</code>
            </div>

            <p>Para comecar a usar o sistema, voce precisa configurar sua senha de acesso. Clique no botao abaixo:</p>

            <div class="button-container">
                <a href="{{ $setupUrl }}" class="button">Configurar Minha Senha</a>
            </div>

            <div class="warning">
                <strong>Importante:</strong> Este link e valido por <strong>{{ $expiresIn }}</strong>.
                Apos esse periodo, sera necessario solicitar um novo link ao administrador.
            </div>

            <p>Se o botao acima nao funcionar, copie e cole o link abaixo no seu navegador:</p>

            <div class="link-fallback">
                {{ $setupUrl }}
            </div>
        </div>

        <div class="footer">
            <p>Este e um e-mail automatico, por favor nao responda.</p>
            <p>&copy; {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
