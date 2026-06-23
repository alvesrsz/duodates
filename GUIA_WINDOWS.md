# Guia de Execução Local — Windows

## Pré-requisitos
- Windows 10 ou 11
- Conexão com a internet ativa (o banco de dados e a IA dependem dela)

---

## Parte 1 — Instalar o Laragon

O Laragon é um ambiente de desenvolvimento local para Windows que instala Apache, PHP e MySQL com um clique.

1. Acesse **laragon.org/download** no navegador e baixe o instalador `.exe`
2. Execute o instalador e clique em **Avançar** em todas as telas (as configurações padrão já são suficientes)
3. Ao abrir o Laragon, clique em **Start All**
4. Os indicadores **Apache** e **MySQL** ficarão verdes quando o servidor estiver pronto

---

## Parte 2 — Exportar o Banco de Dados do InfinityFree

O projeto usa um banco de dados em produção (na nuvem). Precisamos exportar uma cópia dele para usar localmente.

1. Acesse o painel do InfinityFree no navegador e faça login
2. Clique em **phpMyAdmin** (no painel de banco de dados)
3. No painel esquerdo, clique no banco `if0_38704863_db_duodate`
4. No menu superior, clique em **Exportar**
5. Deixe o método **Rápido** e o formato **SQL** selecionados
6. Clique em **Executar** — um arquivo `.sql` será baixado

---

## Parte 3 — Criar o Banco de Dados Local no Laragon

O Laragon não usa `localhost/phpmyadmin` diretamente. Use uma das opções abaixo:

### Opção A — HeidiSQL (recomendado, já vem com o Laragon)

1. Clique com o botão direito no ícone do Laragon na **bandeja do sistema** (canto inferior direito da tela)
2. Clique em **Database** — o HeidiSQL abrirá conectado automaticamente
3. Na coluna esquerda, clique com botão direito em **Unnamed** → **Create new** → **Database**
4. Digite `db_duodate` e confirme
5. Clique duas vezes em `db_duodate` para selecioná-lo
6. No menu superior, clique em **File** → **Run SQL file...**
7. Selecione o arquivo `.sql` baixado e aguarde a importação

### Opção B — phpMyAdmin pelo painel do Laragon

1. Acesse `http://localhost` no navegador — abre o painel do Laragon
2. Clique no link **phpMyAdmin**
3. Clique em **Novo** (coluna esquerda) para criar um banco
4. Digite o nome `db_duodate` e clique em **Criar**
5. Com o banco selecionado, clique na aba **Importar**
6. Clique em **Escolher arquivo**, selecione o `.sql` baixado e clique em **Executar**

---

## Parte 4 — Configurar a Conexão Local

O arquivo `conexao.php` na raiz do projeto aponta para o servidor em produção por padrão. Precisamos trocar para as credenciais locais do Laragon.

1. Abra o arquivo `conexao.php` (na raiz do projeto) em qualquer editor de texto
2. Apague todo o conteúdo e cole o seguinte:

```php
<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "db_duodate";

$conn = new mysqli($servername, $username, $password, $database);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
?>
```

3. Salve o arquivo

> No Laragon para Windows, o usuário padrão do MySQL é `root` e a **senha é vazia** (diferente do Mac com MAMP, onde a senha é `root`).

> **Importante:** Lembre-se de desfazer essa alteração antes de enviar o projeto de volta ao InfinityFree.

---

## Parte 5 — Colocar o Projeto no Laragon

1. Copie a pasta `downloadduodates` para `C:\laragon\www\`
   - Atenção: copie a **pasta inteira**, não apenas os arquivos dentro dela
2. No navegador, acesse:
   ```
   http://localhost/downloadduodates
   ```
3. A página inicial do DuoDates deve aparecer com o layout completo

---

## Parte 6 — Testar a Funcionalidade de IA: Preencher Formulário com IA

### O que foi implementado

Ao cadastrar um novo lugar, o usuário pode digitar apenas o **nome do lugar** e a IA pesquisa, identifica e **preenche automaticamente todos os campos** do formulário: título, descrição, endereço, horário de funcionamento, link, categoria e tags.

A IA também detecta a localização do usuário (se o navegador permitir) para sugerir o endereço da unidade mais próxima.

### Arquivos envolvidos

| Arquivo | Função |
|---|---|
| `php/pesquisar_lugar.php` | Endpoint principal: recebe o nome do lugar, busca categorias e tags no banco, chama a IA e retorna JSON com todos os campos |
| `php/sugerir_tags.php` | Endpoint secundário: recebe título e descrição já preenchidos e sugere apenas as tags |
| `php/adicionar-lugar.php` | Formulário de cadastro — contém o banner de IA, os botões e o JavaScript |

### Passo a passo para testar

1. Faça login com uma conta **admin** em:
   ```
   http://localhost/downloadduodates/php/login.php
   ```
2. Acesse o formulário de cadastro de lugar:
   ```
   http://localhost/downloadduodates/php/adicionar-lugar.php
   ```
3. No topo do formulário, você verá o **banner de IA** com um campo de texto e dois botões
4. **Se o navegador pedir permissão de localização** — clique em **Permitir**. Isso faz com que a IA use a sua localização para sugerir endereços próximos
5. No campo de texto do banner, digite o nome de um lugar conhecido e clique em **🔍 Preencher com IA**:
   - Exemplos: `Outback Steakhouse`, `Jardim Botânico`, `Shopping Iguatemi`
6. Aguarde **2 a 4 segundos** — a IA está consultando informações sobre o lugar
7. Todos os campos do formulário serão preenchidos automaticamente (título, descrição, endereço, horário, link, categoria e tags)
8. Uma mensagem verde no banner confirmará quantas tags foram sugeridas
9. Revise e ajuste o que for necessário, adicione uma **imagem** (único campo não preenchível pela IA) e clique em **Cadastrar Local**

### Botão secundário — Sugerir apenas as Tags

O banner também possui o botão **✨ Sugerir apenas as Tags**, que é útil quando você já preencheu o Título e a Descrição manualmente. Ele analisa esses dois campos e sugere as tags adequadas, sem alterar os outros campos.

---

## Parte 7 — Testar a Recomendação de Date com IA

### O que foi implementado

Na página do perfil compartilhado do casal, existe um botão **"✨ Gerar Recomendação com IA"**. Ao clicar, a IA analisa os perfis dos dois usuários, busca eventos reais em Brasília via Ticketmaster e gera 3 cards personalizados:

| Card | Conteúdo |
|---|---|
| 💞 Análise do Casal | Quem são os dois juntos e o que têm em comum |
| 🗓️ Sugestão de Date | Um programa específico e detalhado para este casal |
| 🎟️ Evento em Destaque | Evento real do Ticketmaster que combina, ou sugestão de tipo de evento |

### Pré-requisitos para testar

> **Atenção:** é preciso ter **duas contas** conectadas entre si, e **ambas** precisam ter preenchido as preferências de essência e o questionário de compatibilidade.

1. Crie duas contas de usuário (ou use contas já existentes no banco importado)
2. Conecte as duas contas entre si usando o fluxo de conexão do DuoDates
3. **Com a conta A logada:** acesse `http://localhost/downloadduodates/php/mudar_essencia.php` e preencha as preferências
4. **Com a conta B logada:** faça o mesmo
5. **Com a conta A logada:** acesse `http://localhost/downloadduodates/php/meus_dates.php`, clique na conexão com B e responda ao questionário de compatibilidade
6. **Com a conta B logada:** faça o mesmo

### Passo a passo para testar

1. Faça login com a conta A em:
   ```
   http://localhost/downloadduodates/php/login.php
   ```
2. Acesse `http://localhost/downloadduodates/php/meus_dates.php`
3. Clique em **"Ver Perfil em Comum"** na conexão com a conta B
4. Na página que abrir, desça até a seção **"Recomendação Personalizada com IA"**
5. Clique em **"✨ Gerar Recomendação com IA"**
6. Aguarde **3 a 5 segundos** — os 3 cards aparecerão automaticamente
7. Clique em **"🔄 Gerar Nova Recomendação"** para ver uma resposta diferente

### Arquivos envolvidos

| Arquivo | Função |
|---|---|
| `php/recomendar_dates.php` | Endpoint: lê perfis do banco, busca eventos no Ticketmaster, chama a IA |
| `php/perfil_em_comum.php` | Página do casal — contém o botão e os cards |

---

## Solução de Problemas

**Página em branco ou mostrando código PHP**
→ O Laragon não está rodando. Abra o Laragon e clique em **Start All**.

**Erro "Conexão falhou" ou página de erro do banco**
→ Verifique se o arquivo `conexao.php` foi atualizado conforme a Parte 4. No Windows, a senha deve ser **vazia** (aspas sem nada dentro).

**Botão de IA não retorna nada ou trava**
→ Verifique se há conexão com a internet. A IA usa a API da Groq, que é externa.

**Erro ao importar o `.sql`**
→ Verifique se o banco `db_duodate` foi criado **antes** de tentar importar.

**O site abre mas as imagens não aparecem**
→ Normal na primeira execução local se as imagens foram enviadas pelo servidor em produção. As imagens cadastradas localmente funcionarão normalmente.

**Porta 80 já em uso (conflito com outro programa)**
→ Clique com o botão direito no ícone do Laragon na bandeja → **Preferences** → **General** → verifique a porta do Apache. Ou encerre o programa que está usando a porta (ex: Skype, IIS).
