<?php

namespace App\Http\Requests;

use Faker\Provider\Base;
use Illuminate\Foundation\Http\FormRequest;

class OpenTicketRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'img_url'                    => ['required', 'image'],
            'title'                      => ['nullable', 'string'],
            'outfit_detail'               => ['nullable', 'string'],

            'scan_category_id'           => ['required', 'array'],

            'scan_category_id.item'      => ['nullable', 'array'],
            'scan_category_id.item.*'    => ['exists:m_scan_categories,id'],

            'scan_category_id.occasion'  => ['nullable', 'array'],
            'scan_category_id.occasion.*'=> ['exists:m_scan_categories,id'],

            'scan_category_id.style'     => ['nullable', 'array'],
            'scan_category_id.style.*'   => ['exists:m_scan_categories,id'],

            'scan_category_id.hijab'     => ['nullable', 'array'],
            'scan_category_id.hijab.*'   => ['exists:m_scan_categories,id'],
        ];
    }
}
