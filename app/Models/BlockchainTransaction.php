<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockchainTransaction extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'related_type','related_id','action','status',
        'tx_hash','block_number','contract_address',
        'from_address','to_address','gas_used','gas_price',
        'chain_id','network','request','response','error'
    ];

    protected $casts = [
        'request'  => 'array',
        'response' => 'array',
    ];
}