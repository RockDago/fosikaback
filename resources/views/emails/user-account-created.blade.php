<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Bienvenue sur {{ $appName }}</title>
    <style>
      body {
        font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        line-height: 1.6;
        color: #1a202c;
        margin: 0;
        padding: 0;
        background-color: #f4f7f9;
      }
      .container {
        max-width: 600px;
        margin: 20px auto;
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      }

      .header {
        background: linear-gradient(135deg, #0c2844 0%, #09407e 100%);
        padding: 35px;
        text-align: center;
        color: #ffffff;
      }
      .content {
        padding: 35px;
      }

      /* Credentials Box */
      .card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 25px;
        margin: 25px 0;
      }
      .item {
        margin-bottom: 12px;
        font-size: 15px;
        border-bottom: 1px solid #edf2f7;
        padding-bottom: 8px;
      }
      .item:last-child {
        border-bottom: none;
      }
      .label {
        color: #64748b;
        font-weight: 600;
        width: 140px;
        display: inline-block;
      }
      .value {
        font-family: monospace;
        color: #09407e;
        font-weight: bold;
        font-size: 16px;
      }

      .badge {
        background: #0c2844;
        color: #ffffff;
        padding: 2px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
      }
      .alert {
        font-size: 14px;
        color: #856404;
        background: #fff3cd;
        padding: 15px;
        border-radius: 6px;
        margin: 25px 0;
        border-left: 4px solid #ffc107;
      }

      .btn {
        display: inline-block;
        background: #09407e;
        color: #ffffff !important;
        padding: 14px 30px;
        text-decoration: none;
        border-radius: 8px;
        font-weight: bold;
        margin-top: 10px;
        box-shadow: 0 4px 6px rgba(9, 64, 126, 0.2);
      }
      .footer {
        padding: 25px;
        text-align: center;
        font-size: 12px;
        color: #94a3b8;
        background: #fafafa;
        border-top: 1px solid #f1f5f9;
      }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="header">
        <h1 style="margin: 0; font-size: 24px">Bienvenue sur {{ $appName }}</h1>
        <p style="margin: 5px 0 0; opacity: 0.8; font-size: 15px">
          Votre accès professionnel est activé
        </p>
      </div>

      <div class="content">
        <p>Bonjour <strong>{{ $user->first_name }}</strong>,</p>
        <p>
          Un compte professionnel a été créé pour vous par l'administration.
          Voici vos accès pour vous connecter à la plateforme :
        </p>

        <div class="card">
          <div class="item">
            <span class="label">Identifiant :</span>
            <span class="value">{{ $user->username }}</span>
          </div>
          <div class="item">
            <span class="label">Mot de passe :</span>
            <span class="value">{{ $plainPassword }}</span>
          </div>
          <div class="item">
            <span class="label">Rôle :</span>
            <span class="badge">{{ $user->role }}</span>
          </div>
        </div>

        <div class="alert">
          <strong>Conseil de sécurité :</strong> Pour protéger votre compte,
          nous vous recommandons de modifier votre mot de passe temporaire dès
          votre première connexion.
        </div>

        <div style="text-align: center; margin-top: 30px">
          <a href="{{ $loginUrl }}" class="btn">Se connecter à mon compte</a>
        </div>
      </div>

      <div class="footer">
        <p><strong>{{ $appName }}</strong> - Système de Gestion Intégré</p>
        <p>&copy; {{ date('Y') }}. Tous droits réservés.</p>
      </div>
    </div>
  </body>
</html>
