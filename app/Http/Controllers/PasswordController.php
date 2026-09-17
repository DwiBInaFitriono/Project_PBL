<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function __invoke(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->password = Hash::make($request->validated('password'));
        $user->setRememberToken(Str::random(60));
        $user->save();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('password_hash_web', Auth::guard('web')->hashPasswordForCookie($user->password));

        return to_route('settings.account')->with('password_status', 'Kata sandi berhasil diubah. Gunakan kata sandi baru saat masuk berikutnya.');
    }
}
