<?php

namespace App\Application\UseCases\User;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SearchUsersUseCase
{
    public function execute(string $query, int $limit = 20): array
    {
        $currentUserId = Auth::id();

        $users = User::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->where('id', '!=', $currentUserId)
            ->whereNotNull('email_verified_at')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'last_seen_at']);

        return $users->map(fn($u) => [
            'id'           => $u->id,
            'name'         => $u->name,
            'email'        => $u->email,
            'last_seen_at' => $u->last_seen_at?->format('Y-m-d H:i:s'),
        ])->values()->all();
    }
}
