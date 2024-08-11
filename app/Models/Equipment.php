<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'state',
    ];

    /**
     * Thiết bị được cấp cho các sân
     */
    public function fields()
    {
        return $this->belongsToMany(Field::class, 'equipment_field')
                    ->withPivot('allocated_at')
                    ->withTimestamps();
    }
}
