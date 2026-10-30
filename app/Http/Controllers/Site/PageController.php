<?php

namespace App\Http\Controllers\Site;

use App\Domain\Support\Models\ContactMessage;
use App\Http\Controllers\Controller;
use App\Mail\ContactMessage as ContactMessageMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function pricing()
    {
        return view('site.pricing', ['plan' => DB::table('plans')->where('slug', 'pro')->where('is_active', true)->first()]);
    }

    /**
     * /contact (blueprint §4.13): validate, record in the database, then queue the formatted mail to support.
     * The honeypot field keeps bots out without an external CAPTCHA; those submissions are stored as spam and never mailed.
     */
    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254'],
            'message' => ['required', 'string', 'min:10', 'max:5000'], 'website' => ['nullable', 'string', 'max:255'],
        ]);
        $isSpam = ! empty($data['website']);
        ContactMessage::query()->create([
            'user_id' => $request->user()?->id, 'name' => $data['name'], 'email' => Str::lower($data['email']), 'message' => $data['message'],
            'status' => $isSpam ? 'spam' : 'new',
            'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip().config('app.key')) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'request_id' => $request->attributes->get('request_id'),
        ]);
        if (! $isSpam) {
            Mail::to(config('platform.support_email'))->queue(new ContactMessageMail($data['name'], $data['email'], $data['message']));
        }

        return redirect()->route('contact')->with('status', 'استلمنا رسالتك وسنرد على بريدك الإلكتروني.');
    }
}
