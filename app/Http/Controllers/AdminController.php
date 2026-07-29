<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    protected function viewData(string $pageTitle, string $pageDescription): array
    {
        $users = User::query()
            ->with('teams:id,name')
            ->orderByRaw("CASE user_role
                WHEN 'superadmin' THEN 0
                WHEN 'admin'      THEN 1
                WHEN 'siteadmin'  THEN 2
                WHEN 'user'       THEN 3
                WHEN 'vip'        THEN 4
                ELSE 5 END")
            ->orderBy('user_name')
            ->get();

        $projects = Team::query()
            ->with(['members:id,user_name,user_role', 'lead:id,user_name'])
            ->orderBy('name')
            ->get();

        return [
            'pageTitle'       => $pageTitle,
            'pageDescription' => $pageDescription,
            'users'           => $users,
            'projects'        => $projects,
        ];
    }

    protected function respond(Request $request, string $message, string $redirect): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message'  => $message,
                'redirect' => $redirect,
            ]);
        }

        return redirect($redirect)->with('success', $message);
    }
}
