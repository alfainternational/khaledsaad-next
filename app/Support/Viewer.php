<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Who is looking at the page. Honours the admin "اعرض المنصة كـ" preview (visitor / member / beginner)
 * so staff can see the public experience without logging out (blueprint §6.1).
 */
class Viewer
{
    public const STAFF_ROLES = ['editor', 'operations', 'finance', 'admin', 'super_admin'];

    public const PREVIEWS = ['visitor' => 'زائر', 'member' => 'عضو', 'beginner' => 'مبتدئ'];

    public function previewAs(): ?string
    {
        $as = session('admin_preview_as');
        if (! is_string($as) || ! isset(self::PREVIEWS[$as])) {
            return null;
        }
        $user = Auth::user();

        return $user && in_array($user->platform_role, self::STAFF_ROLES, true) ? $as : null;
    }

    public function previewLabel(): string
    {
        return self::PREVIEWS[$this->previewAs()] ?? '';
    }

    public function user(): ?User
    {
        return Auth::user();
    }

    /** A signed-in member for product purposes (preview "visitor" hides the member state). */
    public function isMember(): bool
    {
        if ($this->previewAs() === 'visitor') {
            return false;
        }

        return Auth::check();
    }

    /** "beginner" preview: a member with no progress, no assessment and no project profile. */
    public function isBeginner(): bool
    {
        return $this->previewAs() === 'beginner';
    }

    public function isStaff(): bool
    {
        $user = Auth::user();

        return $user !== null && in_array($user->platform_role, self::STAFF_ROLES, true);
    }

    /** The real admin behind «اعرض كهذا المستخدم», or null when nobody is being impersonated. */
    public function impersonating(): ?User
    {
        $id = session('impersonator_id');
        if (! Auth::check() || ! is_numeric($id) || (int) $id <= 0) {
            return null;
        }

        return User::query()->find((int) $id);
    }
}
