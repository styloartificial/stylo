<?php

namespace App\Http\Requests;

class StoreSaveItemRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_id'              => ['required', 'string'],
            'is_partial'             => ['required', 'boolean'],
            'items'                  => ['required', 'array'],
            'items.*.product_name'   => ['required', 'string'],
            'items.*.img_url'        => ['required', 'image'],
            'items.*.rating'         => ['required', 'numeric'],
            'items.*.count_purchase' => ['required', 'numeric'],
            'items.*.price'          => ['required', 'numeric'],
            'items.*.product_url'    => ['required', 'url'],
        ];
    }
}