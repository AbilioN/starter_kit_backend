# Solução: Repository Retorna Entidades de Domínio

## 🎯 Problema Original

O repository estava retornando DTOs diretamente, violando a separação de responsabilidades e misturando lógica de apresentação com lógica de domínio.

## ✅ Solução Implementada

### 1. **Entidade de Domínio: `Chats`**

Criamos uma entidade de domínio para representar uma lista de chats com paginação:

```php
class Chats
{
    public function __construct(
        public readonly array $chats, // Chat[]
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
        public readonly ?int $from = null,
        public readonly ?int $to = null
    ) {}

    // Métodos de domínio
    public function getTotal(): int
    public function getCurrentPage(): int
    public function hasMorePages(): bool
    public function hasPreviousPages(): bool
    public function isEmpty(): bool
    
    // Conversão para DTO (apenas quando necessário)
    public function toDto(): ChatListResponseDto
}
```

### 2. **Repository Atualizado**

O repository agora retorna entidades de domínio:

```php
/**
 * Gets the list of chats for a user with pagination
 * 
 * @param ChatUser $user The user to get chats for
 * @param int $page Page number (default: 1)
 * @param int $perPage Items per page (default: 20)
 * @return \App\Domain\Entities\Chats Returns a domain entity containing:
 *   - chats: array<\App\Domain\Entities\Chat> List of chat entities
 *   - pagination information embedded in the entity
 * 
 * @throws \App\Domain\Exceptions\ChatNotFoundException When no chats are found
 * @throws \App\Domain\Exceptions\InvalidPaginationException When pagination parameters are invalid
 */
public function getUserChats(ChatUser $user, int $page = 1, int $perPage = 20): \App\Domain\Entities\Chats
{
    // Implementação que retorna ChatList (entidade de domínio)
}
```

### 3. **UseCase Atualizado**

O UseCase também retorna entidades de domínio:

```php
class GetChatsUseCase
{
    public function execute(ChatUser $user, int $page = 1, int $perPage = 20): \App\Domain\Entities\Chats
    {
        return $this->chatRepository->getUserChats($user, $page, $perPage);
    }
}
```

### 4. **Controller - Conversão para DTO**

A conversão para DTO acontece apenas na camada de apresentação:

```php
public function getConversations(Request $request, GetChatsUseCase $getChatsUseCase): JsonResponse
{
    $user = $request->user();
    $chatUser = ChatUserFactory::createFromModel($user);
    
    // Get domain entity from use case
    $chats = $getChatsUseCase->execute($chatUser);
    
    // Convert to DTO for API response
    $dto = $chats->toDto();
    
    return response()->json($dto->toArray(), 200);
}
```

## 🏗️ Arquitetura Resultante

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Controller    │    │     UseCase     │    │   Repository    │
│                 │    │                 │    │                 │
│  getConversations│───▶│   execute()     │───▶│  getUserChats() │
│                 │    │                 │    │                 │
│  Chats          │    │  Chats          │    │  Chats          │
│  → toDto()      │    │  (Domain)       │    │  (Domain)       │
│  → toArray()    │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   DTO Layer     │    │  Domain Layer   │    │  Database Layer │
│                 │    │                 │    │                 │
│ ChatListResponse│    │      Chats      │    │   ChatModel     │
│      DTO        │    │      Entity     │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## ✅ Benefícios Alcançados

### 1. **Separação de Responsabilidades**
- ✅ Repository retorna apenas entidades de domínio
- ✅ DTOs são criados apenas na camada de apresentação
- ✅ Lógica de domínio encapsulada nas entidades

### 2. **Tipagem Forte**
- ✅ Repository: `ChatList` (entidade de domínio)
- ✅ UseCase: `ChatList` (entidade de domínio)
- ✅ Controller: Conversão para `ChatListResponseDto`

### 3. **Testabilidade**
- ✅ Testes isolados por camada
- ✅ Mock de entidades de domínio
- ✅ Validação de lógica de negócio

### 4. **Manutenibilidade**
- ✅ Mudanças na API não afetam o domínio
- ✅ Mudanças no domínio não afetam a API
- ✅ Refatoração segura

## 🔄 Fluxo de Dados

### Antes (Repository retornando DTO):
```
Repository → DTO → UseCase → DTO → Controller → JSON
```

### Depois (Repository retornando entidades):
```
Repository → Entity → UseCase → Entity → Controller → DTO → JSON
```

## 🧪 Exemplos de Uso

### Repository Layer:
```php
$chats = $repository->getUserChats($user, 1, 20);
$chatsList = $chats->chats; // Chat[]
$total = $chats->getTotal(); // int
```

### UseCase Layer:
```php
$chats = $getChatsUseCase->execute($user, 1, 20);
if ($chats->isEmpty()) {
    // Handle empty list
}
```

### Controller Layer:
```php
$chats = $getChatsUseCase->execute($user, 1, 20);
$dto = $chats->toDto();
return response()->json($dto->toArray(), 200);
```

## 🎯 Exceções de Domínio

Criamos exceções específicas para o domínio:

### `ChatNotFoundException`
```php
throw ChatNotFoundException::forUser($userId, $userType);
throw ChatNotFoundException::withId($chatId);
```

### `InvalidPaginationException`
```php
throw InvalidPaginationException::invalidPage($page);
throw InvalidPaginationException::invalidPerPage($perPage);
```

## 📊 Comparação Final

| Aspecto | Antes (DTO no Repository) | Depois (Entity no Repository) |
|---------|---------------------------|-------------------------------|
| **Responsabilidades** | ❌ Misturadas | ✅ Separadas |
| **Tipagem** | ❌ DTOs em todas as camadas | ✅ Entidades no domínio |
| **Testabilidade** | ❌ Difícil de testar | ✅ Fácil de testar |
| **Manutenibilidade** | ❌ Acoplado | ✅ Desacoplado |
| **Flexibilidade** | ❌ Rígido | ✅ Flexível |
| **Performance** | ✅ Direto | ✅ Otimizado |

## 🎯 Conclusão

A solução implementada segue os princípios de Clean Architecture e Domain-Driven Design:

1. **Repository** retorna apenas entidades de domínio
2. **UseCase** trabalha com entidades de domínio
3. **Controller** converte para DTO apenas quando necessário
4. **Exceções** específicas do domínio
5. **Tipagem** forte em todas as camadas

Isso resulta em código mais limpo, testável e manutenível, com separação clara de responsabilidades entre as camadas.
