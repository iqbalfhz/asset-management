<?php

namespace App\Http\Requests\Api;

use App\Models\Assignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HandOverAssetRequest extends FormRequest
{
    // Sama dengan tombol Serahkan di panel: butuh izin ubah aset itu.
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('asset'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tujuan_jenis' => ['required', Rule::in(array_keys(Assignment::TARGET_TYPES))],
            'tujuan_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'kondisi' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'tanggal_kembali' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal'],
        ];
    }

    // Tujuan yang juga bisa dipilih di panel, dengan hak lihat user yang sama.
    public function target(): Model
    {
        return Assignment::targetQuery(Assignment::TARGET_TYPES[$this->validated('tujuan_jenis')])->find($this->validated('tujuan_id'))
            ?? throw ValidationException::withMessages(['tujuan_id' => __('Tujuan serah-terima tidak ditemukan.')]);
    }

    /**
     * @return array{tanggal: string, kondisi: string, catatan: ?string, expected_return_at: ?string}
     */
    public function details(): array
    {
        return [
            'tanggal' => $this->validated('tanggal'),
            'kondisi' => $this->validated('kondisi'),
            'catatan' => $this->validated('catatan'),
            'expected_return_at' => $this->validated('tanggal_kembali'),
        ];
    }
}
