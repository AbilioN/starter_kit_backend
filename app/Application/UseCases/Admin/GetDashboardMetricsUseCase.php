<?php

namespace App\Application\UseCases\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Chat;
use App\Models\File;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;

class GetDashboardMetricsUseCase
{
    public function execute(): array
    {
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $sevenDaysAgo = $now->copy()->subDays(7)->startOfDay();

        $totalUsers = User::count();
        $newUsersThisWeek = User::where('created_at', '>=', $startOfWeek)->count();
        $newUsersThisMonth = User::where('created_at', '>=', $startOfMonth)->count();

        $totalAdmins = Admin::count();

        $totalChats = Chat::count();
        $messagesThisWeek = Message::where('created_at', '>=', $startOfWeek)->count();

        // Audit action distribution for the last 7 days
        $auditActions = AuditLog::where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'action')
            ->toArray();

        // New users per day for last 7 days
        $newUsersPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $newUsersPerDay[] = [
                'date' => $day->format('Y-m-d'),
                'count' => User::whereBetween('created_at', [$day, $day->copy()->endOfDay()])->count(),
            ];
        }

        // Messages per day for last 7 days
        $messagesPerDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->startOfDay();
            $messagesPerDay[] = [
                'date' => $day->format('Y-m-d'),
                'count' => Message::whereBetween('created_at', [$day, $day->copy()->endOfDay()])->count(),
            ];
        }

        // Storage: total files and sum of sizes
        $totalFiles = File::count();
        $storageBytesUsed = File::sum('size') ?? 0;

        return [
            'users' => [
                'total' => $totalUsers,
                'new_this_week' => $newUsersThisWeek,
                'new_this_month' => $newUsersThisMonth,
                'per_day_last_7' => $newUsersPerDay,
            ],
            'admins' => [
                'total' => $totalAdmins,
            ],
            'chats' => [
                'total' => $totalChats,
                'messages_this_week' => $messagesThisWeek,
                'messages_per_day_last_7' => $messagesPerDay,
            ],
            'audit' => [
                'action_distribution' => $auditActions,
            ],
            'storage' => [
                'total_files' => $totalFiles,
                'bytes_used' => $storageBytesUsed,
            ],
        ];
    }
}
