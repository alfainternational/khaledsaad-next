<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
            $base = Str::slug($request->name) ?: 'workspace';
            $workspace = Workspace::query()->create([
                'name' => 'مساحة '.$request->name,
                'slug' => $base.'-'.Str::lower(Str::random(6)),
            ]);
            $workspace->members()->attach($user, ['role' => 'owner']);
            $workspace->projects()->create(['name' => 'مشروعي الأول', 'slug' => 'first-project']);
            app(CreditLedger::class)->record($workspace, 'welcome:'.$workspace->id, 'grant', 20, 'رصيد ترحيبي');

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
