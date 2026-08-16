<?php
/**
 * Script de Término de Sessão (Logout)
 *
 * Limpa todos os dados de sessão do utilizador atual e redireciona-o 
 * para a página inicial, garante que o acesso a áreas restritas seja impedido
 */
session_start();

// Limpa todas as variáveis armazenadas na sessão atual
session_unset();

// Destrói a sessão no servidor, removendo o ficheiro físico da sessão
session_destroy();

// Redireciona o utilizador de volta para a página inicial
header("Location: index.php");
exit();
?>