<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class BlockchainService
{
    protected function base(): string
    {
        return rtrim(config('services.blockchain.url'), '/');
    }

    protected function headers(): array
    {
        // Le MS protège toutes les requêtes non-GET via x-api-key
        return array_filter([
            'Accept'    => 'application/json',
            'x-api-key' => config('services.blockchain.api_key'),
        ]);
    }

    protected function http()
    {
        return Http::retry(3, 250)->timeout((int) config('services.blockchain.timeout', 10));
    }

    protected function ensureOk($resp): array
    {
        if ($resp->failed()) {
            throw new RuntimeException($resp->body(), $resp->status());
        }
        $json = $resp->json() ?? [];
        if (!($json['success'] ?? false)) {
            // Conserver un code générique si le MS ne met pas success=true
            throw new RuntimeException(json_encode($json), (int) ($json['code'] ?? 400));
        }
        return $json;
    }

    protected function maybeWithPk(array $payload = []): array
    {
        // Si DEPLOYER_PRIVATE_KEY est défini côté Laravel, on l’envoie.
        // Sinon, laissez vide et configurez DEPLOYER_PRIVATE_KEY côté MS (recommandé).
        if ($pk = config('services.blockchain.deployer_private_key')) {
            $payload['privateKey'] = $pk;
        }
        return $payload;
    }

    public function addInviter(
        int $inviterId,
        string $nom,
        string $prenom,
        string $email,
        string $telephone,
        int $pays_id,
        int $secteur_id
    ): array {
        $url = $this->base() . '/api/inviter/add';
        $payload = $this->maybeWithPk(compact(
            'inviterId','nom','prenom','email','telephone','pays_id','secteur_id'
        ));
        $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
        return $this->ensureOk($resp);
    }

public function updateInviter(
    int $inviterId,
    string $nom,
    string $prenom,
    string $email,
    string $telephone,
    string $status
): array {
$url = $this->base() . '/api/inviter/' . $inviterId;
$payload = $this->maybeWithPk(compact(
    'nom','prenom','email','telephone','status'
));
$resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
return $this->ensureOk($resp);
}


    public function deleteInviter(int $inviterId): array
    {
        $url = $this->base() . "/api/inviter/{$inviterId}";
        $payload = $this->maybeWithPk([]);
        // Certains clients HTTP ignorent le body en DELETE. Ici, Laravel l’enverra bien.
        $resp = $this->http()->withHeaders($this->headers())->delete($url, $payload);
        return $this->ensureOk($resp);
    }

    public function sendInvitation(int $inviterId): array
    {
        $url = $this->base() . "/api/inviter/{$inviterId}/send";
        $payload = $this->maybeWithPk([]);
        $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
        return $this->ensureOk($resp);
    }

    public function acceptInvitation(int $inviterId): array
    {
        $url = $this->base() . "/api/inviter/{$inviterId}/accept";
        $payload = $this->maybeWithPk([]);
        $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
        return $this->ensureOk($resp);
    }

    public function rejectInvitation(int $inviterId): array
    {
        $url = $this->base() . "/api/inviter/{$inviterId}/reject";
        $payload = $this->maybeWithPk([]);
        $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
        return $this->ensureOk($resp);
    }

public function convertInviterToProspect(
    int $inviterId,
    string $nom = '',
    string $adresse = '',
    int $valeurPotentielle = 0,
    string $notesInternes = ''
): array {
    $url = $this->base() . "/api/inviter/{$inviterId}/convert";
    $payload = $this->maybeWithPk([
        'nom' => $nom ?: '',
        'adresse' => $adresse ?: '',
        'valeurPotentielle' => $valeurPotentielle,
        'notesInternes' => $notesInternes ?: ''
    ]);
    $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
    return $this->ensureOk($resp);
}
    public function updateProspect(
    int $prospectId,
    string $name,
    string $description,
    int $valeurPotentielle,
    int $status,
    int $responsiblePerson
): array {
    $url = $this->base() . "/api/prospect/{$prospectId}";
    $payload = $this->maybeWithPk([
        'name' => $name,
        'description' => $description,
        'valeurPotentielle' => $valeurPotentielle,
        'status' => $status,
        'responsiblePerson' => $responsiblePerson,
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}
public function createProspectOnChain(
        string $nom,
        string $adresse = '',
        int $valeurPotentielle = 0,
        string $notesInternes = ''
    ): array {
        $url = $this->base() . "/api/prospect";
        $payload = $this->maybeWithPk([
            'nom' => $nom,
            'adresse' => $adresse ?: '',
            'valeur_potentielle' => $valeurPotentielle,
            'notes_internes' => $notesInternes ?: ''
        ]);
        $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
        return $this->ensureOk($resp);
    }
public function updateProspectStatus(
    int $prospectId,
    int $status
): array {
    $url = $this->base() . "/api/prospect/{$prospectId}/status";
    $payload = $this->maybeWithPk([
        'status' => $status,
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}
public function deleteProspect(int $prospectId): array
{
    $url = $this->base() . "/api/prospect/{$prospectId}";
    $payload = $this->maybeWithPk([]);
    $resp = $this->http()->withHeaders($this->headers())->delete($url, $payload);
    return $this->ensureOk($resp);
}
public function getProspectOnChain(int $prospectId): array
{
    $url = $this->base() . "/api/prospect/{$prospectId}";
    $resp = $this->http()->withHeaders($this->headers())->get($url);
    return $this->ensureOk($resp);
}
public function convertProspectToInvestisseur(
    int $prospectId,
    string $nom,
    int $montantInvestissement = 0,
    string $interetsSpecifiques = '',
    string $criteresInvestissement = '',
    string $statut = 'actif'
): array {
    $url = $this->base() . "/api/prospect/{$prospectId}/convert-investisseur";
    $payload = $this->maybeWithPk([
        'nom' => $nom,
        'montant_investissement' => $montantInvestissement,
        'interets_specifiques' => $interetsSpecifiques,
        'criteres_investissement' => $criteresInvestissement,
        'statut' => ucfirst(strtolower($statut)) 
    ]);
    $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
    return $this->ensureOk($resp);
}
public function createInvestisseurOnChain(
    string $nom,
    int $prospectId = 0,
    int $montantInvestissement = 0,
    string $interetsSpecifiques = '',
    string $criteresInvestissement = '',
    string $statut = 'Actif'
): array {
    $url = $this->base() . "/api/investisseur";
    $payload = $this->maybeWithPk([
        'nom' => $nom,
        'prospect_id' => $prospectId,
        'montant_investissement' => $montantInvestissement,
        'interets_specifiques' => $interetsSpecifiques,
        'criteres_investissement' => $criteresInvestissement,
        'statut' => ucfirst(strtolower($statut)) 
    ]);
    $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
    return $this->ensureOk($resp);
}
public function updateInvestisseur(
    int $investisseurId,
    string $nom,
    int $montantInvestissement,
    string $interetsSpecifiques = '',
    string $criteresInvestissement = '',
    string $statut = 'Actif'
): array {
    $url = $this->base() . "/api/investisseur/{$investisseurId}";
    $payload = $this->maybeWithPk([
        'nom' => $nom,
        'montant_investissement' => $montantInvestissement,
        'interets_specifiques' => $interetsSpecifiques ?: '',
        'criteres_investissement' => $criteresInvestissement ?: '',
        'statut' => ucfirst(strtolower($statut))
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}
public function updateInvestisseurStatus(
    int $investisseurId,
    string $statut
): array {
    $url = $this->base() . "/api/investisseur/{$investisseurId}/status";
    $payload = $this->maybeWithPk([
        'statut' => ucfirst(strtolower($statut)) 
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}
public function deleteInvestisseur(int $investisseurId): array
{
    $url = $this->base() . "/api/investisseur/{$investisseurId}";
    $payload = $this->maybeWithPk([]);
    $resp = $this->http()->withHeaders($this->headers())->delete($url, $payload);
    return $this->ensureOk($resp);
}
private function mapProjetStatusToBlockchain(string $status): string
{
    $mapping = [
        'planned' => 'Planned',           // 0
        'in_progress' => 'InProgress',    // 1
        'completed' => 'Completed',       // 2
        'abandoned' => 'Abandoned',       // 3
        'suspended' => 'Suspended',       // 4
        'on_hold' => 'OnHold'            // 5
    ];
    
    return $mapping[strtolower($status)] ?? 'Planned';
}
private function mapProjetStatusFromBlockchain(string $status): string
{
    $mapping = [
        'Planned' => 'planned',           // 0
        'InProgress' => 'in_progress',    // 1
        'Completed' => 'completed',       // 2
        'Abandoned' => 'abandoned',       // 3
        'Suspended' => 'suspended',       // 4
        'OnHold' => 'on_hold'            // 5
    ];
    
    // Supporter aussi les valeurs numériques
    $numericMapping = [
        '0' => 'planned',
        '1' => 'in_progress',
        '2' => 'completed',
        '3' => 'abandoned',
        '4' => 'suspended',
        '5' => 'on_hold'
    ];
    
    return $mapping[$status] ?? $numericMapping[$status] ?? 'planned';
}
public function convertInvestisseurToProjet(
    int $investisseurId,
    string $companyName,
    string $marketTarget,
    int $investmentAmount,
    int $jobsExpected,
    string $industrialZone,
    string $statut = 'planned'  
): array {
    $url = $this->base() . "/api/investisseur/{$investisseurId}/convert-projet";
    $payload = $this->maybeWithPk([
        'company_name' => $companyName,
        'market_target' => $marketTarget,
        'investment_amount' => $investmentAmount,
        'jobs_expected' => $jobsExpected,
        'industrial_zone' => $industrialZone,
        'statut' => $this->mapProjetStatusToBlockchain($statut)  // ✅ Mapper vers blockchain
    ]);
    $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
    return $this->ensureOk($resp);
}
public function createProjetOnChain(
    string $companyName,
    string $marketTarget,
    int $investmentAmount,
    int $jobsExpected,
    string $industrialZone,
    int $investisseurId = 0,
    string $statut = 'planned'  
): array {
    $url = $this->base() . "/api/projet";
    $payload = $this->maybeWithPk([
        'company_name' => $companyName,
        'market_target' => $marketTarget,
        'investment_amount' => $investmentAmount,
        'jobs_expected' => $jobsExpected,
        'industrial_zone' => $industrialZone,
        'investisseur_id' => $investisseurId,
        'statut' => $this->mapProjetStatusToBlockchain($statut) 
    ]);
    $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
    return $this->ensureOk($resp);
}
public function updateProjet(
    int $projetId,
    string $companyName,
    string $marketTarget,
    int $investmentAmount,
    int $jobsExpected,
    string $industrialZone,
    string $statut = 'planned'  // ✅ Recevoir le statut Laravel
): array {
    $url = $this->base() . "/api/projet/{$projetId}";
    $payload = $this->maybeWithPk([
        'company_name' => $companyName,
        'market_target' => $marketTarget,
        'investment_amount' => $investmentAmount,
        'jobs_expected' => $jobsExpected,
        'industrial_zone' => $industrialZone,
        'statut' => $this->mapProjetStatusToBlockchain($statut)  // ✅ Mapper vers blockchain
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}
public function updateProjetStatus(int $projetId, string $statut): array
{
    $url = $this->base() . "/api/projet/{$projetId}/status";
    $payload = $this->maybeWithPk([
        'statut' => $this->mapProjetStatusToBlockchain($statut)  // ✅ Mapper vers blockchain
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}
public function deleteProjet(int $projetId): array
{
    $url = $this->base() . "/api/projet/{$projetId}";
    $payload = $this->maybeWithPk([]);
    $resp = $this->http()->withHeaders($this->headers())->delete($url, $payload);
    return $this->ensureOk($resp);
}


private function mapTaskStatusToBlockchain(string $status): string
{
    $mapping = [
        'not_started' => 'NotStarted',    // 0
        'in_progress' => 'InProgress',    // 1
        'completed' => 'Completed',       // 2
        'deferred' => 'Deferred',         // 3
        'waiting' => 'Waiting'           // 4
    ];
    
    return $mapping[strtolower($status)] ?? 'NotStarted';
}

/**
 * Mapper les statuts blockchain vers les statuts Laravel pour les tâches
 */
private function mapTaskStatusFromBlockchain(string $status): string
{
    $mapping = [
        'NotStarted' => 'not_started',    // 0
        'InProgress' => 'in_progress',    // 1
        'Completed' => 'completed',       // 2
        'Deferred' => 'deferred',         // 3
        'Waiting' => 'waiting'           // 4
    ];
    
    // Supporter aussi les valeurs numériques
    $numericMapping = [
        '0' => 'not_started',
        '1' => 'in_progress',
        '2' => 'completed',
        '3' => 'deferred',
        '4' => 'waiting'
    ];
    
    return $mapping[$status] ?? $numericMapping[$status] ?? 'not_started';
}
public function createTaskOnChain(
    string $title,
    string $description,
    string $status,
    int $entityId,
    string $entityType,
    int $createdByUserId
): array {
    $url = $this->base() . "/api/task";
    $payload = $this->maybeWithPk([
        'title' => $title,
        'description' => $description,
        'status' => $this->mapTaskStatusToBlockchain($status),
        'entityId' => $entityId,
        'entityType' => $entityType,
        'createdByUserId' => $createdByUserId
    ]);
    $resp = $this->http()->withHeaders($this->headers())->post($url, $payload);
    return $this->ensureOk($resp);
}

/**
 * Récupérer une tâche depuis la blockchain
 * Route MS: GET /api/task/:taskId
 */
public function getTaskOnChain(int $taskId): array
{
    $url = $this->base() . "/api/task/{$taskId}";
    $resp = $this->http()->withHeaders($this->headers())->get($url);
    return $this->ensureOk($resp);
}

/**
 * Récupérer toutes les tâches depuis la blockchain
 * Route MS: GET /api/task/all
 */
public function getAllTasksOnChain(): array
{
    $url = $this->base() . "/api/task/all";
    $resp = $this->http()->withHeaders($this->headers())->get($url);
    return $this->ensureOk($resp);
}

/**
 * Mettre à jour une tâche sur la blockchain
 * Route MS: PUT /api/task/:taskId
 */
public function updateTaskOnChain(
    int $taskId,
    string $title,
    string $description,
    string $status
): array {
    $url = $this->base() . "/api/task/{$taskId}";
    $payload = $this->maybeWithPk([
        'title' => $title,
        'description' => $description,
        'status' => $this->mapTaskStatusToBlockchain($status)
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}

/**
 * Mettre à jour uniquement le statut d'une tâche sur la blockchain
 * Route MS: PUT /api/task/:taskId/status
 */
public function updateTaskStatusOnChain(int $taskId, string $status): array
{
    $url = $this->base() . "/api/task/{$taskId}/status";
    $payload = $this->maybeWithPk([
        'status' => $this->mapTaskStatusToBlockchain($status)
    ]);
    $resp = $this->http()->withHeaders($this->headers())->put($url, $payload);
    return $this->ensureOk($resp);
}

/**
 * Supprimer une tâche sur la blockchain
 * Route MS: DELETE /api/task/:taskId
 */
public function deleteTaskOnChain(int $taskId): array
{
    $url = $this->base() . "/api/task/{$taskId}";
    $payload = $this->maybeWithPk([]);
    $resp = $this->http()->withHeaders($this->headers())->delete($url, $payload);
    return $this->ensureOk($resp);
}

}