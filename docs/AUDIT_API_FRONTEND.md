# API de Auditoria - Documentação para Frontend

## 📋 Visão Geral

Sistema de auditoria que registra **automaticamente** todas as ações realizadas no sistema (criar, atualizar, deletar). Os logs são **imutáveis** por segurança e compliance.

**Base URL:** `http://localhost:8000/api/admin/audit`

**Autenticação:** Todas as rotas requerem:
- Header: `Authorization: Bearer {token}`
- Permissão: `audit-read`

---

## 🔐 Autenticação

Todas as rotas requerem:
1. Token JWT válido no header `Authorization`
2. Usuário deve ser Admin
3. Admin deve ter permissão `audit-read`

**Exemplo de Header:**
```
Authorization: Bearer 1|abc123def456...
Content-Type: application/json
```

---

## 📡 Endpoints Disponíveis

### **1. Listar Logs com Filtros**
`GET /api/admin/audit`

Lista todos os logs de auditoria com filtros opcionais e paginação.

**Query Parameters:**

| Parâmetro | Tipo | Obrigatório | Descrição | Exemplo |
|-----------|------|-------------|-----------|---------|
| `user_id` | number | Não | Filtrar por ID do usuário | `?user_id=5` |
| `user_type` | string | Não | Filtrar por tipo (Admin, User) | `?user_type=Admin` |
| `model_type` | string | Não | Filtrar por modelo | `?model_type=App\Models\User` |
| `model_id` | number | Não | Filtrar por ID do modelo | `?model_id=123` |
| `action` | string | Não | Filtrar por ação (created, updated, deleted) | `?action=deleted` |
| `tags` | string | Não | Filtrar por tags (separado por vírgula) | `?tags=security,critical` |
| `date_from` | string | Não | Data inicial (YYYY-MM-DD) | `?date_from=2025-01-01` |
| `date_to` | string | Não | Data final (YYYY-MM-DD) | `?date_to=2025-01-31` |
| `per_page` | number | Não | Itens por página (1-100, padrão: 50) | `?per_page=20` |
| `page` | number | Não | Número da página (padrão: 1) | `?page=2` |

**Exemplo de Requisição:**
```javascript
GET /api/admin/audit?action=deleted&model_type=App\Models\User&date_from=2025-01-01&per_page=20
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user": {
        "id": 5,
        "type": "Admin",
        "name": "João Silva"
      },
      "action": "deleted",
      "model": {
        "type": "App\\Models\\User",
        "id": 123
      },
      "changes": {
        "old": {
          "name": "Maria Santos",
          "email": "maria@email.com",
          "email_verified_at": "2025-01-10 10:00:00"
        },
        "new": null
      },
      "description": "User 'Maria Santos' foi deletado",
      "context": {
        "ip": "192.168.1.100",
        "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "url": "http://localhost:8000/api/admin/users/123",
        "method": "DELETE"
      },
      "tags": ["security", "critical"],
      "metadata": null,
      "created_at": "2025-01-15 14:30:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8,
    "from": 1,
    "to": 20
  }
}
```

**Resposta de Erro (403):**
```json
{
  "error": "Admin 5 does not have permission to perform this action. Required permission: audit-read"
}
```

**Resposta de Erro (401):**
```json
{
  "message": "Unauthenticated."
}
```

---

### **2. Ver Log Específico**
`GET /api/admin/audit/{id}`

Retorna um log de auditoria específico por ID.

**Parâmetros de URL:**
- `id` (number, obrigatório) - ID do log de auditoria

**Exemplo de Requisição:**
```javascript
GET /api/admin/audit/123
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "user": {
      "id": 5,
      "type": "Admin",
      "name": "João Silva"
    },
    "action": "updated",
    "model": {
      "type": "App\\Models\\Admin",
      "id": 10
    },
    "changes": {
      "old": {
        "name": "Admin Antigo",
        "is_active": true
      },
      "new": {
        "name": "Admin Novo",
        "is_active": false
      }
    },
    "description": "Admin 'Admin Novo' foi atualizado",
    "context": {
      "ip": "192.168.1.100",
      "user_agent": "Mozilla/5.0...",
      "url": "http://localhost:8000/api/admin/admins",
      "method": "PUT"
    },
    "tags": ["security"],
    "metadata": null,
    "created_at": "2025-01-15 14:30:00"
  }
}
```

**Resposta de Erro (404):**
```json
{
  "success": false,
  "message": "Audit log not found"
}
```

---

### **3. Histórico de um Modelo**
`GET /api/admin/audit/model/{type}/{id}`

Retorna todo o histórico de mudanças de um modelo específico.

**Parâmetros de URL:**
- `type` (string, obrigatório) - Tipo do modelo (ex: `App\Models\User`)
- `id` (number, obrigatório) - ID do modelo

**Exemplo de Requisição:**
```javascript
GET /api/admin/audit/model/App\Models\User/123
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "action": "created",
      "user": { "id": 1, "type": "Admin", "name": "Super Admin" },
      "model": { "type": "App\\Models\\User", "id": 123 },
      "changes": {
        "old": null,
        "new": {
          "name": "Maria Santos",
          "email": "maria@email.com"
        }
      },
      "description": "User 'Maria Santos' foi criado",
      "created_at": "2025-01-10 10:00:00"
    },
    {
      "id": 50,
      "action": "updated",
      "user": { "id": 5, "type": "Admin", "name": "João Silva" },
      "model": { "type": "App\\Models\\User", "id": 123 },
      "changes": {
        "old": { "name": "Maria Santos" },
        "new": { "name": "Maria Silva" }
      },
      "description": "User 'Maria Silva' foi atualizado",
      "created_at": "2025-01-12 15:20:00"
    },
    {
      "id": 100,
      "action": "deleted",
      "user": { "id": 5, "type": "Admin", "name": "João Silva" },
      "model": { "type": "App\\Models\\User", "id": 123 },
      "changes": {
        "old": {
          "name": "Maria Silva",
          "email": "maria@email.com"
        },
        "new": null
      },
      "description": "User 'Maria Silva' foi deletado",
      "created_at": "2025-01-15 14:30:00"
    }
  ]
}
```

---

### **4. Atividade de um Usuário**
`GET /api/admin/audit/user/{type}/{id}`

Retorna todas as ações realizadas por um usuário específico.

**Parâmetros de URL:**
- `type` (string, obrigatório) - Tipo do usuário (`Admin` ou `User`)
- `id` (number, obrigatório) - ID do usuário

**Query Parameters:**
- `per_page` (number, opcional) - Itens por página (padrão: 50)
- `page` (number, opcional) - Número da página (padrão: 1)

**Exemplo de Requisição:**
```javascript
GET /api/admin/audit/user/Admin/5?per_page=20
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 100,
      "action": "deleted",
      "user": { "id": 5, "type": "Admin", "name": "João Silva" },
      "model": { "type": "App\\Models\\User", "id": 123 },
      "description": "User 'Maria' foi deletado",
      "created_at": "2025-01-15 14:30:00"
    },
    {
      "id": 99,
      "action": "created",
      "user": { "id": 5, "type": "Admin", "name": "João Silva" },
      "model": { "type": "App\\Models\\Admin", "id": 10 },
      "description": "Admin 'Novo Admin' foi criado",
      "created_at": "2025-01-15 13:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3,
    "from": 1,
    "to": 20
  }
}
```

---

### **5. Logs por Ação**
`GET /api/admin/audit/action/{action}`

Retorna todos os logs de uma ação específica.

**Parâmetros de URL:**
- `action` (string, obrigatório) - Ação (created, updated, deleted, login, etc.)

**Query Parameters:**
- `per_page` (number, opcional) - Itens por página (padrão: 50)
- `page` (number, opcional) - Número da página (padrão: 1)

**Exemplo de Requisição:**
```javascript
GET /api/admin/audit/action/deleted?per_page=50
```

**Resposta:** Mesma estrutura do endpoint de listagem.

---

### **6. Logs por Tag**
`GET /api/admin/audit/tag/{tag}`

Retorna todos os logs com uma tag específica.

**Parâmetros de URL:**
- `tag` (string, obrigatório) - Tag (security, critical, etc.)

**Query Parameters:**
- `per_page` (number, opcional) - Itens por página (padrão: 50)
- `page` (number, opcional) - Número da página (padrão: 1)

**Exemplo de Requisição:**
```javascript
GET /api/admin/audit/tag/security?per_page=30
```

**Resposta:** Mesma estrutura do endpoint de listagem.

---

## 📊 Estrutura de Dados

### **AuditLog Object**

```typescript
interface AuditLog {
  id: number;
  user: {
    id: number;
    type: 'Admin' | 'User';
    name: string;
  };
  action: 'created' | 'updated' | 'deleted' | 'viewed' | 'login' | string;
  model: {
    type: string; // Ex: "App\\Models\\User"
    id: number | null;
  };
  changes: {
    old: Record<string, any> | null; // Valores anteriores
    new: Record<string, any> | null;  // Valores novos
  };
  description: string | null; // Descrição legível
  context: {
    ip: string | null;
    user_agent: string | null;
    url: string | null;
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' | null;
  };
  tags: string[] | null; // ['security', 'critical']
  metadata: Record<string, any> | null; // Dados extras
  created_at: string; // "2025-01-15 14:30:00"
}
```

### **Pagination Object**

```typescript
interface Pagination {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}
```

---

## 🎯 Casos de Uso Comuns

### **1. Listar Todas as Deleções**

```javascript
// Buscar todas as ações de delete
const response = await fetch('/api/admin/audit?action=deleted&per_page=50', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
// data.data = array de logs
// data.pagination = informações de paginação
```

### **2. Histórico de um Usuário Específico**

```javascript
// Ver todo histórico de mudanças de um usuário
const userId = 123;
const response = await fetch(
  `/api/admin/audit/model/App\\Models\\User/${userId}`,
  {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  }
);

const logs = await response.json();
// logs.data = array com histórico completo (criado, atualizado, deletado)
```

### **3. Atividade de um Admin**

```javascript
// Ver tudo que um admin fez
const adminId = 5;
const response = await fetch(
  `/api/admin/audit/user/Admin/${adminId}?per_page=20`,
  {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  }
);

const data = await response.json();
// data.data = ações do admin
// data.pagination = paginação
```

### **4. Logs de Segurança (Críticos)**

```javascript
// Buscar apenas logs críticos de segurança
const response = await fetch('/api/admin/audit/tag/critical?per_page=30', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const data = await response.json();
// data.data = logs críticos
```

### **5. Filtros Combinados**

```javascript
// Buscar deleções de usuários em janeiro de 2025
const params = new URLSearchParams({
  action: 'deleted',
  model_type: 'App\\Models\\User',
  date_from: '2025-01-01',
  date_to: '2025-01-31',
  per_page: '50'
});

const response = await fetch(`/api/admin/audit?${params}`, {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
```

### **6. Paginação**

```javascript
// Navegar entre páginas
const page = 2;
const perPage = 20;

const response = await fetch(
  `/api/admin/audit?page=${page}&per_page=${perPage}`,
  {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  }
);

const data = await response.json();
// data.pagination.current_page = 2
// data.pagination.last_page = 8
// data.pagination.total = 150
```

---

## 🔍 Filtros Disponíveis

### **Por Ação:**
- `created` - Registros criados
- `updated` - Registros atualizados
- `deleted` - Registros deletados
- `viewed` - Registros visualizados
- `login` - Logins realizados

### **Por Modelo:**
- `App\Models\User` - Usuários
- `App\Models\Admin` - Administradores
- `App\Models\Role` - Roles
- `App\Models\Permission` - Permissões

### **Por Tags:**
- `security` - Ações relacionadas a segurança
- `critical` - Ações críticas (deletar, etc.)

### **Por Data:**
- `date_from` - Data inicial (YYYY-MM-DD)
- `date_to` - Data final (YYYY-MM-DD)

---

## ⚠️ Tratamento de Erros

### **Erro 401 - Não Autenticado**

```json
{
  "message": "Unauthenticated."
}
```

**Solução:** Verificar se o token está válido e presente no header.

### **Erro 403 - Sem Permissão**

```json
{
  "error": "Admin 5 does not have permission to perform this action. Required permission: audit-read"
}
```

**Solução:** Admin precisa ter permissão `audit-read` atribuída.

### **Erro 404 - Log Não Encontrado**

```json
{
  "success": false,
  "message": "Audit log not found"
}
```

**Solução:** Verificar se o ID do log existe.

### **Erro 500 - Erro Interno**

```json
{
  "success": false,
  "message": "Internal server error message"
}
```

**Solução:** Erro no servidor, verificar logs.

---

## 💡 Exemplos Práticos

### **React/Vue Component - Lista de Logs**

```javascript
// React Example
import { useState, useEffect } from 'react';
import axios from 'axios';

function AuditLogsList() {
  const [logs, setLogs] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    action: '',
    model_type: '',
    date_from: '',
    date_to: '',
    page: 1,
    per_page: 20
  });

  useEffect(() => {
    fetchLogs();
  }, [filters]);

  const fetchLogs = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams();
      
      Object.entries(filters).forEach(([key, value]) => {
        if (value) params.append(key, value);
      });

      const response = await axios.get(
        `/api/admin/audit?${params}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );

      setLogs(response.data.data);
      setPagination(response.data.pagination);
    } catch (error) {
      console.error('Error fetching logs:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (key, value) => {
    setFilters(prev => ({ ...prev, [key]: value, page: 1 }));
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      {/* Filtros */}
      <div className="filters">
        <select 
          value={filters.action} 
          onChange={(e) => handleFilterChange('action', e.target.value)}
        >
          <option value="">Todas ações</option>
          <option value="created">Criado</option>
          <option value="updated">Atualizado</option>
          <option value="deleted">Deletado</option>
        </select>

        <input
          type="date"
          value={filters.date_from}
          onChange={(e) => handleFilterChange('date_from', e.target.value)}
          placeholder="Data inicial"
        />

        <input
          type="date"
          value={filters.date_to}
          onChange={(e) => handleFilterChange('date_to', e.target.value)}
          placeholder="Data final"
        />
      </div>

      {/* Lista de Logs */}
      <table>
        <thead>
          <tr>
            <th>Data</th>
            <th>Usuário</th>
            <th>Ação</th>
            <th>Modelo</th>
            <th>Descrição</th>
          </tr>
        </thead>
        <tbody>
          {logs.map(log => (
            <tr key={log.id}>
              <td>{new Date(log.created_at).toLocaleString()}</td>
              <td>{log.user.name} ({log.user.type})</td>
              <td>{log.action}</td>
              <td>{log.model.type.split('\\').pop()}</td>
              <td>{log.description}</td>
            </tr>
          ))}
        </tbody>
      </table>

      {/* Paginação */}
      {pagination && (
        <div className="pagination">
          <button 
            disabled={pagination.current_page === 1}
            onClick={() => handleFilterChange('page', pagination.current_page - 1)}
          >
            Anterior
          </button>
          
          <span>
            Página {pagination.current_page} de {pagination.last_page}
            ({pagination.total} total)
          </span>
          
          <button
            disabled={pagination.current_page === pagination.last_page}
            onClick={() => handleFilterChange('page', pagination.current_page + 1)}
          >
            Próxima
          </button>
        </div>
      )}
    </div>
  );
}
```

### **Component - Histórico de Modelo**

```javascript
// Ver histórico completo de um registro
function ModelHistory({ modelType, modelId }) {
  const [history, setHistory] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchHistory();
  }, [modelType, modelId]);

  const fetchHistory = async () => {
    try {
      setLoading(true);
      const response = await axios.get(
        `/api/admin/audit/model/${encodeURIComponent(modelType)}/${modelId}`,
        {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`
          }
        }
      );
      setHistory(response.data.data);
    } catch (error) {
      console.error('Error fetching history:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div>Loading...</div>;

  return (
    <div className="history-timeline">
      {history.map(log => (
        <div key={log.id} className="history-item">
          <div className="history-date">
            {new Date(log.created_at).toLocaleString()}
          </div>
          <div className="history-action">
            <strong>{log.action}</strong> por {log.user.name}
          </div>
          <div className="history-description">
            {log.description}
          </div>
          {log.changes.old && (
            <div className="history-changes">
              <strong>Antes:</strong>
              <pre>{JSON.stringify(log.changes.old, null, 2)}</pre>
            </div>
          )}
          {log.changes.new && (
            <div className="history-changes">
              <strong>Depois:</strong>
              <pre>{JSON.stringify(log.changes.new, null, 2)}</pre>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}
```

---

## 🎨 Sugestões de UI/UX

### **1. Timeline de Histórico**
Mostrar histórico como timeline vertical com:
- Data/hora
- Ícone da ação (criar/atualizar/deletar)
- Usuário que fez
- Mudanças (diff visual)

### **2. Filtros Avançados**
Sidebar com filtros:
- Por ação (checkbox)
- Por modelo (select)
- Por usuário (select)
- Por data (date picker)
- Por tags (tags input)

### **3. Badges de Tags**
Mostrar tags como badges coloridos:
- `security` → Badge vermelho
- `critical` → Badge laranja

### **4. Comparação de Mudanças**
Mostrar diff visual entre `old` e `new`:
- Valores removidos em vermelho
- Valores adicionados em verde
- Valores alterados destacados

### **5. Exportação**
Botão para exportar logs filtrados:
- CSV
- PDF
- Excel

---

## 📝 Notas Importantes

### **⚠️ Campos Sensíveis**
Campos sensíveis são automaticamente sanitizados:
- `password` → `***REDACTED***`
- `token` → `***REDACTED***`
- `secret` → `***REDACTED***`
- `api_key` → `***REDACTED***`

### **🔒 Imutabilidade**
- ❌ **NÃO existe** endpoint de DELETE
- ❌ **NÃO existe** endpoint de UPDATE
- ✅ Apenas leitura (GET)

### **📊 Performance**
- Use paginação (máximo 100 itens por página)
- Use filtros para reduzir resultados
- Cache pode ser implementado no frontend

### **🕐 Formato de Data**
Todas as datas vêm no formato: `"2025-01-15 14:30:00"`

Para converter em JavaScript:
```javascript
const date = new Date(log.created_at);
const formatted = date.toLocaleString('pt-BR');
```

---

## 🚀 Quick Start

### **1. Listar Logs Básico**

```javascript
const token = 'seu-token-aqui';

const response = await fetch('/api/admin/audit?per_page=20', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

const { data, pagination } = await response.json();
console.log('Logs:', data);
console.log('Paginação:', pagination);
```

### **2. Ver Histórico de Usuário**

```javascript
const userId = 123;
const response = await fetch(
  `/api/admin/audit/model/App\\Models\\User/${userId}`,
  {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);

const { data: history } = await response.json();
console.log('Histórico completo:', history);
```

### **3. Filtrar Deleções**

```javascript
const response = await fetch(
  '/api/admin/audit?action=deleted&tags=critical',
  {
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);

const { data: deletions } = await response.json();
console.log('Deleções críticas:', deletions);
```

---

## 📚 Recursos Adicionais

- **Documentação Completa:** `docs/AUDIT_IMPLEMENTATION.md`
- **Permissões:** `docs/AUDIT_PERMISSIONS.md`
- **Logging Automático:** `docs/AUDIT_AUTOMATIC_LOGGING.md`

---

**Dúvidas?** Consulte a documentação completa ou entre em contato com o time de backend! 🚀

