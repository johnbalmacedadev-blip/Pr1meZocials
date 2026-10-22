<?php

namespace App\Http\Requests;

class UpdateContentRequest extends StoreContentRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('content'));
    }
}
