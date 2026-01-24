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
