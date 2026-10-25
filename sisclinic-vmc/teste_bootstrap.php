
<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo 'PASSO 1: PHP está a funcionar.<br>';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo 'PASSO 2: Sessão iniciada.<br>';

require_once __DIR__ . '/config/database.php';

echo 'PASSO 3: Base de dados carregada.<br>';

require_once __DIR__ . '/config/security.php';

echo 'PASSO 4: Segurança carregada.<br>';

require_once __DIR__ . '/includes/bootstrap.php';

echo 'PASSO 5: Bootstrap carregado.<br>';

echo 'TESTE CONCLUÍDO!';
