<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserAccessController extends Controller
{
    public function index(): View
    {
        $users = User::query()->orderBy('name')->get();

        return view('admin.users.index', [
            'users' => $users,
            'allModules' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User,
            'allModules' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $defaultPassword = (string) config('pos.default_user_password', 'password');

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $defaultPassword,
            'must_change_password' => true,
            'role' => UserRole::from($validated['modules'][0]),
            'modules' => $validated['modules'],
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Akun '.$user->name.' berhasil dibuat. Password sementara: '.$defaultPassword.'. Saat login pertama, user wajib mengganti password.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'allModules' => UserRole::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validated($request, $user);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->syncModules($validated['modules']);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
            $user->must_change_password = true;
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Akses akun berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Tidak bisa menghapus akun yang sedang dipakai.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Akun dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => [Rule::in(array_column(UserRole::cases(), 'value'))],
        ]);

        $validated['modules'] = array_values(array_unique($validated['modules']));

        return $validated;
    }
}
