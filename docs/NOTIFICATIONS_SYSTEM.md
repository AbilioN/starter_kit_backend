# Sistema de Notificações - Guia Completo

## Índice

1. [Visão Geral](#visão-geral)
2. [Por que Notificações são Essenciais](#por-que-notificações-são-essenciais)
3. [Arquitetura do Sistema](#arquitetura-do-sistema)
4. [Tipos de Notificações](#tipos-de-notificações)
5. [Canais de Notificação](#canais-de-notificação)
6. [Implementação](#implementação)
7. [Casos de Uso Reais](#casos-de-uso-reais)
8. [API Endpoints](#api-endpoints)
9. [Frontend Integration](#frontend-integration)
10. [Boas Práticas](#boas-práticas)

---

## Visão Geral

O **Sistema de Notificações** é responsável por **comunicar eventos importantes** aos usuários através de **múltiplos canais** (email, in-app, push, SMS), melhorando o **engajamento**, a **experiência do usuário** e fornecendo **alertas críticos** quando necessário.

### Objetivos

- 📧 **Comunicação Efetiva**: Informar usuários sobre eventos relevantes
- 🔔 **Tempo Real**: Notificações instantâneas via broadcasting
- 📱 **Multi-canal**: Email, in-app, push notifications, SMS
- ⚙️ **Configurável**: Usuários controlam o que recebem
- 📊 **Rastreável**: Histórico completo de notificações enviadas

---

## Por que Notificações são Essenciais

### 1. Engajamento do Usuário

**Estatísticas Comprovadas:**
- ↑ **30-40% de aumento** na retenção de usuários
- ↑ **45% de aumento** no tempo de uso da plataforma
- ↑ **25% de aumento** nas conversões
- ↑ **50% de redução** em tickets de suporte (usuários são informados proativamente)

**Como funciona:**
```
SEM Notificações:
- Usuário não sabe que algo aconteceu
- Precisa verificar manualmente
- Pode perder eventos importantes
- Baixo engajamento

COM Notificações:
- Usuário é informado instantaneamente
- Não precisa ficar verificando
- Nunca perde eventos importantes
- Alto engajamento
```

### 2. Experiência do Usuário (UX)

**Cenários Comuns:**

```
Cenário 1: Senha Alterada
❌ Sem notificação: Usuário não sabe, pode ser hackeado
✅ Com notificação: Email instantâneo + alerta in-app

Cenário 2: Pagamento Aprovado
❌ Sem notificação: Usuário fica na dúvida
✅ Com notificação: Confirmação por email + SMS

Cenário 3: Tarefa Atribuída
❌ Sem notificação: Usuário não vê, atrasa projeto
✅ Com notificação: Notificação in-app + push mobile

Cenário 4: Sistema em Manutenção
❌ Sem notificação: Usuário vê erro genérico
✅ Com notificação: Email antecipado + banner no sistema
```

### 3. Segurança

**Notificações Críticas de Segurança:**

```php
1. Login de novo dispositivo
   → Email + SMS instantâneo
   → "Foi você? Se não, clique aqui"

2. Senha alterada
   → Email obrigatório
   → Detalhes: IP, navegador, horário

3. Email alterado
   → Email para AMBOS endereços
   → Antigo: "Seu email foi alterado"
   → Novo: "Confirme seu novo email"

4. Tentativas de login falhadas
   → Após 5 tentativas: Email de alerta
   → Sugestão de trocar senha

5. Acesso de IP suspeito
   → Notificação para admins
   → Possível tentativa de invasão
```

### 4. Operacional (Para Admins)

**Alertas Automáticos:**

```php
1. Sistema com erro
   → Notificação para dev team
   → Canal: Email + SMS + Slack
   → Nível: CRÍTICO

2. Uso de recursos alto
   → "CPU está em 90%"
   → "Disco está em 85%"
   → Canal: Email para ops team

3. Pagamento falhado
   → Notificação para finance team
   → Tentar novamente automaticamente

4. Novo cadastro
   → Notificação para sales team
   → Lead qualificado para contato

5. Cancelamento de assinatura
   → Notificação para retention team
   → Tentar reverter
```

---

## Arquitetura do Sistema

### Fluxo Completo

```
┌─────────────────────────────────────────────────────┐
│                  Evento Acontece                     │
│  (User criado, Senha mudada, Pagamento, etc)        │
└────────────────────┬────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────┐
│              Laravel Event System                    │
│  UserCreated, PasswordChanged, PaymentReceived      │
└────────────────────┬────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────┐
│            Event Listener (Queue)                    │
│  SendWelcomeNotification, SendPasswordAlert         │
└────────────────────┬────────────────────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────────────┐
│         Laravel Notification Class                   │
│  WelcomeNotification, PasswordChangedNotification   │
└────────────────────┬────────────────────────────────┘
                     │
                     ├──────────┬──────────┬──────────┬──────────┐
                     ▼          ▼          ▼          ▼          ▼
              ┌──────────┐ ┌────────┐ ┌────────┐ ┌──────┐ ┌──────┐
              │ Database │ │  Mail  │ │Broadcast│ │ Push │ │ SMS  │
              │ (in-app) │ │(email) │ │(Pusher) │ │      │ │      │
              └──────────┘ └────────┘ └────────┘ └──────┘ └──────┘
                     │          │          │          │        │
                     ▼          ▼          ▼          ▼        ▼
              ┌──────────────────────────────────────────────────┐
              │         Usuário Recebe Notificação                │
              │  - 🔔 Bell icon (in-app)                         │
              │  - 📧 Email inbox                                │
              │  - 📱 Push notification (mobile)                 │
              │  - 💬 SMS no celular                             │
              └──────────────────────────────────────────────────┘
```

### Componentes Principais

#### 1. Tabela de Notificações (Laravel Native)

```sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY, -- UUID
    
    -- Para quem é a notificação
    notifiable_type VARCHAR(255) NOT NULL, -- App\Models\User ou App\Models\Admin
    notifiable_id BIGINT NOT NULL,
    
    -- Tipo da notificação
    type VARCHAR(255) NOT NULL, -- App\Notifications\WelcomeNotification
    
    -- Dados da notificação (JSON)
    data JSON NOT NULL,
    /* Exemplo:
    {
        "title": "Bem-vindo!",
        "message": "Bem-vindo ao sistema",
        "icon": "👋",
        "action_url": "/dashboard",
        "action_text": "Ir para Dashboard",
        "type": "success"
    }
    */
    
    -- Controle de leitura
    read_at TIMESTAMP NULL,
    
    -- Timestamp
    created_at TIMESTAMP,
    
    -- Índices para performance
    INDEX idx_notifiable (notifiable_type, notifiable_id),
    INDEX idx_read (read_at),
    INDEX idx_created (created_at),
    INDEX idx_type (type)
);
```

#### 2. Tabela de Preferências

```sql
CREATE TABLE notification_preferences (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Usuário
    user_id BIGINT NOT NULL,
    user_type VARCHAR(255) NOT NULL, -- Admin ou User
    
    -- Tipo de notificação
    notification_type VARCHAR(255) NOT NULL, -- welcome, password_changed, etc
    
    -- Canais habilitados
    via_database BOOLEAN DEFAULT TRUE,  -- In-app
    via_mail BOOLEAN DEFAULT TRUE,      -- Email
    via_broadcast BOOLEAN DEFAULT TRUE, -- Real-time
    via_sms BOOLEAN DEFAULT FALSE,      -- SMS
    via_push BOOLEAN DEFAULT TRUE,      -- Push mobile
    
    -- Controle
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE KEY unique_user_notification (user_id, user_type, notification_type),
    INDEX idx_user (user_id, user_type)
);
```

#### 3. Tabela de Templates (Opcional)

```sql
CREATE TABLE notification_templates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Identificação
    key VARCHAR(255) UNIQUE NOT NULL, -- welcome_email
    name VARCHAR(255) NOT NULL,
    description TEXT,
    
    -- Template
    subject VARCHAR(255), -- Para email
    body_html TEXT,
    body_text TEXT,
    
    -- Variáveis disponíveis
    available_variables JSON, -- ['{{user_name}}', '{{app_name}}']
    
    -- Canais suportados
    supports_email BOOLEAN DEFAULT TRUE,
    supports_sms BOOLEAN DEFAULT FALSE,
    supports_push BOOLEAN DEFAULT TRUE,
    
    -- Controle
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## Tipos de Notificações

### 1. Notificações de Boas-Vindas

**Quando:** Novo usuário se cadastra

**Canais:** Database + Email

```php
class WelcomeNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $userName
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bem-vindo ao ' . config('app.name'))
            ->greeting('Olá, ' . $this->userName . '!')
            ->line('Seja bem-vindo ao nosso sistema.')
            ->line('Estamos muito felizes em ter você conosco.')
            ->action('Começar Agora', url('/dashboard'))
            ->line('Explore as funcionalidades e aproveite!')
            ->salutation('Atenciosamente, Equipe ' . config('app.name'));
    }
    
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Bem-vindo!',
            'message' => 'Sua conta foi criada com sucesso',
            'icon' => '👋',
            'type' => 'success',
            'action_url' => '/dashboard',
            'action_text' => 'Ir para Dashboard',
        ];
    }
}

// Enviar:
$user->notify(new WelcomeNotification($user->name));
```

---

### 2. Notificações de Segurança

**Quando:** Ações sensíveis (senha, email, login)

**Canais:** Database + Email + SMS (crítico)

#### 2.1 Senha Alterada

```php
class PasswordChangedNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $ipAddress,
        private string $userAgent,
        private string $changedAt
    ) {}
    
    public function via($notifiable): array
    {
        // SEMPRE enviar por email em questões de segurança
        return ['database', 'mail'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔒 Sua senha foi alterada')
            ->greeting('Olá!')
            ->line('Sua senha foi alterada com sucesso.')
            ->line('')
            ->line('⚠️ **Se você não fez esta alteração, sua conta pode estar comprometida.**')
            ->line('')
            ->line('**Detalhes da alteração:**')
            ->line('📅 Data: ' . $this->changedAt)
            ->line('🌐 IP: ' . $this->ipAddress)
            ->line('💻 Navegador: ' . $this->userAgent)
            ->line('')
            ->action('Revisar Atividade da Conta', url('/security/activity'))
            ->line('Se não foi você, clique no botão acima IMEDIATAMENTE e altere sua senha.')
            ->salutation('Equipe de Segurança');
    }
    
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Senha Alterada',
            'message' => 'Sua senha foi alterada com sucesso',
            'icon' => '🔒',
            'type' => 'warning',
            'action_url' => '/security',
            'action_text' => 'Ver Atividade',
            'metadata' => [
                'ip' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'changed_at' => $this->changedAt,
            ],
        ];
    }
}
```

#### 2.2 Login de Novo Dispositivo

```php
class NewDeviceLoginNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $deviceName,
        private string $location,
        private string $ipAddress
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail', 'sms']; // SMS para crítico
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔐 Novo login detectado')
            ->greeting('Olá!')
            ->line('Detectamos um login na sua conta de um novo dispositivo.')
            ->line('')
            ->line('**Detalhes:**')
            ->line('📱 Dispositivo: ' . $this->deviceName)
            ->line('📍 Localização: ' . $this->location)
            ->line('🌐 IP: ' . $this->ipAddress)
            ->line('🕐 Horário: ' . now()->format('d/m/Y H:i:s'))
            ->line('')
            ->line('Se foi você, pode ignorar este email.')
            ->action('Não Fui Eu - Proteger Conta', url('/security/lock'))
            ->line('Se não foi você, clique no botão acima para proteger sua conta.');
    }
    
    public function toSms($notifiable): string
    {
        return "Novo login detectado na sua conta de {$this->location}. Se não foi você, acesse {$url} imediatamente.";
    }
}
```

---

### 3. Notificações de Atividade

**Quando:** Eventos importantes do sistema

#### 3.1 Tarefa Atribuída

```php
class TaskAssignedNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $taskTitle,
        private string $assignedBy,
        private string $dueDate
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail', 'broadcast'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📋 Nova tarefa atribuída a você')
            ->greeting('Olá!')
            ->line("**{$this->assignedBy}** atribuiu uma tarefa a você:")
            ->line('')
            ->line("**Tarefa:** {$this->taskTitle}")
            ->line("**Prazo:** {$this->dueDate}")
            ->line('')
            ->action('Ver Tarefa', url('/tasks'))
            ->line('Boa sorte!');
    }
    
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Nova Tarefa',
            'message' => "{$this->assignedBy} atribuiu '{$this->taskTitle}' a você",
            'icon' => '📋',
            'type' => 'info',
            'action_url' => '/tasks',
            'priority' => 'normal',
        ];
    }
    
    public function toBroadcast($notifiable): array
    {
        return [
            'title' => 'Nova Tarefa',
            'message' => "Nova tarefa: {$this->taskTitle}",
            'type' => 'info',
        ];
    }
}
```

#### 3.2 Comentário Recebido

```php
class CommentReceivedNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $authorName,
        private string $commentText,
        private string $postTitle
    ) {}
    
    public function via($notifiable): array
    {
        // Verificar preferências do usuário
        return $notifiable->getNotificationChannels('comment_received');
    }
    
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Novo Comentário',
            'message' => "{$this->authorName} comentou em '{$this->postTitle}'",
            'preview' => substr($this->commentText, 0, 100),
            'icon' => '💬',
            'type' => 'info',
            'action_url' => '/posts/' . $this->postId,
        ];
    }
}
```

---

### 4. Notificações de Negócio

#### 4.1 Pagamento Aprovado

```php
class PaymentApprovedNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private float $amount,
        private string $plan,
        private string $invoiceUrl
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail', 'sms'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ Pagamento Aprovado')
            ->greeting('Ótimas notícias!')
            ->line('Seu pagamento foi aprovado com sucesso.')
            ->line('')
            ->line("**Valor:** R$ " . number_format($this->amount, 2, ',', '.'))
            ->line("**Plano:** {$this->plan}")
            ->line("**Data:** " . now()->format('d/m/Y H:i:s'))
            ->line('')
            ->action('Ver Fatura', $this->invoiceUrl)
            ->line('Obrigado por ser nosso cliente!');
    }
    
    public function toSms($notifiable): string
    {
        return "Pagamento de R$ {$this->amount} aprovado. Plano {$this->plan} ativado!";
    }
}
```

#### 4.2 Assinatura Expirando

```php
class SubscriptionExpiringNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $expiresAt,
        private int $daysRemaining
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        $urgency = $this->daysRemaining <= 3 ? '⚠️ URGENTE' : '⏰';
        
        return (new MailMessage)
            ->subject("{$urgency} Sua assinatura expira em {$this->daysRemaining} dias")
            ->greeting('Olá!')
            ->line("Sua assinatura expira em **{$this->daysRemaining} dias**.")
            ->line('')
            ->line("**Data de expiração:** {$this->expiresAt}")
            ->line('')
            ->line('Para continuar aproveitando todos os recursos, renove sua assinatura.')
            ->action('Renovar Agora', url('/billing/renew'))
            ->line('Não perca o acesso!');
    }
    
    public function toArray($notifiable): array
    {
        $type = $this->daysRemaining <= 3 ? 'error' : 'warning';
        
        return [
            'title' => 'Assinatura Expirando',
            'message' => "Sua assinatura expira em {$this->daysRemaining} dias",
            'icon' => '⏰',
            'type' => $type,
            'action_url' => '/billing/renew',
            'action_text' => 'Renovar',
            'priority' => $this->daysRemaining <= 3 ? 'high' : 'normal',
        ];
    }
}

// Agendar envio:
// app/Console/Kernel.php
$schedule->call(function () {
    // Notificar 7, 3 e 1 dia antes
    $subscriptions = Subscription::whereIn('expires_at', [
        now()->addDays(7),
        now()->addDays(3),
        now()->addDays(1),
    ])->get();
    
    foreach ($subscriptions as $subscription) {
        $daysRemaining = now()->diffInDays($subscription->expires_at);
        
        $subscription->user->notify(
            new SubscriptionExpiringNotification(
                $subscription->expires_at->format('d/m/Y'),
                $daysRemaining
            )
        );
    }
})->daily();
```

---

### 5. Notificações de Sistema

#### 5.1 Manutenção Programada

```php
class MaintenanceScheduledNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $startTime,
        private string $endTime,
        private string $reason
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔧 Manutenção Programada')
            ->greeting('Aviso Importante')
            ->line('O sistema passará por manutenção programada.')
            ->line('')
            ->line("**Início:** {$this->startTime}")
            ->line("**Fim previsto:** {$this->endTime}")
            ->line("**Motivo:** {$this->reason}")
            ->line('')
            ->line('Durante este período, o sistema ficará indisponível.')
            ->line('Pedimos desculpas pelo inconveniente.');
    }
}

// Enviar para todos:
$users = User::where('is_active', true)->get();
Notification::send($users, new MaintenanceScheduledNotification(
    '15/01/2025 02:00',
    '15/01/2025 04:00',
    'Atualização de segurança'
));
```

#### 5.2 Sistema Restaurado

```php
class SystemRestoredNotification extends Notification
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ Sistema Restaurado')
            ->line('O sistema foi restaurado e está funcionando normalmente.')
            ->action('Acessar Sistema', url('/'))
            ->line('Obrigado pela paciência!');
    }
}
```

---

### 6. Notificações Administrativas

#### 6.1 Novo Usuário Cadastrado

```php
class NewUserRegisteredNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $userName,
        private string $userEmail,
        private int $userId
    ) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('👤 Novo Usuário Cadastrado')
            ->line("Um novo usuário se cadastrou no sistema:")
            ->line('')
            ->line("**Nome:** {$this->userName}")
            ->line("**Email:** {$this->userEmail}")
            ->line("**ID:** {$this->userId}")
            ->line("**Data:** " . now()->format('d/m/Y H:i:s'))
            ->action('Ver Usuário', url("/admin/users/{$this->userId}"));
    }
}

// Enviar para admins:
$admins = Admin::where('is_active', true)->get();
Notification::send($admins, new NewUserRegisteredNotification(
    $user->name,
    $user->email,
    $user->id
));
```

#### 6.2 Erro Crítico no Sistema

```php
class CriticalErrorNotification extends Notification
{
    use Queueable;
    
    public function __construct(
        private string $errorMessage,
        private string $errorFile,
        private int $errorLine,
        private array $context
    ) {}
    
    public function via($notifiable): array
    {
        // Admins recebem por TODOS os canais
        return ['database', 'mail', 'sms', 'slack'];
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->error() // Email vermelho
            ->subject('🚨 ERRO CRÍTICO NO SISTEMA')
            ->line('Um erro crítico foi detectado:')
            ->line('')
            ->line("**Erro:** {$this->errorMessage}")
            ->line("**Arquivo:** {$this->errorFile}:{$this->errorLine}")
            ->line("**Servidor:** " . gethostname())
            ->line("**Ambiente:** " . config('app.env'))
            ->action('Ver Logs', url('/admin/logs'))
            ->line('Ação imediata pode ser necessária!');
    }
    
    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->error()
            ->content('🚨 ERRO CRÍTICO')
            ->attachment(function ($attachment) {
                $attachment->title('Detalhes')
                    ->fields([
                        'Erro' => $this->errorMessage,
                        'Arquivo' => $this->errorFile,
                        'Linha' => $this->errorLine,
                        'Ambiente' => config('app.env'),
                    ]);
            });
    }
    
    public function toSms($notifiable): string
    {
        return "ALERTA: Erro crítico no sistema. Verifique logs imediatamente.";
    }
}
```

---

## Canais de Notificação

### 1. Database (In-App)

**Vantagens:**
- ✅ Histórico permanente
- ✅ Usuário pode revisar
- ✅ Contagem de não lidas
- ✅ Ações diretas (links)

**Implementação:**
```php
public function toArray($notifiable): array
{
    return [
        'title' => 'Título da Notificação',
        'message' => 'Mensagem descritiva',
        'icon' => '🔔', // Emoji ou classe CSS
        'type' => 'success', // success, info, warning, error
        'action_url' => '/alguma-rota',
        'action_text' => 'Ver Detalhes',
        'priority' => 'normal', // low, normal, high, urgent
        'metadata' => [], // Dados extras
    ];
}
```

**Consultar:**
```php
// Todas notificações
$notifications = $user->notifications;

// Não lidas
$unread = $user->unreadNotifications;

// Contar não lidas
$count = $user->unreadNotifications()->count();

// Marcar como lida
$notification->markAsRead();

// Marcar todas como lidas
$user->unreadNotifications->markAsRead();
```

---

### 2. Email (Mail)

**Vantagens:**
- ✅ Alcance universal (todos têm email)
- ✅ Registro permanente
- ✅ Anexos possíveis
- ✅ HTML formatado

**Implementação:**
```php
public function toMail($notifiable): MailMessage
{
    return (new MailMessage)
        ->from('noreply@app.com', 'App Name')
        ->subject('Assunto do Email')
        ->greeting('Olá, ' . $notifiable->name)
        ->line('Primeira linha do email.')
        ->line('Segunda linha.')
        ->action('Botão de Ação', url('/rota'))
        ->line('Linha final.')
        ->salutation('Atenciosamente, Equipe');
}
```

**Customização Avançada:**
```php
public function toMail($notifiable): MailMessage
{
    return (new MailMessage)
        ->view('emails.custom-notification', [
            'user' => $notifiable,
            'data' => $this->data,
        ])
        ->attach('/path/to/file.pdf')
        ->attachData($pdf, 'invoice.pdf', [
            'mime' => 'application/pdf',
        ]);
}
```

---

### 3. Broadcast (Real-time)

**Vantagens:**
- ✅ Instantâneo
- ✅ Sem refresh da página
- ✅ Experiência moderna
- ✅ Ótimo para chat

**Implementação:**
```php
public function toBroadcast($notifiable): BroadcastMessage
{
    return new BroadcastMessage([
        'title' => 'Notificação',
        'message' => 'Mensagem',
        'type' => 'success',
    ]);
}

public function broadcastType(): string
{
    return 'notification.received';
}
```

**Frontend (Vue.js):**
```javascript
// Escutar notificações
Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        // Mostrar toast
        this.$toast.show({
            title: notification.title,
            message: notification.message,
            type: notification.type
        });
        
        // Atualizar contador
        this.unreadCount++;
        
        // Adicionar à lista
        this.notifications.unshift(notification);
        
        // Tocar som (opcional)
        this.playNotificationSound();
    });
```

---

### 4. SMS

**Vantagens:**
- ✅ Alta taxa de abertura (98%)
- ✅ Não precisa de internet
- ✅ Perfeito para 2FA
- ✅ Urgente e crítico

**Implementação (Twilio):**
```php
// Instalar: composer require laravel/vonage-notification-channel
// ou: composer require laravel-notification-channels/twilio

public function toTwilio($notifiable): TwilioMessage
{
    return (new TwilioMessage)
        ->content("Seu código de verificação é: {$this->code}");
}

// Ou SMS simples:
public function toSms($notifiable): string
{
    return "Sua mensagem SMS aqui. Máximo 160 caracteres.";
}
```

**Quando usar SMS:**
```
✅ 2FA (código de verificação)
✅ Alertas de segurança críticos
✅ Confirmação de pagamento
✅ Alertas urgentes de sistema
❌ Marketing (caro)
❌ Atualizações não críticas
```

---

### 5. Push Notifications (Mobile)

**Vantagens:**
- ✅ Engajamento em mobile
- ✅ Notificações mesmo com app fechado
- ✅ Ícone de badge

**Implementação (Firebase):**
```php
// composer require laravel-notification-channels/fcm

public function toFcm($notifiable): FcmMessage
{
    return (new FcmMessage)
        ->setNotification(
            \NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle('Título')
                ->setBody('Mensagem')
                ->setImage('https://example.com/image.jpg')
        )
        ->setData([
            'route' => '/dashboard',
            'id' => 123,
        ]);
}
```

---

### 6. Slack

**Vantagens:**
- ✅ Perfeito para equipes
- ✅ Organização por canais
- ✅ Integração com workflow

**Implementação:**
```php
public function toSlack($notifiable): SlackMessage
{
    return (new SlackMessage)
        ->success() // verde
        ->content('Novo usuário cadastrado!')
        ->attachment(function ($attachment) {
            $attachment
                ->title('João Silva')
                ->fields([
                    'Email' => 'joao@email.com',
                    'Plano' => 'Pro',
                    'Data' => now()->format('d/m/Y H:i'),
                ])
                ->action('Ver Usuário', url('/users/123'));
        });
}
```

---

## Implementação Completa

### Passo 1: Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel já cria esta tabela automaticamente
        // php artisan notifications:table
        
        // Preferências de notificação
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_type'); // Admin ou User
            $table->string('notification_type');
            
            // Canais
            $table->boolean('via_database')->default(true);
            $table->boolean('via_mail')->default(true);
            $table->boolean('via_broadcast')->default(true);
            $table->boolean('via_sms')->default(false);
            $table->boolean('via_push')->default(true);
            
            $table->timestamps();
            
            $table->unique(['user_id', 'user_type', 'notification_type'], 'unique_user_notification');
            $table->index(['user_id', 'user_type']);
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
```

### Passo 2: Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'notification_type',
        'via_database',
        'via_mail',
        'via_broadcast',
        'via_sms',
        'via_push',
    ];
    
    protected $casts = [
        'via_database' => 'boolean',
        'via_mail' => 'boolean',
        'via_broadcast' => 'boolean',
        'via_sms' => 'boolean',
        'via_push' => 'boolean',
    ];
    
    public function user()
    {
        return $this->morphTo('user', 'user_type', 'user_id');
    }
    
    public function getEnabledChannels(): array
    {
        $channels = [];
        
        if ($this->via_database) $channels[] = 'database';
        if ($this->via_mail) $channels[] = 'mail';
        if ($this->via_broadcast) $channels[] = 'broadcast';
        if ($this->via_sms) $channels[] = 'sms';
        if ($this->via_push) $channels[] = 'push';
        
        return $channels;
    }
}
```

### Passo 3: Trait para Models

```php
<?php

namespace App\Traits;

use App\Models\NotificationPreference;

trait HasNotificationPreferences
{
    /**
     * Obter canais de notificação baseado em preferências
     */
    public function getNotificationChannels(string $notificationType): array
    {
        $preference = NotificationPreference::where([
            'user_id' => $this->id,
            'user_type' => get_class($this),
            'notification_type' => $notificationType,
        ])->first();
        
        if ($preference) {
            return $preference->getEnabledChannels();
        }
        
        // Padrão se não houver preferência
        return ['database', 'mail'];
    }
    
    /**
     * Verificar se quer receber notificação por canal específico
     */
    public function wantsNotificationVia(string $notificationType, string $channel): bool
    {
        return in_array($channel, $this->getNotificationChannels($notificationType));
    }
    
    /**
     * Atualizar preferências
     */
    public function updateNotificationPreferences(string $notificationType, array $channels): void
    {
        NotificationPreference::updateOrCreate(
            [
                'user_id' => $this->id,
                'user_type' => get_class($this),
                'notification_type' => $notificationType,
            ],
            [
                'via_database' => in_array('database', $channels),
                'via_mail' => in_array('mail', $channels),
                'via_broadcast' => in_array('broadcast', $channels),
                'via_sms' => in_array('sms', $channels),
                'via_push' => in_array('push', $channels),
            ]
        );
    }
}
```

### Passo 4: Atualizar Models

```php
// app/Models/User.php
use Illuminate\Notifications\Notifiable;
use App\Traits\HasNotificationPreferences;

class User extends Model
{
    use Notifiable, HasNotificationPreferences;
}

// app/Models/Admin.php
class Admin extends Model
{
    use Notifiable, HasNotificationPreferences;
}
```

### Passo 5: Controller

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Listar notificações do usuário
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'unread_count' => $user->unreadNotifications()->count(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
            ]
        ]);
    }
    
    /**
     * Notificações não lidas
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => $user->unreadNotifications,
            'count' => $user->unreadNotifications()->count(),
        ]);
    }
    
    /**
     * Marcar como lida
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();
        
        $notification->markAsRead();
        
        return response()->json([
            'success' => true,
            'message' => 'Notificação marcada como lida'
        ]);
    }
    
    /**
     * Marcar todas como lidas
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->unreadNotifications()->count();
        
        $user->unreadNotifications->markAsRead();
        
        return response()->json([
            'success' => true,
            'message' => "{$count} notificações marcadas como lidas"
        ]);
    }
    
    /**
     * Deletar notificação
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()
            ->notifications()
            ->where('id', $id)
            ->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Notificação deletada'
        ]);
    }
    
    /**
     * Limpar todas lidas
     */
    public function clearRead(Request $request): JsonResponse
    {
        $count = $request->user()
            ->notifications()
            ->whereNotNull('read_at')
            ->delete();
        
        return response()->json([
            'success' => true,
            'message' => "{$count} notificações limpas"
        ]);
    }
    
    /**
     * Obter preferências
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $preferences = NotificationPreference::where([
            'user_id' => $user->id,
            'user_type' => get_class($user),
        ])->get();
        
        return response()->json([
            'success' => true,
            'data' => $preferences
        ]);
    }
    
    /**
     * Atualizar preferências
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_type' => 'required|string',
            'channels' => 'required|array',
            'channels.*' => 'in:database,mail,broadcast,sms,push',
        ]);
        
        $user = $request->user();
        
        $user->updateNotificationPreferences(
            $validated['notification_type'],
            $validated['channels']
        );
        
        return response()->json([
            'success' => true,
            'message' => 'Preferências atualizadas'
        ]);
    }
}
```

### Passo 6: Routes

```php
// routes/api.php

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('notifications')->group(function () {
        // Listar todas
        Route::get('/', [NotificationController::class, 'index']);
        
        // Não lidas
        Route::get('/unread', [NotificationController::class, 'unread']);
        
        // Marcar como lida
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        
        // Marcar todas como lidas
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        
        // Deletar
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        
        // Limpar lidas
        Route::post('/clear-read', [NotificationController::class, 'clearRead']);
        
        // Preferências
        Route::get('/preferences', [NotificationController::class, 'getPreferences']);
        Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
    });
});
```

---

## Casos de Uso Reais

### Caso 1: E-commerce

```php
// Pedido criado
$order->user->notify(new OrderCreatedNotification($order));

// Pedido enviado
$order->user->notify(new OrderShippedNotification($order, $trackingCode));

// Pedido entregue
$order->user->notify(new OrderDeliveredNotification($order));

// Carrinho abandonado (após 24h)
$schedule->call(function () {
    $abandonedCarts = Cart::where('updated_at', '<', now()->subDay())
        ->whereNull('order_id')
        ->get();
    
    foreach ($abandonedCarts as $cart) {
        $cart->user->notify(new AbandonedCartNotification($cart));
    }
})->hourly();
```

### Caso 2: Plataforma de Ensino

```php
// Nova aula disponível
$course->students->each(fn($student) => 
    $student->notify(new NewLessonNotification($course, $lesson))
);

// Certificado emitido
$student->notify(new CertificateIssuedNotification($certificate));

// Lembrete de aula ao vivo
$schedule->call(function () {
    $upcomingClasses = LiveClass::where('starts_at', now()->addHours(1))->get();
    
    foreach ($upcomingClasses as $class) {
        $class->students->each(fn($student) => 
            $student->notify(new LiveClassReminderNotification($class))
        );
    }
})->everyMinute();
```

### Caso 3: Sistema de Tickets

```php
// Ticket criado
$ticket->assignedTo->notify(new TicketAssignedNotification($ticket));

// Novo comentário
$ticket->author->notify(new TicketCommentNotification($ticket, $comment));

// Ticket resolvido
$ticket->author->notify(new TicketResolvedNotification($ticket));

// SLA próximo do vencimento
$schedule->call(function () {
    $nearSLA = Ticket::where('sla_expires_at', '<', now()->addHour())
        ->whereNull('resolved_at')
        ->get();
    
    foreach ($nearSLA as $ticket) {
        $ticket->assignedTo->notify(new SLAWarningNotification($ticket));
    }
})->everyFiveMinutes();
```

---

## Frontend Integration

### Component: Notification Bell (Vue.js)

```vue
<template>
  <div class="notification-bell">
    <!-- Bell Icon -->
    <button 
      @click="toggleDropdown" 
      class="relative p-2 rounded-full hover:bg-gray-100"
      :class="{ 'animate-shake': hasUnread }"
    >
      <BellIcon class="w-6 h-6" />
      
      <!-- Badge com contador -->
      <span 
        v-if="unreadCount > 0" 
        class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>
    
    <!-- Dropdown -->
    <transition name="fade">
      <div 
        v-if="showDropdown" 
        class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl z-50"
        @click.stop
      >
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-semibold">Notificações</h3>
          <button 
            v-if="unreadCount > 0"
            @click="markAllAsRead" 
            class="text-sm text-blue-600 hover:underline"
          >
            Marcar todas como lidas
          </button>
        </div>
        
        <!-- Lista de Notificações -->
        <div class="max-h-96 overflow-y-auto">
          <div 
            v-for="notification in notifications" 
            :key="notification.id"
            :class="[
              'p-4 border-b cursor-pointer transition',
              notification.read_at ? 'bg-white' : 'bg-blue-50',
              'hover:bg-gray-50'
            ]"
            @click="handleNotificationClick(notification)"
          >
            <!-- Conteúdo -->
            <div class="flex gap-3">
              <!-- Ícone -->
              <div class="text-2xl">{{ notification.data.icon }}</div>
              
              <!-- Texto -->
              <div class="flex-1">
                <div class="flex items-start justify-between">
                  <h4 class="font-semibold text-sm">
                    {{ notification.data.title }}
                  </h4>
                  <span class="text-xs text-gray-500">
                    {{ formatTime(notification.created_at) }}
                  </span>
                </div>
                
                <p class="text-sm text-gray-600 mt-1">
                  {{ notification.data.message }}
                </p>
                
                <!-- Ação -->
                <button 
                  v-if="notification.data.action_text"
                  class="text-xs text-blue-600 mt-2 hover:underline"
                >
                  {{ notification.data.action_text }} →
                </button>
              </div>
              
              <!-- Indicador não lido -->
              <div v-if="!notification.read_at" class="w-2 h-2 bg-blue-600 rounded-full"></div>
            </div>
          </div>
          
          <!-- Empty State -->
          <div 
            v-if="notifications.length === 0" 
            class="p-8 text-center text-gray-400"
          >
            <BellSlashIcon class="w-12 h-12 mx-auto mb-2" />
            <p>Nenhuma notificação</p>
          </div>
        </div>
        
        <!-- Footer -->
        <div class="p-3 border-t text-center">
          <router-link 
            to="/notifications" 
            class="text-sm text-blue-600 hover:underline"
          >
            Ver todas as notificações
          </router-link>
        </div>
      </div>
    </transition>
  </div>
</template>

<script>
import { ref, onMounted, computed } from 'vue';
import { BellIcon, BellSlashIcon } from '@heroicons/vue/24/outline';
import { formatDistanceToNow } from 'date-fns';
import { ptBR } from 'date-fns/locale';

export default {
  components: { BellIcon, BellSlashIcon },
  
  setup() {
    const notifications = ref([]);
    const showDropdown = ref(false);
    const unreadCount = computed(() => 
      notifications.value.filter(n => !n.read_at).length
    );
    const hasUnread = computed(() => unreadCount.value > 0);
    
    // Buscar notificações
    const fetchNotifications = async () => {
      try {
        const { data } = await axios.get('/api/notifications');
        notifications.value = data.data;
      } catch (error) {
        console.error('Erro ao buscar notificações:', error);
      }
    };
    
    // Escutar notificações em tempo real
    const listenToRealtime = () => {
      const userId = window.Laravel.user.id;
      const userType = window.Laravel.user.type;
      
      window.Echo.private(`${userType}.${userId}`)
        .notification((notification) => {
          // Adicionar nova notificação no topo
          notifications.value.unshift(notification);
          
          // Tocar som
          playNotificationSound();
          
          // Toast notification
          window.$toast.show({
            title: notification.data.title,
            message: notification.data.message,
            type: notification.data.type || 'info',
            duration: 5000,
          });
        });
    };
    
    // Toggle dropdown
    const toggleDropdown = () => {
      showDropdown.value = !showDropdown.value;
    };
    
    // Fechar dropdown ao clicar fora
    const handleClickOutside = (event) => {
      if (!event.target.closest('.notification-bell')) {
        showDropdown.value = false;
      }
    };
    
    // Marcar como lida e navegar
    const handleNotificationClick = async (notification) => {
      // Marcar como lida
      if (!notification.read_at) {
        try {
          await axios.post(`/api/notifications/${notification.id}/read`);
          notification.read_at = new Date().toISOString();
        } catch (error) {
          console.error('Erro ao marcar notificação:', error);
        }
      }
      
      // Navegar
      if (notification.data.action_url) {
        window.$router.push(notification.data.action_url);
        showDropdown.value = false;
      }
    };
    
    // Marcar todas como lidas
    const markAllAsRead = async () => {
      try {
        await axios.post('/api/notifications/read-all');
        notifications.value.forEach(n => {
          n.read_at = new Date().toISOString();
        });
        
        window.$toast.success('Todas notificações marcadas como lidas');
      } catch (error) {
        console.error('Erro ao marcar todas:', error);
      }
    };
    
    // Formatar tempo relativo
    const formatTime = (date) => {
      return formatDistanceToNow(new Date(date), {
        addSuffix: true,
        locale: ptBR
      });
    };
    
    // Tocar som
    const playNotificationSound = () => {
      const audio = new Audio('/sounds/notification.mp3');
      audio.volume = 0.5;
      audio.play().catch(() => {
        // Ignorar se usuário não permitiu autoplay
      });
    };
    
    onMounted(() => {
      fetchNotifications();
      listenToRealtime();
      document.addEventListener('click', handleClickOutside);
    });
    
    return {
      notifications,
      showDropdown,
      unreadCount,
      hasUnread,
      toggleDropdown,
      handleNotificationClick,
      markAllAsRead,
      formatTime,
    };
  }
};
</script>

<style scoped>
@keyframes shake {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(-15deg); }
  75% { transform: rotate(15deg); }
}

.animate-shake {
  animation: shake 0.5s ease-in-out;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
```

---

## Página Completa de Notificações

```vue
<template>
  <div class="notifications-page max-w-4xl mx-auto p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold">Notificações</h1>
      
      <div class="flex gap-2">
        <!-- Filtros -->
        <select v-model="filter" class="rounded border px-3 py-2">
          <option value="all">Todas</option>
          <option value="unread">Não lidas</option>
          <option value="read">Lidas</option>
        </select>
        
        <!-- Ações -->
        <button 
          @click="markAllAsRead"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Marcar todas como lidas
        </button>
        
        <button 
          @click="clearRead"
          class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
        >
          Limpar lidas
        </button>
      </div>
    </div>
    
    <!-- Lista -->
    <div class="space-y-2">
      <div
        v-for="notification in filteredNotifications"
        :key="notification.id"
        :class="[
          'p-4 rounded-lg border transition cursor-pointer',
          notification.read_at ? 'bg-white' : 'bg-blue-50 border-blue-200',
          'hover:shadow-md'
        ]"
        @click="handleClick(notification)"
      >
        <div class="flex gap-4">
          <!-- Icon -->
          <div class="text-3xl">{{ notification.data.icon }}</div>
          
          <!-- Content -->
          <div class="flex-1">
            <!-- Title e Tempo -->
            <div class="flex items-start justify-between mb-1">
              <h3 class="font-semibold">{{ notification.data.title }}</h3>
              <span class="text-sm text-gray-500">
                {{ formatTime(notification.created_at) }}
              </span>
            </div>
            
            <!-- Message -->
            <p class="text-gray-700 mb-2">{{ notification.data.message }}</p>
            
            <!-- Preview (se houver) -->
            <p 
              v-if="notification.data.preview" 
              class="text-sm text-gray-500 italic mb-2"
            >
              "{{ notification.data.preview }}"
            </p>
            
            <!-- Action Button -->
            <button 
              v-if="notification.data.action_text"
              class="text-sm text-blue-600 hover:underline"
            >
              {{ notification.data.action_text }} →
            </button>
          </div>
          
          <!-- Actions -->
          <div class="flex flex-col gap-2">
            <!-- Mark as read/unread -->
            <button
              @click.stop="toggleRead(notification)"
              class="text-xs text-gray-500 hover:text-blue-600"
            >
              {{ notification.read_at ? '📖 Marcar não lida' : '✓ Marcar lida' }}
            </button>
            
            <!-- Delete -->
            <button
              @click.stop="deleteNotification(notification)"
              class="text-xs text-gray-500 hover:text-red-600"
            >
              🗑️ Deletar
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Empty State -->
    <div v-if="filteredNotifications.length === 0" class="text-center py-12">
      <BellSlashIcon class="w-16 h-16 mx-auto text-gray-300 mb-4" />
      <p class="text-gray-500">Nenhuma notificação {{ filter === 'unread' ? 'não lida' : '' }}</p>
    </div>
    
    <!-- Pagination -->
    <div v-if="pagination.total > pagination.per_page" class="mt-6 flex justify-center">
      <button 
        v-for="page in totalPages" 
        :key="page"
        @click="goToPage(page)"
        :class="[
          'px-4 py-2 border',
          page === currentPage ? 'bg-blue-600 text-white' : 'bg-white hover:bg-gray-50'
        ]"
      >
        {{ page }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';

const notifications = ref([]);
const filter = ref('all');
const currentPage = ref(1);
const pagination = ref({});

const filteredNotifications = computed(() => {
  if (filter.value === 'unread') {
    return notifications.value.filter(n => !n.read_at);
  }
  if (filter.value === 'read') {
    return notifications.value.filter(n => n.read_at);
  }
  return notifications.value;
});

const totalPages = computed(() => 
  Math.ceil(pagination.value.total / pagination.value.per_page)
);

// ... métodos (ver component anterior)
</script>
```

---

## Boas Práticas

### 1. Queue SEMPRE

```php
❌ MAU:
class WelcomeNotification extends Notification
{
    // Sem queue - bloqueia request
}

✅ BOM:
class WelcomeNotification extends Notification
{
    use Queueable; // ← Adicionar sempre!
    
    // Processado em background
}
```

**Por quê?**
- Enviar email pode demorar 1-3 segundos
- Request do usuário fica esperando
- Má experiência
- Com queue: response instantânea, email enviado depois

### 2. Respeitar Preferências

```php
❌ MAU:
public function via($notifiable): array
{
    return ['database', 'mail', 'sms']; // Ignora preferências
}

✅ BOM:
public function via($notifiable): array
{
    return $notifiable->getNotificationChannels('welcome');
}
```

### 3. Não Fazer Spam

```php
❌ MAU:
// Notificar a cada mudança
$post->author->notify(new PostViewNotification());
// 1000 views = 1000 notificações!

✅ BOM:
// Notificar marcos importantes
if ($post->views % 100 === 0) {
    $post->author->notify(new PostMilestoneNotification($post->views));
}
// 1000 views = 10 notificações (100, 200, 300...)
```

### 4. Priorização

```php
✅ BOM:
public function toArray($notifiable): array
{
    return [
        'title' => 'Título',
        'message' => 'Mensagem',
        'priority' => 'high', // low, normal, high, urgent
        'expires_at' => now()->addDays(7), // Auto-limpar antigas
    ];
}

// No frontend:
notifications.sort((a, b) => {
    const priorities = { urgent: 4, high: 3, normal: 2, low: 1 };
    return priorities[b.priority] - priorities[a.priority];
});
```

### 5. Rate Limiting

```php
✅ BOM:
// Não enviar mesma notificação múltiplas vezes
class PasswordChangedNotification extends Notification
{
    use Queueable;
    
    public function viaQueues(): array
    {
        return [
            'mail' => 'notifications',
            'sms' => 'high-priority',
        ];
    }
    
    // Evitar duplicatas em 5 minutos
    public function shouldSend($notifiable, $channel): bool
    {
        $recent = $notifiable->notifications()
            ->where('type', static::class)
            ->where('created_at', '>', now()->subMinutes(5))
            ->exists();
        
        return !$recent;
    }
}
```

### 6. Testes

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    /** @test */
    public function welcome_notification_is_sent_when_user_registers()
    {
        Notification::fake();
        
        // Criar usuário
        $user = User::factory()->create();
        
        // Trigger notificação
        $user->notify(new WelcomeNotification($user->name));
        
        // Assert
        Notification::assertSentTo($user, WelcomeNotification::class);
    }
    
    /** @test */
    public function notification_respects_user_preferences()
    {
        $user = User::factory()->create();
        
        // Desabilitar email
        $user->updateNotificationPreferences('welcome', ['database']);
        
        $notification = new WelcomeNotification($user->name);
        $channels = $notification->via($user);
        
        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }
    
    /** @test */
    public function user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();
        
        // Criar notificação
        $user->notify(new WelcomeNotification($user->name));
        
        $notification = $user->notifications->first();
        $this->assertNull($notification->read_at);
        
        // Marcar como lida
        $notification->markAsRead();
        
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
```

---

## Performance e Escalabilidade

### 1. Queue Workers

```bash
# Processar notificações em background
php artisan queue:work --queue=notifications,default

# Múltiplos workers para alto volume
php artisan queue:work --queue=notifications --tries=3 &
php artisan queue:work --queue=notifications --tries=3 &
php artisan queue:work --queue=notifications --tries=3 &
```

### 2. Database Indexing

```sql
-- Índices essenciais
CREATE INDEX idx_notifiable ON notifications(notifiable_type, notifiable_id);
CREATE INDEX idx_read_at ON notifications(read_at);
CREATE INDEX idx_created_at ON notifications(created_at);

-- Query otimizada
SELECT * FROM notifications 
WHERE notifiable_id = 123 
  AND notifiable_type = 'App\Models\User'
  AND read_at IS NULL
ORDER BY created_at DESC
LIMIT 20;
-- Usa índice: idx_notifiable
```

### 3. Limpeza Automática

```php
// app/Console/Commands/CleanOldNotifications.php
class CleanOldNotifications extends Command
{
    protected $signature = 'notifications:clean {--days=30}';
    
    public function handle()
    {
        $days = $this->option('days');
        
        $deleted = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
        
        $this->info("Deletadas {$deleted} notificações lidas com mais de {$days} dias");
    }
}

// Agendar:
$schedule->command('notifications:clean --days=30')->weekly();
```

### 4. Pagination

```php
// ✅ BOM: Paginar sempre
$notifications = $user->notifications()->paginate(20);

// ❌ MAU: Trazer todas
$notifications = $user->notifications; // Pode ser milhares!
```

---

## Segurança

### 1. Sanitização de Dados

```php
class NotificationService
{
    public static function sanitize(array $data): array
    {
        // Remover HTML perigoso
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = strip_tags($value, '<b><i><u><a>');
            }
        });
        
        return $data;
    }
}
```

### 2. Validação de URLs

```php
public function toArray($notifiable): array
{
    return [
        'title' => 'Título',
        'action_url' => $this->validateUrl($this->url),
    ];
}

private function validateUrl(string $url): string
{
    // Apenas URLs internas
    if (!str_starts_with($url, '/') && !str_starts_with($url, config('app.url'))) {
        return '/';
    }
    
    return $url;
}
```

### 3. Rate Limiting

```php
// Evitar spam de notificações
class NotificationRateLimiter
{
    public static function check($user, $notificationType): bool
    {
        $key = "notification_rate:{$user->id}:{$notificationType}";
        $count = Cache::get($key, 0);
        
        // Máximo 5 notificações do mesmo tipo por hora
        if ($count >= 5) {
            return false;
        }
        
        Cache::put($key, $count + 1, now()->addHour());
        return true;
    }
}
```

---

## Métricas e Analytics

### Dashboard de Notificações (Admin)

```php
class NotificationAnalytics
{
    public static function getStats(string $period = '30days'): array
    {
        $startDate = match($period) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            default => now()->subDays(30),
        };
        
        return [
            'total_sent' => DB::table('notifications')
                ->where('created_at', '>=', $startDate)
                ->count(),
            
            'read_rate' => DB::table('notifications')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('
                    COUNT(CASE WHEN read_at IS NOT NULL THEN 1 END) * 100.0 / COUNT(*) as rate
                ')
                ->value('rate'),
            
            'avg_time_to_read' => DB::table('notifications')
                ->where('created_at', '>=', $startDate)
                ->whereNotNull('read_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, read_at)) as seconds')
                ->value('seconds'),
            
            'by_type' => DB::table('notifications')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get(),
            
            'by_day' => DB::table('notifications')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];
    }
}
```

---

## Integração com Outros Sistemas

### Com Auditoria

```php
// Registrar envio de notificação importante
class PasswordChangedNotification extends Notification
{
    public function toMail($notifiable): MailMessage
    {
        // Registrar no audit log
        AuditLog::create([
            'user_id' => $notifiable->id,
            'user_type' => get_class($notifiable),
            'action' => 'notification_sent',
            'model_type' => static::class,
            'description' => 'Notificação de senha alterada enviada',
            'tags' => ['security', 'notification'],
        ]);
        
        return (new MailMessage)
            ->subject('Senha Alterada')
            // ...
    }
}
```

### Com Configurações

```php
class NotificationService
{
    public static function shouldSend(string $type): bool
    {
        // Verificar se tipo de notificação está habilitado
        return Settings::get("notifications.{$type}_enabled", true);
    }
}

// Uso:
if (NotificationService::shouldSend('welcome')) {
    $user->notify(new WelcomeNotification($user->name));
}
```

---

## Comandos Úteis

```bash
# Criar nova notificação
php artisan make:notification WelcomeNotification

# Criar tabela de notificações
php artisan notifications:table
php artisan migrate

# Testar envio (Tinker)
php artisan tinker
> $user = User::find(1);
> $user->notify(new WelcomeNotification($user->name));

# Ver notificações de um usuário
> User::find(1)->notifications

# Processar queue de notificações
php artisan queue:work --queue=notifications

# Limpar notificações antigas
php artisan notifications:clean --days=30

# Estatísticas
php artisan notifications:stats
```

---

## Checklist de Implementação

### Básico (1-2 dias)
- [ ] Migration `notifications` (Laravel nativo)
- [ ] Migration `notification_preferences`
- [ ] Model `NotificationPreference`
- [ ] Trait `HasNotificationPreferences`
- [ ] 3-5 notificações principais (Welcome, PasswordChanged, etc)
- [ ] Controller + Routes
- [ ] Testes básicos

### Intermediário (3-4 dias)
- [ ] Frontend: Bell component
- [ ] Frontend: Página de notificações
- [ ] Real-time via Pusher
- [ ] Preferências de usuário
- [ ] Email templates customizados
- [ ] Testes completos

### Avançado (1 semana)
- [ ] SMS integration (Twilio/Vonage)
- [ ] Push notifications (Firebase)
- [ ] Slack integration
- [ ] Analytics dashboard
- [ ] Rate limiting
- [ ] Limpeza automática
- [ ] A/B testing de mensagens

---

## Conclusão

O **Sistema de Notificações** é fundamental para qualquer aplicação BtoB moderna porque:

- 📧 **Comunica** eventos importantes
- 🔔 **Engaja** usuários (+30% retenção)
- 🔒 **Protege** (alertas de segurança)
- 📊 **Informa** admins (operacional)
- 📱 **Multi-canal** (email, in-app, push, SMS)
- ⚙️ **Configurável** (usuário controla)

Seguindo este guia, você terá um sistema completo, escalável e profissional de notificações que serve como base para qualquer projeto BtoB.

---

**Próximo passo:** Implementar o sistema seguindo este guia! 🚀

