<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Services\GoogleLoginService;
use App\Http\Controllers\Controller;
use App\Models\User;


class AuthController extends Controller
{
    protected $googleLoginService;
    public function __construct(GoogleLoginService $googleLoginService)
    {
        $this->middleware('auth:api', ['except' => ['login', 'refresh', 'checkRefreshTokenExpiration', 'redirectToGoogle', 'handleGoogleCallback','handleFacebookCallback']]);
        $this->googleLoginService = $googleLoginService;
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
}