<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class FacebookLoginService {

    public function redirectToGoogle()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

}