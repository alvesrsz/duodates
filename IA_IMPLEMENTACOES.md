# Implementações de Inteligência Artificial — DuoDates

## Visão Geral

O DuoDates utiliza a **API da Groq** com o modelo **`llama-3.3-70b-versatile`** para duas funcionalidades de IA distintas. A Groq foi escolhida por oferecer inferência de LLM extremamente rápida (latência baixa), ideal para respostas em tempo real dentro da plataforma.

| Funcionalidade | Onde aparece | Arquivo de entrada |
|---|---|---|
| Sugestão automática de tags | Formulário de cadastro de lugar | `php/sugerir_tags.php` |
| Recomendação personalizada de date | Perfil em comum do casal | `php/recomendar_dates.php` |

---

## Configuração da API

```
Provedor:  Groq  (https://api.groq.com)
Modelo:    llama-3.3-70b-versatile
Endpoint:  https://api.groq.com/openai/v1/chat/completions
Chave:     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
```

A chave está configurada diretamente nos dois arquivos PHP de endpoint. A API da Groq segue o padrão OpenAI, portanto a requisição usa `curl` com `Content-Type: application/json` e `Authorization: Bearer <chave>`.

---

## Funcionalidade 1 — Sugestão de Tags com IA

### O que faz
Ao cadastrar um novo lugar em `php/adicionar-lugar.php`, o usuário pode clicar no botão **✨ Sugerir Tags com IA**. A IA lê o título e a descrição preenchidos e marca automaticamente os checkboxes de tags que melhor descrevem aquele local.

### Arquivos envolvidos

| Arquivo | Responsabilidade |
|---|---|
| `php/adicionar-lugar.php` | Contém o botão, o JavaScript que dispara a requisição e aplica o resultado nos checkboxes |
| `php/sugerir_tags.php` | Endpoint PHP (POST): busca tags no banco, monta o prompt, chama a Groq e retorna os IDs |

### Fluxo técnico

```
[Usuário preenche título + descrição]
        │
        ▼
[JavaScript em adicionar-lugar.php]
  → fetch POST para sugerir_tags.php
  → body: { titulo, descricao }
        │
        ▼
[sugerir_tags.php]
  1. Valida sessão (usuário, admin ou empresa)
  2. SELECT id_tag, nome_tag, categoria FROM tags
  3. Monta lista de tags no prompt
  4. POST para api.groq.com
        │
        ▼
[Groq API — llama-3.3-70b-versatile]
  temperature: 0.2   (resposta mais determinística)
  max_tokens:  300
  Retorna: { "ids": [1, 4, 7, ...] }
        │
        ▼
[sugerir_tags.php]
  5. Extrai JSON da resposta com regex
  6. Valida os IDs contra os existentes no banco
  7. Retorna JSON limpo ao navegador
        │
        ▼
[JavaScript em adicionar-lugar.php]
  → Marca os checkboxes com os IDs recebidos
  → Exibe mensagem "X tags sugeridas"
```

### Prompt utilizado

```
Você é um assistente que categoriza locais para encontros e dates.

Analise o local abaixo e escolha as tags mais adequadas da lista.

LOCAL:
Título: [título preenchido]
Descrição: [descrição preenchida]

TAGS DISPONÍVEIS:
ID 1: Romântico (Grupo: Ambiente)
ID 2: Ao ar livre (Grupo: Ambiente)
... [todas as tags do banco]

Retorne SOMENTE um JSON válido no formato {"ids": [1, 2, 3]} com os IDs
das tags que combinam com o local. Selecione pelo menos uma tag de cada
grupo quando possível. Não inclua nenhum texto fora do JSON.
```

### Parâmetros da requisição Groq

```json
{
  "model": "llama-3.3-70b-versatile",
  "messages": [{ "role": "user", "content": "<prompt>" }],
  "temperature": 0.2,
  "max_tokens": 300
}
```

> `temperature: 0.2` mantém as sugestões consistentes e objetivas (baixa criatividade, alta precisão).

---

## Funcionalidade 2 — Recomendação de Date com IA

### O que faz
Na página `php/perfil_em_comum.php` (perfil compartilhado do casal), existe o botão **✨ Gerar Recomendação com IA**. A IA analisa os perfis de ambos os usuários — incluindo preferências da essência e respostas do questionário de compatibilidade — e gera **3 cards personalizados**.

### Arquivos envolvidos

| Arquivo | Responsabilidade |
|---|---|
| `php/perfil_em_comum.php` | Página do casal — exibe o botão, os 3 cards e faz a requisição via JavaScript |
| `php/recomendar_dates.php` | Endpoint PHP (POST): lê perfis do banco, busca eventos no Ticketmaster, monta prompt e chama a Groq |
| `php/buscar_ticketmaster.php` | Helper auxiliar — consulta a API do Ticketmaster (também usado no painel direito) |

### Cards retornados

| Campo JSON | Card exibido | Conteúdo |
|---|---|---|
| `analise` | 💞 Análise do Casal | Descrição de quem são os dois juntos: o que têm em comum, energia da dupla |
| `sugestao` | 🗓️ Sugestão de Date | Programa de date específico e detalhado para esse casal em particular |
| `evento` | 🎟️ Evento em Destaque | Evento real do Ticketmaster que combina com eles, ou sugestão de tipo de evento |

### Dados enviados para a IA (fontes no banco)

**Sistema 1 — Preferências da Essência** (tabela `usuarios`):
- `pref_vibe` — tipo de vibe/estilo preferido
- `pref_atividade` — atividades preferidas
- `pref_comfort` — nível de conforto / ambiente
- `pref_food_ranking` — ranking de culinárias (JSON)

**Sistema 2 — Questionário de Compatibilidade** (tabela `respostas_usuario` JOIN `tags`):
- Tags selecionadas por cada usuário para a conexão específica
- Agrupadas por categoria (Ambiente, Atividade, Culinária, etc.)

**API externa — Ticketmaster**:
- 5 eventos mais próximos no Distrito Federal (DF), em ordem de data
- Campos utilizados: nome, data, local, gênero/segmento

### Fluxo técnico

```
[Usuário clica "✨ Gerar Recomendação com IA"]
        │
        ▼
[JavaScript em perfil_em_comum.php]
  → fetch POST para recomendar_dates.php
  → body: { partner_id, id_conexao }
        │
        ▼
[recomendar_dates.php]
  1. Valida sessão do usuário logado
  2. SELECT perfil do usuário logado (Sistema 1)
  3. SELECT perfil do parceiro (Sistema 1)
  4. SELECT tags do questionário de ambos (Sistema 2)
  5. GET api.ticketmaster.com → 5 eventos em Brasília/DF
  6. Monta prompt com todos os dados
  7. POST para api.groq.com
        │
        ▼
[Groq API — llama-3.3-70b-versatile]
  temperature: 0.7   (resposta mais criativa e personalizada)
  max_tokens:  700
  Retorna: { "analise": "...", "sugestao": "...", "evento": "..." }
        │
        ▼
[recomendar_dates.php]
  8. Extrai o JSON da resposta com regex
  9. Valida as 3 chaves obrigatórias
  10. Retorna JSON ao navegador
        │
        ▼
[JavaScript em perfil_em_comum.php]
  → Renderiza os 3 cards com animação de entrada
  → Exibe botão "🔄 Gerar Nova Recomendação"
```

### Estrutura do prompt utilizado

```
Você é um assistente especialista em planejar experiências românticas e
dates para casais.

Analise os perfis do casal e gere uma recomendação personalizada.

=== PERFIL DE [Nome A] ===
Vibe: [pref_vibe] | Atividades preferidas: [pref_atividade] | Conforto: [pref_comfort] | Culinária favorita: [top 3]
Interesses do questionário: [tags A separadas por vírgula]

=== PERFIL DE [Nome B] ===
Vibe: [pref_vibe] | ...
Interesses do questionário: [tags B separadas por vírgula]

=== EVENTOS DISPONÍVEIS EM BRASÍLIA ===
- Nome do Evento | Gênero | Local | Data
- ...

Retorne SOMENTE um JSON válido, sem texto fora dele, com exatamente estas 3 chaves:
{
  "analise": "2 a 3 frases descrevendo quem são X e Y como casal...",
  "sugestao": "Uma sugestão de date específica e detalhada para este casal...",
  "evento": "Se algum evento combinar, indique e explique. Caso contrário, sugira um tipo."
}
Responda em português do Brasil.
```

### Parâmetros da requisição Groq

```json
{
  "model": "llama-3.3-70b-versatile",
  "messages": [{ "role": "user", "content": "<prompt>" }],
  "temperature": 0.7,
  "max_tokens": 700
}
```

> `temperature: 0.7` permite variação nas respostas — clicar em "Gerar Nova Recomendação" produz um resultado diferente a cada vez.

---

## Pré-requisitos para as funcionalidades funcionarem

### Sugestão de Tags
- Usuário precisa estar logado (qualquer tipo de conta)
- Deve haver tags cadastradas na tabela `tags` do banco
- Conexão com a internet ativa (chamada externa à Groq)

### Recomendação de Date
- Dois usuários precisam estar conectados (status `aceito` na tabela `conexoes`)
- **Ambos** precisam ter preenchido o questionário de essência (`mudar_essencia.php`) — tabela `usuarios`, colunas `pref_vibe`, `pref_atividade`, etc.
- **Ambos** precisam ter respondido ao questionário de compatibilidade (`compatibilidade.php`) — tabela `respostas_usuario`
- Conexão com a internet ativa (chamadas externas à Groq e ao Ticketmaster)

---

## Tratamento de Erros

Ambos os endpoints retornam JSON com chave `erro` em caso de falha:

| Situação | Resposta |
|---|---|
| Usuário não logado | HTTP 401 `{ "erro": "Não autorizado." }` |
| Método diferente de POST | HTTP 405 `{ "erro": "Método não permitido." }` |
| Sem título/descrição (tags) | `{ "erro": "Preencha o título e/ou a descrição..." }` |
| Sem tags no banco | `{ "erro": "Nenhuma tag cadastrada no sistema ainda." }` |
| Falha de rede com a Groq | `{ "erro": "Falha na conexão com a IA: ..." }` |
| Groq retorna erro HTTP | `{ "erro": "A API retornou erro HTTP XXX." }` |
| JSON inesperado da IA | `{ "erro": "A IA retornou uma resposta inesperada. Tente novamente." }` |

O JavaScript em ambas as páginas exibe a mensagem de erro para o usuário com estilo visual adequado.

---

## Localização no Código — Resumo Rápido

```
php/
├── sugerir_tags.php         ← Endpoint IA 1 (tags)
├── recomendar_dates.php     ← Endpoint IA 2 (recomendação)
├── adicionar-lugar.php      ← Frontend IA 1 (botão + JS, linha ~350+)
└── perfil_em_comum.php      ← Frontend IA 2 (botão + cards + JS, linha ~520+)
```

Para localizar o botão de sugestão de tags em `adicionar-lugar.php`, busque por `Sugerir Tags`. Para localizar a seção de IA em `perfil_em_comum.php`, busque por `ai-hero-section`.
