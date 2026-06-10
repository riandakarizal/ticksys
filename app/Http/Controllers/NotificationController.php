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
                'message' => 'Notification deleted.',
            ]);
        }

        return back()->with('success', 'Notification deleted.');
    }
}
