<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'receiper_name',
        'total_amount',
    ];

    // Quan hệ với ImportReceiptDetail
    public function details()
    {
        return $this->hasMany(ImportReceiptDetail::class, 'receipt_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
