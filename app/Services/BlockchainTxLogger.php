<?php

namespace App\Services;

use App\Models\BlockchainTransaction;

class BlockchainTxLogger
{
    public static function start(string $action, ?string $relatedType, ?int $relatedId, array $request = [], array $meta = []): BlockchainTransaction
    {
        // Nettoyer toute clé sensible du payload
        unset($request['privateKey'], $request['private_key']);

        return BlockchainTransaction::create([
            'action'       => $action,
            'status'       => BlockchainTransaction::STATUS_PENDING,
            'related_type' => $relatedType,
            'related_id'   => $relatedId,
            'request'      => $request,
            'network'      => $meta['network'] ?? 'ganache',
            'chain_id'     => $meta['chain_id'] ?? 5777,
        ]);
    }

    public static function success(BlockchainTransaction $tx, array $response): BlockchainTransaction
    {
        $data = $response['data'] ?? [];
        return tap($tx)->update([
            'status'          => BlockchainTransaction::STATUS_SUCCESS,
            'response'        => $response,
            'tx_hash'         => $data['transactionHash'] ?? ($response['transactionHash'] ?? null),
            'block_number'    => isset($data['blockNumber']) ? (int) $data['blockNumber'] : (isset($response['blockNumber']) ? (int)$response['blockNumber'] : null),
            'contract_address'=> $data['contractAddress'] ?? ($response['contractAddress'] ?? null),
            'gas_used'        => isset($data['gasUsed']) ? (int)$data['gasUsed'] : (isset($response['gasUsed']) ? (int)$response['gasUsed'] : null),
            'to_address'      => $data['contractAddress'] ?? null,
        ]);
    }

    public static function fail(BlockchainTransaction $tx, string $message, array $response = []): BlockchainTransaction
    {
        return tap($tx)->update([
            'status'   => BlockchainTransaction::STATUS_FAILED,
            'response' => $response ?: null,
            'error'    => $message,
        ]);
    }
}