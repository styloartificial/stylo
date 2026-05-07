<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class RegisterRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string'],
            'email'         => ['required', 'string', 'email', 'unique:users,email'],
            'password'      => ['required', 'string', 'confirmed'],
            'gender'        => ['required', 'in:MALE,FEMALE'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:' . now()->subYears(17)->format('Y-m-d')], 
            'height'        => ['required', 'numeric'],
            'weight'        => ['required', 'numeric'],
            'skin_tone_id'  => ['required', 'exists:m_skin_tones,id'],
            'body_shape_id' => ['required', 'exists:m_body_shapes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'Usia minimal untuk mendaftar adalah 17 tahun.',
        ];
    }
}