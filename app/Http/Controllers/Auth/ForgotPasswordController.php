<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendEmailRequest;
use App\Mail\ForgotPasswordOTPMail;
use App\Http\Requests\Auth\SubmitTokenRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends BaseController
{
    public function SendOtp(SendEmailRequest $request)
    {
        try {
            $data = $request->validated();
            $otp = rand(10000, 99999);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $data['email']],
                [
                    'token' => Hash::make($otp),
                    'created_at' => now()
                ]
            );

            Mail::to($data['email'])->send(new ForgotPasswordOTPMail($otp));
            
            return $this->success(null, "OTP berhasil dikirimkan ke email Anda.");
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function SubmitToken(SubmitTokenRequest $request)
    {
        try {
            $data = $request->validated();

            $reset = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

            if (!$reset) {
            return $this->clienterror('Token salah.');
        }

            if (!Hash::check($data['token'], $reset->token)) {
                return $this->clienterror('Token salah.');
        }

            return $this->success(null, 'Success.');
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function ChangePassword(ChangePasswordRequest $request)
    {
        try {
            $data = $request->validated();

            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                return $this->clientError("Email tidak ditemukan.");
            }

            $user->update([
                'password' => Hash::make($data['new_password'])
            ]);

            DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->delete();

            return $this->success(null, "Password berhasil diubah.");
        } catch (\Throwable $th) {
            return $this->serverError($th);
        }
    }
}
