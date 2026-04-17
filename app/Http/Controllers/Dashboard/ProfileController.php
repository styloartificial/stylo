<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BaseController;
use App\Models\MSkinTone;
use Illuminate\Http\Request;
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
                        'name' => $user->userDetail->skinTone->name,
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
}
