<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SelectHotspotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'hotspot_id' => ['required', 'string', 'max:64'],
            'mode' => ['nullable', 'string', Rule::in(['snapshot', 'live', 'simulation'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'hotspot_id.required' => 'ID hotspot wajib diisi.',
            'hotspot_id.string' => 'ID hotspot harus berupa teks.',
            'hotspot_id.max' => 'ID hotspot maksimal 64 karakter.',
            'mode.in' => 'Mode data harus Snapshot Riil, Data Aktual, atau Simulasi.',
        ];
    }
}
