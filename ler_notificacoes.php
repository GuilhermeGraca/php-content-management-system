<?php
/**
 * Processamento de Notificações
 *
 * Este ficheiro processa pedidos para marcar todas as notificações de um utilizador como "lidas"
 * Após a operação, o utilizador é redirecionado de volta para a página onde estava
 */
session_start();
require 'conexao.php';

// Verifica se o utilizador tem sessão iniciada, caso contrário redireciona para o login
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

/**
 * Verifica se o pedido é POST e se o botão 'marcar_lidas' foi pressionado
 * Esta validação do método HTTP previne que as notificações sejam marcadas 
 * acidentalmente ou maliciosamente através de um link GET
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['marcar_lidas'])) {
    $id_utilizador = $_SESSION['id'];

    // Atualiza a flag 'lida' para 1 (verdadeiro) apenas nas notificações do utilizador logado
    $sql_lidas = "UPDATE notificacoes SET lida = 1 WHERE id_utilizador = ?";
    $cmd_lidas = $ligacao->prepare($sql_lidas);
    $cmd_lidas->bind_param("i", $id_utilizador);
    $cmd_lidas->execute();
    $cmd_lidas->close();
}

/**
 * Redireciona o utilizador para a página anterior com o cabeçalho HTTP_REFERER
 * Caso o HTTP_REFERER não esteja definido (por ex. navegação direta ou bloqueio de browser)
 * utiliza o 'index.php' como fallback
 */
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: $referer");
exit();
?>