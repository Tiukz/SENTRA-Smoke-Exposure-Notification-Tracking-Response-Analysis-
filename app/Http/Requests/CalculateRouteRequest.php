<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CalculateRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'origin' => ['required', 'array'],
            'origin.latitude' => ['required', 'numeric', 'between:-90,90'],
            'origin.longitude' => ['required', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'array'],
            'destination.latitude' => ['required', 'numeric', 'between:-90,90'],
            'destination.longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'origin.required' => 'Titik asal wajib diisi.',
            'origin.latitude.between' => 'Latitude asal harus berada antara -90 dan 90.',
            'origin.longitude.between' => 'Longitude asal harus berada antara -180 dan 180.',
            'destination.required' => 'Titik tujuan wajib diisi.',
            'destination.latitude.between' => 'Latitude tujuan harus berada antara -90 dan 90.',
            'destination.longitude.between' => 'Longitude tujuan harus berada antara -180 dan 180.',
        ];
    }
}
