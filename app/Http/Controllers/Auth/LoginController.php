<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends BaseController
{
    public function login(LoginRequest $request)
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
}
