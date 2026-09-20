<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SwitchDataModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['snapshot', 'live', 'simulation'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mode.required' => 'Mode data wajib dipilih.',
            'mode.in' => 'Mode data harus Snapshot Riil, Data Aktual, atau Simulasi.',
        ];
    }
}
