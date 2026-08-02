<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class ActiveOfficeContext
{
    public function resolve(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role === 'office') {
            return $user;
        }

        if ($user->role !== 'support') {
            abort(response()->json([
                'message' => 'Forbidden',
            ], 403));
        }

        $officeId = (int) $request->header('X-Office-ID');
        if ($officeId <= 0) {
            abort(response()->json([
                'message' => 'Forbidden',
            ], 403));
        }

        $office = $user->assignedOffices()
            ->where('users.id', $officeId)
            ->first();

        if (! $office) {
            abort(response()->json([
                'message' => 'Forbidden',
            ], 403));
        }

        return $office;
    }
}
