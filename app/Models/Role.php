<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_name'
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_has_permission');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role_id', 'id'); // Đảm bảo bảng 'users' có cột 'role_id'
    }
}
