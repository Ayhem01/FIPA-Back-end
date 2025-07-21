<!DOCTYPE html>
<html>
<head>
    <title>Réinitialisation de mot de passe</title>
</head>
<body>
    <h1>Bonjour {{ $user->name }} !</h1>

    <p>Vous avez demandé à réinitialiser votre mot de passe. Vous pouvez le faire en cliquant sur le lien ci-dessous :</p>

    <p>
        <a href="{{ $url }}" style="color: white; background-color: #007bff; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Réinitialiser mon mot de passe
        </a>
    </p>

    <p>Si vous n'avez pas demandé cette réinitialisation, veuillez ignorer cet e-mail.</p>

    <p>Votre mot de passe ne sera pas modifié tant que vous n'aurez pas cliqué sur le lien ci-dessus et défini un nouveau mot de passe.</p>

    <p>Cordialement,<br>L'équipe Foreign Investment Promotion Agency</p>
</body>
</html>