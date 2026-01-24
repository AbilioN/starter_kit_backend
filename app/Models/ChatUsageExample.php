<?php

namespace App\Models;

use App\Domain\Entities\ChatUserFactory;

/**
 * Exemplo de uso dos novos métodos do modelo Chat
 * Este arquivo demonstra como resolver o problema de buscar participantes
 * baseado no user_type correto
 */
class ChatUsageExample
{
    /**
     * Exemplo de como buscar participantes corretamente
     */
    public static function exampleUsage()
    {
        // Supondo que você tenha um chat
        $chat = Chat::find(1);
        
        // ❌ PROBLEMA: Este método sempre busca em User::class
        // independentemente do user_type na tabela pivot
        $users = $chat->users; // Sempre busca em User::class
        
        // ✅ SOLUÇÃO 1: Usar relacionamentos específicos
        $users = $chat->users;        // Busca em User::class
        $admins = $chat->admins;      // Busca em Admin::class  
        $assistants = $chat->assistants; // Busca em Assistant::class
        
        // ✅ SOLUÇÃO 2: Buscar por tipo específico
        $users = $chat->getParticipantsByType('user');      // Busca em User::class
        $admins = $chat->getParticipantsByType('admin');    // Busca em Admin::class
        $assistants = $chat->getParticipantsByType('assistant'); // Busca em Assistant::class
        
        // ✅ SOLUÇÃO 3: Buscar todos os participantes com tipos corretos
        $allParticipants = $chat->getAllParticipants();
        // Cada participante terá o modelo correto baseado no user_type
        
        // ✅ SOLUÇÃO 4: Buscar participantes ativos por tipo
        $activeUsers = $chat->getActiveParticipantsByType('user');
        $activeAdmins = $chat->getActiveParticipantsByType('admin');
        
        // ✅ SOLUÇÃO 5: Contar participantes por tipo
        $counts = $chat->getParticipantsCountByType();
        // Retorna: ['user' => 5, 'admin' => 2, 'assistant' => 1]
        
        return [
            'users' => $users,
            'admins' => $admins,
            'assistants' => $assistants,
            'allParticipants' => $allParticipants,
            'counts' => $counts
        ];
    }
    
    /**
     * Exemplo de como iterar sobre participantes com tipos corretos
     */
    public static function iterateParticipants()
    {
        $chat = Chat::find(1);
        $participants = $chat->getAllParticipants();
        
        foreach ($participants as $participant) {
            // $participant->pivot->user_type contém o tipo correto
            $userType = $participant->pivot->user_type;
            
            switch ($userType) {
                case 'user':
                    // $participant é uma instância de User
                    echo "Usuário: " . $participant->name;
                    break;
                    
                case 'admin':
                    // $participant é uma instância de Admin
                    echo "Admin: " . $participant->name;
                    break;
                    
                case 'assistant':
                    // $participant é uma instância de Assistant
                    echo "Assistant: " . $participant->name;
                    break;
            }
            
            // Dados do pivot sempre disponíveis
            echo " - Entrou em: " . $participant->pivot->joined_at;
            echo " - Última leitura: " . $participant->pivot->last_read_at;
            echo " - Ativo: " . ($participant->pivot->is_active ? 'Sim' : 'Não');
        }
    }
    
    /**
     * Exemplo de como buscar participantes específicos para um chat privado
     */
    public static function findPrivateChatParticipants()
    {
        $chat = Chat::find(1);
        
        // Para um chat privado, você quer o outro participante
        // independentemente do tipo
        $participants = $chat->getAllParticipants();
        
        if ($participants->count() === 2) {
            // Chat privado com 2 participantes
            $participant1 = $participants->first();
            $participant2 = $participants->last();
            
            // Agora você tem os modelos corretos baseado no user_type
            return [
                'participant1' => [
                    'model' => $participant1,
                    'type' => $participant1->pivot->user_type,
                    'id' => $participant1->id
                ],
                'participant2' => [
                    'model' => $participant2,
                    'type' => $participant2->pivot->user_type,
                    'id' => $participant2->id
                ]
            ];
        }
        
        return null;
    }

    /**
     * Exemplo de uso do toEntityFromReciever para chat privado
     */
    public static function examplePrivateChatEntity()
    {
        $chat = Chat::find(1);
        
        // Supondo que você tenha um ChatUser receiver
        $receiver = ChatUserFactory::createFromChatUserData(123, 'user');
        
        // Quando você chama toEntityFromReciever, o nome será do outro participante
        $chatEntity = $chat->toEntityFromReciever($receiver);
        
        // $chatEntity->name agora contém o nome do outro participante
        // não do receiver
        
        return $chatEntity;
    }

    /**
     * Exemplo de como funciona quando há conflitos de ID
     */
    public static function exampleWithIdConflicts()
    {
        // Cenário: Chat privado entre:
        // - User com ID 123 (nome: "João Silva")
        // - Assistant com ID 123 (nome: "Bot Assistant")
        // - Admin com ID 456 (nome: "Maria Admin")
        
        $chat = Chat::find(1);
        
        // Receiver é o User (ID 123, tipo 'user')
        $userReceiver = ChatUserFactory::createFromChatUserData(123, 'user');
        $chatEntity1 = $chat->toEntityFromReciever($userReceiver);
        
        // Agora o método considera tanto ID quanto user_type
        // $chatEntity1->name será "Bot Assistant" (nome do Assistant)
        // Porque exclui o User (ID 123, tipo 'user') mas inclui o Assistant (ID 123, tipo 'assistant')
        
        // Receiver é o Assistant (ID 123, tipo 'assistant')
        $assistantReceiver = ChatUserFactory::createFromChatUserData(123, 'assistant');
        $chatEntity2 = $chat->toEntityFromReciever($assistantReceiver);
        
        // $chatEntity2->name será "João Silva" (nome do User)
        // Porque exclui o Assistant (ID 123, tipo 'assistant') mas inclui o User (ID 123, tipo 'user')
        
        return [
            'userReceiver' => $chatEntity1,
            'assistantReceiver' => $chatEntity2
        ];
    }
}
