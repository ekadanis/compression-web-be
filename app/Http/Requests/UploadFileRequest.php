<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = 512 * 1024; // 512 MB in kilobytes

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxSize}",
                'mimes:mp4,mkv,avi,mov,mp3,wav,aac,ogg,m4a',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Allowed file types: mp4, mkv, avi, mov (video) or mp3, wav, aac, ogg, m4a (audio).',
            'file.max'   => 'File may not be greater than 512 MB.',
        ];
    }
}
