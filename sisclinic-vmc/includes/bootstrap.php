<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// Exige que o utilizador tenha iniciado sessão.
function require_login(): void
{
    if (empty($_SESSION['id_utilizador'])) {
        header('Location: /TCC-VMC/sisclinic-vmc/login.php');
        exit;
    }
}

// Exige acesso de administrador.
function require_admin(): void
{
    require_login();

    $perfil = strtolower(trim(
        (string) ($_SESSION['perfil'] ?? '')
    ));

    if (!in_array($perfil, ['administrador', 'admin'], true)) {
        http_response_code(403);
        exit('Acesso negado. Apenas o administrador pode aceder.');
    }
}

// Verifica os perfis autorizados.
function require_role(array $perfisPermitidos): void
{
    require_login();

    $perfil = strtolower(trim(
        (string) ($_SESSION['perfil'] ?? '')
    ));

    $perfisPermitidos = array_map(
        static fn($p) => strtolower(trim((string) $p)),
        $perfisPermitidos
    );

    if (!in_array($perfil, $perfisPermitidos, true)) {
        http_response_code(403);
        exit('Acesso negado. Não tens permissão para esta operação.');
    }
}
