<?php

namespace App\Services;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class GoogleLoginService
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Thêm đoạn mã để kiểm tra phản hồi từ Google
            \Log::info('Google User: ' . json_encode($googleUser));

            // Kiểm tra sự tồn tại của email trong cơ sở dữ liệu
            $user = User::where('email', $googleUser->email)->first();

            if ($user) {
                // Nếu người dùng tồn tại, cập nhật thông tin Google ID và avatar
                $user->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                ]);
            } else {
                // Nếu người dùng không tồn tại, tạo mới người dùng
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    'role_id' => '3', // user role id
                    'password' => bcrypt(Str::random(16)), // Tạo mật khẩu ngẫu nhiên cho người dùng mới
                ]);
            }

            // Đăng nhập người dùng và tạo token
            $token = auth()->login($user);
            $refreshToken = $this->createRefreshToken($user);

            return $this->respondWithToken($token, $refreshToken, $user->id);
            // echo "<script>
            //         window.opener.postMessage({
            //             access_token: '$token',
            //             refresh_token: '$refreshToken',
            //             user_id: '$user->id'
            //         }, 'http://localhost:3001');
            //         window.close();
            //         </script>";
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to authenticate with Google', 'message' => $e->getMessage()], 500);
        }
    }

    protected function respondWithToken($token, $refreshToken, $userId)
    {
        return response()->json([
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'user_id' => $userId,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    private function createRefreshToken($user)
    {
        $data = [
            'user_id' => $user->id,
            'random' => rand() . time(),
            'exp' => time() + config('jwt.refresh_ttl')
        ];

        $refreshToken = JWTAuth::getJWTProvider()->encode($data);
        return $refreshToken;
    }
}