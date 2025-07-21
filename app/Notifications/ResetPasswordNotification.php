<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;
   // protected $token;
    protected $url;
   

    /**
     * Create a new notification instance.
     */
    public function __construct($url,)
    {
        
        $this->url = $url;
    }
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
    
        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->action('Réinitialiser le mot de passe', $this->url)
            ->view('mail.ResetPassword', [
                'user' => $notifiable,
                'url' => $this->url,
                
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
