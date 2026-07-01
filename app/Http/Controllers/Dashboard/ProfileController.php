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
    protected function normalizeSupabasePublicUrl(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $normalized = str_replace('\\', '/', $value);
        $folder = trim(dirname($normalized), '/');
        $fileName = basename($normalized);

        if ($folder === '.' || $folder === '') {
            return $value;
        }

        return S3Helper::getUrlFileS3($folder, $fileName);
    }

    protected function getSupabaseRelativePathFromValue(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return ltrim(str_replace('\\', '/', $value), '/');
        }

        $supabasePublicBase = config('services.supabase.url')
            . '/storage/v1/object/public/'
            . config('services.supabase.bucket')
            . '/';

        if (str_starts_with($value, $supabasePublicBase)) {
            return ltrim(str_replace($supabasePublicBase, '', $value), '/');
        }

        return null;
    }

    protected function deleteSupabaseObject(string $relativePath): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        Http::withHeaders([
            'apikey'        => config('services.supabase.key'),
            'Authorization' => 'Bearer ' . config('services.supabase.key'),
        ])->delete(
            config('services.supabase.url')
                . '/storage/v1/object/'
                . config('services.supabase.bucket')
                . '/'
                . $relativePath
        );
    }

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('userDetail.skinTone', 'userDetail.bodyShape'); // 👈 tambah bodyShape

            $data = [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'name'       => $user->name,
                'userDetail' => [
                    'img_url'       => $this->normalizeSupabasePublicUrl($user->userDetail?->img_url),
                    'gender'        => $user->userDetail?->gender,
                    'height'        => $user->userDetail?->height,
                    'weight'        => $user->userDetail?->weight,
                    'skin_tone_id'  => $user->userDetail?->skin_tone_id,
                    'skin_tone'     => $user->userDetail?->skinTone ? [
                        'id'          => $user->userDetail->skinTone->id,
                        'name'        => $user->userDetail->skinTone->title,
                        'description' => $user->userDetail->skinTone->description,
                    ] : null,
                    'body_shape_id' => $user->userDetail?->body_shape_id, 
                    'body_shape'    => $user->userDetail?->bodyShape ? [  
                        'id'          => $user->userDetail->bodyShape->id,
                        'title'       => $user->userDetail->bodyShape->title,
                        'description' => $user->userDetail->bodyShape->description,
                    ] : null,
                ],
            ];

            return response()->json([
                'code'    => 200,
                'message' => 'Success.',
                'data'    => $data,
            ]);

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

            return response()->json([
                'code'    => 200,
                'message' => 'Success.',
                'data'    => $formattedData,
            ]);

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
                'body_shape_id'  => ['nullable', 'exists:m_body_shapes,id'], 
            ]);

            if ($request->has('name')) {
                $user->update(['name' => $request->name]);
            }

            $detailData = $request->only(['gender', 'height', 'weight', 'skin_tone_id', 'body_shape_id']);

            if (!empty($detailData)) {
                $user->userDetail()->updateOrCreate(
                    ['user_id' => $user->id],
                    $detailData
                );
            }

            $user->load('userDetail.skinTone', 'userDetail.bodyShape');

            $data = [
                'user_id'    => $user->id,
                'email'      => $user->email,
                'name'       => $user->name,
                'userDetail' => [
                    'img_url'      => $this->normalizeSupabasePublicUrl($user->userDetail?->img_url),
                    'gender'       => $user->userDetail?->gender,
                    'height'       => $user->userDetail?->height,
                    'weight'       => $user->userDetail?->weight,
                    'skin_tone_id' => $user->userDetail?->skin_tone_id,
                    'skin_tone'    => $user->userDetail?->skinTone ? [
                        'id'          => $user->userDetail->skinTone->id,
                        'name'        => $user->userDetail->skinTone->title,
                        'description' => $user->userDetail->skinTone->description,
                    ] : null,
                    'body_shape_id' => $user->userDetail?->body_shape_id,
                    'body_shape'    => $user->userDetail?->bodyShape ? [
                        'id'          => $user->userDetail->bodyShape->id,
                        'title'       => $user->userDetail->bodyShape->title,
                        'description' => $user->userDetail->bodyShape->description,
                    ] : null,
                ],
            ];

            return response()->json([
                'code'    => 200,
                'message' => 'Success update data.',
                'data'    => $data,
            ]);

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
                return response()->json([
                    'code'    => 400,
                    'message' => 'Old password does not match.',
                    'data'    => null,
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return response()->json([
                'code'    => 200,
                'message' => 'Success change password.',
                'data'    => null,
            ]);

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
                'img' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

            // 6. Hapus foto lama dari Supabase jika ada
            $oldImgUrl = $user->userDetail?->img_url;
            $oldRelativePath = $this->getSupabaseRelativePathFromValue($oldImgUrl);

            // 7. Update database
            $user->userDetail()->updateOrCreate(
                ['user_id' => $user->id],
                ['img_url' => $newImgUrl]
            );

            if ($oldRelativePath) {
                try {
                    $this->deleteSupabaseObject($oldRelativePath);
                } catch (Throwable) {
                }
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Success change profile image.',
                'data'    => [
                    'img_url' => $newImgUrl,
                ],
            ]);

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }
}
