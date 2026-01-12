<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\MScanCategory;
use Illuminate\Http\JsonResponse;

class ScanController extends Controller
{
    public function scanCategory(): JsonResponse
    {
        $categories = MScanCategory::select('id', 'title', 'icon')->get();

        $data = $categories->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->title, 
                'icon' => $item->icon,
            ];
        });

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'data' => $data
        ]);
    }
}
