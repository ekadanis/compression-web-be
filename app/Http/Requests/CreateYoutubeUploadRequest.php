<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateYoutubeUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'source_type' => ['required', 'in:file,compression'],
            'source_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable'],
            'tags.*' => ['string', 'max:50'],
            'category_id' => ['nullable', 'string', 'max:20'],
            'visibility' => ['required', 'in:private,unlisted,public'],
            'schedule_mode' => ['required', 'in:now,scheduled'],
            'scheduled_at' => ['nullable', 'required_if:schedule_mode,scheduled', 'date', 'after:now'],
        ];
    }
}
