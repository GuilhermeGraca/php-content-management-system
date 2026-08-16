<?php
/**
 * Ficheiro de Configuração de Credenciais - EXEMPLO
 *
 * Renomear este ficheiro para 'credenciais.php' e preencher com os dados corretos.
 * O ficheiro original 'credenciais.php' NÃO deve ser enviado para o repositório público.
 */

// Chaves da API do Google reCAPTCHA v2
define('RECAPTCHA_CHAVE_SITE', 'SUA_CHAVE_SITE_AQUI');
define('RECAPTCHA_CHAVE_SECRETA', 'SUA_CHAVE_SECRETA_AQUI');

// Definições do Servidor SMTP para enviar emails
define('SMTP_HOST', 'smtp.gmail.com'); // Ex: smtp.gmail.com
define('SMTP_USER', 'seu_email@exemplo.com'); // Seu email
define('SMTP_PASS', 'sua_password_de_aplicacao'); // Password de aplicação
define('SMTP_PORT', 587); // Porta TLS (587) ou SSL (465)

?>
