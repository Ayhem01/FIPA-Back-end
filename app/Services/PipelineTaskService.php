<?php
// filepath: c:\Users\AYHEM\Desktop\Back-End\Backend\app\Services\PipelineTaskService.php

namespace App\Services;

use App\Models\Task;
use App\Models\Invite;
use App\Models\InvitePipelineStage;
use Illuminate\Support\Facades\Auth;

class PipelineTaskService
{
    /**
     * Créer une tâche associée à une étape du pipeline
     */
    public function createTaskForStage($entityType, $entityId, $stageId, array $taskData)
    {
        // Vérifier que l'entité existe
        $entity = $this->getEntityByType($entityType, $entityId);
        if (!$entity) {
            throw new \Exception("Entité non trouvée: {$entityType} #{$entityId}");
        }

        // Vérifier que l'étape existe
        $stage = $this->getStageByType($entityType, $stageId);
        if (!$stage) {
            throw new \Exception("Étape non trouvée: #{$stageId}");
        }

        // Créer la tâche avec tous les champs nécessaires
        $task = new Task();
        $task->title = $taskData['title'];
        $task->description = $taskData['description'] ?? null;
        $task->start = $taskData['start'] ?? now();
        $task->end = $taskData['end'] ?? null;
        $task->type = $taskData['type'] ?? 'todo';
        $task->status = $taskData['status'] ?? 'not_started';
        $task->priority = $taskData['priority'] ?? 'normal';
        $task->color = $taskData['color'] ?? $this->getColorByType($taskData['type'] ?? 'todo');
        $task->user_id = Auth::id(); // Utilisateur connecté comme créateur
        $task->assignee_id = $taskData['assignee_id'] ?? $entity->proprietaire_id ?? Auth::id();
        
        // Associer au pipeline
        $task->entity_type = $entityType;
        $task->entity_id = $entityId;
        $task->pipeline_stage_id = $stageId;
        
        $task->save();
        
        return $task->load(['user:id,name', 'assignee:id,name']);
    }
    
    /**
     * Récupérer les tâches pour une étape spécifique
     */
    public function getTasksForStage($entityType, $entityId, $stageId)
    {
        // Vérifier que l'entité existe
        $entity = $this->getEntityByType($entityType, $entityId);
        if (!$entity) {
            throw new \Exception("Entité non trouvée: {$entityType} #{$entityId}");
        }

        // Vérifier que l'étape existe
        $stage = $this->getStageByType($entityType, $stageId);
        if (!$stage) {
            throw new \Exception("Étape non trouvée: #{$stageId}");
        }

        // Récupérer les tâches
        return Task::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('pipeline_stage_id', $stageId)
            ->with(['user:id,name', 'assignee:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Récupérer toutes les tâches pour une entité donnée
     */
    public function getAllPipelineTasks($entityType, $entityId)
    {
        // Vérifier que l'entité existe
        $entity = $this->getEntityByType($entityType, $entityId);
        if (!$entity) {
            throw new \Exception("Entité non trouvée: {$entityType} #{$entityId}");
        }

        // Récupérer les tâches
        return Task::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNotNull('pipeline_stage_id')
            ->with(['user:id,name', 'assignee:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    
    /**
     * Récupérer une tâche spécifique par son ID
     */
    public function getTaskById($taskId)
    {
        $task = Task::whereNotNull('pipeline_stage_id')
                  ->find($taskId);
                  
        if (!$task) {
            throw new \Exception("Tâche non trouvée: #{$taskId}");
        }
        
        return $task->load(['user:id,name', 'assignee:id,name']);
    }
    
    /**
     * Mettre à jour une tâche existante
     */
    public function updateTask($taskId, array $taskData)
    {
        $task = Task::whereNotNull('pipeline_stage_id')
                  ->find($taskId);
                  
        if (!$task) {
            throw new \Exception("Tâche non trouvée: #{$taskId}");
        }
        
        // Mettre à jour la couleur si le type change
        if (isset($taskData['type']) && !isset($taskData['color'])) {
            $taskData['color'] = $this->getColorByType($taskData['type']);
        }
        
        $task->update($taskData);
        
        return $task->fresh(['user:id,name', 'assignee:id,name']);
    }
    
    /**
     * Mettre à jour le statut d'une tâche
     */
    public function updateTaskStatus($taskId, $status)
    {
        $task = Task::whereNotNull('pipeline_stage_id')
                  ->find($taskId);
                  
        if (!$task) {
            throw new \Exception("Tâche non trouvée: #{$taskId}");
        }
        
        // Valider le statut
        if (!in_array($status, ['not_started', 'in_progress', 'completed', 'deferred', 'waiting'])) {
            throw new \Exception("Statut invalide: {$status}");
        }
        
        $task->update(['status' => $status]);
        
        return $task;
    }
    
    /**
     * Déplacer une tâche vers une autre étape du pipeline
     */
    public function moveTaskToStage($taskId, $newStageId)
    {
        $task = Task::whereNotNull('pipeline_stage_id')
                  ->find($taskId);
                  
        if (!$task) {
            throw new \Exception("Tâche non trouvée: #{$taskId}");
        }
        
        // Vérifier que la nouvelle étape existe
        $entityType = $task->entity_type;
        $stage = $this->getStageByType($entityType, $newStageId);
        if (!$stage) {
            throw new \Exception("Nouvelle étape non trouvée: #{$newStageId}");
        }
        
        $task->update(['pipeline_stage_id' => $newStageId]);
        
        return $task->fresh(['user:id,name', 'assignee:id,name']);
    }
    
    /**
     * Supprimer une tâche
     */
    public function deleteTask($taskId)
    {
        $task = Task::whereNotNull('pipeline_stage_id')
                  ->find($taskId);
                  
        if (!$task) {
            throw new \Exception("Tâche non trouvée: #{$taskId}");
        }
        
        return $task->delete();
    }
    
    /**
     * Récupérer une entité par son type et ID
     */
    public function getEntityByType($type, $id)
    {
        return match($type) {
            'invite' => Invite::find($id),
            'prospect' => \App\Models\Prospect::find($id),
            'investor' => \App\Models\Investisseur::find($id),
            'projet' => \App\Models\Project::find($id),
            default => null
        };
    }

    
    
    /**
     * Récupérer une étape de pipeline par type d'entité et ID
     */
    public function getStageByType($entityType, $stageId)
    {
        return match($entityType) {
            'invite' => InvitePipelineStage::find($stageId),
            'prospect' => \App\Models\ProspectPipelineStage::find($stageId),
            'investor' => \App\Models\InvestorPipelineStage::find($stageId),
            'projet' => \App\Models\ProjectPipelineStage::find($stageId),
            default => null
        };
    }
    
    /**
     * Obtenir une couleur basée sur le type de tâche
     */
    private function getColorByType($type)
    {
        return match($type) {
            'call' => '#1890ff',
            'meeting' => '#52c41a',
            'email_journal' => '#eb2f96',
            'note' => '#722ed1',
            'todo' => '#faad14',
            default => '#1890ff',
        };
    }
}