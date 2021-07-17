<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return \Gate::allows('customer_edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'code' => [
                'required'
            ],
            'name' => [
                'required'
            ],
            'email' => [
                'required'
                // 'required'
            ],
            // 'password' => [
            //     'required'
            // ],
            'phone' => 'required|unique:customers,phone',
            'type' => [
                'required'
            ],
            'gender' => [
                'required'
            ],
            'address' => ['required']
        ];
    }
}
