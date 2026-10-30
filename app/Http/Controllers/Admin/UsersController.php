<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ImpersonationGuard;
use App\Models\User;
use App\Support\Viewer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** المستخدمون والأدوار — /admin/users (blueprint §6.5) with «اعرض كهذا المستخدم». */
class UsersController extends Controller
{
    public const ROLES = ['member' => 'عضو', 'editor' => 'محرر', 'operations' => 'تشغيل', 'finance' => 'مالية', 'admin' => 'مدير', 'super_admin' => 'مدير أعلى'];

    public const AUDIENCES = ['owner' => 'صاحب مشروع', 'marketer' => 'مسوّق'];

    private const SORTABLE = ['name', 'email', 'platform_role', 'audience', 'created_at', 'workspaces_count'];

    public function index(Request $request): View
    {
        $this->authorizeRole($request, ['operations', 'admin', 'super_admin']);
        $q = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = User::query()->withCount('workspaces');
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }
        $users = $query->orderBy($sort, $dir)->orderBy('id')->paginate(25)->withQueryString();

        $actor = $request->user();

        return view('admin.users', [
            'users' => $users, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'roles' => self::ROLES, 'audiences' => self::AUDIENCES,
            'canEdit' => in_array($actor->platform_role, ['admin', 'super_admin'], true),
            'isSuper' => $actor->platform_role === 'super_admin',
            'impersonating' => $request->session()->has(ImpersonationGuard::SESSION_KEY),
            'stats' => [
                'total' => User::query()->count(),
                'staff' => User::query()->whereIn('platform_role', Viewer::STAFF_ROLES)->count(),
                'unverified' => User::query()->whereNull('email_verified_at')->count(),
            ],
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeRole($request, ['admin', 'super_admin']);
        $actor = $request->user();
        $data = $request->validate(['platform_role' => ['required', Rule::in(array_keys(self::ROLES))]]);

        abort_if($user->id === $actor->id, 403, 'لا يمكنك تغيير دورك أنت.');
        $touchesSuper = $data['platform_role'] === 'super_admin' || $user->platform_role === 'super_admin';
        abort_if($touchesSuper && $actor->platform_role !== 'super_admin', 403, 'منح دور المدير الأعلى أو سحبه للمدير الأعلى فقط.');

        $before = $user->platform_role;
        if ($before === $data['platform_role']) {
            return back()->with('info', 'الدور لم يتغير.');
        }
        $user->forceFill(['platform_role' => $data['platform_role']])->save();
        $this->audit($request, 'user.role_changed', $user, ['before' => $before, 'after' => $data['platform_role']]);

        return back()->with('status', 'تغيّر دور «'.$user->name.'» من '.self::ROLES[$before].' إلى '.self::ROLES[$data['platform_role']].' وسُجّل في التدقيق.');
    }

    /** «اعرض كهذا المستخدم»: sign in as a member while remembering who the admin is. */
    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $this->authorizeRole($request, ['admin', 'super_admin']);
        $actor = $request->user();
        abort_if($request->session()->has(ImpersonationGuard::SESSION_KEY), 403, 'أنهِ العرض الحالي أولاً.');
        abort_if($user->id === $actor->id, 403, 'أنت هذا المستخدم بالفعل.');
        abort_unless($user->platform_role === 'member', 403, 'يمكن العرض كعضو عادي فقط، لا كموظف.');

        $this->audit($request, 'user.impersonation_started', $user, ['admin_id' => $actor->id]);
        $request->session()->put(ImpersonationGuard::SESSION_KEY, $actor->id);
        $request->session()->forget('admin_preview_as');
        Auth::login($user);

        return redirect()->route('dashboard')->with('status', 'تعرض المنصة الآن كالمستخدم «'.$user->name.'».');
    }

    /** Reachable by the impersonated session (no staff check): restores the admin. */
    public function stopImpersonation(Request $request): RedirectResponse
    {
        $adminId = (int) $request->session()->pull(ImpersonationGuard::SESSION_KEY, 0);
        abort_unless($adminId > 0, 403);
        $admin = User::query()->find($adminId);
        $impersonated = $request->user();
        if (! $admin || ! in_array($admin->platform_role, ['admin', 'super_admin'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Auth::login($admin);
        DB::table('audit_logs')->insert([
            'user_id' => $admin->id, 'action' => 'user.impersonation_ended', 'subject_type' => 'user', 'subject_id' => (string) $impersonated?->id,
            'context' => json_encode(['name' => $impersonated?->name], JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'انتهى العرض كـ «'.($impersonated?->name ?? '—').'».');
    }

    private function audit(Request $request, string $action, User $subject, array $context): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'user', 'subject_id' => (string) $subject->id,
            'context' => json_encode($context + ['email' => $subject->email], JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function authorizeRole(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()?->platform_role, $roles, true), 403);
    }
}
