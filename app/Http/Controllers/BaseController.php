<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class BaseController extends Controller
{
    protected function success(
        $data = null,
        string $message = "Success.",
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    protected function clientError(
        string $message = "Bad Request.",
        $errors = null,
        int $status = 400
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }

    protected function serverError(
        Throwable $exception,
        string $message = "Internal Server Error.",
        int $status = 500
    ): JsonResponse {
        Log::error($exception);

        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}