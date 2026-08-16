<?php
/**
 * Gestão de Interesses (Subscrições)
 *
 * Permite ao utilizador selecionar os distritos e estilos musicais que deseja acompanhar
 * Processa formulários com múltiplos checkboxes e 
 * garante a integridade dos dados na BD com Transactions
 */

// Inicia a sessão para identificar o utilizador logado
session_start();

// Importa a ligação à BD para executar os comandos SQL
require 'conexao.php';

// Proteção da página: Se o id do utilizador não existir na sessão, é redirecionado para o login
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$id_utilizador = $_SESSION['id'];
$mensagem = "";

/**
 * Processamento do formulário
 * A verificação do REQUEST_METHOD garante que o código apenas executa quando o utilizador clica em guardar
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_interesses'])) {

    // O operador ternário (?:) está a verificar se foram enviados arrays de distritos ou estilos
    // Se o utilizador não selecionar nada, o PHP recebe um array vazio []
    $distritos_selecionados = isset($_POST['distritos']) ? $_POST['distritos'] : [];
    $estilos_selecionados = isset($_POST['estilos']) ? $_POST['estilos'] : [];

    /**
     * Iniciar uma Transação SQL (begin_transaction)
     * Uma transação garante que um conjunto de operações na BD (apagar e inserir)
     * ocorra de forma atómica, ou seja, ou todas têm sucesso ou nenhuma é aplicada
     * Isto evita que o utilizador fique sem interesses se ocorrer uma falha a meio do processo
     */
    $ligacao->begin_transaction();

    // O bloco try/catch permite apanhar exceções (erros graves) que possam ocorrer durante a transação
    try {
        // Primeiro, apaga todas as subscrições antigas deste utilizador (limpeza)
        $sql_delete = "DELETE FROM subscricoes WHERE id_utilizador = ?";

        // prepared statement para evitar SQL Injection
        $cmd_delete = $ligacao->prepare($sql_delete);

        // Liga o ID do utilizador (inteiro 'i') ao ponto de interrogação
        $cmd_delete->bind_param("i", $id_utilizador);
        $cmd_delete->execute();
        $cmd_delete->close();

        // Em seguida, insere os novos interesses de distritos (se o array não estiver vazio)
        if (!empty($distritos_selecionados)) {
            $sql_insert_distrito = "INSERT INTO subscricoes (id_utilizador, id_distrito) VALUES (?, ?)";
            $cmd_distrito = $ligacao->prepare($sql_insert_distrito);

            // O ciclo foreach percorre cada distrito selecionado no formulário
            foreach ($distritos_selecionados as $id_dist) {
                // A função intval garante que o ID é um número inteiro válido
                $id_d = intval($id_dist);

                // Liga os dois ints (utilizador e distrito) na mesma query
                $cmd_distrito->bind_param("ii", $id_utilizador, $id_d);
                $cmd_distrito->execute();
            }
            $cmd_distrito->close();
        }

        // Por fim, insere os novos interesses de estilos musicais
        if (!empty($estilos_selecionados)) {
            $sql_insert_estilo = "INSERT INTO subscricoes (id_utilizador, id_estilo) VALUES (?, ?)";
            $cmd_estilo = $ligacao->prepare($sql_insert_estilo);

            foreach ($estilos_selecionados as $id_est) {
                $id_e = intval($id_est);
                $cmd_estilo->bind_param("ii", $id_utilizador, $id_e);
                $cmd_estilo->execute();
            }
            $cmd_estilo->close();
        }

        // Se todas as operações anteriores tiverem sucesso, o commit aplica-as definitivamente na BD
        $ligacao->commit();
        $mensagem = "Interesses guardados com sucesso! Passarás a receber notificações de novos conteúdos.";

    } catch (Exception $e) {
        // Se ocorrer algum erro dentro do bloco try, o rollback cancela as alterações efetuadas até ao momento
        $ligacao->rollback();
        $mensagem = "Ocorreu um erro ao guardar os interesses.";
    }
}

/**
 * Consulta de dados para apresentar na página
 * Buscamos a lista de todos os distritos e estilos para construir as checkboxes dinamicamente no HTML
 */
$sql_distritos = "SELECT id, nome FROM categorias ORDER BY nome ASC";
$res_distritos = $ligacao->query($sql_distritos);

$sql_estilos = "SELECT id, nome FROM estilos_musicais ORDER BY nome ASC";
$res_estilos = $ligacao->query($sql_estilos);

/**
 * Buscar as subscrições atuais do utilizador
 * Isto permite saber quais checkboxes devem aparecer já selecionadas quando a página carrega
 */
$subs_distritos = [];
$subs_estilos = [];

$sql_subs = "SELECT id_distrito, id_estilo FROM subscricoes WHERE id_utilizador = ?";
$cmd_subs = $ligacao->prepare($sql_subs);
$cmd_subs->bind_param("i", $id_utilizador);
$cmd_subs->execute();

// Guarda o resultado da query para poder percorrê-lo com um ciclo
$res_subs = $cmd_subs->get_result();

// O ciclo while executa até não haver mais linhas no resultado - fetch_assoc devolve false quando termina
while ($row = $res_subs->fetch_assoc()) {

    // A função empty verifica se a variável está vazia ou nula
    // Se o id_distrito existir nesta linha, adicionamos ao array $subs_distritos
    if (!empty($row['id_distrito'])) {
        $subs_distritos[] = $row['id_distrito'];
    }

    // O mesmo para os estilos musicais
    if (!empty($row['id_estilo'])) {
        $subs_estilos[] = $row['id_estilo'];
    }
}
$cmd_subs->close();

?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerir Interesses - SoundCloud PT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Style/style.css">
</head>

<body class="bg-dark text-light">

    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php" style="color:#E0E0E0">SoundCloud PT - Área de
                interesses</a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 text-white" style="font-size: 0.9rem;">Olá,
                    <?php echo htmlspecialchars($_SESSION['nome']); ?></span>
                <a href="index.php" class="btn btn-outline-secondary btn-sm"
                    style="color: #E0E0E0; border-color: #555;">Voltar</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="glass-panel" style="padding: 40px;">
                    <div class="text-center mb-4">
                        <h2 class="h4 fw-bold" style="color: #fff;"><i class="fa-solid fa-bell"></i> Os Meus Interesses
                        </h2>
                        <p style="color: #9E9E9E; font-size: 0.9rem; margin-top: 10px;">Seleciona os locais e os estilos
                            musicais que queres acompanhar. Avisar-te-emos sempre que houver novidades!</p>
                    </div>

                    <div>
                        <?php if (!empty($mensagem)): ?>
                            <div class="alert <?php echo (strpos($mensagem, 'sucesso') !== false) ? 'text-success border-success' : 'text-warning border-warning'; ?> py-2"
                                style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                                <?php echo $mensagem; ?>
                            </div>
                        <?php endif; ?>

                        <form action="interesses.php" method="POST">

                            <h5 class="fw-bold mt-3 mb-3 pb-2"
                                style="color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 1.1rem;">
                                Distritos (Cenas Locais)</h5>
                            <div class="row mb-4">
                                <?php while ($d = $res_distritos->fetch_assoc()): ?>
                                    <div class="col-sm-4 mb-2">
                                        <div class="form-check d-flex align-items-center">
                                            <input class="form-check-input me-2" type="checkbox" name="distritos[]"
                                                value="<?php echo $d['id']; ?>" id="dist_<?php echo $d['id']; ?>" <?php echo in_array($d['id'], $subs_distritos) ? 'checked' : ''; ?>
                                                style="accent-color: #FF5500; width: 18px; height: 18px; cursor: pointer;">
                                            <label class="form-check-label" for="dist_<?php echo $d['id']; ?>"
                                                style="color: #E0E0E0; font-size: 0.95rem; cursor: pointer;">
                                                <?php echo htmlspecialchars($d['nome']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <h5 class="fw-bold mt-4 mb-3 pb-2"
                                style="color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 1.1rem;">
                                Estilos Musicais</h5>
                            <div class="row mb-4">
                                <?php while ($e = $res_estilos->fetch_assoc()): ?>
                                    <div class="col-sm-4 mb-2">
                                        <div class="form-check d-flex align-items-center">
                                            <input class="form-check-input me-2" type="checkbox" name="estilos[]"
                                                value="<?php echo $e['id']; ?>" id="est_<?php echo $e['id']; ?>" <?php echo in_array($e['id'], $subs_estilos) ? 'checked' : ''; ?> style="accent-color:
                                            #FF5500; width: 18px; height: 18px; cursor: pointer;">
                                            <label class="form-check-label" for="est_<?php echo $e['id']; ?>"
                                                style="color: #E0E0E0; font-size: 0.95rem; cursor: pointer;">
                                                <?php echo htmlspecialchars($e['nome']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>

                            <div class="text-center mt-5 mb-2">
                                <button type="submit" name="guardar_interesses"
                                    class="btn btn-laranja fw-bold px-5 py-2" style="border-radius: 8px;">Guardar
                                    Interesses</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>