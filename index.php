<?php
/**
 * Página Principal (Público)
 *
 * Lista os conteúdos musicais disponíveis, permite filtros avançados e pesquisa,
 * mostra notificações para utilizadores logados e exibe um mapa com concertos futuros
 */

// Inicia ou retoma a sessão do utilizador
session_start();

// Importa a ligação à BD para executar comandos SQL
require 'conexao.php';

/**
 * Recolha de Filtros (Parâmetros GET)
 * A superglobal $_GET obtém dados enviados no URL - ex: index.php?distrito=1&q=rock
 * O intval() garante que distritos e estilos são ints por segurança
 */
$filtro_distrito = isset($_GET['distrito']) ? intval($_GET['distrito']) : 0;
$filtro_estilo = isset($_GET['estilo']) ? intval($_GET['estilo']) : 0;
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// trim() remove espaços em branco antes e depois do termo pesquisado
$pesquisa = isset($_GET['q']) ? trim($_GET['q']) : '';

/**
 * Construção Dinâmica da Query Principal
 * Juntamos várias tabelas (LEFT JOIN junta lado a lado) para obter o nome do artista, distrito e estilo
 * A cláusula WHERE 1=1 é para podermos adicionar "AND..." dinamicamente abaixo
 */
$sql = "SELECT c.id, c.titulo, c.descricao, c.caminho_ficheiro, c.tipo_ficheiro, c.caminho_capa, c.visibilidade, c.data_upload,
               u.nome AS artista, cat.nome AS distrito, e.nome AS estilo
        FROM conteudos c
        LEFT JOIN utilizadores u ON c.id_utilizador = u.id
        LEFT JOIN categorias cat ON c.id_categoria = cat.id
        LEFT JOIN estilos_musicais e ON c.id_estilo = e.id
        WHERE 1=1";

// Se o utilizador for um convidado (sem sessão iniciada), forçamos que veja apenas ficheiros públicos
if (!isset($_SESSION['id'])) {
    $sql .= " AND c.visibilidade = 'publico'";
}

// Arrays auxiliares para armazenar os parâmetros que vão para o bind_param do Prepared Statement
$params = [];
$tipos = "";

// Se foi escolhido um distrito específico, adicionamos à query e guardamos o valor ('i' para inteiro)
if ($filtro_distrito > 0) {
    $sql .= " AND c.id_categoria = ?";
    $params[] = $filtro_distrito;
    $tipos .= "i";
}

// Se foi escolhido um estilo específico
if ($filtro_estilo > 0) {
    $sql .= " AND c.id_estilo = ?";
    $params[] = $filtro_estilo;
    $tipos .= "i";
}

// O filtro de tipo baseia-se no MIME Type do ficheiro guardado - like %audio% vai buscar todos os que tenham audio no tipo
if ($filtro_tipo === 'audio') {
    $sql .= " AND c.tipo_ficheiro LIKE '%audio%'";
} elseif ($filtro_tipo === 'imagem') {
    $sql .= " AND c.tipo_ficheiro LIKE '%image%'";
}

// A pesquisa de texto usa o LIKE %termo% para encontrar partes de palavras no título, descrição ou nome do autor
if (!empty($pesquisa)) {
    $sql .= " AND (c.titulo LIKE ? OR c.descricao LIKE ? OR u.nome LIKE ?)";
    $termo = "%$pesquisa%";
    $params[] = $termo;
    $params[] = $termo;
    $params[] = $termo;
    $tipos .= "sss"; // Três strings ("s") para os três campos da pesquisa
}

// Ordenamos sempre os resultados para mostrar os uploads mais recentes em primeiro lugar (DESC)
$sql .= " ORDER BY c.data_upload DESC";

// Prepara o comando SQL (prepared statement)
$cmd = $ligacao->prepare($sql);

// Se existirem filtros ativos, o array $params não estará vazio e o operador splat (...) desempacota o array
//como ... está nos parametros da chamada da func então como se estivesse a passalos cada um separado por virgula
if (!empty($params)) {
    $cmd->bind_param($tipos, ...$params);
}
$cmd->execute();

// Extraímos os dados resultantes para uma variável a utilizar no HTML
$resultado = $cmd->get_result();

/**
 * Buscas Secundárias para os Filtros e Mapa
 * Estes selects simples não precisam de statements preparados porque não têm variáveis ($) na query
 */
// Distritos
$sql_distritos = "SELECT id, nome FROM categorias ORDER BY nome ASC";
$distritos = $ligacao->query($sql_distritos);

// Estilos
$sql_estilos = "SELECT id, nome FROM estilos_musicais ORDER BY nome ASC";
$estilos = $ligacao->query($sql_estilos);

/**
 * Concertos para o Mapa (OpenStreetMap com Leaflet)
 * Trazemos as coordenadas (latitude e longitude) de concertos que vão acontecer hoje ou no futuro (NOW())
 */
$sql_concertos = "SELECT co.local_nome, co.latitude, co.longitude, co.data_concerto, u.nome AS artista
                  FROM concertos co
                  LEFT JOIN utilizadores u ON co.id_utilizador = u.id
                  WHERE co.data_concerto >= NOW()
                  ORDER BY co.data_concerto ASC";
$resultado_concertos = $ligacao->query($sql_concertos);

// Armazenamos os concertos num array PHP para dps passar ao JavaScript - json_encode
$concertos_json = [];
while ($conc = $resultado_concertos->fetch_assoc()) {
    $concertos_json[] = [
        'local' => $conc['local_nome'],
        // floatval() assegura que a coordenada é um número decimal para o mapa não dar erro de leitura
        'lat' => floatval($conc['latitude']),
        'lng' => floatval($conc['longitude']),
        // strtotime converte uma data da BD num formato temporal compreensível para poder ser formatada com date()
        'data' => date('d/m/Y H:i', strtotime($conc['data_concerto'])),
        'artista' => $conc['artista']
    ];
}

/**
 * Notificações do Utilizador
 * Se houver sessão iniciada, procuramos as 10 últimas notificações não lidas para o "sininho" da barra de topo
 */
$notificacoes = [];
$num_notificacoes = 0;
if (isset($_SESSION['id'])) {
    $sql_busca_notif = "SELECT id, mensagem, link, data_criacao FROM notificacoes WHERE id_utilizador = ? AND lida = 0 ORDER BY data_criacao DESC LIMIT 10";
    $cmd_busca_notif = $ligacao->prepare($sql_busca_notif);
    $cmd_busca_notif->bind_param("i", $_SESSION['id']);
    $cmd_busca_notif->execute();
    $res_busca_notif = $cmd_busca_notif->get_result();
    
    while ($n = $res_busca_notif->fetch_assoc()) {
        $notificacoes[] = $n;
    }
    // count devolve o nr de elementos dentro do array
    $num_notificacoes = count($notificacoes);
    $cmd_busca_notif->close();
}
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SoundCloud Português - Música Independente de Portugal</title>
    <meta name="description" content="Descobre artistas independentes portugueses. Ouve músicas, vê capas de álbuns e encontra a cena musical do teu distrito.">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="Style/style.css">
</head>

<body class="bg-dark text-light"> <!-- Alterado para classes escuras do bootstrap por base -->

    <!-- Barra de Navegação -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php" style="color:#E0E0E0">
                SoundCloud PT
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navPrincipal">
                <!-- Barra de Pesquisa -->
                <form class="d-flex mx-auto my-2 my-lg-0" action="index.php" method="GET" style="max-width: 500px; width: 100%;">
                    <?php if ($filtro_distrito > 0): ?>
                        <input type="hidden" name="distrito" value="<?php echo $filtro_distrito; ?>">
                    <?php endif; ?>
                    <?php if ($filtro_estilo > 0): ?>
                        <input type="hidden" name="estilo" value="<?php echo $filtro_estilo; ?>">
                    <?php endif; ?>
                    <input type="search" name="q" class="form-control search-input-custom" placeholder="Pesquisar músicas, artistas..." value="<?php echo htmlspecialchars($pesquisa); ?>">
                    <button type="submit" class="btn btn-laranja search-btn-custom">Buscar</button>
                </form>

                <!-- Menu do utilizador -->
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (isset($_SESSION['id'])): ?>
                        <!-- Sino de Notificações -->
                        <li class="nav-item dropdown me-2">
                            <a class="nav-link dropdown-toggle text-light" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-bell"></i> <span class="badge bg-danger rounded-pill"><?php echo $num_notificacoes > 0 ? $num_notificacoes : ''; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="notifDropdown" style="width: 300px; max-height: 400px; overflow-y: auto;">
                                <li class="dropdown-header">Notificações</li>
                                <?php if ($num_notificacoes > 0): ?>
                                    <?php foreach ($notificacoes as $notif): ?>
                                        <li>
                                            <a class="dropdown-item py-2 border-bottom" style="white-space: normal;" href="<?php echo htmlspecialchars($notif['link']); ?>">
                                                <small class="text-muted d-block"><?php echo date('d/m H:i', strtotime($notif['data_criacao'])); ?></small>
                                                <?php echo htmlspecialchars($notif['mensagem']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    <li>
                                        <form action="ler_notificacoes.php" method="POST" class="px-3 py-2 text-center">
                                            <button type="submit" name="marcar_lidas" class="btn btn-sm btn-outline-secondary w-100">Marcar todas como lidas</button>
                                        </form>
                                    </li>
                                <?php else: ?>
                                    <li><span class="dropdown-item text-muted">Sem novas notificações.</span></li>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <span class="nav-link text-light border-start ps-3 me-2">
                                Olá, <strong><?php echo htmlspecialchars($_SESSION['nome']); ?></strong>
                                <span class="badge bg-secondary ms-1"><?php echo ucfirst($_SESSION['perfil']); ?></span>
                            </span>
                        </li>
                        <?php if ($_SESSION['perfil'] == 'administrador'): ?>
                            <li class="nav-item ms-2 my-1">
                                <a href="admin_dashboard.php" class="nav-link px-3 py-1" style="color:#E0E0E0; border: 1px solid #E0E0E0; border-radius: 6px;">Painel Admin</a>
                            </li>
                        <?php endif; ?>
                        <?php if ($_SESSION['perfil'] == 'simpatizante' || $_SESSION['perfil'] == 'administrador'): ?>
                            <li class="nav-item ms-2 my-1">
                                <a href="artista_dashboard.php" class="nav-link px-3 py-1" style="color:#E0E0E0; border: 1px solid #E0E0E0; border-radius: 6px;">Painel Artista</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item ms-2 my-1">
                            <a href="interesses.php" class="nav-link text-info px-3 py-1 border border-info" style="border-radius: 6px;">Interesses</a>
                        </li>
                        <li class="nav-item ms-2 my-1">
                            <a href="logout.php" class="nav-link text-warning px-3 py-1 border border-warning" style="border-radius: 6px;">Sair</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="nav-link text-muted">Entrar</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a href="registo.php" class="btn btn-outline-laranja btn-sm my-1">Registar</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">

        <!-- Secção de Filtros -->
        <div class="filtros-box">
            <form action="index.php" method="GET" class="row g-3 align-items-end justify-content-center">
                <div class="col-md-3">
                    <label>Distrito</label>
                    <select name="distrito" class="form-select form-select-custom">
                        <option value="0">Todos os Distritos</option>
                        <?php while ($d = $distritos->fetch_assoc()): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo ($filtro_distrito == $d['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['nome']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Estilo Musical</label>
                    <select name="estilo" class="form-select form-select-custom">
                        <option value="0">Todos os Estilos</option>
                            <?php while ($e = $estilos->fetch_assoc()): ?>
                                <option value="<?php echo $e['id']; ?>" <?php echo ($filtro_estilo == $e['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($e['nome']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Tipo de Conteúdo</label>
                        <select name="tipo" class="form-select form-select-custom">
                            <option value="">Todos</option>
                            <option value="audio" <?php echo ($filtro_tipo === 'audio') ? 'selected' : ''; ?>>Áudio</option>
                            <option value="imagem" <?php echo ($filtro_tipo === 'imagem') ? 'selected' : ''; ?>>Imagem</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <?php if (!empty($pesquisa)): ?>
                            <input type="hidden" name="q" value="<?php echo htmlspecialchars($pesquisa); ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-laranja flex-grow-1">Filtrar</button>
                        <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
                    </div>
                </form>
                <div class="text-center mt-3 pt-3 border-top" style="border-color: var(--color-border) !important;">
                    <button type="button" class="btn btn-verde fw-bold" data-bs-toggle="modal" data-bs-target="#modalConcertos">
                        Ver Concertos Futuros
                    </button>
                </div>
        </div>

        <!-- Indicador de filtros ativos -->
        <?php if ($filtro_distrito > 0 || $filtro_estilo > 0 || !empty($filtro_tipo) || !empty($pesquisa)): ?>
            <div class="mb-4">
                <small class="text-muted">
                    Filtros ativos:
                    <?php if (!empty($pesquisa)): ?>
                        <span class="badge bg-secondary ms-1">Pesquisa: "<?php echo htmlspecialchars($pesquisa); ?>"</span>
                    <?php endif; ?>
                    <?php if ($filtro_distrito > 0): ?>
                        <span class="badge bg-secondary ms-1">Distrito filtrado</span>
                    <?php endif; ?>
                    <?php if ($filtro_estilo > 0): ?>
                        <span class="badge bg-secondary ms-1">Estilo filtrado</span>
                    <?php endif; ?>
                    <?php if (!empty($filtro_tipo)): ?>
                        <span class="badge bg-secondary ms-1">Tipo: <?php echo $filtro_tipo; ?></span>
                    <?php endif; ?>
                    <a href="index.php" class="text-info ms-2 text-decoration-none">Limpar tudo</a>
                </small>
            </div>
        <?php endif; ?>

        <!-- Grelha de Conteúdos -->
        <?php if ($resultado->num_rows > 0): ?>
            <div class="row">
                <?php while ($item = $resultado->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="conteudo-card">
                            <div class="conteudo-img-wrapper">
                                <?php if (!empty($item['caminho_capa'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['caminho_capa']); ?>" class="conteudo-img" alt="<?php echo htmlspecialchars($item['titulo']); ?>">
                                <?php elseif (strpos($item['tipo_ficheiro'], 'image') !== false): ?>
                                    <img src="<?php echo htmlspecialchars($item['caminho_ficheiro']); ?>" class="conteudo-img" alt="<?php echo htmlspecialchars($item['titulo']); ?>">
                                <?php else: ?>
                                    <div class="conteudo-audio-capa">
                                        <i class="fa-solid fa-music"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-body-custom">
                                <h5 class="card-title-custom"><?php echo htmlspecialchars($item['titulo']); ?></h5>
                                <div class="card-author-custom">
                                    por <span><?php echo htmlspecialchars($item['artista']); ?></span>
                                </div>

                                <?php if (!empty($item['descricao'])): ?>
                                    <div class="card-desc-custom"><?php echo htmlspecialchars(mb_strimwidth($item['descricao'], 0, 120, '...')); ?></div>
                                <?php endif; ?>

                                <div class="badges-wrapper">
                                    <?php if ($item['distrito']): ?>
                                        <span class="badge-outline-laranja"><?php echo htmlspecialchars($item['distrito']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['estilo']): ?>
                                        <span class="badge-outline-laranja"><?php echo htmlspecialchars($item['estilo']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['visibilidade'] == 'privado'): ?>
                                        <span class="badge bg-secondary">Privado</span>
                                    <?php endif; ?>
                                </div>

                                <?php if (strpos($item['tipo_ficheiro'], 'audio') !== false): ?>
                                    <audio controls class="custom-audio-player">
                                        <source src="<?php echo htmlspecialchars($item['caminho_ficheiro']); ?>" type="<?php echo htmlspecialchars($item['tipo_ficheiro']); ?>">
                                        O teu navegador não suporta áudio.
                                    </audio>
                                <?php endif; ?>

                                <div class="card-date-custom mt-auto">
                                    <?php echo date('d/m/Y', strtotime($item['data_upload'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- Estado vazio -->
            <div class="text-center py-5">
                <div class="display-1 mb-3"></div>
                <h4 class="text-muted">Nenhum conteúdo encontrado</h4>
                <p class="text-muted">
                    <?php if ($filtro_distrito > 0 || $filtro_estilo > 0 || !empty($pesquisa)): ?>
                        Tenta ajustar os filtros ou <a href="index.php">limpar a pesquisa</a>.
                    <?php else: ?>
                        A plataforma ainda está à espera dos primeiros artistas! 
                        <?php if (!isset($_SESSION['id'])): ?>
                            <a href="registo.php">Regista-te</a> e sê o primeiro.
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Rodapé simples -->
        <div class="text-center mt-4 mb-4 py-3 border-top">
            <small class="text-muted">
                SoundCloud Português &copy; <?php echo date('Y'); ?> — A plataforma para a nova música independente.
            </small>
        </div>
    </div>

    <!-- Modal de Concertos com Mapa -->
    <div class="modal fade" id="modalConcertos" tabindex="-1" aria-labelledby="modalConcertosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="modalConcertosLabel">Concertos Futuros em Portugal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="mapaConcertos"></div>
                    <?php if (empty($concertos_json)): ?>
                        <div class="text-center py-4 text-muted">
                            <p>De momento não há concertos agendados. Volta mais tarde!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var concertos = <?php echo json_encode($concertos_json); ?>;
        var mapaIniciado = false;
        var mapa;

        // Iniciar o mapa apenas quando o modal abrir (evita bugs de renderização)
        var modalEl = document.getElementById('modalConcertos');
        modalEl.addEventListener('shown.bs.modal', function() {
            if (!mapaIniciado) {
                mapa = L.map('mapaConcertos').setView([39.5, -8.0], 7);
                
                // Tiles do OpenStreetMap
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(mapa);

                // Adicionar marcadores
                concertos.forEach(function(c) {
                    var marker = L.marker([c.lat, c.lng]).addTo(mapa);
                    marker.bindPopup(
                        '<strong><i class="fa-solid fa-music"></i> ' + c.artista + '</strong><br>' +
                        '<i class="fa-solid fa-location-dot"></i> ' + c.local + '<br>' +
                        '<i class="fa-regular fa-calendar"></i> ' + c.data
                    );
                });

                mapaIniciado = true;
            } else {
                mapa.invalidateSize();
            }
        });
    });
    </script>
</body>

</html>