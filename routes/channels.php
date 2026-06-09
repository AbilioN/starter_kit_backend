<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    // Verifica se o usuário é participante do chat
    $chat = \App\Models\Chat::find($chatId);
    if (!$chat) {
        return false;
    }

    // Cria ChatUser para verificar participação
    $chatUser = \App\Domain\Entities\ChatUserFactory::createFromModel($user);
    return $chat->hasParticipant($chatUser);
});

// Personal channel for regular users — receives all MessageSent events across all their chats
Broadcast::channel('user.user.{userId}', function ($user, $userId) {
    return $user instanceof \App\Models\User
        && (int) $user->id === (int) $userId;
});

// Personal channel for admins — receives all MessageSent events across all their chats
Broadcast::channel('user.admin.{adminId}', function ($user, $adminId) {
    return $user instanceof \App\Models\Admin
        && (int) $user->id === (int) $adminId;
});
