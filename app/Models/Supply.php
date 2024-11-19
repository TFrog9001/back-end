<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supply extends Model
{
    use HasFactory;

    protected $table = 'supplies';
    protected $fillable = [
        'serial_number',
        'name',
        'image',
        'quantity',
        'price',
        'state',
    ];

    // Quan hệ với BillSupply
    public function billSupplies()
    {
        return $this->hasMany(BillSupply::class, 'supply_id');
    }

    public function importDetails()
    {
        return $this->hasMany(ImportReceiptDetail::class, 'item_id')
            ->where('item_type', 'supply');
    }
}
