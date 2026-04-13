<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message'     => ['required', 'string', 'max:1000'],
            'sender'      => ['required', 'in:customer,admin'],
            'sender_name' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required'     => 'Tin nhắn không được để trống.',
            'sender.required'      => 'Thiếu thông tin người gửi.',
            'sender.in'            => 'Người gửi không hợp lệ.',
            'sender_name.required' => 'Thiếu tên người gửi.',
        ];
    }
}