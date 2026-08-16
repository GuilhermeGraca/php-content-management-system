<?php
/**
 * Script de Ligação à Base de Dados
 *
 * Este ficheiro centraliza a lógica de conexão ao MySQL com a extensão MySQLi
 * Ao incluir este ficheiro noutras páginas, evitamos a repetição do código de ligação.
 */

// Definições da base de dados (os valores por defeito do XAMPP ou variáveis do Docker)
$servidor = getenv('DB_HOST') ?: "localhost";
$utilizador = getenv('DB_USER') ?: "root";
$palavra_passe = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$base_dados = getenv('DB_NAME') ?: "bd_soundcloud_pt";

// Tentar estabelecer a ligação ao servidor de base de dados
// O objeto mysqli gere a comunicação entre o PHP e o SGBD/Sistema de Gestão de Bases de Dados
$ligacao = new mysqli($servidor, $utilizador, $palavra_passe, $base_dados);

// Garantir que a comunicação suporta acentos e cedilhas (UTF-8)
$ligacao->set_charset("utf8mb4");

// Verificar se houve algum erro na ligação
if ($ligacao->connect_error) {
    // Interrompe a execução do script para não expor erros adicionais em caso de falha crítica
    die("Erro de ligação: " . $ligacao->connect_error);
}
?>