<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'equipment_id',
        'quantity',
        'allocated_at',
        'returned_at',
    ];

    // Quan hệ với Booking
    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    // Quan hệ với Equipment
    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}
