<?php

namespace App\Imports;

use App\Models\Recipient;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountsImport implements ToCollection, WithHeadingRow
{
    protected int $total = 0;
    protected int $imported = 0;
    protected int $failed = 0;
    protected int $duplicates = 0;
    protected int $created = 0;
    protected int $updated = 0;
    protected array $errors = [];
    protected array $seenIdentifiers = [];
    protected array $seenEmails = [];

    public function __construct(protected string $accountType)
    {
        if (! in_array($accountType, [User::ROLE_STUDENT, User::ROLE_RECIPIENT], true)) {
            throw new \InvalidArgumentException('Unsupported account type.');
        }
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = $this->normalizeRow($rawRow->toArray());

            if ($this->isBlankRow($row)) {
                continue;
            }

            $this->total++;
            $validation = Validator::make($row, $this->rules($row), $this->messages());

            if ($validation->fails()) {
                $this->recordFailure($rowNumber, $validation->errors()->toArray());
                continue;
            }

            $identifierField = $this->accountType === User::ROLE_STUDENT ? 'student_id' : 'staff_id';
            $identifier = strtolower((string) $row[$identifierField]);
            $email = strtolower((string) $row['email']);

            if (isset($this->seenIdentifiers[$identifier]) || isset($this->seenEmails[$email])) {
                $this->duplicates++;
                $this->recordFailure($rowNumber, [
                    $identifierField => ['Duplicate identifier or email in this upload.'],
                ], true);
                continue;
            }

            $this->seenIdentifiers[$identifier] = true;
            $this->seenEmails[$email] = true;

            try {
                DB::transaction(function () use ($row, $identifierField): void {
                    $profile = $this->accountType === User::ROLE_STUDENT
                        ? Student::where('student_id', $row[$identifierField])->first()
                        : Recipient::where('staff_id', $row[$identifierField])->first();
                    $user = $profile?->user;

                    if (! $user) {
                        $user = User::where('email', $row['email'])->first();
                    }

                    if ($user && $user->role !== $this->accountType) {
                        throw new \RuntimeException('The email belongs to another account type.');
                    }

                    if (! $user) {
                        $user = User::create([
                            'name' => $row['name'],
                            'first_name' => $row['first_name'],
                            'middle_name' => $row['middle_name'] ?? null,
                            'last_name' => $row['last_name'],
                            'username' => $row[$identifierField],
                            'email' => $row['email'],
                            'password' => Hash::make($row[$identifierField]),
                            'must_change_password' => true,
                            'role' => $this->accountType,
                            'is_active' => false,
                            'email_verified_at' => null,
                        ]);
                        $this->created++;
                    } else {
                        $user->update([
                            'name' => $row['name'],
                            'first_name' => $row['first_name'],
                            'middle_name' => $row['middle_name'] ?? null,
                            'last_name' => $row['last_name'],
                            'username' => $row[$identifierField],
                            'email' => $row['email'],
                            'is_active' => $user->hasVerifiedEmail() ? $row['is_active'] : false,
                        ]);
                        $this->updated++;
                    }

                    if ($this->accountType === User::ROLE_STUDENT) {
                        Student::updateOrCreate(['user_id' => $user->id], [
                            'student_id' => $row['student_id'],
                            'college' => $row['college'],
                            'program' => $row['program'],
                            'year_level' => $row['year_level'],
                            'block' => $row['block'] ?? '',
                        ]);
                    } else {
                        Recipient::updateOrCreate(['user_id' => $user->id], [
                            'staff_id' => $row['staff_id'],
                            'unit' => $row['unit'] ?? '',
                            'designation' => $row['designation'] ?? '',
                        ]);
                    }
                });

                $this->imported++;
            } catch (\Throwable $exception) {
                $message = $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : (str_contains(strtolower($exception->getMessage()), 'unique')
                        ? 'Staff ID or email already exists.'
                        : 'This account could not be imported.');
                $this->recordFailure($rowNumber, [
                    $identifierField => [$message],
                ]);
            }
        }
    }

    protected function normalizeRow(array $row): array
    {
        $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
        $row['year_level'] = $row['year_level'] ?? $row['year'] ?? null;
        $row['designation'] = $row['designation'] ?? $row['position'] ?? null;
        $row['status'] = is_string($row['status'] ?? null) ? strtolower($row['status']) : ($row['status'] ?? null);
        foreach (['student_id', 'staff_id', 'year_level'] as $numericField) {
            if (isset($row[$numericField]) && $row[$numericField] !== '') {
                $row[$numericField] = (string) $row[$numericField];
            }
        }
        $row['block'] = isset($row['block']) && $row['block'] !== null ? (string) $row['block'] : null;
        foreach (['middle_name', 'block'] as $optionalField) {
            if (isset($row[$optionalField]) && trim((string) $row[$optionalField]) === '') {
                $row[$optionalField] = null;
            }
        }
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['full_name'] ?? ''));
        }

        if ($name !== '') {
            $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (trim((string) ($row['first_name'] ?? '')) === '') {
                $row['first_name'] = array_shift($parts) ?: '';
            }
            if (trim((string) ($row['last_name'] ?? '')) === '') {
                $row['last_name'] = array_pop($parts) ?: '';
            }
            if (trim((string) ($row['middle_name'] ?? '')) === '') {
                $row['middle_name'] = implode(' ', $parts) ?: null;
            }
        }

        // Older templates used "department" and "course"; keep reading them.
        $row['college'] = $row['college'] ?? $row['department'] ?? null;
        $row['program'] = $row['program'] ?? $row['course'] ?? null;
        $row['unit'] = $row['unit'] ?? $row['college_office'] ?? $row['office'] ?? $row['college'] ?? $row['recipient_department'] ?? null;
        $row['year_level'] = $this->normalizeYear($row['year_level'] ?? null);
        $row['is_active'] = $this->normalizeStatus($row['status'] ?? 'active');
        $row['name'] = trim(implode(' ', array_filter([
            $row['first_name'] ?? '',
            $row['middle_name'] ?? '',
            $row['last_name'] ?? '',
        ])));

        return $row;
    }

    protected function isBlankRow(array $row): bool
    {
        $fields = $this->accountType === User::ROLE_STUDENT
            ? ['student_id', 'last_name', 'first_name', 'middle_name', 'email', 'college', 'program', 'year_level', 'block', 'status']
            : ['staff_id', 'last_name', 'first_name', 'middle_name', 'email', 'unit', 'designation', 'status'];

        foreach ($fields as $field) {
            if (isset($row[$field]) && trim((string) $row[$field]) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function rules(array $row): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,1,0'],
        ];

        if ($this->accountType === User::ROLE_STUDENT) {
            $collegeId = Unit::query()->colleges()->where('name', $row['college'] ?? null)->value('id');

            return array_merge($rules, [
                'student_id' => ['required', 'regex:/^\d{8}$/'],
                'college' => ['required', 'string', Rule::exists('units', 'name')->where('type', Unit::TYPE_COLLEGE)],
                'program' => ['required', 'string', Rule::exists('programs', 'name')->where('unit_id', $collegeId)],
                'year_level' => ['required', 'regex:/^[1-4](?:st|nd|rd|th)?(?:\s+year)?$/i'],
                'block' => ['nullable', 'string', 'max:255'],
            ]);
        }

        return array_merge($rules, [
            'staff_id' => ['required', 'string', 'max:50'],
            'unit' => ['nullable', 'string', Rule::exists('units', 'name')],
            'designation' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function messages(): array
    {
        return [
            'college.exists' => 'The college is not configured in System Settings.',
            'program.exists' => 'The program is not offered by the selected college.',
            'unit.exists' => 'The college or office is not configured in System Settings.',
        ];
    }

    protected function normalizeYear(mixed $year): mixed
    {
        return $year;
    }

    protected function normalizeStatus(mixed $status): ?bool
    {
        if ($status === null || $status === '') {
            return true;
        }

        return in_array(strtolower((string) $status), ['active', '1'], true);
    }

    protected function recordFailure(int $row, array $errors, bool $duplicate = false): void
    {
        $this->failed++;
        $this->errors[] = [
            'row' => $row,
            'duplicate' => $duplicate,
            'messages' => collect($errors)->flatten()->values()->all(),
        ];
    }

    public function summary(): array
    {
        return [
            'total' => $this->total,
            'imported' => $this->imported,
            'failed' => $this->failed,
            'duplicates' => $this->duplicates,
            'created' => $this->created,
            'updated' => $this->updated,
            'deactivated' => 0,
            'errors' => $this->errors,
        ];
    }
}
