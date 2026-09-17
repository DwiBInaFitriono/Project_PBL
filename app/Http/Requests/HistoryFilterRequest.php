<?php

namespace App\Http\Requests;

use App\Monitoring\HistoryFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HistoryFilterRequest extends FormRequest
{
    protected $redirectRoute = 'history.index';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'node' => ['bail', 'nullable', 'string', Rule::in(['1', '2'])],
            'sensor' => ['bail', 'nullable', 'string', Rule::in(array_column(config('monitoring.sensors'), 'id'))],
            'from' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'],
            'to' => ['bail', 'nullable', 'string', 'date_format:Y-m-d'],
            'timezone' => ['bail', 'sometimes', 'required', 'string', Rule::in(['Asia/Jakarta', 'UTC'])],
            'page' => ['bail', 'nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $filters = new HistoryFilters($validator->validated());
            if ($filters->from->greaterThan($filters->to)) {
                $validator->errors()->add('to', 'Sampai tanggal harus sama dengan atau setelah dari tanggal.');
            } elseif ($filters->from->diffInDays($filters->to) >= HistoryFilters::MAX_DAYS) {
                $validator->errors()->add('to', 'Rentang tanggal maksimal 366 hari termasuk tanggal awal dan akhir.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'node.*' => 'Node yang dipilih tidak valid.',
            'sensor.*' => 'Parameter yang dipilih tidak valid.',
            'from.*' => 'Dari tanggal harus berupa tanggal valid dengan format YYYY-MM-DD.',
            'to.*' => 'Sampai tanggal harus berupa tanggal valid dengan format YYYY-MM-DD.',
            'timezone.*' => 'Zona waktu harus berupa WIB (Asia/Jakarta) atau UTC.',
            'page.*' => 'Halaman harus berupa bilangan bulat positif.',
        ];
    }

    public function filters(): HistoryFilters
    {
        return new HistoryFilters($this->validated());
    }
}
