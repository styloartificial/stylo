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
            ->subject('StyloAI – Kode OTP Reset Password')
            ->html("
            <div style='font-family: Arial, Helvetica, sans-serif; background-color: #ffffff; padding: 40px 0;'>
                <div style='max-width: 480px; margin: auto; background-color: #ffffff; border-radius: 16px; border: 2px solid #8F42DE; padding: 32px;'>

                    <h2 style='color: #8F42DE; text-align: center; margin-bottom: 12px;'>
                        Reset Password
                    </h2>

                    <p style='color: #8F42DE; font-size: 14px; text-align: center; margin-bottom: 28px;'>
                        Berikut ini adalah kode untuk mereset password akun <strong>StyloAI</strong>.
                    </p>

                    <div style='border-radius: 14px; padding: 24px; text-align: center; margin-bottom: 28px;'>
                        <p style='margin: 0 0 10px; font-size: 14px; color: #8F42DE;'>
                            Kode OTP Anda
                        </p>

                        <div style='font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #8F42DE;'>
                            $otp
                        </div>
                    </div>

                    <hr style='border: none; border-top: 1px solid #8F42DE; margin: 28px 0;'>

                    <p style='font-size: 12px; color: #8F42DE; text-align: center;'>
                        © " . date('Y') . " Stylo Artificial
                    </p>
                </div>
            </div>
            ");


            
            $response = MailtrapClient::initSendingEmails(config('app.mailtrap_api_key'))->send($email);
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

            $reset = DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->first();
            
            if (!Hash::check($data['token'], $reset->token)) {
                return $this->clienterror('Token salah.');
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
