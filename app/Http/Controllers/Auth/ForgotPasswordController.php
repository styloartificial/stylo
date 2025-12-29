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
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;

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

            $email = (new MailtrapEmail())
                ->from(new Address('stylo@styloartificial.my.id', 'Stylo Artificial'))
                ->to(new Address($data['email']))
                ->subject('StyloAI - Reset Password Token')
                ->html("
                <p>Berikut merupakan kode OTP untuk reset password akun Stylo Anda.</p>
                <h1>{{ $otp }}</h1>
                ");
            
            $response = MailtrapClient::initSendingEmails(config('mailtrap_api_key'))->send($email);
            if($response->getStatusCode() == 200) return $this->success(null, "OTP berhasil dikirimkan ke email Anda.");

            return $this->clientError('Error send email.');            
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
