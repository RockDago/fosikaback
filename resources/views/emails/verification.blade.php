<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification d'email</title>
    <style>
        body { font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1a202c; margin: 0; padding: 0; background-color: #f4f7f9; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        .header { background: linear-gradient(135deg, #0c2844 0%, #09407e 100%); padding: 35px; text-align: center; color: #ffffff; }
        .content { padding: 35px; }
        
        /* Code Block */
        .code-container { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; text-align: center; margin: 25px 0; }
        .code-label { font-size: 13px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; display: block; }
        .code-value { font-family: monospace; font-size: 42px; font-weight: 800; color: #09407e; letter-spacing: 10px; }

        .btn { display: inline-block; background: #4c7026; color: #ffffff !important; padding: 14px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 10px; }
        
        .link-alt { font-size: 12px; color: #94a3b8; word-break: break-all; margin-top: 25px; text-align: center; border-top: 1px solid #edf2f7; padding-top: 20px; }
        .footer { padding: 25px; text-align: center; font-size: 12px; color: #94a3b8; background: #fafafa; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin:0; font-size: 22px;">Vérifiez votre adresse email</h1>
            <p style="margin:5px 0 0; opacity:0.8; font-size: 14px;">Dernière étape pour activer votre compte</p>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $user->name }}</strong>,</p>
            <p>Merci de vous être inscrit sur <strong>{{ config('app.name') }}</strong>. Pour finaliser votre inscription, utilisez le code de vérification ci-dessous :</p>

            <div class="code-container">
                <span class="code-label">Votre Code OTP</span>
                <div class="code-value">{{ $code }}</div>
                <p style="font-size: 12px; color: #94a3b8; margin-top: 15px;">Expire le {{ $expiration }}</p>
            </div>

            <div style="text-align: center;">
                <p style="font-size: 14px; color: #4a5568;">Ou cliquez simplement sur ce bouton :</p>
                <a href="{{ $url }}" class="btn">Confirmer mon email</a>
            </div>

            <div class="link-alt">
                Si le bouton ne fonctionne pas, copiez ce lien :<br>
                <a href="{{ $url }}" style="color: #09407e;">{{ $url }}</a>
            </div>
        </div>

        <div class="footer">
            <p><strong>{{ config('app.name') }}</strong> - Plateforme Sécurisée</p>
            <p>&copy; {{ date('Y') }}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
