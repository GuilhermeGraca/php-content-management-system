<?php
/**
 * Configuração de Perfil do Artista
 *
 * Permite que um utilizador Simpatizante ou Administrador defina ou atualize 
 * o seu "Distrito Base"/Categoria Principal, que é o local de origem das suas músicas
 */

// Retoma a sessão para aceder às informações do utilizador logado
session_start();

// Importa a ligação à BD para executar as queries
require 'conexao.php';

/**
 * Proteção de Acesso (Controlo de Autorização)
 * Apenas os perfis com permissão (simpatizante ou administrador) podem aceder a esta página
 */
if (!isset($_SESSION['id']) || ($_SESSION['perfil'] !== 'simpatizante' && $_SESSION['perfil'] !== 'administrador')) {
    header("Location: index.php");
    exit();
}

$mensagem = "";
$id_utilizador = $_SESSION['id'];

/**
 * Processamento do Formulário
 * Validamos se o formulário foi enviado via POST e se o botão 'guardar_perfil' foi clicado
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_perfil'])) {
    // A função intval converte o valor submetido para um número int, em principio já é int mas serve como camada d segurança
    $id_distrito = intval($_POST['id_distrito']);

    if ($id_distrito > 0) {
        // Query de UPDATE para atualizar a coluna id_distrito do utilizador em sessão
        $sql = "UPDATE utilizadores SET id_distrito = ? WHERE id = ?";

        // Os Prepared Statements para proteger contra SQL Injection
        $comando = $ligacao->prepare($sql);

        // A string "ii" indica que ambos os parâmetros inseridos nos '?' são inteiros
        $comando->bind_param("ii", $id_distrito, $id_utilizador);

        if ($comando->execute()) {
            $mensagem = "Distrito atualizado com sucesso! Já podes fazer uploads";

            // Guardamos o novo distrito na sessão para evitar fazer consultas desnecessárias noutras páginas
            $_SESSION['id_distrito'] = $id_distrito;
        } else {
            $mensagem = "Erro ao atualizar o distrito. Tenta novamente.";
        }
        $comando->close();
    } else {
        $mensagem = "Por favor, seleciona um distrito válido.";
    }
}

/**
 * Busca do Distrito Atual
 * SELECT para descobrir qual o distrito atual do utilizador na BD
 */
$sql_user = "SELECT id_distrito FROM utilizadores WHERE id = ?";
$cmd_user = $ligacao->prepare($sql_user);
$cmd_user->bind_param("i", $id_utilizador);
$cmd_user->execute();
$resultado_user = $cmd_user->get_result();

// Extraímos a linha devolvida como um array associativo
$dados_user = $resultado_user->fetch_assoc();
$distrito_atual = $dados_user['id_distrito'];
$cmd_user->close();

// Atualizam a sessão com o distrito mais recente, garante sincronia com a BD
$_SESSION['id_distrito'] = $distrito_atual;

/**
 * Busca de Distritos Disponíveis
 * query() é segura aqui porque não envolve variáveis submetidas pelo utilizador
 * quando envolve variaveis dos utilizadores utilizamos os prepared statements com o prepare(), bind_param() e execute()
 * O ORDER BY nome ASC devolve os distritos ordenados alfabeticamente
 */
$sql_cats = "SELECT id, nome FROM categorias ORDER BY nome ASC";
$resultado_cats = $ligacao->query($sql_cats);

/**
 * A superglobal $_GET obtém dados enviados no próprio URL
 * Verificamos se há um parâmetro primeiro=1 para mostrar um aviso específico ao utilizador
 * isset() verifica se uma variável existe
 */
$primeira_vez = isset($_GET['primeiro']) && $_GET['primeiro'] == '1';
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O Meu Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="Style/style.css">
</head>

<body class="bg-dark text-light">

    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php" style="color:#E0E0E0">SoundCloud PT - Perfil</a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 text-white" style="font-size: 0.9rem;">Olá,
                    <?php echo htmlspecialchars($_SESSION['nome']); ?></span>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm"
                    style="color: #E0E0E0; border-color: #555;">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container d-flex flex-column align-items-center justify-content-center" style="min-height: 70vh;">
        <div class="glass-box p-4 p-md-5" style="max-width: 600px; width: 100%;">
            <h3 class="mb-4" style="font-family: var(--font-headline); font-weight: 700; color: #fff;">Configurar Perfil
                de Artista</h3>

            <?php if ($primeira_vez): ?>
                <div class="alert text-start py-2"
                    style="background-color: rgba(255, 85, 0, 0.1); border: 1px solid var(--color-primary); color: var(--color-text-main); font-size: 0.9rem;">
                    <strong>Primeiro passo!</strong> Antes de fazeres o teu primeiro upload, precisas de definir o teu
                    Distrito Base.
                    É o distrito onde a tua música será publicada.
                </div>
            <?php endif; ?>

            <?php if (!empty($mensagem)): ?>
                <div class="alert <?php echo (strpos($mensagem, 'sucesso') !== false) ? 'text-success border-success' : 'text-warning border-warning'; ?> py-2"
                    style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>

            <form action="perfil.php" method="POST" class="text-start">
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.95rem;">O Teu Distrito
                        Base</label>
                    <select name="id_distrito" class="form-select form-select-custom" required>
                        <option value="" disabled <?php echo ($distrito_atual === null) ? 'selected' : ''; ?>>-- Escolhe o
                            teu distrito --</option>
                        <?php while ($cat = $resultado_cats->fetch_assoc()): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($distrito_atual == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="form-text mt-2" style="color: #9E9E9E; font-size: 0.8rem;">
                        As tuas músicas serão automaticamente associadas a este distrito. Podes alterá-lo a qualquer
                        momento.
                    </div>
                </div>

                <button type="submit" name="guardar_perfil" class="btn btn-laranja w-100 fw-bold mt-2"
                    style="height: 48px;">Guardar Distrito</button>
            </form>

            <?php if ($distrito_atual !== null && !empty($mensagem) && strpos($mensagem, 'sucesso') !== false): ?>
                <div class="text-center mt-3">
                    <a href="artista_dashboard.php" class="btn btn-outline-secondary w-100 fw-bold"
                        style="height: 48px; line-height: 34px; color: #fff;">Ir para a Área de Uploads →</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4">
            <a href="index.php" class="text-decoration-none"
                style="color: #9E9E9E; font-size: 0.85rem; transition: 0.3s;">← Voltar à página inicial</a>
            <?php if ($distrito_atual !== null): ?>
                <span style="color: #555;"> | </span>
                <a href="artista_dashboard.php" class="text-decoration-none"
                    style="color: #9E9E9E; font-size: 0.85rem; transition: 0.3s;">Área de Uploads →</a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>