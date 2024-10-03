<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingConversation extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id', 'user_id', 'employee_id'];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

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
        return $this->hasMany(BookingMessage::class, 'conversation_id');
    }
}
