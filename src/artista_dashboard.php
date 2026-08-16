<?php
/**
 * Área do Artista (Dashboard)
 *
 * É o núcleo principal para os criadores de conteúdo (Artistas)
 * Permite fazer upload de músicas individuais ou em lote (ZIP + XML),
 * gerir a visibilidade do conteúdo, anunciar concertos e ver notificações
 */

session_start();
require 'conexao.php';

// Proteção da página: Apenas simpatizantes (artistas) e administradores têm acesso
if (!isset($_SESSION['id']) || ($_SESSION['perfil'] !== 'simpatizante' && $_SESSION['perfil'] !== 'administrador')) {
    header("Location: index.php");
    exit();
}

$id_utilizador = $_SESSION['id'];

/**
 * Validação de Perfil Completo
 * O sistema exige que o artista tenha um distrito base escolhido antes de poder fazer uploads
 */
$sql_distrito = "SELECT id_distrito FROM utilizadores WHERE id = ?";
$cmd_distrito = $ligacao->prepare($sql_distrito);
$cmd_distrito->bind_param("i", $id_utilizador);
$cmd_distrito->execute();
$res_distrito = $cmd_distrito->get_result();
$dados_distrito = $res_distrito->fetch_assoc();
$cmd_distrito->close();

// empty() deteta se o id_distrito é nulo, vazio ou zero
// Redireciona o utilizador para concluir o seu perfil caso não tenha distrito associado
if (empty($dados_distrito['id_distrito'])) {
    header("Location: perfil.php?primeiro=1");
    exit();
}

$id_distrito_artista = $dados_distrito['id_distrito'];

// SELECT para obter o nome do distrito do artista para mostrar no topo do ecrã
$sql_nome_distrito = "SELECT nome FROM categorias WHERE id = ?";
$cmd_nome = $ligacao->prepare($sql_nome_distrito);
$cmd_nome->bind_param("i", $id_distrito_artista);
$cmd_nome->execute();
$res_nome = $cmd_nome->get_result();
$dados_nome = $res_nome->fetch_assoc();
$nome_distrito = $dados_nome['nome'];
$cmd_nome->close();

/**
 * Função Auxiliar de Notificações
 * 
 * Centraliza a lógica de notificar todos os utilizadores que seguem
 * o distrito ou o estilo musical onde o artista acabou de publicar conteúdo
 * Recebe a ligação da BD e os parâmetros do evento gerado
 */
function gerar_notificacoes($ligacao, $id_autor, $id_distrito, $id_estilo, $mensagem, $link)
{
    // A palavra-chave DISTINCT evita que a mesma pessoa receba duas notificações idênticas
    // A cláusula != exclui o próprio autor de receber a notificação do seu próprio upload
    $sql_notif = "INSERT INTO notificacoes (id_utilizador, mensagem, link)
                  SELECT DISTINCT s.id_utilizador, ?, ?
                  FROM subscricoes s
                  WHERE (s.id_distrito = ? OR (s.id_estilo = ? AND s.id_estilo IS NOT NULL))
                  AND s.id_utilizador != ?";
    $cmd_notif = $ligacao->prepare($sql_notif);

    // "ssiii": string(mensagem), string(link), int(distrito), int(estilo), int(autor)
    $cmd_notif->bind_param("ssiii", $mensagem, $link, $id_distrito, $id_estilo, $id_autor);
    $cmd_notif->execute();
    $cmd_notif->close();
}

$mensagem = "";
$mensagem_estilo = "";

/**
 * Criação Rápida de Estilos Musicais
 * Permite que um artista adicione um novo género se não o encontrar na lista
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['criar_estilo'])) {
    $novo_estilo = trim($_POST['novo_estilo']);
    if (!empty($novo_estilo)) {

        // Antes de inserir, verifica se o estilo já existe para evitar duplicados
        $sql_verifica = "SELECT id FROM estilos_musicais WHERE nome = ?";
        $cmd_verifica = $ligacao->prepare($sql_verifica);
        $cmd_verifica->bind_param("s", $novo_estilo);
        $cmd_verifica->execute();
        $res_verifica = $cmd_verifica->get_result();

        // num_rows maior que 0 significa que já tem um registo com esse nome
        if ($res_verifica->num_rows > 0) {
            $mensagem_estilo = "O estilo '" . htmlspecialchars($novo_estilo) . "' já existe na lista";
        } else {
            // INSERT para adicionar estilo à BD com ligação a quem o criou
            $sql_novo = "INSERT INTO estilos_musicais (nome, id_criador) VALUES (?, ?)";
            $cmd_novo = $ligacao->prepare($sql_novo);
            $cmd_novo->bind_param("si", $novo_estilo, $id_utilizador);
            if ($cmd_novo->execute()) {
                $mensagem_estilo = "Estilo '" . htmlspecialchars($novo_estilo) . "' criado com sucesso!";
            } else {
                $mensagem_estilo = "Erro ao criar o estilo musical";
            }
            $cmd_novo->close();
        }
        $cmd_verifica->close();
    } else {
        $mensagem_estilo = "Por favor, escreve o nome do estilo musical";
    }
}

$mensagem_visibilidade = "";

/**
 * Alteração de Visibilidade dos Conteúdos
 * Permite ao artista esconder uma música tornando-a privada
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['alterar_visibilidade'])) {
    // camada de segurança para garantir que recebemos um int
    $id_conteudo_alt = intval($_POST['id_conteudo']);

    // O operador ternário valida se o valor é "publico" ou "privado"
    $nova_visibilidade = $_POST['nova_visibilidade'] === 'publico' ? 'publico' : 'privado';

    // O id_utilizador = ? garante que o artista só pode alterar as próprias músicas
    $sql_alt = "UPDATE conteudos SET visibilidade = ? WHERE id = ? AND id_utilizador = ?";
    $cmd_alt = $ligacao->prepare($sql_alt);
    $cmd_alt->bind_param("sii", $nova_visibilidade, $id_conteudo_alt, $id_utilizador);
    if ($cmd_alt->execute()) {
        $mensagem_visibilidade = "Visibilidade atualizada com sucesso";
    } else {
        $mensagem_visibilidade = "Erro ao atualizar visibilidade";
    }
    $cmd_alt->close();
}

$mensagem_apagar = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apagar_upload'])) {
    $id_conteudo_apagar = intval($_POST['id_conteudo']);

    $sql_fichs = "SELECT caminho_ficheiro, caminho_capa FROM conteudos WHERE id = ? AND id_utilizador = ?";
    $cmd_fichs = $ligacao->prepare($sql_fichs);
    $cmd_fichs->bind_param("ii", $id_conteudo_apagar, $id_utilizador);
    $cmd_fichs->execute();
    $res_fichs = $cmd_fichs->get_result();

    if ($res_fichs->num_rows > 0) {
        $fichs = $res_fichs->fetch_assoc();
        if ($fichs['caminho_ficheiro'] && file_exists($fichs['caminho_ficheiro'])) unlink($fichs['caminho_ficheiro']);
        if ($fichs['caminho_capa'] && file_exists($fichs['caminho_capa'])) unlink($fichs['caminho_capa']);

        $sql_del = "DELETE FROM conteudos WHERE id = ?";
        $cmd_del = $ligacao->prepare($sql_del);
        $cmd_del->bind_param("i", $id_conteudo_apagar);
        if ($cmd_del->execute()) {
            $mensagem_apagar = "Upload removido com sucesso!";
        } else {
            $mensagem_apagar = "Erro ao apagar registo da base de dados.";
        }
        $cmd_del->close();
    } else {
        $mensagem_apagar = "Conteúdo não encontrado ou sem permissão para apagar.";
    }
    $cmd_fichs->close();
}

$mensagem_concerto = "";

/**
 * Anúncio de Concertos (Agenda)
 * Guarda a data e as coordenadas geográficas de um espetáculo
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['anunciar_concerto'])) {
    $local_nome = trim($_POST['local_nome']);

    // floatval converte o valor submetido numa coordenada numérica (número com casas decimais)
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $data_concerto = $_POST['data_concerto'];

    // Validação Servidor de campos obrigatórios e coordenadas válidas
    if (empty($local_nome) || empty($data_concerto)) {
        $mensagem_concerto = "Por favor, preenche o local e a data do concerto";
    } elseif ($latitude == 0 && $longitude == 0) {
        $mensagem_concerto = "Não foi possível encontrar as coordenadas do local. Tenta ser mais específico (ex: 'Coliseu dos Recreios, Lisboa').";
    } else {
        // prepared statement para inserir os detalhes da tour na BD
        $sql_concerto = "INSERT INTO concertos (id_utilizador, local_nome, latitude, longitude, data_concerto) VALUES (?, ?, ?, ?, ?)";
        $cmd_conc = $ligacao->prepare($sql_concerto);

        // "isdds": int, string, double(float), double(float), string
        $cmd_conc->bind_param("isdds", $id_utilizador, $local_nome, $latitude, $longitude, $data_concerto);

        if ($cmd_conc->execute()) {
            $mensagem_concerto = "Concerto anunciado com sucesso!";

            // Chama a função de notificação construída acima
            $msg_notif = "Novo concerto agendado por " . $_SESSION['nome'] . " em {$local_nome}!";
            gerar_notificacoes($ligacao, $id_utilizador, $id_distrito_artista, null, $msg_notif, "index.php");
        } else {
            $mensagem_concerto = "Erro ao registar o concerto";
        }
        $cmd_conc->close();
    }
}

/**
 * Cancelamento / Eliminação de Concertos
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apagar_concerto'])) {
    $id_concerto_apagar = intval($_POST['id_concerto']);

    // DELETE rigoroso: apaga o concerto apenas se for mesmo do utilizador autenticado
    $sql_apagar = "DELETE FROM concertos WHERE id = ? AND id_utilizador = ?";
    $cmd_apagar = $ligacao->prepare($sql_apagar);
    $cmd_apagar->bind_param("ii", $id_concerto_apagar, $id_utilizador);
    $cmd_apagar->execute();
    $cmd_apagar->close();
    $mensagem_concerto = "Concerto removido.";
}

/**
 * Upload Unitário (1 Ficheiro Multimédia por vez)
 * Trata ficheiros MP3/WAV e também uploads de imagens de capa opcionais
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_simples'])) {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);

    // Se o artista escolheu um estilo, converte para int, senão null
    $id_estilo = !empty($_POST['id_estilo']) ? intval($_POST['id_estilo']) : null;
    $visibilidade = $_POST['visibilidade'];

    // O distrito fica automaticamente trancado ao perfil do artista
    $id_categoria = $id_distrito_artista;

    // Validação Servidor: Garantir que título não está vazio
    if (empty($titulo)) {
        $mensagem = "Por favor, preenche o título da música ou capa.";
    } else {

        // Verifica se a superglobal $_FILES existe e se não houve erro no upload do form para o servidor
        // error = 0 significa UPLOAD_ERR_OK
        if (isset($_FILES['ficheiro']) && $_FILES['ficheiro']['error'] == 0) {
            $ficheiro = $_FILES['ficheiro'];

            // basename() extrai apenas o nome e extensão original do ficheiro por segurança
            $nome_ficheiro = basename($ficheiro['name']);
            $tipo_mime = $ficheiro['type'];

            // Caminho relativo da pasta onde se guarda os ficheiros multimédia
            $pasta_destino = 'uploads/';

            // is_dir() verifica se a pasta uploads existe, e mkdir() cria-a com permissões de leitura/escrita caso não exista
            if (!is_dir($pasta_destino)) {
                mkdir($pasta_destino, 0777, true);
            }

            // A função pathinfo com a flag PATHINFO_EXTENSION retira apenas a extensão (ex. mp3)
            // strtolower() converte para minúsculas para não haver problemas de diferenciação
            $extensao = strtolower(pathinfo($nome_ficheiro, PATHINFO_EXTENSION));

            // A função uniqid() gera um nome totalmente aleatório baseada no relógio do sistema
            // Isto impede que o "ficheiro.mp3" do User1 esmague o "ficheiro.mp3" do User2
            $novo_nome = uniqid() . '.' . $extensao;
            $caminho_completo = $pasta_destino . $novo_nome;

            // Segurança: Restrição de Tipos de Ficheiro (Whitelist) permitidos (Áudio e Imagem)
            $permitidos = ['mp3', 'wav', 'jpg', 'jpeg', 'png'];

            // in_array() verifica se a extensão do ficheiro do utilizador está dentro da nossa Whitelist
            if (in_array($extensao, $permitidos)) {

                // move_uploaded_file() é uma função de segurança do PHP que move o ficheiro temporário para a pasta destino
                if (move_uploaded_file($ficheiro['tmp_name'], $caminho_completo)) {

                    /**
                     * Processar Imagem de Capa Auxiliar (Opcional)
                     * Mesma lógica, mas para o segundo campo de ficheiro do form
                     */
                    $caminho_capa_final = null;
                    if (isset($_FILES['ficheiro_capa']) && $_FILES['ficheiro_capa']['error'] == 0) {
                        $capa = $_FILES['ficheiro_capa'];
                        $ext_capa = strtolower(pathinfo($capa['name'], PATHINFO_EXTENSION));
                        if (in_array($ext_capa, ['jpg', 'jpeg', 'png'])) {
                            $novo_nome_capa = uniqid() . '_capa.' . $ext_capa;
                            $caminho_capa_final = $pasta_destino . $novo_nome_capa;
                            move_uploaded_file($capa['tmp_name'], $caminho_capa_final);
                        }
                    }

                    // Gravação do caminho do ficheiro (e metadados) na base de dados
                    $sql = "INSERT INTO conteudos (id_utilizador, id_categoria, id_estilo, titulo, descricao, caminho_ficheiro, tipo_ficheiro, caminho_capa, visibilidade) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $comando = $ligacao->prepare($sql);
                    $comando->bind_param("iiissssss", $id_utilizador, $id_categoria, $id_estilo, $titulo, $descricao, $caminho_completo, $tipo_mime, $caminho_capa_final, $visibilidade);

                    if ($comando->execute()) {
                        $mensagem = "Upload da obra '$titulo' realizado com sucesso!";

                        // Envio automático de Notificações
                        $msg_notif = "Novo lançamento: '{$titulo}' por " . $_SESSION['nome'];
                        $link_notif = "index.php?distrito={$id_categoria}" . ($id_estilo ? "&estilo={$id_estilo}" : "");
                        gerar_notificacoes($ligacao, $id_utilizador, $id_categoria, $id_estilo, $msg_notif, $link_notif);
                    } else {
                        $mensagem = "Erro ao guardar na base de dados.";
                    }
                    $comando->close();
                } else {
                    $mensagem = "Erro ao mover o ficheiro para a pasta de uploads no servidor.";
                }
            } else {
                $mensagem = "Tipo de ficheiro não permitido. Apenas envios de Áudio (MP3/WAV) ou Imagens de Capa (JPG/PNG).";
            }
        } else {
            $mensagem = "Por favor, seleciona um ficheiro válido.";
        }
    }
}

$mensagem_lote = "";

/**
 * Upload em Lote (Zip + XML Parsing)
 * Esta secção lê um ficheiro ZIP, extrai as músicas e um descritivo XML,
 * e importa tudo automaticamente para a plataforma de forma autónoma
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_lote'])) {
    if (isset($_FILES['ficheiro_zip']) && $_FILES['ficheiro_zip']['error'] == 0) {
        $zip_ficheiro = $_FILES['ficheiro_zip'];
        $extensao_zip = strtolower(pathinfo($zip_ficheiro['name'], PATHINFO_EXTENSION));

        // Tem de ser ficheiro com a extensão '.zip'
        if ($extensao_zip !== 'zip') {
            $mensagem_lote = "Por favor, envia um ficheiro no formato .zip";
        } else {

            // Cria uma pasta temporária (ex. uploads/temp_ab34x9/) exclusiva para este processo e evita colisões
            $pasta_temp = 'uploads/temp_' . uniqid() . '/';
            mkdir($pasta_temp, 0777, true);

            // A classe nativa ZipArchive do PHP abre e extrai o ficheiro submetido
            $zip = new ZipArchive();
            if ($zip->open($zip_ficheiro['tmp_name']) === TRUE) {

                // Despeja todo o conteúdo do ZIP para a pasta temp
                $zip->extractTo($pasta_temp);
                $zip->close();

                // scandir() lê todos os ficheiros da pasta extraída e procura um '.xml'
                $ficheiro_xml = null;
                $ficheiros_extraidos = scandir($pasta_temp);
                foreach ($ficheiros_extraidos as $f) {
                    if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'xml') {
                        $ficheiro_xml = $pasta_temp . $f;
                        break;
                    }
                }

                // Se o utilizador se esqueceu do XML, não há como prosseguir
                if ($ficheiro_xml === null) {
                    $mensagem_lote = "Erro: o ficheiro ZIP não contém nenhum ficheiro XML com a metainformação";
                } else {

                    // simplexml_load_file() é uma função que converte todo o ficheiro XML num objeto PHP
                    $xml = simplexml_load_file($ficheiro_xml);

                    if ($xml === false) {
                        $mensagem_lote = "Erro: o ficheiro XML está mal formatado";
                    } else {
                        // Contadores e arrays para fazer um sumário/relatório ao utilizador no final do processo
                        $total_inseridos = 0;
                        $erros_faixas = [];

                        $pasta_destino = 'uploads/';
                        $permitidos = ['mp3', 'wav', 'jpg', 'jpeg', 'png'];
                        $id_categoria = $id_distrito_artista;

                        // Percorre a tag <faixa> dentro do objeto XML
                        foreach ($xml->faixa as $faixa) {

                            // Extraímos o texto puro das tags, ex: <titulo>Texto</titulo>, através de cast para string
                            $nome_fich = (string) $faixa->ficheiro;
                            $titulo_faixa = (string) $faixa->titulo;
                            $desc_faixa = isset($faixa->descricao) ? (string) $faixa->descricao : '';
                            $estilo_nome = isset($faixa->estilo) ? trim((string) $faixa->estilo) : '';
                            $nome_capa = isset($faixa->capa) ? (string) $faixa->capa : null;
                            $vis_faixa = isset($faixa->visibilidade) ? (string) $faixa->visibilidade : 'publico';

                            // Validar se o mp3 mencionado no XML existe fisicamente na pasta onde o ZIP foi extraído
                            $caminho_fich_temp = $pasta_temp . $nome_fich;
                            if (!file_exists($caminho_fich_temp)) {
                                $erros_faixas[] = "'$nome_fich' referenciado no XML mas não encontrado no ZIP";
                                continue;
                            }

                            // Validação de formato para prevenir scripts maliciosos de entrarem no ZIP
                            $ext_faixa = strtolower(pathinfo($nome_fich, PATHINFO_EXTENSION));
                            if (!in_array($ext_faixa, $permitidos)) {
                                $erros_faixas[] = "'$nome_fich' tem extensão não permitida ($ext_faixa)";
                                continue;
                            }

                            // Processar e mover a Capa (caso esteja descrita no XML e conste no ZIP)
                            $caminho_capa_final = null;
                            if ($nome_capa) {
                                $caminho_capa_temp = $pasta_temp . $nome_capa;
                                if (file_exists($caminho_capa_temp)) {
                                    $ext_capa = strtolower(pathinfo($nome_capa, PATHINFO_EXTENSION));
                                    if (in_array($ext_capa, ['jpg', 'jpeg', 'png'])) {
                                        $novo_nome_capa = uniqid() . '_capa.' . $ext_capa;
                                        $caminho_capa_final = $pasta_destino . $novo_nome_capa;
                                        copy($caminho_capa_temp, $caminho_capa_final);
                                    } else {
                                        $erros_faixas[] = "Capa '$nome_capa' tem extensão não permitida.";
                                    }
                                } else {
                                    $erros_faixas[] = "Capa '$nome_capa' referenciada no XML não foi encontrada no ZIP.";
                                }
                            }

                            //Tentar ligar o estilo escrito no XML à base de dados automaticamente
                            $id_estilo_faixa = null;
                            if (!empty($estilo_nome)) {
                                $sql_est = "SELECT id FROM estilos_musicais WHERE nome = ?";
                                $cmd_est = $ligacao->prepare($sql_est);
                                $cmd_est->bind_param("s", $estilo_nome);
                                $cmd_est->execute();
                                $res_est = $cmd_est->get_result();

                                // Se o estilo do XML já existir no sistema, obtemos o seu ID numérico
                                if ($res_est->num_rows > 0) {
                                    $id_estilo_faixa = $res_est->fetch_assoc()['id'];
                                } else {
                                    //Se não existir, o sistema insere o novo género musical e recolhe o ID (insert_id)
                                    $sql_criar_est = "INSERT INTO estilos_musicais (nome, id_criador) VALUES (?, ?)";
                                    $cmd_criar = $ligacao->prepare($sql_criar_est);
                                    $cmd_criar->bind_param("si", $estilo_nome, $id_utilizador);
                                    $cmd_criar->execute();

                                    // insert_id é uma ferramenta que apanha a chave primária que acabou de ser gerada
                                    $id_estilo_faixa = $ligacao->insert_id;
                                    $cmd_criar->close();
                                }
                                $cmd_est->close();
                            }

                            // Mover música / imagem final da pasta temporária para a pasta final do site
                            $novo_nome_fich = uniqid() . '.' . $ext_faixa;
                            $caminho_final = $pasta_destino . $novo_nome_fich;

                            // rename() movimenta os ficheiros dentro do servidor
                            if (rename($caminho_fich_temp, $caminho_final)) {

                                // Detetamos qual o MIME Type de acordo com a extensão isto é necessário para o HTML <audio>
                                $mimes = ['mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

                                // Operador de null coalescing ?? - se não existir no array, usa o fallback genérico application/octet-stream (binario para baixar o ficheiro)
                                $tipo_mime_faixa = $mimes[$ext_faixa] ?? 'application/octet-stream';

                                // INSERT global do ficheiro atual processado na iteração do foreach
                                $sql_ins = "INSERT INTO conteudos (id_utilizador, id_categoria, id_estilo, titulo, descricao, caminho_ficheiro, tipo_ficheiro, caminho_capa, visibilidade) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                $cmd_ins = $ligacao->prepare($sql_ins);
                                $cmd_ins->bind_param("iiissssss", $id_utilizador, $id_categoria, $id_estilo_faixa, $titulo_faixa, $desc_faixa, $caminho_final, $tipo_mime_faixa, $caminho_capa_final, $vis_faixa);

                                if ($cmd_ins->execute()) {
                                    $total_inseridos++;

                                    $msg_notif = "Novo lançamento: '{$titulo_faixa}' por " . $_SESSION['nome'];
                                    $link_notif = "index.php?distrito={$id_categoria}" . ($id_estilo_faixa ? "&estilo={$id_estilo_faixa}" : "");
                                    gerar_notificacoes($ligacao, $id_utilizador, $id_categoria, $id_estilo_faixa, $msg_notif, $link_notif);
                                } else {
                                    $erros_faixas[] = "Erro ao guardar '$titulo_faixa' na base de dados";
                                }
                                $cmd_ins->close();
                            } else {
                                $erros_faixas[] = "Erro ao mover '$nome_fich' para a pasta de uploads";
                            }
                        }

                        /**
                         * Limpeza Final - Garbage Collection
                         * No final da importação, é importante apagar a pasta extraída que já está inútil
                         * glob() apanha todos os ficheiros restantes, e unlink() apaga o ficheiro do disco
                         */
                        $restos = glob($pasta_temp . '*');
                        foreach ($restos as $resto) {
                            if (is_file($resto))
                                unlink($resto);
                        }
                        // rmdir() remove a pasta de vez (o @ silencia avisos se a pasta falhar o delete)
                        @rmdir($pasta_temp);

                        // Report final das ações da importação com função implode para mostrar os erros com barras |
                        if ($total_inseridos > 0 && empty($erros_faixas)) {
                            $mensagem_lote = "Upload em lote concluído com sucesso! $total_inseridos faixa(s) publicada(s).";
                        } elseif ($total_inseridos > 0) {
                            $mensagem_lote = "Upload parcial: $total_inseridos faixa(s) publicada(s). Erros: " . implode(' | ', $erros_faixas);
                        } else {
                            $mensagem_lote = "Nenhuma faixa foi publicada. Erros: " . implode(' | ', $erros_faixas);
                        }
                    }
                }
            } else {
                $mensagem_lote = "Erro ao abrir o ficheiro ZIP";
                @rmdir($pasta_temp);
            }
        }
    } else {
        $mensagem_lote = "Por favor, seleciona um ficheiro ZIP válido";
    }
}

/**
 * Buscas Secundárias Finais
 * Consultas para popular dropdowns e as tabelas com a listagem dos dados para o painel de controlo
 */

$sql_estilos = "SELECT id, nome FROM estilos_musicais ORDER BY nome ASC";
$resultado_estilos = $ligacao->query($sql_estilos);

// Todos os uploads criados pelo utilizador para a grelha
$sql_meus = "SELECT c.id, c.titulo, c.tipo_ficheiro, c.visibilidade, c.data_upload, cat.nome AS distrito, e.nome AS estilo 
             FROM conteudos c 
             LEFT JOIN categorias cat ON c.id_categoria = cat.id 
             LEFT JOIN estilos_musicais e ON c.id_estilo = e.id 
             WHERE c.id_utilizador = ? 
             ORDER BY c.data_upload DESC";
$cmd_meus = $ligacao->prepare($sql_meus);
$cmd_meus->bind_param("i", $id_utilizador);
$cmd_meus->execute();
$resultado_meus = $cmd_meus->get_result();

// Todos os concertos associados ao artista
$sql_concertos = "SELECT id, local_nome, data_concerto, data_criacao FROM concertos WHERE id_utilizador = ? ORDER BY data_concerto ASC";
$cmd_conc_lista = $ligacao->prepare($sql_concertos);
$cmd_conc_lista->bind_param("i", $id_utilizador);
$cmd_conc_lista->execute();
$resultado_concertos = $cmd_conc_lista->get_result();

// Sino das Notificações Rápidas do Topo do Ecrã
$notificacoes = [];
$num_notificacoes = 0;
$sql_busca_notif = "SELECT id, mensagem, link, data_criacao FROM notificacoes WHERE id_utilizador = ? AND lida = 0 ORDER BY data_criacao DESC LIMIT 10";
$cmd_busca_notif = $ligacao->prepare($sql_busca_notif);
$cmd_busca_notif->bind_param("i", $id_utilizador);
$cmd_busca_notif->execute();
$res_busca_notif = $cmd_busca_notif->get_result();
while ($n = $res_busca_notif->fetch_assoc()) {
    $notificacoes[] = $n;
}
$num_notificacoes = count($notificacoes);
$cmd_busca_notif->close();
?>
<!DOCTYPE html>
<html lang="pt-PT">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área do Artista</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="Style/style.css">
</head>

<body class="bg-dark text-light">

    <nav class="navbar navbar-expand-lg navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php" style="color:#E0E0E0">SoundCloud PT - Área do Artista</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navArtista">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navArtista">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Sino de Notificações -->
                    <li class="nav-item dropdown me-2">
                        <a class="nav-link dropdown-toggle text-warning" href="#" id="notifDropdownArt" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill"></i> <span
                                class="badge bg-danger rounded-pill"><?php echo $num_notificacoes > 0 ? $num_notificacoes : ''; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="notifDropdownArt"
                            style="width: 300px; max-height: 400px; overflow-y: auto;">
                            <li class="dropdown-header">Notificações</li>
                            <?php if ($num_notificacoes > 0): ?>
                                <?php foreach ($notificacoes as $notif): ?>
                                    <li>
                                        <a class="dropdown-item py-2 border-bottom" style="white-space: normal;"
                                            href="<?php echo htmlspecialchars($notif['link']); ?>">
                                            <small
                                                class="text-muted d-block"><?php echo date('d/m H:i', strtotime($notif['data_criacao'])); ?></small>
                                            <?php echo htmlspecialchars($notif['mensagem']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li>
                                    <form action="ler_notificacoes.php" method="POST" class="px-3 py-2 text-center">
                                        <button type="submit" name="marcar_lidas"
                                            class="btn btn-sm btn-outline-secondary w-100">Marcar todas como lidas</button>
                                    </form>
                                </li>
                            <?php else: ?>
                                <li><span class="dropdown-item text-muted">Sem novas notificações.</span></li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <li class="nav-item me-2">
                        <a href="interesses.php" class="btn btn-sm"
                            style="color: #00D1FF; border: 1px solid #00D1FF;"><i class="bi bi-star"></i> Interesses</a>
                    </li>
                    <li class="nav-item me-2">
                        <a href="perfil.php" class="btn btn-sm" style="color: #FF5500; border: 1px solid #FF5500;">Meu
                            Perfil</a>
                    </li>
                    <li class="nav-item me-3">
                        <span class="navbar-text text-white" style="font-size: 0.9rem;">Olá,
                            <?php echo htmlspecialchars($_SESSION['nome']); ?></span>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="btn btn-outline-secondary btn-sm"
                            style="color: #E0E0E0; border-color: #555;">Sair</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Info do Distrito do Artista -->
        <div class="d-flex justify-content-between align-items-center mb-4"
            style="background: rgba(30, 30, 32, 0.4); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 12px; padding: 15px 25px;">
            <div style="font-size: 0.95rem;">
                <span style="color: #E0E0E0;">O Teu Distrito Base:</span>
                <strong style="color: #fff; margin-left: 5px;"><?php echo htmlspecialchars($nome_distrito); ?></strong>
                <small class="ms-2" style="color: #8E8E93;">— Todas as tuas músicas são publicadas neste
                    distrito.</small>
            </div>
            <a href="perfil.php" class="btn btn-outline-secondary btn-sm"
                style="color: #E0E0E0; border-color: #555;">Alterar</a>
        </div>

        <!-- Secção de Upload Unitário -->
        <div class="glass-panel">
            <h3>Novo Lançamento (Upload Unitário)</h3>

            <?php if (!empty($mensagem)): ?>
                <div class="alert <?php echo (strpos($mensagem, 'sucesso') !== false) ? 'text-success border-success' : 'text-warning border-warning'; ?> py-2"
                    style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                    <?php echo $mensagem; ?>
                </div>
            <?php endif; ?>

            <form action="artista_dashboard.php" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.9rem;">Título da
                            Música ou Capa</label>
                        <input type="text" name="titulo" class="form-control form-control-custom"
                            placeholder="Ex: Batida de Fado Acústico" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.9rem;">Estilo
                            Musical</label>
                        <select name="id_estilo" class="form-select form-select-custom">
                            <option value="">-- Sem estilo específico --</option>
                            <?php while ($estilo = $resultado_estilos->fetch_assoc()): ?>
                                <option value="<?php echo $estilo['id']; ?>">
                                    <?php echo htmlspecialchars($estilo['nome']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text" style="color: #9E9E9E; font-size: 0.8rem;">
                            Não encontras o teu estilo? <a href="#" data-bs-toggle="collapse"
                                data-bs-target="#criarEstilo" style="color: #00D1FF; text-decoration: none;">Cria um
                                novo →</a>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 mb-3">
                        <label class="form-label"
                            style="font-weight: 600; color: #fff; font-size: 0.9rem;">Descrição</label>
                        <textarea name="descricao" class="form-control form-control-custom" rows="3"
                            placeholder="Fala-nos um pouco sobre este lançamento..."></textarea>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.9rem;">Privacidade
                            / Visibilidade</label>
                        <select name="visibilidade" class="form-select form-select-custom" required>
                            <option value="publico">Público (Visível para toda a gente)</option>
                            <option value="privado">Privado (Apenas para utilizadores logados)</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.9rem;">Ficheiro
                            Multimédia (Áudio/Imagem) <span class="text-danger">*</span></label>
                        <input type="file" name="ficheiro" class="form-control form-control-custom"
                            style="padding: 10px;" accept=".mp3,.wav,.jpg,.jpeg,.png" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.9rem;">Imagem de
                            Capa (Opcional)</label>
                        <input type="file" name="ficheiro_capa" class="form-control form-control-custom"
                            style="padding: 10px;" accept=".jpg,.jpeg,.png">
                        <div class="form-text" style="color: #9E9E9E; font-size: 0.8rem;">Associar uma capa específica a
                            esta música.</div>
                    </div>
                </div>
                <button type="submit" name="upload_simples" class="btn btn-laranja w-100 fw-bold mt-2"
                    style="height: 48px;">Enviar Ficheiro</button>
            </form>

            <!-- Mini-formulário colapsável para criar um novo estilo musical -->
            <div class="collapse mt-3" id="criarEstilo">
                <div class="card card-body"
                    style="background-color: var(--color-surface); border: 1px solid var(--color-border); color: #fff;">
                    <h6 class="fw-bold mb-2">Criar Novo Estilo Musical</h6>
                    <?php if (!empty($mensagem_estilo)): ?>
                        <div class="alert <?php echo (strpos($mensagem_estilo, 'sucesso') !== false) ? 'text-success border-success' : 'text-warning border-warning'; ?> py-2 mb-2"
                            style="background-color: rgba(255,255,255,0.05); font-size: 0.85rem;">
                            <?php echo $mensagem_estilo; ?>
                        </div>
                    <?php endif; ?>
                    <form action="artista_dashboard.php" method="POST">
                        <div class="input-group">
                            <input type="text" name="novo_estilo" class="form-control form-control-custom"
                                placeholder="Ex: Lo-Fi, Reggaeton, Jazz Fusion..." required>
                            <button type="submit" name="criar_estilo" class="btn btn-success fw-bold"
                                style="border-radius: 0 8px 8px 0; background-color: var(--color-tertiary); color: #000; border: none;">Criar</button>
                        </div>
                        <div class="form-text mt-1" style="color: #9E9E9E; font-size: 0.8rem;">Após criares, o novo
                            estilo aparecerá na lista acima.</div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Secção de Upload em Lote (ZIP + XML) -->
        <div class="glass-panel">
            <h3>Lançamento em Lote (ZIP + XML)</h3>

            <?php if (!empty($mensagem_lote)): ?>
                <div class="alert <?php echo (strpos($mensagem_lote, 'sucesso') !== false) ? 'text-success border-success' : 'text-warning border-warning'; ?> py-2"
                    style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                    <?php echo $mensagem_lote; ?>
                </div>
            <?php endif; ?>

            <p class="mb-3" style="color: #9E9E9E; font-size: 0.95rem;">
                Envia várias músicas e capas de uma só vez! Prepara um ficheiro <code
                    style="color: #00D1FF;">.zip</code> contendo os ficheiros multimédia
                e um ficheiro <code style="color: #FF5500;">metadados.xml</code> com a informação de cada faixa.
            </p>

            <div class="accordion mb-3" id="exemploXML"
                style="--bs-accordion-bg: rgba(255,255,255,0.05); --bs-accordion-color: #fff; --bs-accordion-border-color: rgba(255,255,255,0.1);">
                <div class="accordion-item" style="border-radius: 8px; overflow: hidden;">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed shadow-none" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseExemplo" style="background-color: transparent; color: #fff;">
                            Ver exemplo de ficheiro XML
                        </button>
                    </h2>
                    <div id="collapseExemplo" class="accordion-collapse collapse" data-bs-parent="#exemploXML">
                        <div class="accordion-body">
                            <pre class="p-3 rounded"
                                style="background-color: #000; color: #E0E0E0; border: 1px solid rgba(255,255,255,0.1);"><code style="color: #E0E0E0;">&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;album&gt;
  &lt;faixa&gt;
    &lt;ficheiro&gt;musica1.mp3&lt;/ficheiro&gt;
    &lt;capa&gt;minha_capa.jpg&lt;/capa&gt; &lt;!-- Opcional --&gt;
    &lt;titulo&gt;Noite no Bairro&lt;/titulo&gt;
    &lt;descricao&gt;Beat trap com influências de fado&lt;/descricao&gt;
    &lt;estilo&gt;Trap&lt;/estilo&gt;
    &lt;visibilidade&gt;publico&lt;/visibilidade&gt;
  &lt;/faixa&gt;
  &lt;faixa&gt;
    &lt;ficheiro&gt;concerto.png&lt;/ficheiro&gt;
    &lt;titulo&gt;Cartaz do Concerto&lt;/titulo&gt;
    &lt;descricao&gt;Dia 25 no Porto&lt;/descricao&gt;
    &lt;visibilidade&gt;publico&lt;/visibilidade&gt;
  &lt;/faixa&gt;
&lt;/album&gt;</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <form action="artista_dashboard.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.9rem;">Ficheiro
                        ZIP</label>
                    <input type="file" name="ficheiro_zip" class="form-control form-control-custom"
                        style="padding: 10px;" accept=".zip" required>
                    <div class="form-text mt-2" style="color: #9E9E9E; font-size: 0.8rem;">O ZIP deve conter os
                        ficheiros multimédia (MP3/WAV/JPG/PNG) e um ficheiro <code
                            style="color: #FF5500;">metadados.xml</code>.</div>
                </div>
                <button type="submit" name="upload_lote" class="btn btn-outline-light w-100 fw-bold mt-2"
                    style="height: 48px; border-color: rgba(255,255,255,0.3);">Enviar Lote (ZIP)</button>
            </form>
        </div>

        <!-- Secção: Os Meus Uploads -->
        <?php if ($resultado_meus->num_rows > 0): ?>
            <div class="glass-panel">
                <h3>Os Meus Uploads</h3>

                <?php if (!empty($mensagem_visibilidade)): ?>
                    <div class="alert text-success border-success py-2"
                        style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                        <?php echo $mensagem_visibilidade; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($mensagem_apagar)): ?>
                    <div class="alert text-info border-info py-2"
                        style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                        <?php echo $mensagem_apagar; ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Estilo</th>
                                <th>Distrito</th>
                                <th>Visibilidade</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($conteudo = $resultado_meus->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold" style="color: #fff;">
                                        <?php echo htmlspecialchars($conteudo['titulo']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (strpos($conteudo['tipo_ficheiro'], 'audio') !== false) {
                                            echo '<span class="badge" style="background-color: #00D1FF; color: #000;">Áudio</span>';
                                        } else {
                                            echo '<span class="badge" style="background-color: #FF5500; color: #fff;">Imagem</span>';
                                        }
                                        ?>
                                    </td>
                                    <td style="color: #fff;">
                                        <?php echo $conteudo['estilo'] ? htmlspecialchars($conteudo['estilo']) : '<span style="color: #555;">—</span>'; ?>
                                    </td>
                                    <td style="color: #fff;"><?php echo htmlspecialchars($conteudo['distrito']); ?></td>
                                    <td>
                                        <form action="artista_dashboard.php" method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="id_conteudo" value="<?php echo $conteudo['id']; ?>">
                                            <select name="nova_visibilidade" class="form-select form-select-sm me-2"
                                                style="background-color: #1A1A1E; border: 1px solid var(--color-border); color: #fff; min-width: 90px;">
                                                <option value="publico" <?php echo $conteudo['visibilidade'] == 'publico' ? 'selected' : ''; ?>>Público</option>
                                                <option value="privado" <?php echo $conteudo['visibilidade'] == 'privado' ? 'selected' : ''; ?>>Privado</option>
                                            </select>
                                            <button type="submit" name="alterar_visibilidade"
                                                class="btn btn-outline-secondary btn-sm me-2"
                                                style="color: #E0E0E0; border-color: #555;">Guardar</button>
                                            <button type="submit" name="apagar_upload"
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Tem a certeza que deseja apagar esta faixa permanentemente?');"
                                                style="border-color: #dc3545; color: #dc3545;">Apagar</button>
                                        </form>
                                    </td>
                                    <td style="color: #9E9E9E;">
                                        <?php echo date('d/m/Y H:i', strtotime($conteudo['data_upload'])); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Secção: Anúncio de Concertos -->
        <div class="glass-panel">
            <div class="form-check d-flex align-items-center">
                <input class="form-check-input me-3" type="checkbox" id="checkConcerto" data-bs-toggle="collapse"
                    data-bs-target="#secConcerto"
                    style="width: 20px; height: 20px; background-color: #1A1A1E; border: 1px solid var(--color-border); cursor: pointer;">
                <label class="form-check-label mb-0" for="checkConcerto"
                    style="font-family: var(--font-headline); font-weight: 700; color: #fff; font-size: 1.15rem; cursor: pointer; user-select: none;">Anunciar
                    um Concerto?</label>
            </div>
            <div class="collapse mt-3" id="secConcerto">
                <div>
                    <?php if (!empty($mensagem_concerto)): ?>
                        <div class="alert <?php echo (strpos($mensagem_concerto, 'sucesso') !== false || strpos($mensagem_concerto, 'removido') !== false) ? 'text-success border-success' : 'text-warning border-warning'; ?> py-2"
                            style="background-color: rgba(255,255,255,0.05); font-size: 0.9rem;">
                            <?php echo $mensagem_concerto; ?>
                        </div>
                    <?php endif; ?>

                    <form id="formConcerto" action="artista_dashboard.php" method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"
                                    style="font-weight: 600; color: #fff; font-size: 0.9rem;">Local do Concerto</label>
                                <input type="text" name="local_nome" id="localConcerto"
                                    class="form-control form-control-custom" placeholder="Ex: Hard Rock Cafe, Lisboa"
                                    required>
                                <div class="form-text mt-2" style="color: #9E9E9E; font-size: 0.8rem;">Escreve o nome do
                                    local e a cidade. O sistema irá localizá-lo automaticamente no mapa.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" style="font-weight: 600; color: #fff; font-size: 0.9rem;">Data
                                    e Hora</label>
                                <input type="datetime-local" name="data_concerto"
                                    class="form-control form-control-custom" style="color-scheme: dark;" required>
                            </div>
                        </div>
                        <input type="hidden" name="latitude" id="latConcerto" value="0">
                        <input type="hidden" name="longitude" id="lngConcerto" value="0">
                        <input type="hidden" name="anunciar_concerto" value="1">
                        <div id="geocodeErro" class="alert text-danger border-danger py-2 d-none"
                            style="background-color: rgba(255,0,0,0.1); font-size: 0.9rem;"></div>
                        <button type="submit" class="btn btn-outline-success w-100 fw-bold mt-2" id="btnConcerto"
                            style="height: 48px;">
                            <i class="fa-solid fa-location-dot"></i> Publicar Concerto
                        </button>
                    </form>

                    <?php if ($resultado_concertos->num_rows > 0): ?>
                        <hr style="border-color: rgba(255,255,255,0.1); margin: 30px 0;">
                        <h6 class="fw-bold mb-3" style="color: #fff;">Os Meus Concertos Anunciados</h6>
                        <div class="table-responsive">
                            <table class="table table-custom table-sm">
                                <thead>
                                    <tr>
                                        <th>Local</th>
                                        <th>Data</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($conc = $resultado_concertos->fetch_assoc()): ?>
                                        <tr>
                                            <td style="color: #fff;"><?php echo htmlspecialchars($conc['local_nome']); ?></td>
                                            <td style="color: #9E9E9E;">
                                                <?php echo date('d/m/Y H:i', strtotime($conc['data_concerto'])); ?>
                                            </td>
                                            <td>
                                                <form action="artista_dashboard.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="id_concerto" value="<?php echo $conc['id']; ?>">
                                                    <button type="submit" name="apagar_concerto"
                                                        class="btn btn-outline-danger btn-sm"
                                                        style="background-color: transparent;">Remover</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="text-center mt-4 mb-5">
            <a href="index.php" class="text-decoration-none"
                style="color: #9E9E9E; font-size: 0.85rem; transition: 0.3s;">← Voltar à página inicial</a>
        </div>
    </div>

    <!-- Bootstrap JS para o collapse funcionar -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (!empty($mensagem_estilo)): ?>
        <!-- Abrir automaticamente o painel se houve uma mensagem de criação de estilo -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var collapseEl = document.getElementById('criarEstilo');
                var bsCollapse = new bootstrap.Collapse(collapseEl, { show: true });
            });
        </script>
    <?php endif; ?>

    <!-- Script de Geocodificação Nominatim (OpenStreetMap) -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('formConcerto');
            if (form) {
                form.addEventListener('submit', function (e) {
                    var localInput = document.getElementById('localConcerto');
                    var latInput = document.getElementById('latConcerto');
                    var lngInput = document.getElementById('lngConcerto');
                    var erroDiv = document.getElementById('geocodeErro');
                    var btn = document.getElementById('btnConcerto');

                    // Se já tem coordenadas válidas, deixar submeter
                    if (parseFloat(latInput.value) !== 0 || parseFloat(lngInput.value) !== 0) {
                        return true;
                    }

                    // Caso contrário, parar o submit e ir buscar ao Nominatim
                    e.preventDefault();
                    erroDiv.classList.add('d-none');
                    btn.disabled = true;
                    btn.textContent = 'A localizar...';

                    var query = encodeURIComponent(localInput.value);
                    fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + query + '&countrycodes=pt&limit=1')
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data.length > 0) {
                                latInput.value = data[0].lat;
                                lngInput.value = data[0].lon;
                                // Agora submeter o formulário
                                form.submit();
                            } else {
                                erroDiv.textContent = 'Não foi possível encontrar "' + localInput.value + '" em Portugal. Tenta reformular (ex: "Coliseu dos Recreios, Lisboa").';
                                erroDiv.classList.remove('d-none');
                                btn.disabled = false;
                                btn.textContent = '\ud83d\udccd Publicar Concerto';
                            }
                        })
                        .catch(function () {
                            erroDiv.textContent = 'Erro de ligação ao serviço de mapas. Verifica a tua internet.';
                            erroDiv.classList.remove('d-none');
                            btn.disabled = false;
                            btn.textContent = '\ud83d\udccd Publicar Concerto';
                        });
                });
            }
        });
    </script>
</body>

</html>