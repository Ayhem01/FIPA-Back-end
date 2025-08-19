<?php

namespace App\Models\Traits;

use App\Models\PipelineConversion;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPipelineConversions
{
    /**
     * Les conversions où cette entité est la source (converti vers une autre entité)
     */
    public function conversionsAsSource(): MorphMany
    {
        return $this->morphMany(PipelineConversion::class, 'source');
    }

    /**
     * Les conversions où cette entité est la cible (converti depuis une autre entité)
     */
    public function conversionsAsTarget(): MorphMany
    {
        return $this->morphMany(PipelineConversion::class, 'target');
    }

    /**
     * Vérifier si cette entité a été convertie vers un autre type
     */
    public function hasBeenConvertedTo(string $targetType): bool
    {
        return $this->conversionsAsSource()
                    ->where('target_type', $targetType)
                    ->exists();
    }

    /**
     * Vérifier si cette entité provient d'une conversion d'un autre type
     */
    public function wasConvertedFrom(string $sourceType): bool
    {
        return $this->conversionsAsTarget()
                    ->where('source_type', $sourceType)
                    ->exists();
    }

    /**
     * Obtenir l'entité vers laquelle cette entité a été convertie
     */
    public function getConvertedTo(string $targetType)
    {
        $conversion = $this->conversionsAsSource()
                           ->where('target_type', $targetType)
                           ->latest()
                           ->first();

        if (!$conversion) {
            return null;
        }

        // Obtenir la classe cible
        $targetClass = '\\App\\Models\\' . ucfirst($conversion->target_type);
        return $targetClass::find($conversion->target_id);
    }

    /**
     * Obtenir l'entité depuis laquelle cette entité a été convertie
     */
    public function getConvertedFrom(string $sourceType)
    {
        $conversion = $this->conversionsAsTarget()
                           ->where('source_type', $sourceType)
                           ->latest()
                           ->first();

        if (!$conversion) {
            return null;
        }

        // Obtenir la classe source
        $sourceClass = '\\App\\Models\\' . ucfirst($conversion->source_type);
        return $sourceClass::find($conversion->source_id);
    }

    /**
     * Obtenir la chaîne complète de conversions pour cette entité
     */
    public function getConversionChain(): array
    {
        $chain = [];
        $currentEntity = $this;
        $entityType = strtolower(class_basename($this));
        
        // Ajouter l'entité actuelle
        $chain[] = [
            'type' => $entityType,
            'id' => $this->id,
            'name' => $this->name ?? $this->nom ?? $this->title ?? $this->getFullNameAttribute() ?? "ID: {$this->id}",
            'created_at' => $this->created_at
        ];
        
        // Trouver la source de cette entité
        $conversion = PipelineConversion::where('target_type', $entityType)
                                       ->where('target_id', $this->id)
                                       ->latest()
                                       ->first();
        
        // Si aucune source trouvée, retourner juste cette entité
        if (!$conversion) {
            return $chain;
        }
        
        // Récupérer récursivement les sources précédentes
        while ($conversion) {
            $sourceType = $conversion->source_type;
            $sourceId = $conversion->source_id;
            
            // Obtenir l'entité source
            $sourceClass = "App\\Models\\" . ucfirst($sourceType);
            $sourceEntity = $sourceClass::find($sourceId);
            
            if (!$sourceEntity) {
                break;
            }
            
            // Ajouter à la chaîne
            $chain[] = [
                'type' => $sourceType,
                'id' => $sourceEntity->id,
                'name' => $sourceEntity->name ?? $sourceEntity->nom ?? $sourceEntity->title ?? 
                          ($sourceEntity->getFullNameAttribute() ?? "ID: {$sourceEntity->id}"),
                'created_at' => $sourceEntity->created_at,
                'converted_at' => $conversion->created_at,
                'converted_by' => $conversion->converter ? $conversion->converter->name : "User #{$conversion->converted_by}"
            ];
            
            // Chercher la source précédente
            $conversion = PipelineConversion::where('target_type', $sourceType)
                                           ->where('target_id', $sourceId)
                                           ->latest()
                                           ->first();
        }
        
        return array_reverse($chain);
    }
}