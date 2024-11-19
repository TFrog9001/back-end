<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillSupply extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'supply_id',
        'quantity',
        'price',
    ];

    // Quan hệ với Bill
    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    // Quan hệ với Supply
    public function supply()
    {
        return $this->belongsTo(Supply::class, 'supply_id', 'id');
    }
}
