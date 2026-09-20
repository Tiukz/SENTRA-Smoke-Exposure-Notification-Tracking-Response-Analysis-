<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SimulateScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'wind_direction' => ['required', 'numeric', 'between:0,359'],
            'wind_speed' => ['required', 'numeric', 'gt:0', 'lte:150'],
            'projection_horizon' => ['required', 'numeric', 'gt:0', 'lte:24'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'wind_direction.required' => 'Arah angin wajib diisi.',
            'wind_direction.numeric' => 'Arah angin harus berupa angka.',
            'wind_direction.between' => 'Arah angin harus berada antara 0 dan 359 derajat.',
            'wind_speed.required' => 'Kecepatan angin wajib diisi.',
            'wind_speed.numeric' => 'Kecepatan angin harus berupa angka.',
            'wind_speed.gt' => 'Kecepatan angin harus lebih besar dari 0 km/jam.',
            'wind_speed.lte' => 'Kecepatan angin maksimal 150 km/jam untuk simulasi ini.',
            'projection_horizon.required' => 'Horizon proyeksi wajib diisi.',
            'projection_horizon.numeric' => 'Horizon proyeksi harus berupa angka.',
            'projection_horizon.gt' => 'Horizon proyeksi harus lebih besar dari 0 jam.',
            'projection_horizon.lte' => 'Horizon proyeksi maksimal 24 jam.',
        ];
    }
}
