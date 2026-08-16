<?php
/**
 * Painel de Administração
 *
 * Permite aos administradores gerir os perfis dos utilizadores (fã, artista, admin)
 * e adicionar novas categorias (distritos) à plataforma
 */

session_start();
require 'conexao.php';

// Proteção da página: Apenas perfis 'administrador' podem aceder
if (!isset($_SESSION['id']) || $_SESSION['perfil'] !== 'administrador') {
    header("Location: index.php");
    exit();
}

$mensagem = "";
$mensagem_categoria = "";

/**
 * Lidar com a atualização de perfil de utilizadores
 * Verifica se o formulário de atualização de perfil foi submetido via POST
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_perfil'])) {
    $id_utilizador = $_POST['id_utilizador'];
    $novo_perfil = $_POST['novo_perfil'];

    // Impede o admin de mudar o seu próprio perfil acidentalmente para não perder acesso à página
    if ($id_utilizador == $_SESSION['id'] && $novo_perfil !== 'administrador') {
        $mensagem = "Não podes alterar o teu próprio perfil de administrador.";
    } else {
        // Query de UPDATE para alterar o nível de acesso do utilizador
        $sql_update = "UPDATE utilizadores SET perfil = ? WHERE id = ?";
        
        // prepared statement para evitar SQL Injection
        $comando = $ligacao->prepare($sql_update);
        
        // Liga as variáveis: "s" para string (novo_perfil) e "i" para int (id_utilizador)
        $comando->bind_param("si", $novo_perfil, $id_utilizador);
        
        if ($comando->execute()) {
            $mensagem = "Perfil atualizado com sucesso!";
        } else {
            $mensagem = "Erro ao atualizar perfil.";
        }
        $comando->close();
    }
}

/**
 * Lidar com a adição de categorias
 * Verifica se o formulário de nova categoria foi submetido
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['adicionar_categoria'])) {
    // trim corta espaços em branco no início ou no fim do texto digitado
    $nova_categoria = trim($_POST['nova_categoria']);
    
    // empty() verifica se o campo não ficou vazio após o trim
    if (!empty($nova_categoria)) {
        // Query de INSERT para adicionar o novo distrito à BD
        $sql_cat = "INSERT INTO categorias (nome) VALUES (?)";
        
        $comando_cat = $ligacao->prepare($sql_cat);
        $comando_cat->bind_param("s", $nova_categoria);
        
        if ($comando_cat->execute()) {
            $mensagem_categoria = "Categoria adicionada com sucesso!";
        } else {
            $mensagem_categoria = "Erro ao adicionar. A categoria já pode existir.";
        }
        $comando_cat->close();
    }
}

/**
 * Consulta de Dados para as Tabelas
 * Seleciona todos os utilizadores ordenados pelos registos mais recentes
 */
$sql_users = "SELECT id, nome, email, perfil, conta_ativa, data_registo FROM utilizadores ORDER BY data_registo DESC";
$resultado_users = $ligacao->query($sql_users);

// Seleciona todas as categorias (distritos) para listar na secção inferior
$sql_cats = "SELECT id, nome FROM categorias ORDER BY nome ASC";
$resultado_cats = $ligacao->query($sql_cats);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="Style/style.css">
</head>
<body class="bg-dark text-light">

    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php" style="color:#E0E0E0">SoundCloud PT - Admin</a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 text-white" style="font-size: 0.9rem;">Olá, <?php echo htmlspecialchars($_SESSION['nome']); ?></span>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm" style="color: #E0E0E0; border-color: #555;">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Secção de Gestão de Utilizadores -->
        <div class="glass-panel">
            <h3>Gestão de Utilizadores</h3>
            
            <?php if (!empty($mensagem)): ?>
                <div class="alert text-info border-info py-2" style="background-color: rgba(0,209,255,0.1); font-size: 0.9rem;">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Estado</th>
                            <th>Perfil Atual</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $resultado_users->fetch_assoc()): ?>
                            <tr>
                                <td style="color: #E0E0E0;"><?php echo $user['id']; ?></td>
                                <td style="color: #fff;"><?php echo htmlspecialchars($user['nome']); ?></td>
                                <td style="color: #E0E0E0;"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php echo ($user['conta_ativa'] == 1) ? '<span class="badge" style="background-color: #00E676; color: #000;">Ativa</span>' : '<span class="badge" style="background-color: #FF5500; color: #fff;">Inativa</span>'; ?>
                                </td>
                                <form action="admin_dashboard.php" method="POST" class="d-flex align-items-center m-0">
                                    <td style="padding-top: 10px; padding-bottom: 10px;">
                                        <input type="hidden" name="id_utilizador" value="<?php echo $user['id']; ?>">
                                        <select name="novo_perfil" class="form-select form-select-sm" style="background-color: #000; border: 1px solid var(--color-border); color: #fff;" <?php echo ($user['id'] == $_SESSION['id']) ? 'disabled' : ''; ?>>
                                            <option value="utilizador" <?php echo ($user['perfil'] == 'utilizador') ? 'selected' : ''; ?>>Utilizador (Fã)</option>
                                            <option value="simpatizante" <?php echo ($user['perfil'] == 'simpatizante') ? 'selected' : ''; ?>>Simpatizante (Artista)</option>
                                            <option value="administrador" <?php echo ($user['perfil'] == 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                                        </select>
                                    </td>
                                    <td style="padding-top: 10px; padding-bottom: 10px;">
                                        <button type="submit" name="atualizar_perfil" class="btn btn-sm text-white" style="background-color: #0d6efd;" <?php echo ($user['id'] == $_SESSION['id']) ? 'disabled' : ''; ?>>Atualizar</button>
                                    </td>
                                </form>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Secção de Gestão de Categorias -->
        <div class="glass-panel">
            <h3>Gestão de Categorias (Distritos)</h3>
            
            <?php if (!empty($mensagem_categoria)): ?>
                <div class="alert <?php echo (strpos($mensagem_categoria, 'sucesso') !== false) ? 'text-success border-success' : 'text-warning border-warning'; ?> py-2" style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                    <?php echo $mensagem_categoria; ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <ul class="list-group" style="border-radius: 8px; overflow: hidden; border: 1px solid var(--color-border);">
                        <?php while ($cat = $resultado_cats->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center" style="background-color: transparent; border: none; border-bottom: 1px solid var(--color-border); color: #E0E0E0; padding: 12px 20px;">
                                <?php echo htmlspecialchars($cat['nome']); ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <div class="col-md-6">
                    <form action="admin_dashboard.php" method="POST" class="p-4" style="background-color: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;">
                        <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.95rem;">Adicionar Novo Distrito</label>
                        <div class="input-group mt-2">
                            <input type="text" name="nova_categoria" class="form-control form-control-custom" style="background-color: #000; border-right: none;" placeholder="Ex: Setúbal" required>
                            <button type="submit" name="adicionar_categoria" class="btn" style="background-color: #198754; color: white; border-radius: 0 8px 8px 0; padding-left: 20px; padding-right: 20px;">Adicionar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4 mb-5">
            <a href="index.php" class="text-decoration-none" style="color: #9E9E9E; font-size: 0.85rem; transition: 0.3s;">← Voltar à página inicial</a>
        </div>
    </div>
</body>
</html>
