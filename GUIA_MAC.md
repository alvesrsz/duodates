# Guia de Execução Local — Mac

> Este guia foi escrito para quem nunca usou MAMP antes. Cada passo inclui o que fazer **e por quê**, para facilitar a apresentação do projeto.

## Pré-requisitos
- macOS (qualquer versão dos últimos 5 anos)
- Conexão com a internet ativa (o banco de dados e a IA dependem dela)

---

## Parte 1 — Instalar o MAMP

O MAMP é um programa que transforma o seu Mac em um servidor local, permitindo rodar aplicações PHP sem precisar de hospedagem externa.

1. Acesse **mamp.info** no navegador
2. Clique em **Download** — baixe a versão gratuita **MAMP** (não o MAMP PRO)
3. Quando o download terminar, abra o arquivo `.pkg` na pasta Downloads
4. Siga o instalador clicando em **Continuar** e depois em **Instalar**
   - Se o Mac pedir a senha do usuário, digite e confirme
5. Ao terminar, abra o **MAMP** em: `Finder → Aplicativos → MAMP → MAMP` (o ícone com o elefante)
6. Na janela que abrir, clique no botão **Start Servers** (canto superior direito)
7. Aguarde alguns segundos — os indicadores **Apache** e **MySQL** ficarão **verdes**

> Se aparecer um alerta de segurança dizendo "aplicativo de desenvolvedor não identificado", vá em **Preferências do Sistema → Segurança e Privacidade → Geral** e clique em **"Abrir mesmo assim"**.

> Por padrão, o MAMP usa a porta **8888**. Todos os endereços neste guia já consideram isso. Se a porta estiver ocupada, veja a seção de Solução de Problemas no final.

---

## Parte 2 — Exportar o Banco de Dados do InfinityFree

O projeto usa um banco de dados em produção (na nuvem). Precisamos exportar uma cópia dele para usar localmente.

1. Acesse o painel do InfinityFree no navegador e faça login
2. Clique em **phpMyAdmin** (pode estar em "Manage" ou no painel de banco de dados)
3. No painel esquerdo, clique no banco chamado `if0_38704863_db_duodate`
4. No menu superior da página, clique em **Exportar**
5. Deixe o método como **Rápido** e o formato como **SQL** (já vêm selecionados por padrão)
6. Clique em **Executar**
7. Um arquivo `.sql` será baixado para a sua pasta Downloads — guarde-o, você vai precisar dele na próxima parte

---

## Parte 3 — Criar o Banco de Dados Local no MAMP

Agora vamos criar um banco de dados vazio no servidor local e importar os dados que acabamos de exportar.

1. Com o MAMP **rodando** (indicadores verdes), abra o navegador e acesse:
   ```
   http://localhost:8888/phpmyadmin
   ```
2. Na página do phpMyAdmin que abrir, localize o menu **"Novo"** na coluna esquerda e clique nele
3. No campo **"Nome do banco de dados"**, digite exatamente:
   ```
   db_duodate
   ```
4. Clique no botão **Criar**
5. O banco `db_duodate` aparecerá na coluna esquerda — clique nele para selecioná-lo
6. No menu superior, clique na aba **Importar**
7. Clique em **Escolher arquivo** e selecione o arquivo `.sql` que você baixou na Parte 2
8. Clique em **Executar** (ou "Go") no final da página
9. Aguarde — quando terminar, aparecerá uma mensagem de sucesso em verde. As tabelas do projeto agora aparecerão na coluna esquerda dentro de `db_duodate`

---

## Parte 4 — Configurar a Conexão Local

O arquivo `conexao.php` na raiz do projeto contém as credenciais do banco de dados. Ele aponta para o servidor em produção por padrão — precisamos trocar para as credenciais locais do MAMP.

1. Navegue até a pasta do projeto `downloadduodates` e abra o arquivo `conexao.php` em qualquer editor de texto (TextEdit, VS Code, etc.)
2. Apague todo o conteúdo do arquivo e cole o seguinte:

```php
<?php
$servername = "localhost";
$username   = "root";
$password   = "root";
$database   = "db_duodate";

$conn = new mysqli($servername, $username, $password, $database);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
?>
```

3. Salve o arquivo

> **Por que a senha é "root"?** No MAMP para Mac, o usuário padrão do MySQL é `root` e a senha padrão também é `root`. Isso é diferente do Windows (Laragon), onde a senha é vazia.

> **Importante:** Lembre-se de desfazer essa alteração antes de enviar o projeto de volta ao servidor InfinityFree, ou o site online vai parar de funcionar.

---

## Parte 5 — Colocar o Projeto no MAMP

O MAMP serve arquivos a partir de uma pasta específica chamada `htdocs`. O projeto precisa estar dentro dela para funcionar.

1. Abra o **Finder**
2. No menu superior, clique em **Ir → Ir para a pasta...**
3. Digite o caminho abaixo e pressione Enter:
   ```
   /Applications/MAMP/htdocs/
   ```
4. Copie a pasta `downloadduodates` (o projeto inteiro) para dentro de `htdocs`
   - Atenção: copie a **pasta**, não apenas os arquivos dentro dela
5. Abra o navegador e acesse:
   ```
   http://localhost:8888/downloadduodates
   ```
6. A página inicial do DuoDates deve aparecer com o layout completo

> Se aparecer uma página em branco ou um erro, verifique se o MAMP está rodando (indicadores verdes) e se o `conexao.php` foi atualizado conforme a Parte 4.

---

## Parte 6 — Testar a Funcionalidade de IA: Preencher Formulário com IA

### O que foi implementado

Ao cadastrar um novo lugar, o usuário pode digitar apenas o **nome do lugar** e a IA pesquisa, identifica e **preenche automaticamente todos os campos** do formulário: título, descrição, endereço, horário de funcionamento, link, categoria e tags.

A IA também detecta a localização do usuário (se o navegador permitir) para sugerir o endereço da unidade mais próxima — por exemplo, ao digitar "Outback", ela busca a unidade em Brasília e não em São Paulo.

### Arquivos envolvidos

| Arquivo | Função |
|---|---|
| `php/pesquisar_lugar.php` | Endpoint principal: recebe o nome do lugar, busca categorias e tags no banco, chama a IA e retorna JSON com todos os campos |
| `php/sugerir_tags.php` | Endpoint secundário: recebe título e descrição já preenchidos e sugere apenas as tags |
| `php/adicionar-lugar.php` | Formulário de cadastro — contém o banner de IA, os botões e o JavaScript |

### Passo a passo para testar

1. Faça login com uma conta **admin** em:
   ```
   http://localhost:8888/downloadduodates/php/login.php
   ```
2. Acesse o formulário de cadastro de lugar:
   ```
   http://localhost:8888/downloadduodates/php/adicionar-lugar.php
   ```
3. No topo do formulário, você verá o **banner de IA** com um campo de texto e dois botões
4. **Se o navegador pedir permissão de localização** — clique em **Permitir**. Isso faz com que a IA use a sua localização para sugerir endereços próximos
5. No campo de texto do banner, digite o nome de um lugar conhecido (exemplos abaixo) e clique em **🔍 Preencher com IA**:
   - `Outback Steakhouse`
   - `Jardim Botânico de Brasília`
   - `Shopping Iguatemi`
   - `Casa de Shows`
6. Aguarde **2 a 4 segundos** — a IA está consultando informações sobre o lugar
7. Ao terminar, **todos os campos do formulário serão preenchidos automaticamente**:
   - Título, Descrição, Endereço, Horário de Funcionamento, Link e Texto do Botão
   - A **Categoria** será selecionada automaticamente no dropdown
   - As **Tags** serão marcadas nos grupos correspondentes
8. Uma mensagem verde no banner confirmará quantas tags foram sugeridas
9. Revise os campos preenchidos e ajuste o que for necessário
10. Envie uma **imagem** do lugar (único campo que não pode ser preenchido pela IA)
11. Clique em **Cadastrar Local**

### Botão secundário — Sugerir apenas as Tags

O banner também possui o botão **✨ Sugerir apenas as Tags**, que funciona de forma diferente:
- É útil quando você **já preencheu** o Título e a Descrição manualmente
- Ele analisa esses dois campos e sugere as tags mais adequadas, sem alterar os outros campos

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
3. **Com a conta A logada:** acesse `http://localhost:8888/downloadduodates/php/mudar_essencia.php` e preencha todas as preferências
4. **Com a conta B logada:** faça o mesmo
5. **Com a conta A logada:** acesse `http://localhost:8888/downloadduodates/php/meus_dates.php`, clique na conexão com B e responda ao questionário de compatibilidade
6. **Com a conta B logada:** faça o mesmo

### Passo a passo para testar

1. Faça login com a conta A em:
   ```
   http://localhost:8888/downloadduodates/php/login.php
   ```
2. Acesse `http://localhost:8888/downloadduodates/php/meus_dates.php`
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
→ O MAMP não está rodando. Abra o MAMP e clique em **Start Servers**. Aguarde os indicadores ficarem verdes.

**Erro "Conexão falhou" ou página de erro do banco**
→ Verifique se o arquivo `conexao.php` foi atualizado conforme a Parte 4. A senha deve ser `root` (não vazia).

**Botão de IA não retorna nada ou trava**
→ Verifique se há conexão com a internet. A IA usa a API da Groq, que é externa.

**Erro ao importar o `.sql`**
→ Verifique se o banco `db_duodate` foi criado **antes** de tentar importar. Se o banco não existir, a importação falha.

**O site abre mas as imagens não aparecem**
→ Normal na primeira execução local se as imagens foram enviadas pelo servidor em produção. As imagens cadastradas localmente funcionarão normalmente.

**Porta 8888 não funciona**
→ Abra o MAMP → clique em **Preferences** → aba **Ports** → verifique se **Apache Port** está como `8888`. Se necessário, clique em **Set Web & MySQL ports to 8888**.

**Alerta de segurança ao abrir o MAMP**
→ Vá em **Preferências do Sistema → Segurança e Privacidade → Geral** e clique em **"Abrir mesmo assim"**.
