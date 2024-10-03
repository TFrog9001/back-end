<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'employee_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function messages()
    {
        return $this->hasMany(GeneralMessage::class, 'conversation_id');
    }
}
