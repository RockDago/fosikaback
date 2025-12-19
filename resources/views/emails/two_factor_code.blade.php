<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Code d'Authentification à Deux Facteurs</title>
    <style>
        /* Reset et styles de base */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #0c2844;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .email-container {
            max-width: 650px;
            margin: 30px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(12, 40, 68, 0.1);
        }

        /* En-tête */
        .header {
            background: linear-gradient(135deg, #0c2844 0%, #09407e 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .logo-container {
            background: white;
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            font-size: 40px; /* Fallback si pas d'image */
        }
        .logo {
            max-width: 60px;
            max-height: 60px;
            object-fit: contain;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .security-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 10px;
            display: inline-block;
        }

        /* Contenu Principal */
        .content { padding: 40px 35px; }

        .code-section {
            background: #f8f9fa;
            border: 2px dashed #09407e;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        .code-label {
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 10px;
        }
        .code {
            font-size: 42px;
            font-weight: 700;
            letter-spacing: 10px;
            color: #09407e;
            font-family: 'Courier New', monospace;
            background: white;
            padding: 15px;
            border-radius: 8px;
            display: inline-block;
        }

        /* Alertes et Infos */
        .expiration-box {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .security-steps {
            margin-top: 30px;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .step-item {
            display: flex;
            align-items: start;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .step-icon {
            margin-right: 15px;
            font-size: 18px;
        }

        /* Pied de page */
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container { margin: 0; border-radius: 0; }
            .code { font-size: 32px; letter-spacing: 5px; }
            .content { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="email-container">
    <!-- Header -->
    <div class="header">

        <h1>{{ config('app.name') }}</h1>
        <span class="security-badge">Authentification Sécurisée</span>
    </div>

    <!-- Body -->
    <div class="content">
        <p>Bonjour <strong>{{ $user->name }}</strong>,</p>

        <p>Une demande de connexion a été initiée sur votre compte. Pour finaliser l'accès, veuillez utiliser le code de vérification unique ci-dessous.</p>

        <!-- Code Box -->
        <div class="code-section">
            <span class="code-label">Votre Code de Vérification</span>
            <div class="code">{{ $code }}</div>
        </div>

        <!-- Expiration Warning -->
        <div class="expiration-box">
            ⏰ <strong>Attention :</strong> Ce code expire le {{ $expiration }}.
        </div>

        <!-- Security Steps -->
        <div class="security-steps">
            <div class="step-item">
                <span class="step-icon">🔒</span>
                <div>
                    <strong>Ne partagez jamais ce code.</strong><br>
                    Notre équipe ne vous demandera jamais votre code par téléphone ou email.
                </div>
            </div>
            <div class="step-item">
                <span class="step-icon">⚠️</span>
                <div>
                    <strong>Vous n'êtes pas à l'origine de cette demande ?</strong><br>
                    Modifiez immédiatement votre mot de passe et contactez le support.
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ url('/') }}" style="background-color: #09407e; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 14px;">Accéder à mon compte</a>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Cet email a été envoyé automatiquement par {{ config('app.name') }}.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
    </div>
</div>
</body>
</html>
