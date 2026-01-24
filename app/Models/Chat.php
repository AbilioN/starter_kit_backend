<?php

namespace App\Models;

use App\Domain\Entities\ChatUser;
use App\Domain\Entities\ChatUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Entities\Chat as ChatEntity;
use App\Models\User;
use App\Models\Admin;
use App\Models\Assistant;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function toEntity(): ChatEntity
    {
        return new ChatEntity(
            id: $this->id,
            name: $this->name,
            type: $this->type,
            description: $this->description ?? '',
            createdBy: $this->created_by,
            createdByType: $this->created_by_type,
            createdAt: $this->created_at,
            updatedAt: $this->updated_at
        );
    }

    public function toEntityFromReciever(ChatUser $reciever): ChatEntity
    {
        // dd($reciever);
        // Busca o outro participante (não o receiver) baseado no tipo correto
        // Considera tanto ID quanto user_type para evitar conflitos
        $otherParticipant = $this->getAllParticipants()
            ->filter(function ($participant) use ($reciever) {
                // Exclui o receiver baseado no ID E no tipo
                return !($participant->id == $reciever->getId() && 
                        $participant->pivot->user_type == $reciever->getType());
            })
            ->first()->toEntity();
        if (!$otherParticipant) {
            throw new \Exception('Outro participante não encontrado');
        }


        return new ChatEntity(
            id: $this->id,
            name: $otherParticipant->getName(), // Nome do outro participante
            type: $this->type,
            description: $this->description,
            createdBy: $reciever->getId(),
            createdByType: $reciever->getType(),
            createdAt: $this->created_at,
            updatedAt: $this->updated_at
        );
    }

    public function toEntityFromSender(ChatUser $sender): ChatEntity
    {
        return new ChatEntity(
            id: $this->id,
            name: $sender->getName(),
            type: $this->type,
            description: $this->description,
            createdBy: $sender->getId(),
            createdByType: $sender->getType(),
            createdAt: $this->created_at,
            updatedAt: $this->updated_at
        );
    }
    

    /**
     * Relacionamento com usuários através da tabela pivot
     * ATENÇÃO: Este relacionamento sempre busca no modelo User::class
     * Para buscar baseado no user_type, use getParticipantsByType()
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_user', 'chat_id', 'user_id')
            ->withPivot(['user_type', 'joined_at', 'last_read_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Relacionamento com admins através da tabela pivot
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'chat_user', 'chat_id', 'user_id')
            ->wherePivot('user_type', 'admin')
            ->withPivot(['joined_at', 'last_read_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Relacionamento com assistants através da tabela pivot
     */
    public function assistants(): BelongsToMany
    {
        return $this->belongsToMany(Assistant::class, 'chat_user', 'chat_id', 'user_id')
            ->wherePivot('user_type', 'assistant')
            ->withPivot(['joined_at', 'last_read_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Obtém participantes baseado no tipo específico
     * @param string $userType 'user', 'admin', ou 'assistant'
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getParticipantsByType(string $userType)
    {
        $modelClass = match ($userType) {
            'user' => User::class,
            'admin' => Admin::class,
            'assistant' => Assistant::class,
            default => throw new \InvalidArgumentException("Tipo de usuário inválido: {$userType}")
        };

        return $this->belongsToMany($modelClass, 'chat_user', 'chat_id', 'user_id')
            ->wherePivot('user_type', $userType)
            ->withPivot(['joined_at', 'last_read_at', 'is_active'])
            ->withTimestamps()
            ->get();
    }

    /**
     * Obtém todos os participantes com seus respectivos tipos
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllParticipants()
    {
        $participants = \Illuminate\Support\Facades\DB::table('chat_user')
            ->where('chat_id', $this->id)
            ->where('is_active', true)
            ->get();

        $result = collect();

        foreach ($participants as $participant) {
            $modelClass = match ($participant->user_type) {
                'user' => User::class,
                'admin' => Admin::class,
                'assistant' => Assistant::class,
                default => null
            };

            if ($modelClass) {
                $model = $modelClass::find($participant->user_id);
                if ($model) {
                    // Adiciona os dados do pivot ao modelo
                    $model->pivot = (object) [
                        'user_type' => $participant->user_type,
                        'joined_at' => $participant->joined_at,
                        'last_read_at' => $participant->last_read_at,
                        'is_active' => $participant->is_active,
                        'created_at' => $participant->created_at,
                        'updated_at' => $participant->updated_at,
                    ];
                    $result->push($model);
                }
            }
        }

        return $result;
    }

    /**
     * Relacionamento com mensagens
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Última mensagem do chat
     */
    public function lastMessage(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    /**
     * Obtém o tipo do criador baseado na tabela chat_user
     */
    public function getCreatedByTypeAttribute(): ?string
    {
        return \Illuminate\Support\Facades\DB::table('chat_user')
            ->where('chat_id', $this->id)
            ->where('user_id', $this->created_by)
            ->value('user_type');
    }

    /**
     * Obtém o ChatUser que criou o chat
     */
    public function getCreatedByChatUser(): ?ChatUser
    {
        if (!$this->created_by) {
            return null;
        }

        $userType = $this->created_by_type;
        if (!$userType) {
            return null;
        }

        return ChatUserFactory::createFromChatUserData($this->created_by, $userType);
    }

    /**
     * Verifica se é um chat privado
     */
    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }

    /**
     * Verifica se é um chat em grupo
     */
    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    /**
     * Busca ou cria um chat privado entre dois ChatUsers
     */
    public static function findOrCreatePrivateChat(ChatUser $user1, ChatUser $user2): self
    {
        $chat = self::where('type', 'private')
            ->whereHas('users', function ($query) use ($user1) {
                $query->where('user_id', $user1->getId())
                      ->where('user_type', $user1->getType());
            })
            ->whereHas('users', function ($query) use ($user2) {
                $query->where('user_id', $user2->getId())
                      ->where('user_type', $user2->getType());
            })
            ->whereDoesntHave('users', function ($query) use ($user1, $user2) {
                $query->where(function ($subQuery) use ($user1, $user2) {
                    $subQuery->where('user_id', '!=', $user1->getId())
                             ->orWhere('user_type', '!=', $user1->getType());
                })->where(function ($subQuery) use ($user1, $user2) {
                    $subQuery->where('user_id', '!=', $user2->getId())
                             ->orWhere('user_type', '!=', $user2->getType());
                });
            })
            ->first();
        if (!$chat) {
            // Cria um novo chat privado
            $chat = self::create([
                'type' => 'private',
                'created_by' => $user1->getId(),
            ]);

            // Adiciona os participantes
            $chat->addParticipant($user1);
            $chat->addParticipant($user2);
            $chat->name = $user2->getName();
            $chat->description = '';
            $chat->save();
            $chat->refresh();
        }

        return $chat;
    }

    /**
     * Adiciona um ChatUser ao chat
     */
    public function addParticipant(ChatUser $chatUser): void
    {
        $this->users()->attach($chatUser->getId(), [
            'user_type' => $chatUser->getType(),
            'joined_at' => now(),
            'is_active' => true
        ]);
    }

    /**
     * Remove um ChatUser do chat
     */
    public function removeParticipant(ChatUser $chatUser): void
    {
        $this->users()->wherePivot('user_type', $chatUser->getType())->detach($chatUser->getId());
    }

    /**
     * Verifica se um ChatUser é participante do chat
     */
    public function hasParticipant(ChatUser $chatUser): bool
    {
        // Verifica diretamente na tabela chat_user para suportar todos os tipos
        return \DB::table('chat_user')
            ->where('chat_id', $this->id)
            ->where('user_id', $chatUser->getId())
            ->where('user_type', $chatUser->getType())
            ->exists();
    }

    /**
     * Verifica se um usuário é participante do chat (método de compatibilidade)
     */
    public function hasParticipantById(int $userId): bool
    {
        return $this->users()->where('user_id', $userId)->exists();
    }

    /**
     * Marca mensagens como lidas para um ChatUser
     */
    public function markAsReadForChatUser(ChatUser $chatUser): void
    {
        $this->users()
            ->wherePivot('user_type', $chatUser->getType())
            ->updateExistingPivot($chatUser->getId(), [
                'last_read_at' => now()
            ]);

        // Marca mensagens não lidas como lidas
        $this->messages()
            ->where('sender_id', '!=', $chatUser->getId())
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Marca mensagens como lidas para um usuário (método de compatibilidade)
     */
    public function markAsReadForUser(int $userId): void
    {
        $this->users()->updateExistingPivot($userId, [
            'last_read_at' => now()
        ]);

        // Marca mensagens não lidas como lidas
        $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    /**
     * Obtém participantes ativos como ChatUsers
     */
    public function getActiveChatUsers(): array
    {
        $participants = \Illuminate\Support\Facades\DB::table('chat_user')
            ->where('chat_id', $this->id)
            ->where('is_active', true)
            ->get();

        $chatUsers = [];
        foreach ($participants as $participant) {
            $chatUsers[] = ChatUserFactory::createFromChatUserData(
                $participant->user_id,
                $participant->user_type
            );
        }

        return $chatUsers;
    }

    /**
     * Obtém participantes ativos baseado no tipo
     * @param string $userType 'user', 'admin', ou 'assistant'
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveParticipantsByType(string $userType)
    {
        return $this->getParticipantsByType($userType)
            ->where('pivot.is_active', true);
    }

    /**
     * Obtém o número de participantes por tipo
     * @return array
     */
    public function getParticipantsCountByType(): array
    {
        $counts = \Illuminate\Support\Facades\DB::table('chat_user')
            ->where('chat_id', $this->id)
            ->where('is_active', true)
            ->selectRaw('user_type, COUNT(*) as count')
            ->groupBy('user_type')
            ->pluck('count', 'user_type')
            ->toArray();

        return [
            'user' => $counts['user'] ?? 0,
            'admin' => $counts['admin'] ?? 0,
            'assistant' => $counts['assistant'] ?? 0,
        ];
    }

    /**
     * Obtém participantes ativos
     */
    public function activeParticipants()
    {
        return $this->users()->wherePivot('is_active', true);
    }

    /*
    ============================================================================
    EXEMPLOS DE USO DOS NOVOS MÉTODOS
    ============================================================================
    
    // Buscar participantes por tipo específico
    $users = $chat->getParticipantsByType('user');
    $admins = $chat->getParticipantsByType('admin');
    $assistants = $chat->getParticipantsByType('assistant');
    
    // Buscar todos os participantes com seus tipos corretos
    $allParticipants = $chat->getAllParticipants();
    
    // Buscar participantes ativos por tipo
    $activeUsers = $chat->getActiveParticipantsByType('user');
    $activeAdmins = $chat->getActiveParticipantsByType('admin');
    
    // Contar participantes por tipo
    $counts = $chat->getParticipantsCountByType();
    // Retorna: ['user' => 5, 'admin' => 2, 'assistant' => 1]
    
    // Usar relacionamentos específicos (já existiam)
    $users = $chat->users;        // Sempre busca em User::class
    $admins = $chat->admins;      // Busca em Admin::class
    $assistants = $chat->assistants; // Busca em Assistant::class
    
    ============================================================================
    */
}
