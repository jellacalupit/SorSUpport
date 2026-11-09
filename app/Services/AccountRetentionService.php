<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccountRetentionService
{
    /**
     * Retain accounts that have ticket-related records; otherwise remove the account.
     * Returns true when the account was anonymized.
     */
    public function remove(User $user, ?User $actor = null): bool
    {
        return DB::transaction(function () use ($user, $actor): bool {
            if ($this->hasRelatedRecords($user)) {
                $this->anonymize($user, $actor);

                return true;
            }

            AuditLog::activity(
                'account_deleted',
                $actor && (int) $actor->id !== (int) $user->id ? $actor->id : null,
                sprintf('Deleted account ID %d with no related ticket records.', $user->id)
            );
            User::query()->whereKey($user->getKey())->delete();

            return false;
        });
    }

    protected function hasRelatedRecords(User $user): bool
    {
        $names = $this->namesFor($user);

        return $user->student()->whereHas('complaints')->exists()
            || Ticket::query()
                ->where('assigned_to', $user->id)
                ->orWhere('current_handler_id', $user->id)
                ->exists()
            || $user->threadMessages()->exists()
            || $user->auditLogs()->exists()
            || $this->auditMentions($names);
    }

    protected function anonymize(User $user, ?User $actor): void
    {
        $names = $this->namesFor($user);

        $avatarPath = $user->getRawOriginal('avatar_path');
        if (filled($avatarPath)) {
            Storage::disk('public')->delete($avatarPath);
        }

        $placeholder = 'deleted-' . $user->id;
        $user->forceFill([
            'name' => 'Deleted user',
            'first_name' => 'Deleted',
            'middle_name' => null,
            'last_name' => 'user',
            'username' => $placeholder,
            'email' => $placeholder . '@deleted.invalid',
            'avatar_path' => null,
            'is_active' => false,
            'email_verified_at' => null,
            'must_change_password' => true,
        ])->save();

        foreach ($names as $name) {
            AuditLog::query()
                ->whereNotNull('details')
                ->whereRaw('INSTR(details, ?) > 0', [$name])
                ->get()
                ->each(function (AuditLog $log) use ($name): void {
                    $log->update(['details' => str_replace($name, 'Deleted user', $log->details)]);
                });
        }

        AuditLog::activity(
            'account_anonymized',
            $actor?->id ?? $user->id,
            sprintf('Anonymized account ID %d; related ticket records were retained.', $user->id)
        );
    }

    /**
     * @return list<string>
     */
    protected function namesFor(User $user): array
    {
        return array_values(array_unique(array_filter([
            $user->name,
            $user->display_name,
            $user->table_name,
        ], fn (?string $name) => filled($name))));
    }

    /**
     * Audit entries that identify an account in their details are related history even when
     * the action was performed by another user.
     *
     * @param  list<string>  $names
     */
    protected function auditMentions(array $names): bool
    {
        if ($names === []) {
            return false;
        }

        return AuditLog::query()
            ->whereNotNull('details')
            ->where(function ($query) use ($names): void {
                foreach ($names as $name) {
                    $query->orWhereRaw('INSTR(details, ?) > 0', [$name]);
                }
            })
            ->exists();
    }
}
