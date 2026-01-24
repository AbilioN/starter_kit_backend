# Guia de Configuração do Chat em Tempo Real

## 1. Configurar Pusher

### 1.1 Criar conta no Pusher
1. Acesse [pusher.com](https://pusher.com)
2. Crie uma conta gratuita
3. Crie um novo app
4. Anote as credenciais:
   - App ID
   - App Key
   - App Secret
   - Cluster

### 1.2 Configurar variáveis de ambiente
Edite o arquivo `env.docker` e adicione suas credenciais do Pusher:

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=seu_app_id
PUSHER_APP_KEY=sua_app_key
PUSHER_APP_SECRET=seu_app_secret
PUSHER_APP_CLUSTER=seu_cluster
```

### 1.3 Reiniciar containers
```bash
docker-compose down
docker-compose up -d
```

## 2. Testar o Backend

### 2.1 Verificar se as rotas estão funcionando
```bash
# Listar rotas de chat
docker-compose exec app php artisan route:list --name=chat

# Testar envio de mensagem (substitua os IDs)
curl -X POST http://localhost:8006/api/chat/send \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Teste de mensagem",
    "receiver_type": "admin",
    "receiver_id": 1
  }'
```

### 2.2 Verificar broadcast
```bash
# Verificar se o evento está sendo disparado
docker-compose exec app php artisan tinker
>>> event(new App\Events\MessageSent(App\Models\Message::first()));
```

## 3. Implementação no Nuxt.js (Admin)

### 3.1 Instalar dependências
```bash
npm install laravel-echo pusher-js
```

### 3.2 Configurar plugin
Crie `plugins/echo.js`:
```javascript
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export default defineNuxtPlugin(() => {
  const config = useRuntimeConfig()
  
  window.Pusher = Pusher
  
  window.Echo = new Echo({
    broadcaster: 'pusher',
    key: config.public.pusherKey,
    cluster: config.public.pusherCluster,
    forceTLS: true,
    authEndpoint: '/api/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`
      }
    }
  })
})
```

### 3.3 Configurar nuxt.config.ts
```typescript
export default defineNuxtConfig({
  runtimeConfig: {
    public: {
      pusherKey: process.env.PUSHER_APP_KEY,
      pusherCluster: process.env.PUSHER_APP_CLUSTER
    }
  }
})
```

### 3.4 Criar componente de chat
```vue
<template>
  <div class="chat-container">
    <div class="messages" ref="messagesContainer">
      <div v-for="message in messages" :key="message.id" 
           :class="['message', message.sender_type === 'admin' ? 'sent' : 'received']">
        <div class="message-content">{{ message.content }}</div>
        <div class="message-time">{{ formatTime(message.created_at) }}</div>
      </div>
    </div>
    
    <div class="input-container">
      <input v-model="newMessage" @keyup.enter="sendMessage" placeholder="Digite sua mensagem..." />
      <button @click="sendMessage">Enviar</button>
    </div>
  </div>
</template>

<script setup>
const { $api } = useNuxtApp()
const messages = ref([])
const newMessage = ref('')
const messagesContainer = ref(null)
const currentUser = ref(null)
const otherUserId = ref(null)

// Props
const props = defineProps({
  userId: {
    type: Number,
    required: true
  }
})

onMounted(() => {
  currentUser.value = JSON.parse(localStorage.getItem('user'))
  otherUserId.value = props.userId
  loadMessages()
  listenToMessages()
})

const loadMessages = async () => {
  try {
    const response = await $api.get(`/admin/chat/conversation?user_id=${otherUserId.value}`)
    messages.value = response.data.messages
    scrollToBottom()
  } catch (error) {
    console.error('Erro ao carregar mensagens:', error)
  }
}

const sendMessage = async () => {
  if (!newMessage.value.trim()) return
  
  try {
    await $api.post('/admin/chat/send', {
      content: newMessage.value,
      user_id: otherUserId.value
    })
    newMessage.value = ''
  } catch (error) {
    console.error('Erro ao enviar mensagem:', error)
  }
}

const listenToMessages = () => {
  const channelName = `chat.${Math.min(currentUser.value.id, otherUserId.value)}-${Math.max(currentUser.value.id, otherUserId.value)}`
  
  window.Echo.private(channelName)
    .listen('MessageSent', (e) => {
      messages.value.push(e.message)
      scrollToBottom()
    })
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

const formatTime = (time) => {
  return new Date(time).toLocaleTimeString('pt-BR', { 
    hour: '2-digit', 
    minute: '2-digit' 
  })
}
</script>

<style scoped>
.chat-container {
  display: flex;
  flex-direction: column;
  height: 500px;
  border: 1px solid #ddd;
  border-radius: 8px;
}

.messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.message {
  margin-bottom: 12px;
  max-width: 70%;
}

.message.sent {
  margin-left: auto;
  text-align: right;
}

.message.received {
  margin-right: auto;
  text-align: left;
}

.message-content {
  padding: 8px 12px;
  border-radius: 12px;
  display: inline-block;
}

.message.sent .message-content {
  background-color: #007bff;
  color: white;
}

.message.received .message-content {
  background-color: #f1f1f1;
  color: #333;
}

.message-time {
  font-size: 12px;
  color: #666;
  margin-top: 4px;
}

.input-container {
  display: flex;
  padding: 16px;
  border-top: 1px solid #ddd;
}

.input-container input {
  flex: 1;
  padding: 8px 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  margin-right: 8px;
}

.input-container button {
  padding: 8px 16px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
</style>
```

## 4. Implementação no Flutter (Cliente)

### 4.1 Adicionar dependências
```yaml
# pubspec.yaml
dependencies:
  flutter:
    sdk: flutter
  pusher_channels_flutter: ^2.0.0
  http: ^1.1.0
  shared_preferences: ^2.2.2
```

### 4.2 Criar serviço de chat
```dart
// lib/services/chat_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ChatService {
  static final ChatService _instance = ChatService._internal();
  factory ChatService() => _instance;
  ChatService._internal();

  late PusherChannelsFlutter pusher;
  final String baseUrl = 'http://localhost:8006/api';
  String? _token;

  Future<void> initialize() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('token');

    pusher = PusherChannelsFlutter.getInstance();
    await pusher.init(
      apiKey: "YOUR_PUSHER_KEY",
      cluster: "YOUR_PUSHER_CLUSTER",
    );
  }

  void listenToMessages(int userId, int otherUserId, Function(Map<String, dynamic>) onMessage) {
    final channelName = 'chat.${userId < otherUserId ? userId : otherUserId}-${userId < otherUserId ? otherUserId : userId}';
    
    pusher.subscribe(
      channelName: channelName,
      onEvent: (event) {
        if (event.eventName == 'MessageSent') {
          final data = jsonDecode(event.data);
          onMessage(data['message']);
        }
      },
    );
  }

  Future<List<Map<String, dynamic>>> getConversation(int otherUserId, {int page = 1}) async {
    final response = await http.get(
      Uri.parse('$baseUrl/chat/conversation?other_user_type=admin&other_user_id=$otherUserId&page=$page'),
      headers: {
        'Authorization': 'Bearer $_token',
        'Content-Type': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      return List<Map<String, dynamic>>.from(data['messages']);
    }
    throw Exception('Falha ao carregar conversa');
  }

  Future<void> sendMessage(String content, int receiverId) async {
    final response = await http.post(
      Uri.parse('$baseUrl/chat/send'),
      headers: {
        'Authorization': 'Bearer $_token',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'content': content,
        'receiver_type': 'admin',
        'receiver_id': receiverId,
      }),
    );

    if (response.statusCode != 201) {
      throw Exception('Falha ao enviar mensagem');
    }
  }

  void disconnect() {
    pusher.disconnect();
  }
}
```

### 4.3 Criar tela de chat
```dart
// lib/screens/chat_screen.dart
import 'package:flutter/material.dart';
import '../services/chat_service.dart';

class ChatScreen extends StatefulWidget {
  final int adminId;
  
  const ChatScreen({Key? key, required this.adminId}) : super(key: key);

  @override
  _ChatScreenState createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final ChatService _chatService = ChatService();
  final TextEditingController _messageController = TextEditingController();
  final List<Map<String, dynamic>> _messages = [];
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _initializeChat();
  }

  Future<void> _initializeChat() async {
    await _chatService.initialize();
    await _loadMessages();
    _listenToMessages();
  }

  Future<void> _loadMessages() async {
    try {
      final messages = await _chatService.getConversation(widget.adminId);
      setState(() {
        _messages.clear();
        _messages.addAll(messages);
      });
      _scrollToBottom();
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Erro ao carregar mensagens: $e')),
      );
    }
  }

  void _listenToMessages() {
    // Assumindo que o userId está armazenado em algum lugar
    final userId = 1; // Substitua pelo ID real do usuário
    
    _chatService.listenToMessages(userId, widget.adminId, (message) {
      setState(() {
        _messages.add(message);
      });
      _scrollToBottom();
    });
  }

  Future<void> _sendMessage() async {
    if (_messageController.text.trim().isEmpty) return;

    final content = _messageController.text;
    _messageController.clear();

    try {
      await _chatService.sendMessage(content, widget.adminId);
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Erro ao enviar mensagem: $e')),
      );
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Chat com Admin'),
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView.builder(
              controller: _scrollController,
              padding: EdgeInsets.all(16),
              itemCount: _messages.length,
              itemBuilder: (context, index) {
                final message = _messages[index];
                final isFromUser = message['sender_type'] == 'user';
                
                return Align(
                  alignment: isFromUser ? Alignment.centerRight : Alignment.centerLeft,
                  child: Container(
                    margin: EdgeInsets.only(bottom: 8),
                    padding: EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isFromUser ? Colors.blue : Colors.grey[300],
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          message['content'],
                          style: TextStyle(
                            color: isFromUser ? Colors.white : Colors.black,
                          ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          message['created_at'],
                          style: TextStyle(
                            fontSize: 12,
                            color: isFromUser ? Colors.white70 : Colors.grey[600],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          Container(
            padding: EdgeInsets.all(16),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _messageController,
                    decoration: InputDecoration(
                      hintText: 'Digite sua mensagem...',
                      border: OutlineInputBorder(),
                    ),
                    onSubmitted: (_) => _sendMessage(),
                  ),
                ),
                SizedBox(width: 8),
                IconButton(
                  onPressed: _sendMessage,
                  icon: Icon(Icons.send),
                  color: Colors.blue,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _chatService.disconnect();
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }
}
```

## 5. Testar o Sistema

### 5.1 Testar backend
```bash
# Criar usuários de teste
docker-compose exec app php artisan tinker
>>> App\Models\User::factory()->count(3)->create()
>>> App\Models\Admin::factory()->count(2)->create()

# Testar envio de mensagem
curl -X POST http://localhost:8006/api/chat/send \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"content": "Teste", "receiver_type": "admin", "receiver_id": 1}'
```

### 5.2 Testar frontend
1. Configure o Pusher no Nuxt.js
2. Configure o Pusher no Flutter
3. Teste o envio e recebimento de mensagens
4. Verifique se as mensagens aparecem em tempo real

## 6. Troubleshooting

### Problemas comuns:
1. **Broadcast não funciona**: Verifique se o `BROADCAST_DRIVER=pusher`
2. **Canais privados não autenticam**: Verifique a rota `/broadcasting/auth`
3. **Mensagens não chegam**: Verifique as credenciais do Pusher
4. **Erro de CORS**: Configure o CORS no Laravel se necessário

### Logs úteis:
```bash
# Ver logs do Laravel
docker-compose logs app

# Ver logs do Pusher (no dashboard)
# Acesse o dashboard do Pusher para ver eventos
``` 