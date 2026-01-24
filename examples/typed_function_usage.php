<?php

/**
 * Exemplo de uso da função getUserChats com tipagem adequada
 * 
 * Este exemplo demonstra como usar a função getUserChats que agora retorna
 * um DTO tipado em vez de um array genérico.
 */

use App\Application\DTOs\ChatListResponseDto;
use App\Application\DTOs\ChatListItemDto;
use App\Application\DTOs\PaginationDto;
use App\Application\DTOs\LastMessageDto;
use App\Domain\Entities\ChatUser;
use App\Infrastructure\Repositories\ChatRepository;

// Exemplo 1: Uso básico com tipagem
function exemploUsoBasico(ChatRepository $repository, ChatUser $user): void
{
    // A função agora retorna um DTO tipado
    $response = $repository->getUserChats($user, 1, 20);
    
    // TypeScript-like intellisense funciona aqui
    $chats = $response->chats; // ChatListItemDto[]
    $pagination = $response->pagination; // PaginationDto
    
    // Acesso tipado aos dados
    foreach ($chats as $chat) {
        echo "Chat: {$chat->name}\n";
        echo "Tipo: {$chat->type}\n";
        echo "Mensagens não lidas: {$chat->unreadCount}\n";
        
        if ($chat->lastMessage) {
            echo "Última mensagem: {$chat->lastMessage->content}\n";
            echo "Remetente: {$chat->lastMessage->senderType}\n";
        }
    }
    
    // Informações de paginação tipadas
    echo "Página atual: {$pagination->currentPage}\n";
    echo "Total de itens: {$pagination->total}\n";
    echo "Última página: {$pagination->lastPage}\n";
}

// Exemplo 2: Conversão para array (para APIs)
function exemploParaAPI(ChatRepository $repository, ChatUser $user): array
{
    $response = $repository->getUserChats($user, 1, 20);
    
    // Converte para array estruturado
    return $response->toArray();
    
    // Resultado:
    // [
    //     'chats' => [
    //         [
    //             'id' => 1,
    //             'name' => 'Chat Privado',
    //             'type' => 'private',
    //             'description' => 'Descrição do chat',
    //             'last_message' => [
    //                 'id' => 123,
    //                 'content' => 'Olá!',
    //                 'sender_type' => 'user',
    //                 'sender_id' => 456,
    //                 'created_at' => '2025-01-01 12:00:00'
    //             ],
    //             'unread_count' => 2,
    //             'participants_count' => 2,
    //             'created_at' => '2025-01-01 10:00:00',
    //             'updated_at' => '2025-01-01 12:00:00'
    //         ]
    //     ],
    //     'pagination' => [
    //         'current_page' => 1,
    //         'per_page' => 20,
    //         'total' => 1,
    //         'last_page' => 1,
    //         'from' => 1,
    //         'to' => 1
    //     ]
    // ]
}

// Exemplo 3: Validação de tipos em tempo de execução
function exemploValidacaoTipos(ChatRepository $repository, ChatUser $user): void
{
    $response = $repository->getUserChats($user, 1, 20);
    
    // Verificações de tipo em tempo de execução
    if ($response instanceof ChatListResponseDto) {
        echo "✅ Response é do tipo correto\n";
    }
    
    if (is_array($response->chats)) {
        echo "✅ Chats é um array\n";
    }
    
    if (!empty($response->chats) && $response->chats[0] instanceof ChatListItemDto) {
        echo "✅ Primeiro chat é do tipo correto\n";
    }
    
    if ($response->pagination instanceof PaginationDto) {
        echo "✅ Paginação é do tipo correto\n";
    }
}

// Exemplo 4: Uso com IDE/Editor que suporta PHPDoc
/**
 * @param ChatRepository $repository
 * @param ChatUser $user
 * @return ChatListResponseDto
 */
function exemploComPHPDoc(ChatRepository $repository, ChatUser $user): ChatListResponseDto
{
    // O IDE agora sabe que $response é ChatListResponseDto
    $response = $repository->getUserChats($user, 1, 20);
    
    // Autocomplete funciona para todas as propriedades
    $chats = $response->chats; // IDE sabe que é ChatListItemDto[]
    $pagination = $response->pagination; // IDE sabe que é PaginationDto
    
    return $response;
}

// Exemplo 5: Comparação com a abordagem anterior (array genérico)
function comparacaoComArrayGenerico(): void
{
    echo "=== ANTES (Array genérico) ===\n";
    echo "❌ Sem tipagem\n";
    echo "❌ Sem autocomplete\n";
    echo "❌ Sem validação de tipos\n";
    echo "❌ Propenso a erros\n";
    
    echo "\n=== DEPOIS (DTO tipado) ===\n";
    echo "✅ Tipagem forte\n";
    echo "✅ Autocomplete completo\n";
    echo "✅ Validação de tipos\n";
    echo "✅ Menos propenso a erros\n";
    echo "✅ Documentação integrada\n";
    echo "✅ Refatoração segura\n";
}

// Exemplo 6: Benefícios para testes
function exemploParaTestes(ChatRepository $repository, ChatUser $user): void
{
    $response = $repository->getUserChats($user, 1, 20);
    
    // Testes mais específicos e seguros
    assert($response instanceof ChatListResponseDto);
    assert(is_array($response->chats));
    assert($response->pagination instanceof PaginationDto);
    
    // Testes de propriedades específicas
    foreach ($response->chats as $chat) {
        assert($chat instanceof ChatListItemDto);
        assert(is_int($chat->id));
        assert(is_string($chat->name));
        assert(in_array($chat->type, ['private', 'group']));
        assert(is_int($chat->unreadCount));
        
        if ($chat->lastMessage) {
            assert($chat->lastMessage instanceof LastMessageDto);
            assert(is_string($chat->lastMessage->content));
        }
    }
    
    // Testes de paginação
    assert($response->pagination->currentPage > 0);
    assert($response->pagination->perPage > 0);
    assert($response->pagination->total >= 0);
    assert($response->pagination->lastPage >= 1);
} 