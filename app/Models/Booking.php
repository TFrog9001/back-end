<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'user_id',
        'start_time',
        'end_time',
        'booking_date',
        'price',
        'field_price',
        'status',
        'deposit',
    ];

    /**
     * Get the user that owns the Booking.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the field that the booking is for.
     */
    public function field()
    {
        return $this->belongsTo(Field::class, 'field_id');
    }
}
