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

        if (config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => collect($exception->getTrace())->take(5),
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
