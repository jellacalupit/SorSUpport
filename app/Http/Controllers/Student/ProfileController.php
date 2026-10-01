<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update([
            'avatar_path' => $request->file('avatar')->store('avatars', 'public'),
        ]);

        AuditLog::activity('profile_photo_updated', $user->id, sprintf('Updated profile photo for %s.', $user->name));

        return back();
    }
}