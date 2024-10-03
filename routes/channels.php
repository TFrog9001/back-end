<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Booking;
/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('booking.{bookingId}', function ($user, $bookingId) {
    $booking = Booking::find($bookingId);
    if (!$booking) {
        Log::error('Booking not found for ID: ' . $bookingId);
        return false;
    }
    
    return (int) $user->id === (int) $booking->user_id || $user->isEmployee();
});


