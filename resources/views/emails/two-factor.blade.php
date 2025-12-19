<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Code de Sécurité {{ config('app.name') }}</title>
    <style>
      /* Base & Reset */
      body {
        font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        line-height: 1.6;
        color: #1a202c;
        margin: 0;
        padding: 0;
        background-color: #f4f7f9;
      }
      .email-wrapper {
        width: 100%;
        padding: 20px 0;
      }
      .container {
        max-width: 600px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      }

      /* Header */
      .header {
        background: linear-gradient(135deg, #0c2844 0%, #09407e 100%);
        padding: 40px 20px;
        text-align: center;
        color: #ffffff;
      }
      .header h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        letter-spacing: -0.5px;
      }
      .badge {
        display: inline-block;
        background: rgba(255, 255, 255, 0.15);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        text-transform: uppercase;
        margin-top: 10px;
        font-weight: bold;
      }

      /* Main Content */
      .content {
        padding: 40px 35px;
      }
      .greeting {
        font-size: 18px;
        margin-bottom: 10px;
        color: #0c2844;
      }

      /* Code Block */
      .code-container {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        margin: 30px 0;
      }
      .code-label {
        font-size: 13px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 15px;
        display: block;
      }
      .code-value {
        font-family: "Courier New", monospace;
        font-size: 48px;
        font-weight: 800;
        color: #09407e;
        letter-spacing: 10px;
        margin: 10px 0;
      }
      .expiry {
        font-size: 13px;
        color: #94a3b8;
        margin-top: 15px;
      }

      /* Info Box */
      .info-box {
        border-left: 4px solid #09407e;
        background: #f0f7ff;
        padding: 20px;
        border-radius: 4px;
        margin-bottom: 25px;
      }
      .info-box p {
        margin: 0;
        font-size: 14px;
        color: #1e40af;
      }

      /* Button */
      .cta-container {
        text-align: center;
        margin-top: 30px;
      }
      .btn {
        background-color: #09407e;
        color: #ffffff !important;
        padding: 14px 30px;
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
        font-size: 15px;
        display: inline-block;
        transition: background 0.3s;
      }

      /* Footer */
      .footer {
        padding: 25px;
        text-align: center;
        font-size: 12px;
        color: #94a3b8;
        background: #fafafa;
        border-top: 1px solid #f1f5f9;
      }

      @media only screen and (max-width: 600px) {
        .content {
          padding: 25px 20px;
        }
        .code-value {
          font-size: 36px;
          letter-spacing: 6px;
        }
      }
    </style>
  </head>
  <body>
    <div class="email-wrapper">
      <div class="container">
        <!-- Header -->
        <div class="header">
          <h1>{{ config('app.name') }}</h1>
          <div class="badge">Sécurité du compte</div>
        </div>

        <!-- Body -->
        <div class="content">
          <div class="greeting">
            Bonjour <strong>{{ $user->name }}</strong>,
          </div>
          <p>
            Pour sécuriser votre accès, veuillez saisir le code de vérification
            suivant sur notre interface :
          </p>

          <!-- Code Section -->
          <div class="code-container">
            <span class="code-label">Code d'activation (OTP)</span>
            <div class="code-value">{{ $code }}</div>
            <div class="expiry">
              ⏰ Ce code expire le <strong>{{ $expiration }}</strong>
            </div>
          </div>

          <!-- Security Note -->
          <div class="info-box">
            <p>
              <strong>Note de sécurité :</strong> Ne partagez jamais ce code. Si
              vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer
              cet email en toute sécurité.
            </p>
          </div>

          <!-- Action -->
          <div class="cta-container">
            <a href="{{ url('/') }}" class="btn">Retourner sur la plateforme</a>
          </div>
        </div>

        <!-- Footer -->
        <div class="footer">
          <p>
            Ceci est un message automatique de
            <strong>{{ config('app.name') }}</strong>.
          </p>
          <p>
            &copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits
            réservés.
          </p>
        </div>
      </div>
    </div>
  </body>
</html>
