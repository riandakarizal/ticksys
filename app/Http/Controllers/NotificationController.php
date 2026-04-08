<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function destroy(Request $request, AppNotification $notification): RedirectResponse|JsonResponse
    {
        abort_unless($notification->user_id === Auth::id(), 404);

        $notification->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Notifikasi berhasil dihapus.',
            ]);
        }

        return back()->with('success', 'Notifikasi berhasil dihapus.');
    }
}
