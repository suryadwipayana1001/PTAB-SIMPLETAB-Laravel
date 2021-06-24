<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return \Gate::allows('ticket_create');
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
            'title' => [
                'required'
            ],
            'status' =>[
                'required'
            ],
            'category_id' => [
                'required'
            ],
            'customer_id' => [
                'required'
            ],
            'description' => 'required'
        ];
    }
}
