# Exemplos de Implementação

## 1. Aplicação Web Vanilla JS

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Multitenant Branding</title>
  <style>
    :root {
      --primary: #333;
      --secondary: #666;
      --tertiary: #ccc;
    }
    
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    
    .header {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 30px;
      border-bottom: 2px solid var(--primary);
      padding-bottom: 15px;
    }
    
    .logo {
      width: 64px;
      height: 64px;
    }
    
    .tenant-name {
      color: var(--primary);
      font-size: 24px;
      font-weight: bold;
    }
    
    .color-palette {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      margin-top: 30px;
    }
    
    .color-card {
      border: 1px solid #eee;
      border-radius: 8px;
      padding: 15px;
      text-align: center;
    }
    
    .color-swatch {
      width: 100%;
      height: 100px;
      border-radius: 4px;
      margin-bottom: 10px;
    }
    
    .color-name {
      font-weight: bold;
      color: var(--primary);
    }
    
    .color-hex {
      font-family: monospace;
      color: var(--secondary);
      font-size: 12px;
    }
  </style>
</head>
<body>
  <div id="app"></div>
  
  <script>
    // Configuração de tenants
    const tenants = {
      'tenant-a': {
        name: 'Tenant A',
        logo: 'logo-tenant-a.svg',
        colors: {
          primary: '#185FA5',
          secondary: '#0F6E56',
          tertiary: '#85B7EB'
        }
      },
      'tenant-b': {
        name: 'Tenant B',
        logo: 'logo-tenant-b.svg',
        colors: {
          primary: '#D85A30',
          secondary: '#993C1D',
          tertiary: '#F5B895'
        }
      },
      'tenant-c': {
        name: 'Tenant C',
        logo: 'logo-tenant-c.svg',
        colors: {
          primary: '#7F77DD',
          secondary: '#BA46D9',
          tertiary: '#E8E4F5'
        }
      }
    };
    
    // Função para renderizar tenant
    function renderTenant(tenantId) {
      const tenant = tenants[tenantId];
      const app = document.getElementById('app');
      
      // Aplicar cores ao CSS
      document.documentElement.style.setProperty('--primary', tenant.colors.primary);
      document.documentElement.style.setProperty('--secondary', tenant.colors.secondary);
      document.documentElement.style.setProperty('--tertiary', tenant.colors.tertiary);
      
      // HTML
      app.innerHTML = `
        <div class="header">
          <img src="${tenant.logo}" alt="${tenant.name}" class="logo">
          <h1 class="tenant-name">${tenant.name}</h1>
        </div>
        
        <div class="color-palette">
          <div class="color-card">
            <div class="color-swatch" style="background-color: ${tenant.colors.primary}"></div>
            <div class="color-name">Primária</div>
            <div class="color-hex">${tenant.colors.primary}</div>
          </div>
          
          <div class="color-card">
            <div class="color-swatch" style="background-color: ${tenant.colors.secondary}"></div>
            <div class="color-name">Secundária</div>
            <div class="color-hex">${tenant.colors.secondary}</div>
          </div>
          
          <div class="color-card">
            <div class="color-swatch" style="background-color: ${tenant.colors.tertiary}"></div>
            <div class="color-name">Terciária</div>
            <div class="color-hex">${tenant.colors.tertiary}</div>
          </div>
        </div>
      `;
    }
    
    // Renderizar tenant padrão
    renderTenant('tenant-a');
  </script>
</body>
</html>
```

## 2. React Component

```jsx
import React, { useState } from 'react';
import tenantConfig from './tenant-branding.json';

export function TenantBrandingViewer() {
  const [currentTenantId, setCurrentTenantId] = useState('tenant-a');
  
  const tenant = tenantConfig.tenants.find(t => t.id === currentTenantId);
  
  const applyBranding = (tenantId) => {
    const selectedTenant = tenantConfig.tenants.find(t => t.id === tenantId);
    document.documentElement.style.setProperty(
      '--tenant-primary',
      selectedTenant.colors.primary
    );
    document.documentElement.style.setProperty(
      '--tenant-secondary',
      selectedTenant.colors.secondary
    );
    document.documentElement.style.setProperty(
      '--tenant-tertiary',
      selectedTenant.colors.tertiary
    );
    setCurrentTenantId(tenantId);
  };
  
  return (
    <div style={{ padding: '20px' }}>
      <h1>Multitenant Branding</h1>
      
      <div style={{ marginBottom: '30px' }}>
        {tenantConfig.tenants.map(t => (
          <button
            key={t.id}
            onClick={() => applyBranding(t.id)}
            style={{
              padding: '10px 20px',
              marginRight: '10px',
              backgroundColor: currentTenantId === t.id ? t.colors.primary : '#ccc',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: 'pointer'
            }}
          >
            {t.name}
          </button>
        ))}
      </div>
      
      {tenant && (
        <div style={{
          border: '1px solid #ddd',
          borderRadius: '8px',
          padding: '20px'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '15px', marginBottom: '20px' }}>
            <img 
              src={tenant.logo} 
              alt={tenant.name}
              width="64"
              height="64"
            />
            <h2>{tenant.name}</h2>
          </div>
          
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '15px' }}>
            {Object.entries(tenant.colors).map(([key, value]) => (
              <div key={key} style={{ textAlign: 'center' }}>
                <div
                  style={{
                    width: '100%',
                    height: '100px',
                    backgroundColor: value,
                    borderRadius: '4px',
                    marginBottom: '10px'
                  }}
                />
                <p style={{ margin: 0, fontWeight: 'bold' }}>
                  {key.charAt(0).toUpperCase() + key.slice(1)}
                </p>
                <p style={{ margin: '5px 0 0', fontFamily: 'monospace', fontSize: '12px', color: '#666' }}>
                  {value}
                </p>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export default TenantBrandingViewer;
```

## 3. Express + Node.js

```javascript
import express from 'express';
import fs from 'fs';
import path from 'path';
import tenantConfig from './tenant-branding.json' assert { type: 'json' };

const app = express();
const __dirname = path.resolve();

// Middleware para aplicar branding
app.use((req, res, next) => {
  const tenantId = req.query.tenant || 'tenant-a';
  const tenant = tenantConfig.tenants.find(t => t.id === tenantId);
  
  res.locals.tenant = tenant || tenantConfig.tenants[0];
  next();
});

// Rota para servir branding JSON
app.get('/api/branding/:tenantId', (req, res) => {
  const tenant = tenantConfig.tenants.find(t => t.id === req.params.tenantId);
  
  if (!tenant) {
    return res.status(404).json({ error: 'Tenant not found' });
  }
  
  res.json(tenant);
});

// Rota para servir logos
app.get('/logos/:filename', (req, res) => {
  const logoPath = path.join(__dirname, 'branding', req.params.filename);
  res.sendFile(logoPath);
});

// Rota para página HTML com tenant selecionado
app.get('/', (req, res) => {
  const tenant = res.locals.tenant;
  
  res.send(`
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <title>${tenant.name}</title>
      <style>
        :root {
          --primary: ${tenant.colors.primary};
          --secondary: ${tenant.colors.secondary};
          --tertiary: ${tenant.colors.tertiary};
        }
        
        body {
          font-family: Arial, sans-serif;
          margin: 0;
          padding: 20px;
          background: #f5f5f5;
        }
        
        .header {
          background: var(--primary);
          color: white;
          padding: 20px;
          border-radius: 8px;
          display: flex;
          align-items: center;
          gap: 15px;
        }
        
        .logo {
          width: 64px;
          height: 64px;
          background: rgba(255,255,255,0.1);
          padding: 8px;
          border-radius: 4px;
        }
      </style>
    </head>
    <body>
      <div class="header">
        <img src="/logos/${tenant.logo}" alt="${tenant.name}" class="logo">
        <h1>${tenant.name}</h1>
      </div>
    </body>
    </html>
  `);
});

app.listen(3000, () => {
  console.log('Servidor rodando em http://localhost:3000');
});
```

## 4. CSS Puro

```css
/* Define variáveis CSS para cada tenant */
:root[data-tenant="tenant-a"] {
  --brand-primary: #185FA5;
  --brand-secondary: #0F6E56;
  --brand-tertiary: #85B7EB;
  --brand-logo: url('logo-tenant-a.svg');
}

:root[data-tenant="tenant-b"] {
  --brand-primary: #D85A30;
  --brand-secondary: #993C1D;
  --brand-tertiary: #F5B895;
  --brand-logo: url('logo-tenant-b.svg');
}

:root[data-tenant="tenant-c"] {
  --brand-primary: #7F77DD;
  --brand-secondary: #BA46D9;
  --brand-tertiary: #E8E4F5;
  --brand-logo: url('logo-tenant-c.svg');
}

/* Use as variáveis nos componentes */
.header {
  background-color: var(--brand-primary);
  color: white;
  padding: 20px;
}

.logo::before {
  content: var(--brand-logo);
}

.btn-primary {
  background-color: var(--brand-primary);
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 4px;
  cursor: pointer;
}

.btn-primary:hover {
  background-color: var(--brand-secondary);
}

.accent {
  border-left: 4px solid var(--brand-tertiary);
}
```

## 5. TypeScript

```typescript
interface TenantColors {
  primary: string;
  secondary: string;
  tertiary: string;
}

interface Tenant {
  id: string;
  name: string;
  logo: string;
  colors: TenantColors;
}

class TenantBrandingManager {
  private tenants: Map<string, Tenant>;
  
  constructor(tenantConfig: Tenant[]) {
    this.tenants = new Map(tenantConfig.map(t => [t.id, t]));
  }
  
  getTenant(tenantId: string): Tenant | undefined {
    return this.tenants.get(tenantId);
  }
  
  applyBranding(tenantId: string): void {
    const tenant = this.getTenant(tenantId);
    if (!tenant) return;
    
    const root = document.documentElement;
    root.style.setProperty('--primary', tenant.colors.primary);
    root.style.setProperty('--secondary', tenant.colors.secondary);
    root.style.setProperty('--tertiary', tenant.colors.tertiary);
  }
  
  getAllTenants(): Tenant[] {
    return Array.from(this.tenants.values());
  }
}
```

---

Use estes exemplos como ponto de partida para sua implementação! 🚀
