<?php
/**
 * Ativação de Conta de Utilizador
 *
 * Recebe um token através do método GET (que vem do link no email) 
 * e altera o estado da conta para ativa (1) se o token corresponder a uma conta inativa.
 */
require 'conexao.php';

$mensagem = "";

/**
 * Verifica se o parâmetro 'token' foi enviado no URL
 * O uso do token garante que apenas o dono do email (que recebeu o link) 
 * consegue ativar a conta, o que confirma a propriedade do endereço de email
 */
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Procura por um utilizador que tenha este token exato e cuja conta ainda esteja inativa (0)
    $sql = "SELECT id FROM utilizadores WHERE token_validacao = ? AND conta_ativa = 0";
    $comando = $ligacao->prepare($sql);
    $comando->bind_param("s", $token);
    $comando->execute();
    $resultado = $comando->get_result();

    if ($resultado->num_rows > 0) {
        $linha = $resultado->fetch_assoc();
        $id_utilizador = $linha['id'];

        /**
         * Se encontrar o utilizador, atualiza o estado para ativo (1) e 
         * limpa o token_validacao (mete a NULL) para que o mesmo link não possa ser reusado
         */
        $sql_update = "UPDATE utilizadores SET conta_ativa = 1, token_validacao = NULL WHERE id = ?";
        $comando_update = $ligacao->prepare($sql_update);
        $comando_update->bind_param("i", $id_utilizador);

        if ($comando_update->execute()) {
            $mensagem = "A tua conta foi ativada com sucesso! Já podes iniciar sessão.";
        } else {
            $mensagem = "Ocorreu um erro ao ativar a conta. Tenta novamente.";
        }
        $comando_update->close();
    } else {
        // Trata o caso em que o token já foi usado, foi mal copiado ou a conta já está ativa
        $mensagem = "Token de ativação inválido ou a conta já se encontra ativa.";
    }
    $comando->close();
} else {
    $mensagem = "Nenhum token fornecido.";
}
?>

<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar Conta - SoundCloud Português</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="Style/style.css">
</head>

<body class="bg-dark text-light d-flex justify-content-center align-items-center min-vh-100 py-4">

    <div class="p-4 text-center shadow"
        style="max-width: 450px; width: 100%; background-color: var(--color-surface); border: 1px solid var(--color-border); border-radius: 12px;">
        <h2 style="font-family: var(--font-headline); font-weight: 700; color: #fff; margin-bottom: 25px;">
            Ativação de Conta
        </h2>

        <!-- Alert customizado para fundo escuro -->
        <?php if (strpos($mensagem, 'sucesso') !== false): ?>
            <div class="alert text-center py-3" role="alert"
                style="background-color: rgba(0, 230, 118, 0.1); border: 1px solid var(--color-tertiary); color: var(--color-tertiary);">
                <?php echo $mensagem; ?>
            </div>
        <?php else: ?>
            <div class="alert text-center py-3" role="alert"
                style="background-color: rgba(255, 85, 0, 0.1); border: 1px solid var(--color-primary); color: var(--color-primary);">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <a href="index.php" class="btn btn-laranja w-100 fw-bold mt-3" style="height: 48px; line-height: 34px;">Ir para
            o Início</a>
    </div>

</body>

</html>