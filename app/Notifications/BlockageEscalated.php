<?php

namespace App\Notifications;

use App\Models\Blockage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Laravel\Passport\Client;

class BlockageEscalated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $blockage;

    public function __construct(Blockage $blockage)
    {
        $this->blockage = $blockage;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $entityType = class_basename($this->blockage->blockable_type);
        $entityId = $this->blockage->blockable_id;
        $daysOld = now()->diffInDays($this->blockage->created_at);
        
        // Créer une URL qui marchera avec le frontend authentifié par Passport
        $frontendUrl = 'http://localhost:3000/api';
        $blockageUrl = "{$frontendUrl}/blockages/{$this->blockage->id}";
        
        return (new MailMessage)
            ->subject('🚨 Escalade Automatique - Blocage non résolu')
            ->line("Un blocage est resté non résolu pendant {$daysOld} jours et a été escaladé automatiquement à votre attention.")
            ->line("Nom du blocage: {$this->blockage->name}")
            ->line("Entité concernée: {$entityType}")
            ->line("Description: {$this->blockage->description}")
            ->line("Priorité: CRITIQUE (escaladé automatiquement)")
            ->action('Voir le blocage', $blockageUrl)
            ->line('Connectez-vous à votre compte pour gérer ce blocage.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'blockage_id' => $this->blockage->id,
            'blockage_name' => $this->blockage->name,
            'blockable_type' => $this->blockage->blockable_type,
            'blockable_id' => $this->blockage->blockable_id,
            'escalated_at' => $this->blockage->escalated_at,
            'days_old' => now()->diffInDays($this->blockage->created_at),
            'auto_escalated' => true,
        ];
    }
}