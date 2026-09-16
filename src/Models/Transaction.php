<?php

namespace Unipay\BD\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'float',
        'raw_response' => 'array',
    ];

    public function getTable()
    {
        return config('unipay.logging.table_name', 'unipay_transactions');
    }
}
