<?php

namespace App\Http\Controllers\Site;

use App\Domain\Content\Models\NewsletterSubscriber;
use App\Http\Controllers\Controller;
use App\Mail\NewsletterConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * النشرة البريدية (footer, blueprint §3.2): double opt-in. POST stores the address and queues a confirmation mail;
 * the emailed token confirms; the same token unsubscribes. Responses are redirects with a flash (and JSON for fetch()).
 */
class NewsletterController extends Controller
{
    public function store(Request $request)
    {
        // Event beacons fire while the browser follows this redirect; keep them from eating the flash (same pattern as EventController).
        if ($request->hasSession()) {
            $request->session()->reflash();
        }
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'website' => ['nullable', 'size:0'], // honeypot: bots fill it, people never see it
        ], ['email.required' => 'اكتب بريدك الإلكتروني.', 'email.email' => 'البريد الإلكتروني غير صالح.']);

        $email = Str::lower(trim($data['email']));
        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $message = 'أرسلنا رسالة تأكيد إلى بريدك. افتحها واضغط «أكّد اشتراكي».';
        if ($subscriber->exists && $subscriber->isActive()) {
            $message = 'هذا البريد مشترك بالفعل في النشرة.';
        } else {
            if (! $subscriber->exists || $subscriber->unsubscribed_at !== null) {
                $subscriber->token = Str::random(48);
                $subscriber->unsubscribed_at = null;
                $subscriber->confirmed_at = null;
            }
            $subscriber->source = $subscriber->source ?: 'footer';
            $subscriber->ip_hash = hash('sha256', (string) $request->ip());
            $subscriber->save();
            Mail::to($email)->queue(new NewsletterConfirmation($subscriber->token));
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->to(url()->previous().'#newsletter')->with('newsletter', $message);
    }

    public function confirm(string $token)
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->first();
        if (! $subscriber) {
            return view('site.newsletter', ['title' => 'رابط التأكيد غير صالح', 'message' => 'انتهت صلاحية الرابط أو سبق استخدامه. اشترك من جديد من أسفل أي صفحة.', 'ok' => false]);
        }
        if ($subscriber->confirmed_at === null) {
            $subscriber->forceFill(['confirmed_at' => now(), 'unsubscribed_at' => null])->save();
        }

        return view('site.newsletter', ['title' => 'تم تأكيد اشتراكك', 'message' => 'ستصلك النشرة الأسبوعية: فكرة، تطبيق، وأداة. يمكنك إلغاء الاشتراك من الرابط في أسفل أي رسالة.', 'ok' => true]);
    }

    public function unsubscribe(string $token)
    {
        $subscriber = NewsletterSubscriber::query()->where('token', $token)->first();
        if ($subscriber && $subscriber->unsubscribed_at === null) {
            $subscriber->forceFill(['unsubscribed_at' => now()])->save();
        }

        return view('site.newsletter', ['title' => 'تم إلغاء الاشتراك', 'message' => 'لن تصلك رسائل أخرى من النشرة. يمكنك العودة متى شئت من أسفل أي صفحة.', 'ok' => true]);
    }
}
