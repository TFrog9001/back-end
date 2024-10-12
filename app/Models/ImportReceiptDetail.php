<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportReceiptDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'item_type',
        'item_id',
        'quantity',
        'price',
    ];

    // Quan hệ với ImportReceipt
    public function receipt()
    {
        return $this->belongsTo(ImportReceipt::class, 'receipt_id');
    }

    // Dynamic relation tùy theo item_type (equipment hoặc supply)
    public function item()
    {
        if ($this->item_type === 'equipment') {
            return $this->belongsTo(Equipment::class, 'item_id');
        } else {
            return $this->belongsTo(Supply::class, 'item_id');
        }
    }
}
