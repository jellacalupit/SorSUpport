<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\AuditLog;
use App\Notifications\AccountUpdateNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->load('recipient');
        $savedNameParts = $request->session()->get('profile_name_parts', []);

        return view('profile.edit', [
            'user' => $user,
            'recipient' => $user->recipient,
            'editable' => $user->isSdsAdmin(),
            'nameParts' => array_pad(explode(' ', trim($user->name), 3), 3, ''),
            'savedNameParts' => $savedNameParts,
            'departments' => [
                'Student Development Services', 'Registrar Office', 'Guidance Office',
                'College of Education', 'College of Information and Computing Technology',
                'College of Arts and Sciences', 'Supreme Student Council', 'Bulan Campus',
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update([
            'avatar_path' => $request->file('avatar')->store('avatars', 'public'),
        ]);

        AuditLog::activity('profile_photo_updated', $user->id, sprintf('Updated profile photo for %s.', $user->name));

        return Redirect::back();
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $user->fill(array_intersect_key($validated, array_flip(['name', 'email'])));

        if ($user->isSdsAdmin() && array_key_exists('username', $validated)) {
            $user->username = $validated['username'];
            $firstName = trim((string) ($validated['first_name'] ?? ''));
            $middleName = trim((string) ($validated['middle_name'] ?? ''));
            $lastName = trim((string) ($validated['last_name'] ?? ''));
            $extension = trim((string) ($validated['extension'] ?? ''));
            $request->session()->put('profile_name_parts', [
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'extension' => $extension,
            ]);
            $user->name = trim(implode(' ', array_filter([
                $firstName,
                $middleName,
                $lastName,
                $extension,
            ], fn ($part) => $part !== null && $part !== '')));
            $user->first_name = $firstName;
            $user->middle_name = $middleName !== '' ? $middleName : null;
            $user->last_name = $lastName;
            $user->role = $validated['role'];
        }

        if (! $user->isSdsAdmin() && $request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $user->save();

        if ($user->isSdsAdmin() || $user->isRecipient()) {
            $recipientData = array_intersect_key(
                $validated,
                array_flip(['staff_id', 'department', 'designation'])
            );

            if (empty($recipientData['staff_id'])) {
                $recipientData['staff_id'] = $user->username;
            }

            if (empty($recipientData['department'])) {
                $recipientData['department'] = '';
            }

            if (empty($recipientData['designation'])) {
                $recipientData['designation'] = '';
            }

            $user->recipient()->updateOrCreate([
                'user_id' => $user->id,
            ], $recipientData);
        }

        AuditLog::activity(
            $user->isSdsAdmin() ? 'admin_profile_updated' : 'profile_updated',
            $user->id,
            sprintf('Updated profile for %s.', $user->name)
        );

        if ($user->email) {
            $user->notify(new AccountUpdateNotification(
                'Your SORSUPPORT profile was updated',
                'Your profile information was successfully updated.'
            ));
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        AuditLog::activity('account_deleted', $user->id, sprintf('Deleted account for %s.', $user->name));

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
