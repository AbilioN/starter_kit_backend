<?php

namespace App\Models;

use App\Domain\Entities\ChatUser;
use App\Domain\Entities\ChatUserFactory;
use App\Domain\Entities\Message as MessageEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'content',
        'sender_id',
        'message_type',
        'sender_type',
        'metadata',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacionamento com o chat
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function toEntity(): MessageEntity
    {
        return new MessageEntity(
            id: $this->id,
            chatId: $this->chat_id,
            content: $this->content,
            sender: $this->getSenderChatUser(),
            messageType: $this->message_type,
            metadata: $this->metadata,
            isRead: $this->is_read,
            readAt: $this->read_at,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at
        );
    }
    /**
     * Relacionamento com o remetente (pode ser User ou Admin)
     */
    public function sender(): BelongsTo
    {
        // Primeiro tenta encontrar como User
        $user = User::find($this->sender_id);
        if ($user) {
            return $this->belongsTo(User::class, 'sender_id');
        }
        
        // Se não encontrar, tenta como Admin
        return $this->belongsTo(Admin::class, 'sender_id');
    }

    /**
     * Obtém o tipo do sender baseado na tabela chat_user
     */
    public function getSenderTypeAttribute(): ?string
    {
        return \Illuminate\Support\Facades\DB::table('chat_user')
            ->where('chat_id', $this->chat_id)
            ->where('user_id', $this->sender_id)
            ->value('user_type');
    }

    /**
     * Obtém o ChatUser que enviou a mensagem
     */
    public function getSenderChatUser(): ?ChatUser
    {
        $senderType = $this->sender_type;
        if (!$senderType) {
            return null;
        }

        return ChatUserFactory::createFromChatUserData($this->sender_id, $senderType);
    }

    /**
     * Escopo para buscar mensagens entre dois ChatUsers
     */
    public function scopeBetweenChatUsers($query, ChatUser $user1, ChatUser $user2)
    {
        return $query->whereHas('chat', function ($q) use ($user1, $user2) {
            $q->where('type', 'private')
                ->whereHas('users', function ($subQ) use ($user1) {
                    $subQ->where('user_id', $user1->getId());
                })
                ->whereHas('users', function ($subQ) use ($user2) {
                    $subQ->where('user_id', $user2->getId());
                });
        });
    }

    /**
     * Escopo para buscar mensagens entre dois usuários (método de compatibilidade)
     */
    public function scopeBetweenUsers($query, int $user1Id, int $user2Id)
    {
        return $query->whereHas('chat', function ($q) use ($user1Id, $user2Id) {
            $q->where('type', 'private')
                ->whereHas('users', function ($subQ) use ($user1Id) {
                    $subQ->where('user_id', $user1Id);
                })
                ->whereHas('users', function ($subQ) use ($user2Id) {
                    $subQ->where('user_id', $user2Id);
                });
        });
    }

    /**
     * Verifica se a mensagem é de um ChatUser
     */
    public function isFromChatUser(ChatUser $chatUser): bool
    {
        return $this->sender_id === $chatUser->getId() && 
               $this->sender_type === $chatUser->getType();
    }

    /**
     * Verifica se a mensagem é de um usuário
     */
    public function isFromUser(): bool
    {
        return $this->sender_type === 'user';
    }

    /**
     * Verifica se a mensagem é de um admin
     */
    public function isFromAdmin(): bool
    {
        return $this->sender_type === 'admin';
    }

    /**
     * Verifica se a mensagem é de um assistente
     */
    public function isFromAssistant(): bool
    {
        return $this->sender_type === 'assistant';
    }

    /**
     * Obtém informações completas do sender
     */
    public function getSenderInfo(): array
    {
        return [
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->getSenderChatUser()?->getName(),
            'is_user' => $this->isFromUser(),
            'is_admin' => $this->isFromAdmin(),
            'is_assistant' => $this->isFromAssistant()
        ];
    }

    /**
     * Marca a mensagem como lida
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    /**
     * Escopo para mensagens não lidas
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Escopo para mensagens por tipo
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('message_type', $type);
    }

    /**
     * Escopo para mensagens enviadas por um ChatUser
     */
    public function scopeBySender($query, ChatUser $chatUser)
    {
        return $query->where('sender_id', $chatUser->getId())
                    ->where('sender_type', $chatUser->getType());
    }
}
