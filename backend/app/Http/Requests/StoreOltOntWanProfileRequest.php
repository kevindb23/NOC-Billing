<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreOltOntWanProfileRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['profile_id' => ['required','integer','between:0,63'], 'profile_name' => ['required','string','max:190'], 'nat_enabled' => ['sometimes','boolean'], 'status' => ['sometimes','string','in:draft,ready,applied'], 'notes' => ['nullable','string','max:5000']]; } }
