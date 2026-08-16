<?php
/**
 * Registo de Novos Utilizadores
 *
 * Processa o formulário de registo, aplica medidas de segurança (reCAPTCHA v2 e Password Hash),
 * insere o utilizador na BD com o estado inativo e envia um mail com 
 * um token de ativação para confirmar a validade do endereço de e-mail
 */

// Importa os ficheiros com os dados de acesso à BD e às APIs/SMTP
require 'conexao.php';
require 'credenciais.php';

// Importa as classes da biblioteca PHPMailer para envio de e-mails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Inclui os ficheiros originais do PHPMailer necessários para criar o objeto do e-mail
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mensagem = "";

$chave_secreta_recaptcha = RECAPTCHA_CHAVE_SECRETA;

/**
 * A superglobal $_SERVER['REQUEST_METHOD'] verifica o método HTTP
 * Se for POST, significa que o utilizador clicou no botão para enviar o formulário
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // trim corta espaços em branco no início ou no fim do texto digitado
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $palavra_passe = trim($_POST['palavra_passe']);

    // Captura o código secreto que a Google introduziu ocultamente no formulário após o teste do utilizador
    $resposta_recaptcha = $_POST['g-recaptcha-response'];

    if (empty($nome) || empty($email) || empty($palavra_passe)) {
        $mensagem = "Por favor, preencha todos os campos.";
    }
    // Verifica se o utilizador resolveu o puzzle do reCAPTCHA
    elseif (empty($resposta_recaptcha)) {
        $mensagem = "Por favor, confirme que não é um robô.";
    } else {
        /**
         * Para validar o reCAPTCHA, o nosso servidor precisa de enviar um pedido HTTP à Google
         * com a nossa chave secreta das credenciais e a resposta do utilizador
         */
        $url = 'https://www.google.com/recaptcha/api/siteverify';

        // Criamos um array associativo (estrutura chave-valor do PHP) com os dados
        $dados = [
            'secret' => $chave_secreta_recaptcha,
            'response' => $resposta_recaptcha
        ];

        // Configuramos os cabeçalhos e o método POST para o pedido externo à API da Google
        // A função http_build_query transforma o array associativo q criamos no formato padronizado de URL para envio
        $opcoes = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($dados)
            ]
        ];

        // Criamos o contexto e efetuamos o pedido efetivo à API externa
        // A função file_get_contents lê a resposta da Google como se fosse um ficheiro de texto
        $contexto = stream_context_create($opcoes);
        $resultado_google = file_get_contents($url, false, $contexto);

        // A Google responde em formato JSON, a função json_decode converte isso para um objeto do PHP
        $resposta_json = json_decode($resultado_google);

        // O objeto JSON tem uma propriedade 'success' que é um  bool
        if (!$resposta_json->success) {
            $mensagem = "Falha na validação do reCAPTCHA. Tente novamente.";
        } else {
            /**
             * Encriptamos a palavra-passe com um algoritmo de encriptação PHP (BCRYPT) - passa para um hash
             * A função password_hash gera sempre uma hash diferente, mesmo para a mesma palavra-passe
             */
            $passe_encriptada = password_hash($palavra_passe, PASSWORD_DEFAULT);

            // Gera um token e converte-o para formato hexadecimal
            $token_validacao = bin2hex(random_bytes(32));

            // Prepara a query de inserção (o estado conta_ativa começa a 0 e o perfil é sempre 'utilizador')
            $sql = "INSERT INTO utilizadores (nome, email, palavra_passe, perfil, conta_ativa, token_validacao) VALUES (?, ?, ?, 'utilizador', 0, ?)";

            // Os Prepared Statements protegem a BD de aSQL Injection
            $comando = $ligacao->prepare($sql);

            // Ligamos as variáveis aos pontos de interrogação - "ssss" significa 4 variáveis do tipo string
            $comando->bind_param("ssss", $nome, $email, $passe_encriptada, $token_validacao);

            // execute() corre a query e devolve true se conseguir inserir a linha
            if ($comando->execute()) {

                // Instanciamos a classe PHPMailer para tratar do envio do e-mail de ativação
                $mail = new PHPMailer(true);
                try {
                    // Informamos o PHPMailer para se ligar a um servidor SMTP com autenticação(das credenciais) e encriptação TLS
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = SMTP_PORT;

                    // Definimos quem envia e quem recebe o e-mail
                    $mail->setFrom(SMTP_USER, 'SoundCloud PT');
                    $mail->addAddress($email, $nome);

                    // A supervariavel $_SERVER['HTTPS'] ajuda a descobrir se o site usa certificado de segurança HTTPS - com TLS
                    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

                    // Apanhamos o domínio base e a pasta atual para montar o link dinamicamente independentemente do host
                    $dominio = $_SERVER['HTTP_HOST'];
                    $pasta_atual = dirname($_SERVER['PHP_SELF']); //PHP_SELF é o nome do ficheiro atual relativo à document root
                    $link_ativacao = $protocolo . "://" . $dominio . $pasta_atual . "/ativar_conta.php?token=" . $token_validacao;

                    // Configuramos o PHPMailer para aceitar tags de formatação HTML no corpo do e-mail
                    $mail->isHTML(true);
                    $mail->Subject = 'Ativa a tua conta - SoundCloud Portugues';
                    $mail->Body = "Olá <b>$nome</b>,<br><br>Bem-vindo ao SoundCloud Português! Para começares a usar a plataforma, por favor ativa a tua conta clicando no link abaixo:<br><br><a href='$link_ativacao'>Ativar Minha Conta</a><br><br>Se não te registaste, ignora este e-mail.";

                    $mail->send();
                    $mensagem = "Conta criada com sucesso! Por favor, verifica o teu e-mail para ativar a conta.";
                } catch (Exception $e) {
                    // O catch apanha erros de rede ou de autenticação no momento de enviar o e-mail sem rebentar o programa
                    $mensagem = "Conta criada, mas ocorreu um erro no envio do e-mail de ativação";
                }
            } else {
                $mensagem = "Erro ao criar conta. O e-mail já poderá estar registado.";
            }
            $comando->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="Style/style.css">

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body class="bg-soundwave d-flex justify-content-center align-items-center min-vh-100 py-4">

    <div class="glass-box text-center">
        <h2>Criar Nova Conta</h2>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-warning text-center py-2" role="alert" style="font-size: 0.9rem;">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <form action="registo.php" method="POST" class="text-start mt-4">
            <div class="input-icon-group">
                <label for="nome">Nome ou Nome da Banda</label>
                <i class="bi bi-person"></i>
                <input type="text" class="form-control" id="nome" name="nome" placeholder="ex: João Silva" required>
            </div>

            <div class="input-icon-group">
                <label for="email">E-mail</label>
                <i class="bi bi-envelope"></i>
                <input type="email" class="form-control" id="email" name="email" placeholder="exemplo@email.com"
                    required>
            </div>

            <div class="input-icon-group">
                <label for="palavra_passe">Palavra-passe</label>
                <i class="bi bi-lock"></i>
                <input type="password" class="form-control" id="palavra_passe" name="palavra_passe"
                    placeholder="••••••••" required>
            </div>

            <!-- Manter o container do reCAPTCHA o mais neutro possível para não desformatar -->
            <div class="mb-3 d-flex justify-content-center" style="opacity: 0.9;">
                <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_CHAVE_SITE; ?>"></div>
            </div>

            <button type="submit" class="btn btn-laranja w-100 fw-bold mt-2">Registar</button>
        </form>

        <div class="text-center mt-4 pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
            <a href="login.php" class="text-decoration-none text-muted d-block mb-2"
                style="font-size: 0.9rem; transition: 0.3s;">Já tens conta? <span class="text-white">Inicia
                    Sessão</span></a>
            <a href="index.php" class="text-decoration-none text-muted d-block"
                style="font-size: 0.85rem; transition: 0.3s;">Voltar ao início</a>
        </div>
    </div>
</body>

</html>