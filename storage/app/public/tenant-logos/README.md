# Multitenant Branding Kit

Kit completo de branding para sistema multitenant com logos 64x64 e paletas de cores distintas.

## 📁 Estrutura de Arquivos

```
multitenant-branding/
├── logo-tenant-a.svg          # Logo Tenant A (Azul/Teal)
├── logo-tenant-b.svg          # Logo Tenant B (Coral/Terracota)
├── logo-tenant-c.svg          # Logo Tenant C (Roxo/Magenta)
├── tenant-branding.json       # Configuração centralizada
└── README.md                   # Este arquivo
```

## 🎨 Paletas de Cores

### Tenant A - Paleta Fria
- **Primária**: Azul Profundo (#185FA5)
- **Secundária**: Teal Escuro (#0F6E56)
- **Terciária**: Azul Claro (#85B7EB)

### Tenant B - Paleta Quente
- **Primária**: Coral Vibrante (#D85A30)
- **Secundária**: Marrom Escuro (#993C1D)
- **Terciária**: Pêssego (#F5B895)

### Tenant C - Paleta Vibrante
- **Primária**: Roxo Vibrante (#7F77DD)
- **Secundária**: Magenta (#BA46D9)
- **Terciária**: Roxo Claro (#E8E4F5)

## 🚀 Como Usar

### 1. HTML + CSS Simples

```html
<!-- HTML -->
<img src="/branding/logo-tenant-a.svg" alt="Tenant A" class="tenant-logo" />

<!-- CSS -->
<style>
  :root[data-tenant="tenant-a"] {
    --primary-color: #185FA5;
    --secondary-color: #0F6E56;
    --tertiary-color: #85B7EB;
  }
  
  .tenant-logo {
    width: 64px;
    height: 64px;
  }
</style>
```

### 2. JavaScript/Node.js

```javascript
import tenantConfig from './tenant-branding.json';

const tenant = tenantConfig.tenants.find(t => t.id === 'tenant-a');

// Aplicar cores dinamicamente
document.documentElement.style.setProperty(
  '--primary-color',
  tenant.colors.primary
);

// Carregar logo
const logo = new Image();
logo.src = tenant.logo;
document.body.appendChild(logo);
```

### 3. React

```jsx
import tenantConfig from './tenant-branding.json';

function TenantBranding({ tenantId }) {
  const tenant = tenantConfig.tenants.find(t => t.id === tenantId);
  
  return (
    <div style={{
      '--tenant-primary': tenant.colors.primary,
      '--tenant-secondary': tenant.colors.secondary,
      '--tenant-tertiary': tenant.colors.tertiary,
    }}>
      <img 
        src={tenant.logo} 
        alt={tenant.name}
        width="64"
        height="64"
      />
    </div>
  );
}
```

### 4. Backend/Banco de Dados

```javascript
// Armazenar no banco de dados
const tenantBranding = {
  tenantId: 'tenant-a',
  logo: 'logo-tenant-a.svg',
  primaryColor: '#185FA5',
  secondaryColor: '#0F6E56',
  tertiaryColor: '#85B7EB',
  theme: 'corporate'
};

db.collection('tenants').updateOne(
  { _id: 'tenant-a' },
  { $set: { branding: tenantBranding } }
);
```

## 📝 Especificações dos Logos

- **Dimensões**: 64x64 pixels
- **Formato**: SVG (escalável, sem perda de qualidade)
- **Cores**: Integradas com a paleta do tenant
- **Estilo**: Minimalista, moderno
- **Compatibilidade**: Todos os navegadores modernos

## 🎯 Casos de Uso

✅ Dashboard com branding por tenant
✅ Login com logo customizado
✅ E-mails com cores da marca
✅ Relatórios e documentos customizados
✅ Notificações com identidade visual
✅ Interfaces responsivas

## 💾 Integração com Banco de Dados

```sql
-- Exemplo SQL
ALTER TABLE tenants ADD COLUMN branding_config JSONB;

UPDATE tenants 
SET branding_config = '{
  "logo": "logo-tenant-a.svg",
  "primary": "#185FA5",
  "secondary": "#0F6E56",
  "tertiary": "#85B7EB"
}'
WHERE id = 'tenant-a';
```

## 🔧 Customizações Futuras

Os logos são SVG totalmente customizáveis. Para modificar:

1. Abra o arquivo SVG em um editor de texto
2. Altere os valores hex das cores na seção `<defs>` e `fill`
3. Ajuste dimensões ou formas conforme necessário
4. Salve e teste

## 📦 Suporte

Os arquivos estão prontos para uso imediato em qualquer aplicação web moderna. Para aplicações mobile, considere converter para PNG em diferentes resoluções.

## 📄 Licença

Este kit é fornecido como base para seu sistema multitenant. Sinta-se livre para adaptar cores e estilos conforme necessário.

---

**Versão**: 1.0.0  
**Data de Criação**: 2026  
**Formato**: SVG + JSON
