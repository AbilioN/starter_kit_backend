# Estratégias de Tipagem no PHP (Sem Generics)

Este documento explica como implementar tipagem forte no PHP mesmo sem o suporte nativo a generics, usando diferentes estratégias e padrões.

## 🎯 Problema

No PHP, mesmo com type hints, arrays genéricos não têm tipagem específica:

```php
// ❌ Sem tipagem específica
public function getUserChats(ChatUser $user): array
{
    // O que exatamente está neste array?
    return [
        'chats' => [...], // Que tipo de dados?
        'pagination' => [...] // Que estrutura?
    ];
}
```

## ✅ Soluções Implementadas

### 1. **DTOs (Data Transfer Objects)**

**Vantagens:**
- ✅ Tipagem forte e específica
- ✅ Autocomplete completo no IDE
- ✅ Validação de tipos em tempo de execução
- ✅ Documentação integrada
- ✅ Refatoração segura

**Implementação:**

```php
// DTOs específicos para cada estrutura
class ChatListResponseDto
{
    public function __construct(
        public readonly array $chats, // ChatListItemDto[]
        public readonly PaginationDto $pagination
    ) {}
}

class ChatListItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $type,
        public readonly ?LastMessageDto $lastMessage,
        public readonly int $unreadCount,
        // ...
    ) {}
}

// Uso tipado
public function getUserChats(ChatUser $user): ChatListResponseDto
{
    // Implementação...
    return new ChatListResponseDto($chats, $pagination);
}
```

### 2. **PHPDoc com Anotações Detalhadas**

**Vantagens:**
- ✅ Documentação rica
- ✅ Suporte a IDEs avançadas
- ✅ Especificação de tipos complexos

```php
/**
 * Obtém a lista de chats de um usuário
 * 
 * @param ChatUser $user O usuário para buscar os chats
 * @param int $page Número da página (padrão: 1)
 * @param int $perPage Itens por página (padrão: 20)
 * @return ChatListResponseDto Retorna um DTO contendo:
 *   - chats: array<ChatListItemDto> Lista de chats
 *   - pagination: PaginationDto Informações de paginação
 * 
 * @example
 * $response = $repository->getUserChats($user, 1, 20);
 * $chats = $response->chats; // ChatListItemDto[]
 * $pagination = $response->pagination; // PaginationDto
 */
public function getUserChats(ChatUser $user, int $page = 1, int $perPage = 20): ChatListResponseDto
```

### 3. **Interfaces com Contratos Específicos**

**Vantagens:**
- ✅ Contratos claros
- ✅ Polimorfismo seguro
- ✅ Testabilidade

```php
interface ChatListResponseInterface
{
    public function getChats(): array; // ChatListItemInterface[]
    public function getPagination(): PaginationInterface;
    public function toArray(): array;
}

interface ChatListItemInterface
{
    public function getId(): int;
    public function getName(): string;
    public function getType(): string;
    public function getLastMessage(): ?LastMessageInterface;
    public function getUnreadCount(): int;
}
```

### 4. **Value Objects**

**Vantagens:**
- ✅ Encapsulamento de lógica
- ✅ Validação integrada
- ✅ Imutabilidade

```php
class ChatList
{
    private array $chats;
    private Pagination $pagination;
    
    public function __construct(array $chats, Pagination $pagination)
    {
        $this->validateChats($chats);
        $this->chats = $chats;
        $this->pagination = $pagination;
    }
    
    public function getChats(): array
    {
        return $this->chats;
    }
    
    public function getPagination(): Pagination
    {
        return $this->pagination;
    }
    
    private function validateChats(array $chats): void
    {
        foreach ($chats as $chat) {
            if (!$chat instanceof ChatListItem) {
                throw new InvalidArgumentException('Invalid chat item');
            }
        }
    }
}
```

## 🔄 Estratégias Alternativas

### 5. **Arrays Tipados com Validação**

```php
class TypedArray
{
    private array $items;
    private string $type;
    
    public function __construct(array $items, string $type)
    {
        $this->validateItems($items, $type);
        $this->items = $items;
        $this->type = $type;
    }
    
    public function getItems(): array
    {
        return $this->items;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    private function validateItems(array $items, string $type): void
    {
        foreach ($items as $item) {
            if (!$item instanceof $type) {
                throw new InvalidArgumentException("Item must be instance of {$type}");
            }
        }
    }
}

// Uso
$chatList = new TypedArray($chats, ChatListItemDto::class);
```

### 6. **Collections Específicas**

```php
class ChatListCollection
{
    private array $chats = [];
    
    public function add(ChatListItemDto $chat): void
    {
        $this->chats[] = $chat;
    }
    
    public function get(int $index): ?ChatListItemDto
    {
        return $this->chats[$index] ?? null;
    }
    
    public function all(): array
    {
        return $this->chats;
    }
    
    public function count(): int
    {
        return count($this->chats);
    }
    
    public function filter(callable $callback): self
    {
        $filtered = new self();
        foreach ($this->chats as $chat) {
            if ($callback($chat)) {
                $filtered->add($chat);
            }
        }
        return $filtered;
    }
}
```

### 7. **Enums para Tipos Específicos**

```php
enum ChatType: string
{
    case PRIVATE = 'private';
    case GROUP = 'group';
}

enum MessageType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case FILE = 'file';
}

// Uso em DTOs
class ChatListItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ChatType $type, // Enum tipado
        public readonly ?LastMessageDto $lastMessage,
        public readonly int $unreadCount
    ) {}
}
```

## 🧪 Benefícios para Testes

### Antes (Array genérico):
```php
public function testGetUserChats(): void
{
    $result = $repository->getUserChats($user);
    
    // ❌ Testes frágeis
    $this->assertIsArray($result);
    $this->assertArrayHasKey('chats', $result);
    $this->assertArrayHasKey('pagination', $result);
    // O que mais testar? Estrutura não é clara
}
```

### Depois (DTO tipado):
```php
public function testGetUserChats(): void
{
    $result = $repository->getUserChats($user);
    
    // ✅ Testes específicos e seguros
    $this->assertInstanceOf(ChatListResponseDto::class, $result);
    $this->assertIsArray($result->chats);
    $this->assertInstanceOf(PaginationDto::class, $result->pagination);
    
    foreach ($result->chats as $chat) {
        $this->assertInstanceOf(ChatListItemDto::class, $chat);
        $this->assertIsInt($chat->id);
        $this->assertIsString($chat->name);
        $this->assertContains($chat->type, ['private', 'group']);
        
        if ($chat->lastMessage) {
            $this->assertInstanceOf(LastMessageDto::class, $chat->lastMessage);
        }
    }
}
```

## 📊 Comparação de Abordagens

| Abordagem | Tipagem | Autocomplete | Validação | Performance | Complexidade |
|-----------|---------|--------------|-----------|-------------|--------------|
| Array genérico | ❌ | ❌ | ❌ | ✅ | ✅ |
| DTOs | ✅ | ✅ | ✅ | ✅ | ✅ |
| Interfaces | ✅ | ✅ | ✅ | ✅ | ✅ |
| Value Objects | ✅ | ✅ | ✅ | ✅ | 🔶 |
| Collections | ✅ | ✅ | ✅ | ✅ | 🔶 |
| Enums | ✅ | ✅ | ✅ | ✅ | ✅ |

## 🎯 Recomendações

### Para APIs e Respostas:
1. **Use DTOs** - Melhor equilíbrio entre tipagem e simplicidade
2. **Combine com PHPDoc** - Documentação rica
3. **Implemente toArray()** - Para serialização JSON

### Para Domínio:
1. **Use Value Objects** - Encapsulamento e validação
2. **Use Enums** - Para tipos específicos
3. **Use Collections** - Para listas com comportamento

### Para Testes:
1. **Use instanceof** - Validação de tipos
2. **Teste propriedades específicas** - Não apenas estrutura
3. **Use mocks tipados** - Para dependências

## 🔮 Futuro: PHP 8.2+ e Generics

Quando o PHP suportar generics nativamente:

```php
// Futuro (quando disponível)
public function getUserChats(ChatUser $user): array<ChatListItemDto>
{
    // Implementação com generics nativos
}

// Ou
public function getUserChats(ChatUser $user): Collection<ChatListItemDto>
{
    // Collection tipada
}
```

## 📝 Conclusão

Mesmo sem generics nativos, o PHP oferece várias estratégias para implementar tipagem forte:

1. **DTOs são a melhor opção** para a maioria dos casos
2. **PHPDoc complementa** a tipagem com documentação
3. **Value Objects** para lógica de domínio complexa
4. **Enums** para tipos específicos e limitados

A combinação dessas estratégias resulta em código mais seguro, legível e manutenível, mesmo sem o suporte nativo a generics. 