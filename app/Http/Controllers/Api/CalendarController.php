<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    /**
     * Récupérer les événements pour le calendrier dans le format FullCalendar
     */
    public function getEvents(Request $request)
    {
        try {
            // Récupérer les dates de début et de fin du calendrier
            $start = $request->input('start');
            $end = $request->input('end');
            
            $userId = $request->input('user_id', Auth::id());
            
            $query = Task::query();
            
            // Filtrer par plage de dates si fournies
            if ($start && $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start', [$start, $end])
                      ->orWhereBetween('end', [$start, $end])
                      ->orWhere(function ($q2) use ($start, $end) {
                          $q2->where('start', '<', $start)
                             ->where('end', '>', $end);
                      });
                });
            }
            
            // Filtrer selon l'utilisateur (tâches assignées ou créées)
            if ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->orWhere('assignee_id', $userId);
                });
            }
            
            $tasks = $query->get();
            
            // Formater les données pour FullCalendar
            $events = $tasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'start' => $task->start->toIso8601String(),
                    'end' => $task->end ? $task->end->toIso8601String() : null,
                    'allDay' => $task->all_day,
                    'backgroundColor' => $task->color,
                    'borderColor' => $this->getBorderColorForPriority($task->priority),
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'description' => $task->description,
                        'type' => $task->type,
                        'status' => $task->status,
                        'priority' => $task->priority,
                        'assignee_id' => $task->assignee_id,
                        'related_company_id' => $task->related_company_id,
                        'related_contact_id' => $task->related_contact_id
                    ]
                ];
            });
            
            return response()->json($events);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des événements du calendrier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Mettre à jour une tâche par glisser-déposer sur le calendrier
     */
    public function moveEvent(Request $request, $id)
    {
        try {
            $request->validate([
                'start' => 'required|date',
                'end' => 'nullable|date',
                'allDay' => 'nullable|boolean'
            ]);
            
            $task = Task::findOrFail($id);
            
            // Mettre à jour les dates
            $task->start = $request->start;
            $task->end = $request->end;
            
            // Mettre à jour allDay si fourni
            if ($request->has('allDay')) {
                $task->all_day = $request->allDay;
            }
            
            $task->save();
            
            return response()->json([
                'message' => 'Événement déplacé avec succès',
                'task' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du déplacement de l\'événement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Redimensionner une tâche sur le calendrier
     */
    public function resizeEvent(Request $request, $id)
    {
        try {
            $request->validate([
                'end' => 'required|date'
            ]);
            
            $task = Task::findOrFail($id);
            $task->end = $request->end;
            $task->save();
            
            return response()->json([
                'message' => 'Durée de l\'événement modifiée avec succès',
                'task' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la modification de la durée',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir la couleur de bordure selon la priorité
     */
    private function getBorderColorForPriority($priority)
    {
        $colors = [
            'low' => '#28a745',    // Vert
            'normal' => '#3788d8', // Bleu
            'high' => '#fd7e14',   // Orange
            'urgent' => '#dc3545', // Rouge
        ];
        
        return $colors[$priority] ?? '#6c757d'; // Gris par défaut
    }
}