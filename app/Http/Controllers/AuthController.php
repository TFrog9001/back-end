<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;


use App\Services\SmsService;
use App\Services\GoogleLoginService;
use App\Http\Controllers\Controller;
use App\Models\User;


class AuthController extends Controller
{
    protected $googleLoginService;

    protected $smsService;

    public function __construct(GoogleLoginService $googleLoginService, SmsService $smsService)
    {
        $this->middleware('auth:api', [
            'except' => [
                'login',
                'refresh',
                'checkRefreshTokenExpiration',
                'redirectToGoogle',
                'handleGoogleCallback',
                'handleFacebookCallback'
                ,
                'sendOtpForResetPassword',
                'verifyOtpForResetPassword',
                'resetPassword'
            ]
        ]);
        $this->googleLoginService = $googleLoginService;
        $this->smsService = $smsService;
    }


    public function login(Request $request)
    {
        // $credentials = request(['email', 'password']);
        try {
            if (isset($request->email)) {
                $customerMessages = [
                    'email.required' => "Email không được để trống",
                    'password.required' => 'Mật khẩu không được để trống',
                    'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự',
                    'password.max' => 'Mật khẩu tối đa 32 ký tự',
                ];

                $validator = Validator::make($request->all(), [
                    'email' => 'required|email',
                    'password' => 'required|min:8|max:32'
                ], $customerMessages);

                if ($validator->fails()) {
                    $errors = $validator->errors();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => $errors,
                    ], 442);
                } else {
                    $credentials = $request->only('email', 'password');
                }
                if (!$token = auth()->attempt($credentials)) {
                    return response()->json(['error' => 'Email or Password is incorrect'], 500);
                }
                $user_id = auth()->user()->id;
                $refreshToken = $this->createRefreshToken();
                return $this->respondWithToken($token, $refreshToken, $user_id);
            } else {
                $customerMessages = [
                    'username.required' => "Email không được để trống",
                    'password.required' => 'Mật khẩu không được để trống',
                    'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự',
                    'password.max' => 'Mật khẩu tối đa 32 ký tự',
                ];

                $validator = Validator::make($request->all(), [
                    'username' => 'required',
                    'password' => 'required|min:8|max:32'
                ], $customerMessages);

                if ($validator->fails()) {
                    $errors = $validator->errors();
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Validation failed',
                        'errors' => $errors,
                    ], 442);
                } else {
                    $credentials = $request->only('username', 'password');
                }
                if (!$token = auth()->attempt($credentials)) {
                    return response()->json(['error' => 'Username or password is incorrect'], 500);
                }
                $user_id = auth()->user()->id;

                if (auth()->user()->roles != 'super_admin' && auth()->user()->roles != 'admin' && auth()->user()->roles != 'support') {
                    return response()->json(['error' => 'You do not have permission to login'], 442);
                }
                $refreshToken = $this->createRefreshToken();
                return $this->respondWithToken($token, $refreshToken, $user_id);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function me()
    {
        try {
            return response()->json(['user' => auth()->user()]);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }


    public function refresh(Request $request)
    {
        $refreshToken = $request->bearerToken();
        try {
            $decoded = JWTAuth::getJWTProvider()->decode($refreshToken);
            $user = User::find($decoded['user_id']);
            if (!$user) {
                return response()->json([
                    "message" => "User Invalid"
                ], 404);
            }
            $token = auth()->login($user);
            $refreshToken = $this->createRefreshToken();
            return $this->respondWithToken($token, $refreshToken, $decoded['user_id']);
        } catch (JWTException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }


    protected function respondWithToken($token, $refreshToken, $userId)
    {
        return response()->json([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'user_id' => $userId,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    private function createRefreshToken()
    {
        $data = [
            'user_id' => auth()->user()->id,
            'random' => rand() . time(),
            'exp' => time() + config('jwt.refresh_ttl')
        ];

        $refreshToken = JWTAuth::getJWTProvider()->encode($data);
        return $refreshToken;
    }

    public function checkRefreshTokenExpiration(Request $request)
    {
        try {

            $refreshToken = $request->refresh_Token;
            $decoded = JWTAuth::getJWTProvider()->decode($refreshToken);

            if (isset($decoded['exp']) && $decoded['exp'] > time()) {
                return response()->json([

                    'status' => 'success',
                    'message' => "true",
                    'time' => $decoded['exp'],
                    'curent_time' => time()
                ]);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => "false"
                ]);
            }
        } catch (\Exception $e) {
            // Xảy ra lỗi khi giải mã token
            return false;
        }
    }

    public function redirectToGoogle()
    {
        return $this->googleLoginService->redirectToGoogle();
    }

    public function handleGoogleCallback()
    {
        return $this->googleLoginService->handleGoogleCallback();
    }


    public function sendOtpForResetPassword(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
        ]);


        $phone = $request->phone;
        $otp = rand(100000, 999999); // Tạo mã OTP gồm 6 chữ số
        // $otp = 123456;

        // Lưu OTP vào cache với thời gian sống là 5 phút
        Cache::put('otp_reset_password_' . $phone, $otp, 300);

        // Gửi OTP qua SMS
        $message = "Mã OTP để đặt lại mật khẩu của bạn là: {$otp}. Mã sẽ hết hạn sau 5 phút.";
        try {
            $this->smsService->sendSmsWithRateLimit($phone, $message);
            return response()->json(['message' => 'Mã OTP đã được gửi.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function verifyOtpForResetPassword(Request $request)
    {
        $request->validate([
            'phone' => 'required|numeric|exists:users,phone',
            'otp' => 'required|numeric',
        ]);

        $phone = $request->phone;
        $otp = $request->otp;

        // Kiểm tra OTP từ cache
        $cachedOtp = Cache::get('otp_reset_password_' . $phone);

        if ($cachedOtp && $cachedOtp == $otp) {
            // Xóa OTP sau khi xác thực thành công
            Cache::forget('otp_reset_password_' . $phone);

            // Tạo token reset mật khẩu (có thể lưu vào bảng `password_resets` hoặc JWT)
            $resetToken = bin2hex(random_bytes(32));

            // Lưu token vào Cache để liên kết với số điện thoại
            $cacheKey = 'reset_token_' . $phone;
            $tokenTTL = 900; // Thời gian sống 15 phút (900 giây)
            Cache::put($cacheKey, $resetToken, $tokenTTL);

            // Trả token cho client để sử dụng đặt lại mật khẩu
            return response()->json(['reset_token' => $resetToken], 200);
        }

        return response()->json(['message' => 'Mã OTP không đúng hoặc đã hết hạn.'], 400);
    }

    public function resetPassword(Request $request)
    {
        \Log::info($request);

        $request->validate([
            'phone' => 'required|string|exists:users,phone',
            'reset_token' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $phone = $request->phone;
        $resetToken = $request->reset_token;


        // Lấy token từ Cache
        $cachedToken = Cache::get('reset_token_' . $phone);

        \Log::info($cachedToken);

        if ($cachedToken && $cachedToken == $resetToken) {
            // Xóa token sau khi sử dụng
            Cache::forget('reset_token_' . $phone);

            // Cập nhật mật khẩu người dùng
            $user = User::where('phone', $phone)->first();
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json(['message' => 'Đặt lại mật khẩu thành công.'], 200);
        }

        return response()->json(['message' => 'Token không hợp lệ hoặc đã hết hạn.'], 400);
    }


}