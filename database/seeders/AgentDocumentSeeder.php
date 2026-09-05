<?php

namespace Database\Seeders;

use App\Models\AgentDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample documents so the end-user agent has something real to consult.
 *
 * Each one is generated as an actual PDF **and** stored as text. That mirrors a
 * real ingest: extraction happens once, when the document arrives, and every
 * later question is a database read rather than a file parse.
 *
 * There is no PDF parser installed, and none is needed here because the text is
 * authored alongside the document. When real uploads arrive, a parser
 * (smalot/pdfparser, or pdftotext in the image) plugs in at exactly this point —
 * the tools read `content` and never touch the file, so none of them change.
 *
 * Content is Portuguese because it is product material a Brazilian tenant would
 * publish for its own users; the code around it stays English.
 */
class AgentDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $document) {
            $path = $this->writePdf($document['title'], $document['content']);

            AgentDocument::updateOrCreate(
                ['title' => $document['title']],
                [
                    'description' => $document['description'],
                    'file_path' => $path,
                    'content' => $document['content'],
                    'is_active' => true,
                // Published: these are the manual/FAQ/terms the table was made
                // for. Without it the column default (`internal`) applies and the
                // end-user agent cannot see a single one of them.
                'audience' => \App\Models\AgentDocument::AUDIENCE_PUBLISHED,
                ],
            );

            $this->command?->info("  {$document['title']}".($path ? " → {$path}" : ' (text only)'));
        }
    }

    /**
     * @return array<int, array{title: string, description: string, content: string}>
     */
    private function documents(): array
    {
        return [
            [
                'title' => 'Guia de Início Rápido',
                'description' => 'Primeiros passos: criar conta, entrar no workspace e começar uma conversa.',
                'content' => <<<'TXT'
Guia de Início Rápido

1. Entrar no seu workspace
Para entrar, você precisa de três coisas: o nome do workspace da sua empresa, seu e-mail e sua senha. O nome do workspace é o identificador curto da empresa — por exemplo, tenant-a. Se você digitar o nome com maiúsculas ou espaços, o aplicativo normaliza automaticamente. Se o workspace não for encontrado, confirme o nome com o administrador da sua empresa: não é a mesma coisa que uma senha errada.

2. Verificação de e-mail
Contas novas recebem um código de verificação por e-mail. Enquanto o e-mail não for verificado, o acesso fica limitado. O código expira em 60 minutos. Se ele expirar, peça um novo pela tela de verificação — não é necessário criar a conta outra vez.

3. Conversas
A tela de conversas mostra tudo em que você participa: conversas privadas, grupos e conversas com agentes de IA. Mensagens não lidas aparecem com um marcador. Você pode pedir ao agente quantas mensagens não lidas tem, e ele responde com o número atual, não com um número guardado de antes.

4. Esqueci minha senha
Use "Esqueci minha senha" na tela de login. O link enviado vale por 60 minutos e só pode ser usado uma vez. Trocar a senha encerra as sessões abertas em outros aparelhos.

5. Notificações
Você recebe notificação quando alguém envia mensagem em uma conversa sua e quando um administrador comunica algo ao workspace. Notificações são pessoais: ninguém além de você as vê.
TXT,
            ],
            [
                'title' => 'Perguntas Frequentes',
                'description' => 'Dúvidas comuns sobre conta, privacidade, agentes de IA e limites.',
                'content' => <<<'TXT'
Perguntas Frequentes

O que o agente de IA consegue ver sobre mim?
Apenas os seus próprios dados: seu perfil, suas conversas, suas mensagens não lidas e suas notificações. O agente não consegue ler dados de outras pessoas do workspace, mesmo que alguém peça. Isso não é uma regra de conduta que ele decide seguir — as funções disponíveis a ele são incapazes de devolver dados de terceiros.

O agente pode alterar alguma coisa?
Não. As funções disponíveis ao usuário são somente de leitura. Se você pedir para criar, apagar ou alterar algo, ele vai explicar que não pode e indicar onde fazer isso pela interface.

Por que o agente às vezes diz que atingiu um limite?
Cada pergunta tem um número máximo de consultas que o agente pode fazer. Ao chegar nesse limite, ele responde com o que já reuniu em vez de continuar. Se precisar de mais, faça uma nova pergunta.

Meu workspace precisa pagar para ter IA?
O acesso à IA vem do plano da empresa. Se a empresa tem IA contratada e o recurso está ativo, todos os usuários daquele workspace têm acesso — não é um item comprado por pessoa.

Os documentos daqui são visíveis para todo mundo?
Sim. Documentos publicados pelo workspace, como este, são o material que a empresa disponibiliza para seus usuários. São iguais para todos. Isso é diferente dos seus dados pessoais, que só você vê.

Onde ficam meus dados?
Cada empresa tem seu próprio banco de dados, separado dos demais. Nenhuma consulta atravessa de um workspace para outro.
TXT,
            ],
            [
                'title' => 'Política de Privacidade e Uso de Dados',
                'description' => 'Como os dados são tratados, o que é registrado e por quanto tempo.',
                'content' => <<<'TXT'
Política de Privacidade e Uso de Dados

Separação por empresa
Cada empresa cliente possui um banco de dados próprio. Consultas são resolvidas dentro do banco da empresa identificada na requisição, e não existe caminho que leve uma consulta de um workspace ao banco de outro.

Registro de auditoria
Ações relevantes são registradas em um log imutável: quem fez, o que fez, quando e com qual identificador de requisição. O registro não pode ser editado nem apagado por ninguém, incluindo administradores. Isso existe para que qualquer pergunta sobre o que aconteceu tenha resposta verificável.

Uso de inteligência artificial
Quando você conversa com um agente de IA, o texto da sua mensagem é enviado ao provedor de IA configurado pela sua empresa para gerar a resposta. Toda consulta que o agente faz aos seus dados fica registrada na auditoria, com a função usada e os parâmetros. O conteúdo das mensagens de outras pessoas nunca é enviado.

Retenção
Mensagens permanecem enquanto a conversa existir. Registros de auditoria seguem a política de retenção da empresa. Backups são cifrados e guardados conforme o plano contratado.

Seus direitos
Você pode pedir cópia dos seus dados e a exclusão da sua conta ao administrador do seu workspace. A exclusão anonimiza seus dados pessoais preservando a integridade dos registros de auditoria, que por definição não são alterados.
TXT,
            ],
        ];
    }

    /**
     * Renders the document as a real PDF. A failure here is not fatal: the text
     * is what the agent reads, so a missing file leaves the feature working and
     * only the download unavailable.
     */
    private function writePdf(string $title, string $content): ?string
    {
        try {
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
            $mpdf->SetTitle($title);

            $body = collect(explode("\n", $content))
                ->map(fn (string $line) => trim($line) === '' ? '' : '<p>'.e($line).'</p>')
                ->implode('');

            $mpdf->WriteHTML('<h1>'.e($title).'</h1>'.$body);

            $path = 'agent-documents/'.Str::slug($title).'.pdf';
            Storage::put($path, $mpdf->Output('', 'S'));

            return $path;
        } catch (\Throwable $e) {
            $this->command?->warn("  PDF for '{$title}' could not be rendered: {$e->getMessage()}");

            return null;
        }
    }
}
