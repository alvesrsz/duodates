# DuoDates — Contexto do Projeto

## O que é este projeto

**DuoDates** é um TCC (Trabalho de Conclusão de Curso) em PHP + MySQL. É uma plataforma web para casais e amigos planejarem dates e encontros, com sistema de locais, agendamentos, compatibilidade e sugestões personalizadas.

---

## Stack tecnológica

- **Backend:** PHP puro (sem framework)
- **Banco de dados:** MySQL / MariaDB via `mysqli`
- **Frontend:** HTML + CSS próprio + JavaScript puro
- **Email:** PHPMailer (arquivos em `src/`)
- **Mapas:** Geoapify API (chave em `php/config_api.php`)
- **IA:** Groq API — modelo `llama-3.3-70b-versatile`

---

## Estrutura de pastas

```
/
├── index.php              # Página inicial
├── conexao.php            # Conexão com o banco (PRODUÇÃO — InfinityFree)
├── php/                   # Todas as páginas e endpoints PHP
├── css/                   # Estilos por página
├── images/                # Imagens estáticas do projeto
├── javascript/            # script.js (JS global)
├── src/                   # PHPMailer (Exception.php, PHPMailer.php, SMTP.php)
├── uploads/               # Imagens enviadas pelos usuários (criado em runtime)
├── GUIA_MAC.md            # Guia de execução local no Mac (MAMP)
└── GUIA_WINDOWS.md        # Guia de execução local no Windows (Laragon)
```

---

## Banco de dados

### Produção (InfinityFree)
```php
$servername = "sql200.infinityfree.com";
$username   = "if0_38704863";
$password   = "duodates2025";
$database   = "if0_38704863_db_duodate";
```
O arquivo `conexao.php` na raiz aponta para produção por padrão.

### Local (para testes/apresentação)
Para rodar localmente, substituir `conexao.php` por:

**Windows (Laragon):**
```php
$servername = "localhost"; $username = "root"; $password = ""; $database = "db_duodate";
```

**Mac (MAMP):**
```php
$servername = "localhost"; $username = "root"; $password = "root"; $database = "db_duodate";
```

O banco deve ser chamado `db_duodate` localmente. Exportar do phpMyAdmin do InfinityFree e importar localmente.

---

## Executar localmente

- **Windows:** Laragon → `C:\laragon\www\downloadduodates` → `http://localhost/downloadduodates`
- **Mac:** MAMP (porta 8888) → `/Applications/MAMP/htdocs/downloadduodates` → `http://localhost:8888/downloadduodates`
- Ver `GUIA_WINDOWS.md` e `GUIA_MAC.md` para passo a passo completo.

---

## Funcionalidades principais

| Área | Arquivos |
|---|---|
| Login / Cadastro | `php/login.php`, `php/processar_cadastro.php`, `php/telacadastro.php` |
| Perfil e essência | `php/perfil.php`, `php/editar_perfil.php`, `php/mudar_essencia.php` |
| Conexões (casal/amigos) | `php/criar_conexao.php`, `php/aceitar_conexao.php`, `php/desvincular_conexao.php` |
| Locais / Lugares | `php/locais.php`, `php/local_detalhe.php`, `php/adicionar-lugar.php`, `php/salvar-lugar.php` |
| Admin | `php/admin.php`, `php/gerenciar_usuarios.php`, `php/gerenciar_locais.php`, `php/gerenciar_tags.php` |
| Empresa / Negócios | `php/cadastro-empresa.php`, `php/dashboard_empresa.php`, `php/meu_estabelecimento.php` |
| Agenda e agendamentos | `php/agenda.php`, `php/agendamentos.php`, `php/salvar_agendamento.php` |
| Favoritos | `php/favoritos.php`, `php/salvar_favorito.php`, `php/remover_favorito.php` |
| Questionário | `php/questionario.php`, `php/salvar_respostas_questionario.php` |
| Compatibilidade | `php/compatibilidade.php`, `php/perfil_em_comum.php` |
| IA de tags | `php/sugerir_tags.php` (endpoint), `php/adicionar-lugar.php` (botão + JS) |

---

## Funcionalidades de IA implementadas

### 1. Sugestão de Tags (cadastro de lugares)
Ao cadastrar um novo lugar (`php/adicionar-lugar.php`), existe um botão **"✨ Sugerir Tags com IA"** que analisa o título e descrição do local e marca automaticamente os checkboxes de tags adequadas.

### Como funciona (sugestão de tags)
1. JavaScript captura título e descrição do formulário
2. POST para `php/sugerir_tags.php`
3. PHP busca todas as tags do banco (`SELECT id_tag, nome_tag, categoria FROM tags`)
4. Monta prompt e chama **Groq API** com `llama-3.3-70b-versatile`
5. Groq retorna `{"ids": [1, 4, 7, ...]}` com os IDs das tags sugeridas
6. JavaScript marca os checkboxes correspondentes

### 2. Recomendação de Date com IA (Motor de Recomendação)
Em `php/perfil_em_comum.php` (página do casal), existe um botão **"✨ Gerar Recomendação com IA"** que analisa os dois perfis e retorna 3 cards personalizados.

**Endpoint:** `php/recomendar_dates.php` (POST: `partner_id`, `id_conexao`)

**Fluxo:**
1. Busca preferências de ambos no banco (Sistema 1: `pref_vibe`, `pref_food_ranking`, `pref_atividade`, `pref_comfort`)
2. Busca respostas do questionário de compatibilidade com nomes de tags (Sistema 2: `respostas_usuario` JOIN `tags`)
3. Busca eventos ao vivo no Ticketmaster (DF, 5 eventos)
4. Manda tudo para Groq e recebe JSON com 3 campos
5. JavaScript renderiza os 3 cards na página

**Cards retornados:**
- `analise` — quem é o casal, o que têm em comum (💞 Análise do Casal)
- `sugestao` — programa de date específico e detalhado (🗓️ Sugestão de Date)
- `evento` — evento do Ticketmaster que combina, ou sugestão de tipo de evento (🎟️ Evento em Destaque)

### Chave da API Groq
Hardcoded nos dois endpoints:
```
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
```

---

## APIs externas

| API | Uso | Onde fica a chave |
|---|---|---|
| Groq (llama-3.3-70b) | Sugestão de tags por IA | hardcoded em `php/sugerir_tags.php` |
| Geoapify | Mapas e localização | `php/config_api.php` (`GEOAPIFY_KEY`) |
| Ticketmaster | Busca de eventos | `php/buscar_ticketmaster.php` |
| PHPMailer + SMTP | Emails transacionais | configurado em cada arquivo que envia email |

---

## Tipos de conta

- **Usuário comum** — `$_SESSION['user_id']` + `$_SESSION['tipo_conta'] = 'comum'`
- **Admin** — `$_SESSION['tipo_conta'] = 'admin'`
- **Empresa logada** — `$_SESSION['tipo_conta'] = 'empresarial'` + `$_SESSION['id']`
- **Empresa em cadastro** — `$_SESSION['dados_cadastro_empresa']` (fluxo multi-etapa)

---

## Contexto do projeto (TCC)

- Projeto acadêmico, sendo apresentado localmente (professores usarão MAMP/Laragon)
- Os guias `GUIA_MAC.md` e `GUIA_WINDOWS.md` foram criados para facilitar a apresentação
- A funcionalidade de IA com Groq foi adicionada como diferencial tecnológico
- O banco em produção está no InfinityFree (hospedagem gratuita)
- O projeto precisa funcionar tanto online (InfinityFree) quanto offline/local (MAMP/Laragon)
