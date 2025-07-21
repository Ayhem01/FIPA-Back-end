<!DOCTYPE html>
<html>
<head>
    <title>Rappel de tâche</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            text-align: center;
        }
        .urgent {
            background-color: #d9534f !important;
        }
        .content {
            padding: 15px;
        }
        .task-details {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
        }
        .deadline {
            color: #d9534f;
            font-weight: bold;
        }
        .priority-high {
            color: #d9534f;
        }
        .priority-medium {
            color: #f0ad4e;
        }
        .priority-low {
            color: #5cb85c;
        }
        .button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
        }
        .footer {
            margin-top: 20px;
            padding: 15px;
            text-align: center;
            color: #777;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <!-- Le reste du code reste inchangé -->
    <div class="header {{ $reminderType === '10min' ? 'urgent' : '' }}">
        <h1 style="margin: 0;">{{ $reminderType === '24h' ? 'Rappel de tâche' : 'URGENT: Tâche imminente' }}</h1>
    </div>
    
    <div class="content">
        <p>Bonjour <strong>{{ $user->name }}</strong>,</p>
        
        @if($reminderType === '24h')
            <p>Ceci est un rappel que la tâche suivante commence <strong>demain</strong> :</p>
        @else
            <p><strong>ATTENTION</strong>: La tâche suivante commence dans <strong>10 minutes</strong> :</p>
        @endif
        
        <div class="task-details">
            <h2 style="margin-top: 0;">{{ $task->title }}</h2>
            
            <p>
                <strong>Priorité:</strong> 
                <span class="priority-{{ strtolower($task->priority) }}">{{ ucfirst($task->priority) }}</span>
            </p>
            
            <p>
                <strong>Début:</strong> 
                <span class="deadline">{{ $task->start->format('d/m/Y à H:i') }}</span>
            </p>
            
            @if(isset($task->description) && !empty($task->description))
            <p><strong>Description:</strong><br>{{ $task->description }}</p>
            @endif
        </div>
        
        @if($reminderType === '24h')
            <p>Veuillez vous préparer pour cette tâche.</p>
        @else
            <p>Veuillez vous connecter immédiatement pour commencer cette tâche.</p>
        @endif
        
        <a href="{{ $url }}" class="button">Voir la tâche</a>
    </div>
    
    <div class="footer">
        <p>Cordialement,<br>L'équipe Foreign Investment Promotion Agency</p>
    </div>
</body>
</html>