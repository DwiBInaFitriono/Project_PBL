<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use App\Monitoring\MonitoringSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function esp(MonitoringSnapshot $snapshot): View
    {
        Gate::authorize('manage-esp');

        return view('settings.esp', $snapshot->forNode());
    }

    public function account(Request $request): View
    {
        return view('settings.account', ['user' => $request->user()]);
    }

    public function updateAccount(UpdateAccountRequest $request): RedirectResponse
    {
        $request->user()->update($request->safe()->only(['name', 'email']));

        return to_route('settings.account')->with('status', 'Informasi akun berhasil diperbarui.');
    }
}
