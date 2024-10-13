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
        'quantity',
        'price',
        'state',
    ];

    // Quan hệ với BillSupply
    public function billSupplies()
    {
        return $this->hasMany(BillSupply::class);
    }
}
