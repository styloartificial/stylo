<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

abstract class BaseRequest extends FormRequest
{
    /**
     * Default: semua request diizinkan
     * Override jika perlu
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Format response jika validasi gagal
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error('Validation error', $validator->errors()->toArray());
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Format response jika authorize gagal
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403)
        );
    }
}
