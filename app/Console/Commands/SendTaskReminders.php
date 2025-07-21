<?php
// app/Console/Commands/SendTaskReminders.php

namespace App\Console\Commands;

use App\Mail\TaskReminderMail;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTaskReminders extends Command
{
    protected $signature = 'tasks:send-reminders';
    protected $description = 'Envoie des rappels par email 24h et 10min avant le début des tâches';

    public function handle()
    {
        // Envoyer les rappels de 24h
        $this->sendDailyReminders();
        
        // Envoyer les rappels de 10min
        $this->sendLastMinuteReminders();
        
        $this->info('Processus de rappel terminé');
        return Command::SUCCESS;
    }
    
    /**
     * Envoyer les rappels 24h avant le début des tâches
     */
    private function sendDailyReminders()
    {
        // Date exactement 24 heures plus tard (en UTC car Carbon::now() est en UTC par défaut)
        $targetDate = Carbon::now()->addHours(24);
        
        // Plage horaire de 1 heure pour éviter de manquer des tâches
        $startDate = (clone $targetDate)->subMinutes(30);
        $endDate = (clone $targetDate)->addMinutes(30);
        
        $this->info('Recherche des tâches commençant le: ' . $targetDate->toDateTimeString() . ' (rappel 24h)');
    
        // Trouver les tâches commençant dans environ 24 heures
        // Utiliser DB::raw pour éviter que les accesseurs ne convertissent les dates
        $tasks = Task::whereRaw('start BETWEEN ? AND ?', [$startDate, $endDate])
                     ->whereNotIn('status', ['completed', 'deferred'])
                     ->where(function($query) {
                         $query->where('reminder_24h_sent', false)
                               ->orWhereNull('reminder_24h_sent');
                     })
                     ->with(['assignee'])
                     ->get();
        
        $this->info('Tâches trouvées pour rappel 24h: ' . $tasks->count());
        
        // Pour chaque tâche, envoyer un email à la personne assignée
        foreach ($tasks as $task) {
            if ($task->assignee) {
                try {
                    $this->info('Envoi de rappel 24h pour: ' . $task->title . ' à ' . $task->assignee->email);
                    
                    Mail::to($task->assignee->email)
                        ->send(new TaskReminderMail($task, $task->assignee, '24h'));
                    
                    // Mettre à jour la tâche pour indiquer qu'un rappel de 24h a été envoyé
                    $task->update(['reminder_24h_sent' => true]);
                    
                    // Ajouter un délai pour éviter de surcharger le serveur mail
                    sleep(1);
                } catch (\Exception $e) {
                    $this->error('Erreur lors de l\'envoi du mail 24h pour la tâche #' . $task->id);
                    Log::error('Erreur email rappel tâche 24h', [
                        'task_id' => $task->id,
                        'assignee_id' => $task->assignee_id,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                $this->warn('La tâche #' . $task->id . ' n\'a pas d\'assigné');
            }
        }
    }
    
    /**
     * Envoyer les rappels 10 minutes avant le début des tâches
     */
    private function sendLastMinuteReminders()
    {
        // Date exactement 10 minutes plus tard
        $targetDate = Carbon::now()->addMinutes(10);
        
        // Plage horaire de 2 minutes pour éviter de manquer des tâches
        $startDate = (clone $targetDate)->subMinutes(1);
        $endDate = (clone $targetDate)->addMinutes(1);
        
        $this->info('Recherche des tâches commençant le: ' . $targetDate->toDateTimeString() . ' (rappel 10min)');

        // Trouver les tâches commençant dans environ 10 minutes
        $tasks = Task::whereBetween('start', [$startDate, $endDate])
                     ->whereNotIn('status', ['completed', 'deferred'])
                     ->where(function($query) {
                         $query->where('reminder_10min_sent', false)
                               ->orWhereNull('reminder_10min_sent');
                     })
                     ->with(['assignee'])
                     ->get();
        
        $this->info('Tâches trouvées pour rappel 10min: ' . $tasks->count());
        
        // Pour chaque tâche, envoyer un email à la personne assignée
        foreach ($tasks as $task) {
            if ($task->assignee) {
                try {
                    $this->info('Envoi de rappel 10min pour: ' . $task->title . ' à ' . $task->assignee->email);
                    
                    Mail::to($task->assignee->email)
                        ->send(new TaskReminderMail($task, $task->assignee, '10min'));
                    
                    // Mettre à jour la tâche pour indiquer qu'un rappel de 10min a été envoyé
                    $task->update(['reminder_10min_sent' => true]);
                    
                    // Ajouter un délai pour éviter de surcharger le serveur mail
                    sleep(1);
                } catch (\Exception $e) {
                    $this->error('Erreur lors de l\'envoi du mail 10min pour la tâche #' . $task->id);
                    Log::error('Erreur email rappel tâche 10min', [
                        'task_id' => $task->id,
                        'assignee_id' => $task->assignee_id,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                $this->warn('La tâche #' . $task->id . ' n\'a pas d\'assigné');
            }
        }
    }
}