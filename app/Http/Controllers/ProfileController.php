<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Display the user's settings form.
     */
    public function settings(Request $request): View
    {
        return view('admin.settings.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        DB::transaction(function () use ($request) {
            $user = $request->user();

            $validated = $request->validate([
                'name' => 'string|max:255',
                'email' => 'string|email|max:255|unique:users,email,' . $user->id,
                'avatar' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
                // Add other fields as needed
            ]);

            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $validated['avatar'] = $avatarPath;
            }

            $oldAttributes = $user->getAttributes();

            if (isset($validated['email']) && $user->email !== $validated['email']) {
                $validated['email_verified_at'] = null;
            }

            $user->update($validated);

            // Log activity
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->withProperties([
                    'old' => $oldAttributes,
                    'attributes' => $user->getAttributes()
                ])
                ->log('User profile updated');
        });

        return redirect()->route('profile.edit')->with('status', 'profile-updated');
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

        Auth::logout();

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->log('User account deleted');

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
