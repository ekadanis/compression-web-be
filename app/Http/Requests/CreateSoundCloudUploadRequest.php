<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSoundCloudUploadRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable'],
            'tags.*' => ['string', 'max:50'],
            'genre' => ['nullable', 'string', 'max:100'],
            'sharing' => ['required', 'in:private,public'],
            'schedule_mode' => ['required', 'in:now,scheduled'],
            'scheduled_at' => ['nullable', 'required_if:schedule_mode,scheduled', 'date', 'after:now'],
        ];
    }
}
