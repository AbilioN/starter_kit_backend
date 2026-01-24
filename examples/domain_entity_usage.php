<?php

/**
 * Example of using the new domain entity structure
 * 
 * This example demonstrates how to use the getUserChats function that now returns
 * a domain entity instead of a DTO, and how to convert it to DTO in the controller layer.
 */

use App\Domain\Entities\Chats;
use App\Domain\Entities\Chat;
use App\Domain\Entities\ChatUser;
use App\Infrastructure\Repositories\ChatRepository;
use App\Application\UseCases\Chat\GetChatsUseCase;
use App\Domain\Exceptions\ChatNotFoundException;
use App\Domain\Exceptions\InvalidPaginationException;
use Exception;

// Example 1: Repository returns domain entities
function exemploRepositoryDomainEntity(ChatRepository $repository, ChatUser $user): void
{
    // Repository now returns domain entity
    $chats = $repository->getUserChats($user, 1, 20);
    
    // TypeScript-like intellisense works here
    $chatsList = $chats->chats; // Chat[]
    $total = $chats->getTotal(); // int
    $currentPage = $chats->getCurrentPage(); // int
    
    // Access typed domain data
    foreach ($chatsList as $chat) {
        echo "Chat: {$chat->name}\n";
        echo "Tipo: {$chat->type}\n";
        echo "Descrição: {$chat->description}\n";
        echo "Criado por: {$chat->createdBy}\n";
        
        // Domain methods
        if ($chat->isPrivate()) {
            echo "✅ Chat privado\n";
        }
        
        if ($chat->isGroup()) {
            echo "✅ Chat em grupo\n";
        }
    }
    
    // Pagination information
    echo "Página atual: {$chats->getCurrentPage()}\n";
    echo "Total de itens: {$chats->getTotal()}\n";
    echo "Última página: {$chats->getLastPage()}\n";
    echo "Tem mais páginas: " . ($chats->hasMorePages() ? 'Sim' : 'Não') . "\n";
}

// Example 2: UseCase layer
function exemploUseCaseLayer(GetChatsUseCase $useCase, ChatUser $user): void
{
    // UseCase also returns domain entity
    $chats = $useCase->execute($user, 1, 20);
    
    // Domain entity methods
    if ($chats->isEmpty()) {
        echo "Nenhum chat encontrado\n";
        return;
    }
    
    echo "Encontrados {$chats->getCount()} chats na página atual\n";
    echo "Total de chats: {$chats->getTotal()}\n";
    
    // Business logic with domain entities
    foreach ($chats->chats as $chat) {
        if ($chat->isPrivate()) {
            echo "Chat privado: {$chat->name}\n";
        } else {
            echo "Chat em grupo: {$chat->name}\n";
        }
    }
}

// Example 3: Controller layer - conversion to DTO
function exemploControllerLayer(GetChatsUseCase $useCase, ChatUser $user): array
{
    // Get domain entity from use case
    $chats = $useCase->execute($user, 1, 20);
    
    // Convert to DTO for API response
    $dto = $chats->toDto();
    
    // Return array for JSON response
    return $dto->toArray();
    
    // Result structure:
    // [
    //     'chats' => [
    //         [
    //             'id' => 1,
    //             'name' => 'Chat Privado',
    //             'type' => 'private',
    //             'description' => 'Descrição do chat',
    //             'last_message' => null, // Would be populated by enriched data
    //             'unread_count' => 0, // Would be calculated by enriched data
    //             'participants_count' => 0, // Would be calculated by enriched data
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

// Example 4: Domain entity benefits
function exemploBeneficiosEntidadeDominio(Chats $chats): void
{
    // ✅ Strong typing
    $chatsList = $chats->chats; // Chat[]
    $total = $chats->getTotal(); // int
    
    // ✅ Domain methods
    if ($chats->hasMorePages()) {
        echo "Há mais páginas disponíveis\n";
    }
    
    if ($chats->hasPreviousPages()) {
        echo "Há páginas anteriores\n";
    }
    
    // ✅ Business logic encapsulation
    foreach ($chats->chats as $chat) {
        if ($chat->isPrivate()) {
            echo "Chat privado: {$chat->name}\n";
        } elseif ($chat->isGroup()) {
            echo "Chat em grupo: {$chat->name}\n";
        }
    }
    
    // ✅ Validation and constraints
    if ($chats->isEmpty()) {
        echo "Nenhum chat encontrado\n";
    }
}

// Example 5: Error handling with domain exceptions
function exemploTratamentoErros(ChatRepository $repository, ChatUser $user): void
{
    try {
        $chatList = $repository->getUserChats($user, 1, 20);
        
        // Success - work with domain entity
        echo "Encontrados {$chatList->getCount()} chats\n";
        
    } catch (ChatNotFoundException $e) {
        echo "Erro: {$e->getMessage()}\n";
        // Handle not found
    } catch (InvalidPaginationException $e) {
        echo "Erro de paginação: {$e->getMessage()}\n";
        // Handle pagination error
    } catch (Exception $e) {
        echo "Erro inesperado: {$e->getMessage()}\n";
        // Handle other errors
    }
}

// Example 6: Testing with domain entities
function exemploTestes(ChatRepository $repository, ChatUser $user): void
{
    $chats = $repository->getUserChats($user, 1, 20);
    
    // ✅ Specific and safe tests
    assert($chats instanceof Chats);
    assert(is_array($chats->chats));
    assert($chats->getTotal() >= 0);
    assert($chats->getCurrentPage() > 0);
    assert($chats->getPerPage() > 0);
    assert($chats->getLastPage() >= 1);
    
    // ✅ Test domain logic
    foreach ($chats->chats as $chat) {
        assert($chat instanceof Chat);
        assert(is_int($chat->id));
        assert(is_string($chat->name));
        assert(in_array($chat->type, ['private', 'group']));
        assert(is_string($chat->description));
        
        // Test domain methods
        if ($chat->type === 'private') {
            assert($chat->isPrivate());
            assert(!$chat->isGroup());
        } else {
            assert($chat->isGroup());
            assert(!$chat->isPrivate());
        }
    }
    
    // ✅ Test pagination logic
    assert($chats->hasMorePages() === ($chats->getCurrentPage() < $chats->getLastPage()));
    assert($chats->hasPreviousPages() === ($chats->getCurrentPage() > 1));
    assert($chats->isEmpty() === empty($chats->chats));
}

// Example 7: Comparison with previous approach
function comparacaoComAbordagemAnterior(): void
{
    echo "=== ANTES (Repository retornando DTO) ===\n";
    echo "❌ Repository violava separação de responsabilidades\n";
    echo "❌ DTOs misturados com lógica de domínio\n";
    echo "❌ Difícil de testar isoladamente\n";
    echo "❌ Acoplamento entre camadas\n";
    
    echo "\n=== DEPOIS (Repository retornando entidades de domínio) ===\n";
    echo "✅ Repository retorna apenas entidades de domínio\n";
    echo "✅ DTOs são criados apenas na camada de apresentação\n";
    echo "✅ Fácil de testar isoladamente\n";
    echo "✅ Separação clara de responsabilidades\n";
    echo "✅ Lógica de domínio encapsulada\n";
    echo "✅ Conversão para DTO apenas quando necessário\n";
}
