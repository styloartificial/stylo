<?php

namespace App\Http\Controllers\Dashboard;

use App\Helpers\S3Helper;
use App\Http\Controllers\BaseController;
use App\Models\MSkinTone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ProfileController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('userDetail.skinTone');

            $data = [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'name'       => $user->name,
                'userDetail' => [
                    'img_url'      => $user->userDetail?->img_url,
                    'gender'       => $user->userDetail?->gender,
                    'height'       => $user->userDetail?->height,
                    'weight'       => $user->userDetail?->weight,
                    'skin_tone_id' => $user->userDetail?->skin_tone_id,
                    'skin_tone'    => $user->userDetail?->skinTone ? [
                        'id'          => $user->userDetail->skinTone->id,
                        'name'        => $user->userDetail->skinTone->title,
                        'description' => $user->userDetail->skinTone->description,
                    ] : null,
                ],
            ];

            return $this->success($data);
        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function getSkinTone()
    {
        try {
            $skinTones = MSkinTone::all();

            $formattedData = $skinTones->map(fn($item) => [
                'id'          => $item->id,
                'name'        => $item->title,
                'description' => $item->description,
            ]);

            return $this->success($formattedData);

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function update(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'name'         => ['nullable', 'string', 'max:100'],
                'gender'       => ['nullable', 'in:MALE,FEMALE'],
                'height'       => ['nullable', 'numeric'],
                'weight'       => ['nullable', 'numeric'],
                'skin_tone_id' => ['nullable', 'exists:m_skin_tones,id'],
            ]);

            if ($request->has('name')) {
                $user->update(['name' => $request->name]);
            }

            $detailData = $request->only(['gender', 'height', 'weight', 'skin_tone_id']);

            if (!empty($detailData)) {
                $user->userDetail()->updateOrCreate(
                    ['user_id' => $user->id],
                    $detailData
                );
            }

            $user->load('userDetail.skinTone');

            $data = [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'name'       => $user->name,
                'userDetail' => [
                    'img_url'      => $user->userDetail?->img_url,
                    'gender'       => $user->userDetail?->gender,
                    'height'       => $user->userDetail?->height,
                    'weight'       => $user->userDetail?->weight,
                    'skin_tone_id' => $user->userDetail?->skin_tone_id,
                    'skin_tone'    => $user->userDetail?->skinTone ? [
                        'id'          => $user->userDetail->skinTone->id,
                        'name'        => $user->userDetail->skinTone->title,
                        'description' => $user->userDetail->skinTone->description,
                    ] : null,
                ],
            ];

            return $this->success($data);   

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'old_password' => ['required', 'string'],
                'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if (!Hash::check($request->old_password, $user->password)) {
                return $this->clientError("Old password does not match.");
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);
            
            return $this->success(null);

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function changeImgUrl(Request $request)
    {
        try {
            $user = $request->user();

            // 1. Validasi input
            $request->validate([
                'img' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            ]);

            $file   = $request->file('img');
            $folder = 'profile-images';

            // 2. Simpan ke temp storage lokal (sekaligus konversi ke webp via S3Helper)
            $tempFileName = S3Helper::storeFileTemp($file);

            // 3. Upload dari temp ke Supabase S3
            S3Helper::storeFileToS3($folder, $tempFileName);

            // 4. Bersihkan temp file setelah upload
            S3Helper::removeFileTemp($tempFileName);

            // 5. Generate public URL dari Supabase
            $newImgUrl = S3Helper::getUrlFileS3($folder, $tempFileName);

            // 7. Update database
            $user->userDetail()->updateOrCreate(
                ['user_id' => $user->id],
                ['img_url' => $newImgUrl]
            );

            return $this->success(['img_url' => $newImgUrl]);
        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }
}
