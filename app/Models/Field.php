<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        // 'location',
        'type',
        'status',
        'description',
    ];

    /**
     * Get the prices for the field.
     */
    public function prices()
    {
        return $this->hasMany(FieldPrice::class, 'field_id');
    }

    /**
     * Get the bookings for the field.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'field_id');
    }

    public function equipment()
    {
        return $this->belongsToMany(Equipment::class, 'equipment_field')
                    ->withPivot('allocated_at')
                    ->withTimestamps();
    }
}

