<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';
    protected $fillable = [
        'service',
        'description',
        'fee',
        'role_id'
    ];

    public function billServices()
    {
        return $this->hasMany(BillService::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    
}
