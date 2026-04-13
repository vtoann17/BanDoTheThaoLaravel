<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:150'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'topic'   => ['required', 'in:order,product,payment,technical,other'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Vui lòng nhập họ tên.',
            'email.required'   => 'Vui lòng nhập email.',
            'email.email'      => 'Email không hợp lệ.',
            'topic.required'   => 'Vui lòng chọn chủ đề.',
            'topic.in'         => 'Chủ đề không hợp lệ.',
            'message.required' => 'Vui lòng nhập nội dung.',
            'message.min'      => 'Nội dung phải có ít nhất 10 ký tự.',
        ];
    }
}