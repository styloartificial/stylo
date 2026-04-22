<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BaseController;
use App\Models\MSkinTone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('userDetail.skinTone');

            $data = [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'userDetail' => [
                    'img_url' => $user->userDetail?->img_url,
                    'gender' => $user->userDetail?->gender,
                    'height' => $user->userDetail?->height,
                    'weight' => $user->userDetail?->weight,
                    'skin_tone_id' => $user->userDetail?->skin_tone_id,
                    'skin_tone' => $user->userDetail?->skinTone ? [
                        'id' => $user->userDetail->skinTone->id,
                        'name' => $user->userDetail->skinTone->title,
                        'description' => $user->userDetail->skinTone->description
                    ] : null
                ]
            ];

            return response()->json([
                'code' => 200,
                'message' => 'Success.',
                'data' => $data
            ]);

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function getSkinTone()
    {
        try {
            // Mengambil semua data dari table m_skin_tones
            $skinTones = MSkinTone::all();

            // Mapping agar output sesuai dengan permintaan (title -> name)
            $formattedData = $skinTones->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->title, // Mengubah 'title' menjadi 'name' sesuai dokumentasi API
                    'description' => $item->description,
                ];
            });

            return response()->json([
                'code' => 200,
                'message' => 'Success.',
                'data' => $formattedData
            ]);

        } catch (Throwable $th) {
            // Menggunakan method error handling dari BaseController kamu
            return $this->serverError($th);
        }
    }

    public function update(Request $request)
    {
        try {
            $user = $request->user();

            // 1. Validasi Input
            $request->validate([
                "name" => ["nullable", "string", "max:100"],
                "gender" => ["nullable", "in:MALE,FEMALE"],
                "height" => ["nullable", "numeric"],
                "weight" => ["nullable", "numeric"],
                "skin_tone_id" => ["nullable", "exists:m_skin_tones,id"]
            ]);

            // 2. Update tabel 'users' (untuk field name)
            if ($request->has('name')) {
                $user->update(['name' => $request->name]);
            }

            // 3. Update atau Create tabel 'user_details'
            // Kita ambil data yang dikirim saja untuk diupdate
            $detailData = $request->only(['gender', 'height', 'weight', 'skin_tone_id']);
            
            if (!empty($detailData)) {
                $user->userDetail()->updateOrCreate(
                    ['user_id' => $user->id], // Identifier
                    $detailData               // Data yang diupdate
                );
            }

            // 4. Load ulang data terbaru beserta relasinya untuk response
            $user->load('userDetail.skinTone');

            $data = [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'userDetail' => [
                    'img_url' => $user->userDetail?->img_url,
                    'gender' => $user->userDetail?->gender,
                    'height' => $user->userDetail?->height,
                    'weight' => $user->userDetail?->weight,
                    'skin_tone_id' => $user->userDetail?->skin_tone_id,
                    'skin_tone' => $user->userDetail?->skinTone ? [
                        'id' => $user->userDetail->skinTone->id,
                        'name' => $user->userDetail->skinTone->title, // Pakai .title sesuai model MSkinTone
                        'description' => $user->userDetail->skinTone->description
                    ] : null
                ]
            ];

            return response()->json([
                'code' => 200,
                'message' => 'Success update data.',
                'data' => $data
            ]);

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();

            // 1. Validasi Input
            // 'confirmed' artinya Laravel akan mencari input 'new_password_confirmation'
            $request->validate([
                'old_password' => ['required', 'string'],
                'new_password' => ['required', 'string', 'min:8', 'confirmed'], 
            ]);

            // 2. Cek apakah password lama sesuai dengan database
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'code' => 400,
                    'message' => 'Old password does not match.',
                    'data' => null
                ], 400);
            }

            // 3. Update Password Baru (Otomatis di-hash oleh Laravel jika menggunakan model User standar)
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'code' => 200,
                'message' => 'Success change password.',
                'data' => null
            ]);

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }

    public function changeImgUrl(Request $request)
    {
        try {
            $user = $request->user();

            // 1. Validasi Input
            $request->validate([
                'img' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'] // limit 2MB
            ]);

            // 2. Proses Upload File
            if ($request->hasFile('img')) {
                $file = $request->file('img');
                
                // Buat nama file unik: user_id_timestamp.ekstensi
                $fileName = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                
                // Simpan ke folder 'public/profile_images'
                $path = $file->storeAs('profile_images', $fileName, 'public');
                $url = asset('storage/' . $path);

                // 3. Hapus foto lama jika ada (opsional tapi disarankan)
                if ($user->userDetail && $user->userDetail->img_url) {
                    // Ambil path relatif dari URL lama untuk dihapus dari storage
                    $oldPath = str_replace(asset('storage/'), '', $user->userDetail->img_url);
                    Storage::disk('public')->delete($oldPath);
                }

                // 4. Update Database
                $user->userDetail()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['img_url' => $url]
                );
            }

            return response()->json([
                'code' => 200,
                'message' => 'Success change profile image.',
                'data' => null
            ]);

        } catch (Throwable $th) {
            return $this->serverError($th);
        }
    }
}
