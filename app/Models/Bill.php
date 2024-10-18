<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'total_amount',
    ];

    // Quan hệ với Booking
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Quan hệ với BillSupply
    public function supplies()
    {
        return $this->hasMany(BillSupply::class);
    }
}