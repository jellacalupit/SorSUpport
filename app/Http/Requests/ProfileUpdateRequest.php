<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user()?->isSdsAdmin()) {
            return [
                'first_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]+$/'],
                'middle_name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]+$/'],
                'last_name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-zÑñÁÉÍÓÚáéíóúüÇç\- ]+$/'],
                'extension' => ['nullable', 'string', 'max:20'],
                'email' => [
                    'required', 'string', 'lowercase', 'email', 'max:255',
                    Rule::unique(User::class)->ignore($this->user()->id),
                ],
                'username' => ['required', 'digits_between:1,20', Rule::unique(User::class)->ignore($this->user()->id)],
                'department' => [
                    'nullable',
                    'string',
                    Rule::exists('departments', 'name')->where(fn ($query) => $query->where('type', 'recipient')),
                ],
                'designation' => [
                    'nullable',
                    'string',
                    Rule::exists('department_positions', 'name')->where(function ($query) {
                        $departmentId = Department::query()
                            ->forRecipients()
                            ->where('name', $this->input('department'))
                            ->value('id');

                        $query->where('department_id', $departmentId);
                    }),
                ],
                'role' => ['required', 'in:recipient,student,sds_admin'],
            ];
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'staff_id' => ['sometimes', 'required', 'string', 'max:255'],
            'department' => ['sometimes', 'required', 'string', 'max:255'],
            'designation' => ['sometimes', 'required', 'string', 'max:255'],
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'This field is required.',
            'first_name.regex' => 'First name may only contain letters, spaces, and hyphen.',
            'middle_name.regex' => 'Middle name may only contain letters, spaces, and hyphen.',
            'last_name.required' => 'This field is required.',
            'last_name.regex' => 'Last name may only contain letters, spaces, and hyphen.',
            'email.required' => 'This field is required.',
            'username.required' => 'This field is required.',
            'department.required' => 'This field is required.',
            'designation.required' => 'This field is required.',
            'role.required' => 'This field is required.',
        ];
    }
}
