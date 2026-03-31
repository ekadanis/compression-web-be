<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_id'       => ['required', 'exists:files,id'],
            'format'        => ['required', 'string', 'in:mp4,mkv,avi,mov,mp3,wav,aac,ogg'],
            'codec'         => ['nullable', 'string'],
            'bitrate'       => ['nullable', 'integer', 'min:100'],
            'resolution'    => ['nullable', 'string', 'regex:/^\d+:\d+$/'],
            'fps'           => ['nullable', 'integer', 'min:1', 'max:120'],
            'audio_bitrate' => ['nullable', 'integer', 'min:32'],
            'sample_rate'   => ['nullable', 'integer', 'in:22050,44100,48000'],
            'channel'       => ['nullable', 'string', 'in:mono,stereo'],
            'is_recommended'=> ['nullable', 'boolean'],
        ];
    }
}
