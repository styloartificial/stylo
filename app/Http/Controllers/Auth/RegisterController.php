<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\CheckEmailRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\MSkinTone;
use App\Models\User;

class RegisterController extends BaseController
{
    public function CheckEmail(CheckEmailRequest $request) {
        try {
            $data = $request->validated();
            return $this->success();
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function GetSkinTone() {
        try {
            $data = MSkinTone::all();
            return $this->success($data);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function Register(RegisterRequest $request) {
        try {
            $data = $request->validated();
            $data['password'] = bcrypt($data['password']);
            
            $newUser = User::create($data)->assignRole('user');
            $newUser->userDetail()->create([
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'height' => $data['height'],
                'weight' => $data['weight'],
                'skin_tone_id' => $data['skin_tone_id'],
            ]);
            $newUser->load('userDetail');
            $token = $newUser->createToken('StyloartificialToken')->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => $newUser
            ]);
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
