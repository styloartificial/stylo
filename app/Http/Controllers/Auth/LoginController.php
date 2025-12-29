<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\LoginGoogleRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class LoginController extends BaseController
{
    public function Login(LoginRequest $request)
    {
        try {
            $data = $request->validated();

            $user = User::where('email', $data['email'])->first();

            if (!$user || !Hash::check($data['password'], $user->password)) {
                return $this->clientError(
                    'Email atau password salah.'
                );
            }

            $token = $user->createToken('StyloartificialToken')->plainTextToken;

            $user->load('userDetail', 'userDetail.skinTone');

            return $this->success([
                'token' => $token,
                'user' => $user
            ], 'Login berhasil');
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function LoginGoogle(LoginGoogleRequest $request)
    {
        try {
            $data = $request->validated();
            $response = Http::get(
                'https://oauth2.googleapis.com/tokeninfo',
                ['id_token' => $data['id_token']]
            );

            if (!$response->ok()) return $this->clientError("Invalid token.");

            $googleUser = $response->json();

            $checkUserExists = User::where('email', $googleUser['email'])->first();
            if (!$checkUserExists) {
                $gender = null;
                if (isset($googleUser['gender'])) {
                    if (strtolower($googleUser['gender']) == 'male') {
                        $gender = 'MALE';
                    } elseif (strtolower($googleUser['gender']) == 'female') {
                        $gender = 'FEMALE';
                    }
                }

                $user = User::create([
                    'email' => $googleUser['email'],
                    'name' => $googleUser['name'],
                    'password' => bcrypt(uniqid())
                ]);

                $user->userDetail()->create([
                    'gender' => $gender,
                    'date_of_birth' => $googleUser['birthdate'],
                    'height' => null,
                    'weight' => null,
                    'skin_tone_id' => null,
                ]);
            } else {
                $user = $checkUserExists;
            }

            $user->load('userDetail', 'userDetail.skinTone');
            $token = $user->createToken('StyloartificialToken')->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => $user
            ], 'Login berhasil');
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
