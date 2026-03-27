<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxIdleSeconds = 30 * 60;
        $lastActivity = (int) $request->session()->get('last_activity_at', now()->timestamp);

        if (Auth::check() && (now()->timestamp - $lastActivity) > $maxIdleSeconds) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Sesi Anda berakhir karena tidak ada aktivitas selama 30 menit.');
        }

        $request->session()->put('last_activity_at', now()->timestamp);

        return $next($request);
    }
}
