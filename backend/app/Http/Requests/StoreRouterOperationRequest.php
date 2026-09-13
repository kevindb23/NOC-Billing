<?php

namespace App\Http\Requests;

use App\Services\RouterOperationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRouterOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'correlation_id' => $this->input('correlation_id')
                ?: $this->header('X-Request-Id')
                ?: (string) Str::uuid(),
            'parameters' => $this->input('parameters', []),
        ]);
    }

    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', Rule::in(RouterOperationService::operations())],
            'parameters' => ['present', 'array', 'max:30'],
            'correlation_id' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->containsSensitiveKey($this->input('parameters', []))) {
                $validator->errors()->add('parameters', 'Operation parameters may not contain credentials or arbitrary commands.');
            }
        });
    }

    private function containsSensitiveKey(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            $normalized = strtolower((string) preg_replace('/[^a-z0-9]/', '', (string) $key));
            if ($normalized !== 'credentialprofileid' && preg_match('/(password|passphrase|token|secret|community|privatekey|authorization|command|cookie|certificate|username|user)/', $normalized) === 1) {
                return true;
            }

            if ($this->containsSensitiveKey($item)) {
                return true;
            }
        }

        return false;
    }
}
