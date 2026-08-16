<?php
/**
 * Autenticação de Utilizadores (Login)
 *
 * Recebe as credenciais do utilizador, verifica a sua existência na base de dados e 
 * valida a palavra-passe com recurso a técnicas de encriptação segura
 * Se tudo estiver correto, inicia uma sessão e redireciona o utilizador
 */

// Inicia ou retoma uma sessão existente para podermos guardar os dados do utilizador logado através da superglobal $_SESSION
session_start();

// Importa a ligação à base de dados para podermos executar comandos SQL
require 'conexao.php';

$mensagem = "";

/**
 * A superglobal $_SERVER['REQUEST_METHOD'] indica qual o método de pedido HTTP foi utilizado
 * Validar se o método é POST garante que os dados foram submetidos através do formulário
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // trim remove espaços em branco acidentais no início e fim do texto inserido
    $email = trim($_POST['email']);
    $palavra_passe = trim($_POST['palavra_passe']);

    if (empty($email) || empty($palavra_passe)) {
        $mensagem = "Por favor, preencha todos os campos.";
    } else {
        // O símbolo '?' representa um parametro, é um marcador de posição que protege contra injeções de SQL - prepared statement
        $sql = "SELECT id, nome, palavra_passe, perfil, conta_ativa FROM utilizadores WHERE email = ?";

        // A função prepare prepara o comando SQL para ser executado de forma segura no motor de base de dados
        $comando = $ligacao->prepare($sql);

        // A função bind_param substitui o '?' pelo valor real do email, "s" indica q é uma string
        $comando->bind_param("s", $email);
        $comando->execute();

        // Armazena o resultado da execução numa variável para extrair os dados
        $resultado = $comando->get_result();

        // O atributo num_rows diz quantas linhas foram encontradas
        // Se for > 0, significa q o email existe na base de dados
        if ($resultado->num_rows > 0) {
            // A função fetch_assoc converte a linha da BD num array associativo 
            $utilizador = $resultado->fetch_assoc();

            /**
             * A função password_verify compara a palavra-passe digitada em texto
             * com a hash (encriptado) armazenada na base de dados
             * É a maneira mais segura de validar passwords em PHP
             */
            if (password_verify($palavra_passe, $utilizador['palavra_passe'])) {

                // Antes de permitir o login, validamos se a conta foi previamente confirmada por email
                if ($utilizador['conta_ativa'] == 1) {

                    // Login com sucesso, guarda os dados essenciais na sessão
                    $_SESSION['id'] = $utilizador['id'];
                    $_SESSION['nome'] = $utilizador['nome'];
                    $_SESSION['perfil'] = $utilizador['perfil'];

                    // A função header diz ao navegador para redirecionar para outra página
                    header("Location: index.php");

                    // A função exit impede que o resto do código da página seja executado depois de redirect
                    exit();
                } else {
                    $mensagem = "A tua conta ainda não está ativa. Por favor, verifica o teu e-mail.";
                }
            } else {
                $mensagem = "Palavra-passe incorreta.";
            }
        } else {
            $mensagem = "Não existe nenhuma conta com este e-mail.";
        }
        $comando->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="Style/style.css">
</head>

<body class="bg-soundwave d-flex justify-content-center align-items-center min-vh-100 py-4">

    <div class="glass-box text-center">
        <h2>Iniciar Sessão</h2>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-warning text-center py-2" role="alert" style="font-size: 0.9rem;">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="text-start mt-4">
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

            <button type="submit" class="btn btn-laranja w-100 fw-bold mt-2">Entrar</button>
        </form>

        <div class="text-center mt-4 pt-3 border-top" style="border-color: rgba(255,255,255,0.1) !important;">
            <a href="registo.php" class="text-decoration-none text-muted d-block mb-2"
                style="font-size: 0.9rem; transition: 0.3s;">Ainda não tens conta? <span
                    class="text-white">Regista-te</span></a>
            <a href="index.php" class="text-decoration-none text-muted d-block"
                style="font-size: 0.85rem; transition: 0.3s;">Voltar ao início</a>
        </div>
    </div>
</body>

</html>