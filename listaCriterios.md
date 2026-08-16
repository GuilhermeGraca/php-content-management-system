
# Guia de Discussão — Critérios Implementados

> **Contexto:** A professora vai analisar os critérios, perguntar *porquê* e *como funciona* cada um. Este documento prepara-te para responder com confiança.

---

## Requisitos Obrigatórios

---

### ✅ 1. Login

**Onde:** `login.php`

**Como funciona:**
- O utilizador submete o formulário com email e palavra-passe via POST.
- O servidor usa um **Prepared Statement** (`SELECT ... WHERE email = ?`) para procurar o utilizador na BD — isto protege contra **SQL Injection**.
- A palavra-passe **nunca é comparada diretamente** — usamos `password_verify()`, que compara o texto inserido com a **hash bcrypt** guardada na BD.
- Antes de iniciar sessão, verificamos se `conta_ativa == 1` (ou seja, se o email já foi confirmado).
- Se tudo estiver correto, criamos variáveis de sessão (`$_SESSION['id']`, `nome`, `perfil`) e redirecionamos com `header("Location: index.php")`.

**Se a professora perguntar:**
- *"Porquê password_verify e não comparar diretamente?"* — Porque as passwords são guardadas como hashes bcrypt. Cada hash é diferente mesmo para a mesma password (devido ao salt aleatório), por isso é impossível comparar com `==`. O `password_verify` sabe como extrair o salt e comparar corretamente.
- *"O que é um Prepared Statement?"* — É uma técnica onde separamos a estrutura SQL dos dados. O `?` é um marcador e o `bind_param` substitui-o pelo valor real. Isto impede que alguém injecte código SQL malicioso através dos campos do formulário.

---

### ✅ 2. Logout

**Onde:** `logout.php`

**Como funciona:**
- `session_unset()` limpa todas as variáveis da sessão (esvazia o `$_SESSION`).
- `session_destroy()` destrói o ficheiro físico da sessão no servidor.
- Redireciona o utilizador para `index.php`.

**Se a professora perguntar:**
- *"Porquê dois passos (unset + destroy)?"* — O `session_unset()` limpa os dados em memória e o `session_destroy()` apaga o ficheiro de sessão no disco do servidor. Juntos garantem que não ficam restos da sessão anterior.

---

### ✅ 3. Registo com autenticação de dois fatores (e-mail)

**Onde:** `registo.php` + `ativar_conta.php` + `credenciais.php`

**Como funciona (fluxo completo):**
1. O utilizador preenche nome, email e password no formulário.
2. A password é encriptada com `password_hash()` (bcrypt) antes de ir para a BD.
3. É gerado um **token aleatório** com `bin2hex(random_bytes(32))` — 64 caracteres hexadecimais.
4. O utilizador é inserido na BD com `conta_ativa = 0` (inativo).
5. Usamos a biblioteca **PHPMailer** para enviar um email via SMTP (Gmail) com um link de ativação que contém o token.
6. Quando o utilizador clica no link do email, o `ativar_conta.php` recebe o token via GET, procura-o na BD, e se for válido muda `conta_ativa` para `1` e apaga o token (coloca a `NULL` para que o link não possa ser reusado).

**É autenticação de dois fatores?** — Sim: o **1º fator** é a password (algo que o utilizador sabe) e o **2º fator** é o acesso ao email (algo que o utilizador possui). O utilizador só pode fazer login se tiver ambos.

**Se a professora perguntar:**
- *"Porquê PHPMailer e não a função mail() do PHP?"* — A função `mail()` nativa depende da configuração do servidor e muitas vezes vai para spam. O PHPMailer liga-se diretamente ao Gmail via SMTP com autenticação TLS, o que é mais fiável e seguro.
- *"O que acontece se alguém adivinhar o token?"* — O token tem 64 caracteres hexadecimais gerados com `random_bytes(32)` (criptograficamente seguro). A probabilidade de adivinhar é praticamente zero (2^256 combinações).
- *"E se alguém clicar no link duas vezes?"* — Na primeira vez, o token é limpo (`SET token_validacao = NULL`). Na segunda vez, o `SELECT` com `AND conta_ativa = 0` não encontra resultados e diz "Token inválido ou conta já ativa."

---

### ✅ 4. Validação "Não sou um robô" (reCAPTCHA)

**Onde:** `registo.php` + `credenciais.php`

**Como funciona:**
- No HTML, incluímos o script da Google (`recaptcha/api.js`) e um `<div>` com a `data-sitekey` (chave pública).
- A Google mostra o puzzle "Não sou um robô" ao utilizador.
- Quando o formulário é submetido, a Google insere um campo escondido (`g-recaptcha-response`) com um código secreto.
- No PHP, enviamos esse código + a nossa **chave secreta** (privada) para a API da Google (`siteverify`) via `file_get_contents()` com `stream_context_create()`.
- A Google responde em JSON com `success: true/false`.

**Se a professora perguntar:**
- *"Porquê duas chaves (site e secreta)?"* — A chave do site é pública (vai para o HTML). A chave secreta é privada (fica só no servidor). A Google valida que o pedido é legítimo porque só quem tem a chave secreta pode confirmar a resposta.
- *"Se a validação é feita pela Google, porque precisamos de verificar no servidor?"* — Porque qualquer pessoa pode forjar o formulário HTML e enviar um POST sem o reCAPTCHA. A verificação no servidor (com a chave secreta) é a única validação real.

---

### ✅ 5. Carregamento e visualização de conteúdos multimédia (áudio + imagem)

**Onde:** `artista_dashboard.php` (upload) + `index.php` (visualização)

**Tipos suportados:** Áudio (MP3, WAV) e Imagem (JPG, PNG) — **2 dos 3 tipos pedidos.**

**Como funciona o upload:**
- O ficheiro é enviado via `<form enctype="multipart/form-data">` e fica disponível na superglobal `$_FILES`.
- Validamos a extensão contra uma **whitelist** (`['mp3', 'wav', 'jpg', 'jpeg', 'png']`).
- O ficheiro recebe um nome aleatório com `uniqid()` para evitar colisões entre utilizadores.
- `move_uploaded_file()` move o ficheiro da pasta temporária do PHP para `uploads/`.
- O caminho, tipo MIME e metadados são guardados na BD.

**Como funciona a visualização:**
- Na `index.php`, os conteúdos são listados em cards com grelha responsiva (Bootstrap).
- Se for áudio, mostra um `<audio controls>` com player nativo.
- Se for imagem, mostra com `<img>`. Se houver capa associada, mostra a capa.

**Se a professora perguntar:**
- *"Porquê renomear o ficheiro com uniqid()?"* — Para evitar que o "musica.mp3" do User1 esmague o "musica.mp3" do User2. Cada ficheiro fica com um nome único baseado no timestamp.
- *"Porquê validar a extensão e não confiar no tipo MIME?"* — Porque o tipo MIME pode ser falsificado pelo browser. A extensão em whitelist é uma camada de segurança extra.

---

### ✅ 6. Conteúdos com diferentes visibilidades (público/privado)

**Onde:** `artista_dashboard.php` (definição + alteração) + `index.php` (filtragem)

**Como funciona:**
- Cada conteúdo na BD tem o campo `visibilidade` do tipo `ENUM('publico', 'privado')`.
- No momento do upload, o artista escolhe público ou privado.
- No `index.php`, se o utilizador **não está logado**, a query adiciona `AND c.visibilidade = 'publico'` — logo, conteúdos privados ficam invisíveis.
- Se está logado, vê tudo (público + privado).

**Se a professora perguntar:**
- *"Como garantem que a visibilidade é aplicada?"* — A filtragem é feita **no lado do servidor**, na query SQL. Não é feita com CSS/JavaScript (que seria fácil de contornar).

---

### ✅ 7. Acesso a conteúdos privados só para utilizadores autenticados

**Onde:** `index.php` (linhas 41-43)

**Como funciona:**
- É uma extensão do critério anterior. Se `$_SESSION['id']` não existe (utilizador não logado), o SQL adiciona `AND c.visibilidade = 'publico'`.
- Isto é enforced **no servidor** — não é possível contornar via browser.

---

### ✅ 8. Uso de serviços externos

**Serviços usados:**

| Serviço | O que faz | Onde |
|---|---|---|
| **OpenStreetMap + Leaflet** | Mapa interativo com marcadores de concertos futuros | `index.php` (modal do mapa) |
| **Nominatim (OpenStreetMap)** | Geocodificação — converte nome de local em coordenadas GPS | `artista_dashboard.php` (fetch assíncrono ao publicar concerto) |
| **Google reCAPTCHA v2** | Validação anti-bot no registo | `registo.php` |
| **Gmail SMTP** | Envio de emails de ativação de conta | `registo.php` (via PHPMailer) |
| **Google Fonts** | Tipografia customizada (Montserrat + Hanken Grotesk) | `style.css` |
| **Bootstrap 5.3** | Framework CSS responsiva | Todas as páginas |
| **Font Awesome** | Ícones vetoriais | `index.php`, `artista_dashboard.php`, `interesses.php` |

**Se a professora perguntar:**
- *"Porquê OpenStreetMap e não Google Maps?"* — É gratuito, sem necessidade de chave API e sem limites de utilização para projetos académicos.
- *"Como funciona a geocodificação?"* — Quando o artista escreve "Coliseu dos Recreios, Lisboa", o JavaScript faz um `fetch()` assíncrono à API Nominatim que converte o texto em latitude/longitude. Só depois disso é que o formulário é submetido.

---

### ✅ 9. Validação de dados do lado do cliente (browser)

**Onde:** Todos os formulários HTML

**Como funciona:**
- Usamos atributos HTML5 nativos: `required` (campos obrigatórios), `type="email"` (formato de email) e `type="password"`.
- O browser não deixa submeter o formulário se estes campos estiverem vazios ou mal formatados.
- No formulário de concertos, o JavaScript intercepta o submit (`e.preventDefault()`) e verifica se há coordenadas válidas antes de enviar.

**Se a professora perguntar:**
- *"A validação do cliente é suficiente?"* — **Não.** A validação do cliente é apenas UX (feedback rápido). Qualquer pessoa pode contornar o browser e enviar pedidos diretamente. A validação real está **no servidor**.

---

### ✅ 10. Validação de dados do lado do servidor

**Onde:** Todos os ficheiros PHP (login.php, registo.php, artista_dashboard.php, etc.)

**Como funciona:**
- `trim()` em todos os inputs para remover espaços.
- `empty()` para verificar campos obrigatórios.
- `intval()` para garantir que IDs são inteiros.
- `floatval()` para coordenadas GPS.
- Whitelist de extensões de ficheiro (`in_array($extensao, $permitidos)`).
- Prepared Statements com `bind_param()` em **todas** as queries com dados do utilizador.

---

### ✅ 11. Regras de validação iguais no servidor e no cliente

**Exemplo concreto:**
- **Cliente:** `<input type="email" required>` — o browser exige um email válido e que o campo não esteja vazio.
- **Servidor:** `if (empty($email) || empty($palavra_passe))` — o PHP também verifica se estão vazios.

Ambos os lados verificam as **mesmas regras**: campos obrigatórios preenchidos, formato de email, etc. Se alguém contornar o browser, o servidor apanha os mesmos erros.

---

## Requisitos Opcionais

---

### ✅ 12. Uso de AJAX para melhorar a experiência do utilizador

**Onde:** `artista_dashboard.php` (geocodificação Nominatim)

**Como funciona:**
- Quando o artista publica um concerto e não tem coordenadas GPS, o JavaScript intercepta o `submit` com `e.preventDefault()`.
- Faz um `fetch()` assíncrono à API Nominatim para converter o nome do local em latitude/longitude.
- O pedido é **assíncrono** — o browser não bloqueia, o botão mostra "A localizar..." e a página não recarrega.
- Quando as coordenadas chegam (via `.then()`), são preenchidas nos campos hidden e o formulário é submetido programaticamente.
- Em caso de erro (`.catch()`), mostra uma mensagem ao utilizador.

**Se a professora perguntar:**
- *"O fetch é assíncrono?"* — Sim. A Fetch API retorna uma `Promise`, que é por natureza assíncrona. O browser continua responsivo enquanto espera pela resposta da API.
- *"Porquê não usar XMLHttpRequest?"* — O `fetch()` é a API moderna que substitui o `XMLHttpRequest`. É mais limpa, baseada em Promises e mais legível.
- *"Porquê que as outras ações (formulários) não usam AJAX?"* — A maioria das ações (login, upload, etc.) envolve mudança de página completa, logo o POST tradicional com redirect é adequado. O AJAX foi aplicado onde faz sentido: uma ação pequena (geocodificar) que não justifica recarregar toda a página.

---

### ✅ 13. A visibilidade dos conteúdos pode ser alterada

**Onde:** `artista_dashboard.php` (secção de alteração de visibilidade)

**Como funciona:**
- Na tabela "Os Meus Uploads", cada linha tem um dropdown com "público" / "privado" e um botão "Alterar".
- O PHP faz `UPDATE conteudos SET visibilidade = ? WHERE id = ? AND id_utilizador = ?` — a cláusula `AND id_utilizador = ?` garante que o artista só pode alterar os **seus próprios** conteúdos (segurança).

---

### ✅ 14. Gestão de utilizadores

**Onde:** `admin_dashboard.php`

**Como funciona:**
- Apenas perfis `administrador` podem aceder a esta página (proteção no topo com `$_SESSION['perfil']`).
- O admin vê uma tabela com todos os utilizadores (ID, nome, email, estado da conta, perfil atual).
- Pode alterar o perfil de qualquer utilizador entre: **Utilizador (Fã)**, **Simpatizante (Artista)** e **Administrador**.
- O admin **não pode alterar o seu próprio perfil** para não se trancafiar acidentalmente fora do painel.
- Pode também **adicionar novos distritos** (categorias) à plataforma.

**Se a professora perguntar:**
- *"O que são os 3 níveis de perfil?"* — **Utilizador** (fã, só pode ver e subscrever), **Simpatizante** (artista, pode fazer uploads e anunciar concertos), **Administrador** (pode gerir utilizadores e categorias).

---

### ✅ 15. Uso de CSS

**Onde:** `Style/style.css` (479 linhas)

**O que implementámos:**
- **CSS Variables** (custom properties) para paleta de cores, tipografia e componentes reutilizáveis — facilita manutenção.
- **Google Fonts** customizadas: Montserrat (títulos) e Hanken Grotesk (corpo).
- **Glassmorphism** nos painéis: `backdrop-filter: blur()` + fundo semi-transparente + border subtil + sombras interiores.
- **Cards com hover animado**: `transform: translateY(-4px)` + `box-shadow` no hover.
- **Custom scrollbar** temática (escura com acentuação laranja).
- **Tema escuro completo** com paleta coerente (laranja #FF5500, ciano #00D1FF, verde #00E676).
- Sobrescrição de estilos nativos: player de áudio, selects, file inputs, popups do Leaflet.

**Se a professora perguntar:**
- *"Porquê usar variáveis CSS?"* — Para centralizar a paleta de cores e tipografia. Se quisermos mudar a cor principal de laranja para azul, basta alterar uma linha em `:root`. Todos os componentes que usam `var(--color-primary)` atualizam automaticamente.

---

### ✅ 16. Interface intuitiva e apelativa

**Evidências:**
- Design **dark mode** completo com contrastes estudados.
- Efeitos visuais premium: **glassmorphism**, gradientes nos cards, transições suaves (0.2s-0.3s).
- Feedback visual em todos os botões (hover states com mudança de cor).
- Ícones consistentes (Font Awesome) para comunicação visual rápida.
- Mensagens de feedback contextuais (alerts coloridos para sucesso/erro).
- Barra de navegação adaptativa com informações do utilizador.

---

### ✅ 17. Interface ajusta-se ao tamanho do ecrã (Responsive)

**Como funciona:**
- **Bootstrap 5** com grid system (`col-md-6 col-lg-4`) para layout responsivo automático.
- `<meta name="viewport" content="width=device-width, initial-scale=1.0">` em todas as páginas.
- Navbar com **hamburger menu** para mobile (`navbar-toggler` + `collapse`).
- Cards e tabelas com classes responsivas (`table-responsive`).
- Formulários com `w-100` e `max-width` para se adaptarem a ecrãs pequenos.

**Se a professora perguntar:**
- *"Que meta tag garante o responsive?"* — A `viewport` com `width=device-width` diz ao browser para usar a largura real do dispositivo em vez de simular um ecrã de desktop.

---

### ✅ 18. Notificação de novos conteúdos

**Onde:** `artista_dashboard.php` (geração) + `index.php` / `artista_dashboard.php` (exibição) + `ler_notificacoes.php` (marcação como lidas)

**Como funciona:**
1. O artista publica uma música, capa ou concerto.
2. A função `gerar_notificacoes()` faz um `INSERT ... SELECT DISTINCT` que encontra todos os utilizadores que subscrevem o distrito ou estilo desse conteúdo (excluindo o próprio autor).
3. Cada utilizador subscrito recebe uma entrada na tabela `notificacoes` com a mensagem e um link.
4. Quando o utilizador carrega qualquer página, o PHP consulta as notificações não lidas (`lida = 0`) e mostra-as num **dropdown de sininho** 🔔 na navbar.
5. O botão "Marcar todas como lidas" envia um POST para `ler_notificacoes.php` que faz `UPDATE notificacoes SET lida = 1`.

**Ligação com os interesses:** As notificações estão ligadas ao sistema de subscrições (`interesses.php`). O utilizador escolhe distritos e estilos, e só recebe notificações dos conteúdos correspondentes.

**Se a professora perguntar:**
- *"Porquê DISTINCT no INSERT...SELECT?"* — Para evitar que o mesmo utilizador receba notificações duplicadas se subscrever tanto o distrito como o estilo de um conteúdo.
- *"As notificações são em tempo real?"* — Não, são carregadas quando a página é renderizada pelo PHP. Para tempo real seria necessário WebSockets ou polling.

---

### ✅ 19. Conteúdos em modo de lote/batch (ZIP + XML)

**Onde:** `artista_dashboard.php` (secção de upload em lote)

**Como funciona:**
1. O artista cria um ficheiro ZIP contendo: os ficheiros multimédia (MP3, WAV, JPG, PNG) + um ficheiro XML com metadados de cada faixa.
2. O PHP recebe o ZIP, usa a classe nativa `ZipArchive` para extrair tudo para uma pasta temporária (`uploads/temp_XXXX/`).
3. `scandir()` procura o ficheiro XML dentro dos ficheiros extraídos.
4. `simplexml_load_file()` faz o parsing do XML e converte-o num objeto PHP.
5. Um `foreach` percorre cada `<faixa>` do XML, valida os ficheiros, move-os para `uploads/` e insere-os na BD.
6. Se o estilo mencionado no XML não existir na BD, o sistema **cria-o automaticamente** (`INSERT INTO estilos_musicais`).
7. No final, a pasta temporária é limpa (garbage collection) com `glob()` + `unlink()` + `rmdir()`.

**Estrutura do XML esperada:**
```xml
<album>
  <faixa>
    <ficheiro>musica.mp3</ficheiro>
    <capa>capa.jpg</capa>
    <titulo>Noite no Bairro</titulo>
    <descricao>Beat trap com fado urbano</descricao>
    <estilo>Trap</estilo>
    <visibilidade>publico</visibilidade>
  </faixa>
</album>
```

**Se a professora perguntar:**
- *"Porquê XML e não JSON?"* — O XML é o formato pedido para metadados estruturados na UC. É mais adequado para descrever documentos com hierarquia e é o padrão em metadados multimédia (ex. Dublin Core).
- *"E se um ficheiro mencionado no XML não existir no ZIP?"* — O sistema deteta isso (`file_exists()`) e adiciona um erro ao relatório, mas continua a processar as restantes faixas (processamento parcial tolerante a erros).

---

### ✅ 20. Pesquisa de conteúdos

**Onde:** `index.php` (barra de pesquisa na navbar + filtros)

**Como funciona:**
- A barra de pesquisa envia o termo via GET (`?q=rock`).
- O PHP usa `LIKE %termo%` no SQL para encontrar correspondências no **título**, **descrição** e **nome do artista** simultaneamente.
- Existem também filtros por **Distrito**, **Estilo Musical** e **Tipo de Conteúdo** (áudio ou imagem).
- Os filtros são combinados dinamicamente: a query começa com `WHERE 1=1` e adiciona `AND ...` conforme os filtros ativos.
- Quando há filtros ativos, aparecem **badges** indicadores com opção de "Limpar tudo".

**Se a professora perguntar:**
- *"O que é o WHERE 1=1?"* — É um truque técnico. Como os filtros são opcionais, precisamos de um `WHERE` base que seja sempre verdadeiro para poder concatenar `AND ...` sem nos preocuparmos se é o primeiro ou segundo filtro.
- *"O LIKE é eficiente?"* — Para volumes pequenos é adequado. Para milhões de registos, usaríamos Full Text Search do MySQL.

---

### ✅ 21. Dados dos templates vêm dos conteúdos carregados

**Implementação:**
- Todos os dados exibidos no Bootstrap (cards, tabelas, badges, opções de filtro) vêm diretamente da **base de dados**.
- Não há dados estáticos/hardcoded no HTML — os distritos, estilos, conteúdos, notificações e concertos são todos dinâmicos.
- Até os marcadores do mapa são gerados via `json_encode()` a partir dos dados da BD.

---

### ✅ 22. Outras funcionalidades relevantes

| Funcionalidade | Descrição |
|---|---|
| **Sistema de Perfis (3 níveis)** | Utilizador (fã), Simpatizante (artista), Administrador — com diferentes permissões |
| **Gestão de Interesses/Subscrições** | Utilizador escolhe distritos e estilos que quer acompanhar (com BD transacional) |
| **Agenda de Concertos com Mapa** | Artistas publicam concertos com geocodificação automática, visíveis num mapa |
| **Criação de Estilos Musicais** | Artistas podem adicionar novos géneros à plataforma |
| **Gestão de Categorias (Admin)** | O administrador pode adicionar novos distritos |
| **Eliminação de Concertos** | Artista pode remover concertos da agenda |
| **Imagem de Capa opcional** | Upload de capa associada a cada faixa musical |
| **Script SQL de instalação** | `database/base_dados.sql` cria toda a estrutura da BD automaticamente |
| **Ficheiro XML de exemplo** | `exemplo_metadados.xml` documenta o formato esperado para uploads em lote |
| **Segurança geral** | Prepared Statements em tudo, htmlspecialchars() na saída, password hashing, proteção de sessão |

---

## Critérios NÃO implementados (se a professora perguntar)

| Critério | Estado | Justificação |
|---|---|---|
| Configurações de BD/Email editáveis | ❌ | As credenciais estão em `credenciais.php` (constantes). Não há painel para as alterar em runtime. |
| Instalação em servidor vazio | ❌ parcial | O `base_dados.sql` cria a BD automaticamente, mas não há installer web completo. |
| Interação com redes sociais | ❌ | Não implementado. |
| Suporte para múltiplos idiomas | ❌ | A plataforma é toda em Português. |
