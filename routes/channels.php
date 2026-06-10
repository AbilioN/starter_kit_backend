<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user->id === $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    $chat = \App\Models\Chat::find($chatId);
    if (!$chat) {
        return false;
    }

    $chatUser = \App\Domain\Entities\ChatUserFactory::createFromModel($user);
    return $chat->hasParticipant($chatUser);
});

// Personal channel for regular users
Broadcast::channel('user.user.{userId}', function ($user, $userId) {
    return $user instanceof \App\Models\User
        && $user->id === $userId;
});

// Personal channel for admins
Broadcast::channel('user.admin.{adminId}', function ($user, $adminId) {
    return $user instanceof \App\Models\Admin
        && $user->id === $adminId;
});
