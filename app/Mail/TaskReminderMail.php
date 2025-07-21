<?php
namespace App\Mail;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $user;
    public $url;
    public $reminderType;

    /**
     * Create a new message instance.
     */
    public function __construct(Task $task, User $user, string $reminderType = '24h')
    {
        $this->task = $task;
        $this->user = $user;
        $this->url = url('/tasks/' . $task->id);
        $this->reminderType = $reminderType;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reminderType === '24h' 
                ? 'Rappel: Votre tâche commence demain' 
                : 'URGENT: Votre tâche commence dans 10 minutes',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.TaskReminder',
            with: [
                'task' => $this->task,
                'user' => $this->user,
                'url' => $this->url,
                'reminderType' => $this->reminderType
            ],
        );
    }
}