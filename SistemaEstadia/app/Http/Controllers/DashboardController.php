<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        return view('dashboard.index', [
            'user' => $user,
            'roleName' => $this->roleName($user->role),
        ]);
    }

    private function roleName(?string $role): string
    {
        return User::roles()[$role] ?? 'Estudiante';
    }
}
