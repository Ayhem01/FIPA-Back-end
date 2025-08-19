<!DOCTYPE html>
<html>
<head>
    <title>Invitation</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            background-color: #0056b3;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
        }
        .footer {
            background-color: #eee;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            border-radius: 0 0 5px 5px;
            border: 1px solid #ddd;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
        }
        .confirm {
            background-color: #28a745;
            color: white;
        }
        .decline {
            background-color: #dc3545;
            color: white;
        }
        .details {
            margin: 20px 0;
            padding: 15px;
            background-color: #f0f8ff;
            border-left: 4px solid #0056b3;
        }
        table {
            width: 100%;
        }
        td {
            padding: 8px;
        }
        .label {
            font-weight: bold;
            width: 120px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Invitation</h2>
    </div>
    
    <div class="content">
        <p>Bonjour {{ $mailData['invite']->prenom }} {{ $mailData['invite']->nom }},</p>
        
        <p>Nous avons le plaisir de vous inviter à participer à <strong>{{ $mailData['action']->nom ?? 'notre événement' }}</strong>.</p>
        
        <div class="details">
            <table>
                <tr>
                    <td class="label">Date:</td>
                    <td>{{ $mailData['invite']->date_evenement ? $mailData['invite']->date_evenement->format('d/m/Y à H:i') : 'À déterminer' }}</td>
                </tr>
                <tr>
                    <td class="label">Lieu:</td>
                    <td>{{ $mailData['action']->lieu ?? 'À déterminer' }}</td>
                </tr>
                @if(isset($mailData['action']->description))
                <tr>
                    <td class="label">Description:</td>
                    <td>{{ $mailData['action']->description }}</td>
                </tr>
                @endif
            </table>
        </div>
        
        <p>Merci de nous indiquer si vous serez présent(e) en cliquant sur l'un des boutons ci-dessous:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $mailData['confirmUrl'] }}" class="button confirm">Je confirme ma présence</a>
            <a href="{{ $mailData['declineUrl'] }}" class="button decline">Je ne pourrai pas participer</a>
        </div>
        
        <p>Nous espérons vous compter parmi nous!</p>
        
        <p>Cordialement,<br>
        L'équipe FIPA</p>
    </div>
    
    <div class="footer">
        <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre directement.</p>
        <p>© {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
    </div>
</body>
</html>