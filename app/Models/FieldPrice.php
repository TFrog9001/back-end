<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'start_time',
        'end_time',
        'day_type',
        'price',
    ];

    /**
     * Get the field that owns the FieldPrice.
     */
    public function field()
    {
        return $this->belongsTo(Field::class, 'field_id');
    }
}

