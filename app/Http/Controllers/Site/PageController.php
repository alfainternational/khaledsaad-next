<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function pricing()
    {
        return view('site.pricing', ['plan' => DB::table('plans')->where('slug', 'pro')->where('is_active', true)->first()]);
    }

    public function contact(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254'],
            'message' => ['required', 'string', 'min:10', 'max:5000'], 'website' => ['nullable', 'string', 'max:255'],
        ]);
        if (empty($data['website'])) {
            Mail::to(config('platform.support_email'))->queue(new ContactMessage($data['name'], $data['email'], $data['message']));
        }

        return redirect()->route('contact')->with('status', 'استلمنا رسالتك وسنرد على بريدك الإلكتروني.');
    }
}
