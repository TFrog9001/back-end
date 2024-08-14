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
        $this->middleware('auth:api', ['except' => ['login', 'refresh', 'checkRefreshTokenExpiration', 'redirectToGoogle', 'handleGoogleCallback']]);
        $this->googleLoginService = $googleLoginService;
    }


    public function login(Request $request)
    {
        // $credentials = request(['email', 'password']);
        try {
            if (isset($request)) {
                $customerMessages = [
                    'email.email' => 'Định dạng email không đúng',
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


    public function logout(Request $request)
    {
        try {
            $token = $request->bearerToken();
            JWTAuth::setToken($token);
            $payload = JWTAuth::getPayload();

            // Kiểm tra xem đây có phải là access token không
            if (!isset($payload['refresh']) || $payload['refresh'] !== true) {
                auth()->logout();
                return response()->json(['message' => 'Successfully logged out']);
            } else {
                return response()->json(['error' => 'Invalid token type for logout'], 400);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
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
            $accessToken = $request->bearerToken();
            if ($accessToken) {
                try {
                    JWTAuth::setToken($accessToken);
                    $payload = JWTAuth::getPayload();
                    auth()->invalidate();
                } catch (TokenExpiredException $e) {
                } catch (JWTException $e) {
                    return response()->json(['message' => $e->getMessage()], 500);
                }
            }

            
            // auth()->invalidate($accessToken); 
            auth()->invalidate();
            
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
            'sub' => auth()->user()->id, // Sử dụng 'sub' như một standard claim cho subject
            'user_id' => auth()->user()->id,
            'random' => rand() . time(),
            'exp' => time() + config('jwt.refresh_ttl'), // Expiry time
            'iat' => time(), // Issued at time
            'iss' => config('app.url'), // Issuer claim (thường là URL của ứng dụng)
            'refresh' => true
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

    // public function redirectToFacebook()
    // {
    //     return $this->facebookLoginService->redirectToFacebook();
    // }

    // public function handleFacebookCallback()
    // {
    //     return $this->facebookLoginService->handleFacebookCallback();
    // }
}