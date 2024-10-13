<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipments';
    protected $fillable = [
        'serial_number',
        'name',
        'quantity',
    ];

    /**
     * Thiết bị được cấp cho các sân
     */
    public function allocations()
    {
        return $this->hasMany(EquipmentAllocation::class);
    }
}
