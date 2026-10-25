<?php
declare(strict_types=1);

/** "Médico" -> "medico", "Administrador" -> "administrador" */
function perfil_chave(?string $perfil): string
{
    $p = strtr(mb_strtolower(trim((string)$perfil), 'UTF-8'), ['é' => 'e']);
    return $p === 'admin' ? PERFIL_ADMIN : $p;
}

function perfil_actual(): string
{
    return perfil_chave($_SESSION['perfil'] ?? '');
}

function pagina_inicial(string $chave): string
{
    return match ($chave) {
        PERFIL_ADMIN         => 'admin/dashboard.php',
        PERFIL_RECEPCIONISTA => 'recepcao/dashboard.php',
        PERFIL_MEDICO        => 'medico/dashboard.php',
        PERFIL_PACIENTE      => 'paciente/dashboard.php',
        default              => 'login.php',
    };
}

/** Só deixa passar utilizadores autenticados cujo perfil esteja na lista. */
function require_role(array $perfisPermitidos): void
{
    if (!esta_autenticado()) {
        redirect('login.php');
    }
    if (!in_array(perfil_actual(), $perfisPermitidos, true)) {
        http_response_code(403);
        exit('Acesso negado. Não tem permissão para aceder a esta página.');
    }
}

function require_admin(): void
{
    require_role([PERFIL_ADMIN]);
}

/** Menu lateral de cada perfil: [rótulo, caminho, ícone]. */
function menu_do_perfil(): array
{
    $menus = [
        PERFIL_ADMIN => [
            ['Visão geral', 'admin/dashboard.php', '▦'],
            ['Utilizadores', 'admin/utilizadores.php', '♙'],
            ['Médicos', 'admin/medicos.php', '✚'],
            ['Pacientes', 'recepcao/pacientes.php', '♡'],
            ['Consultas', 'recepcao/consultas.php', '▣'],
            ['Auditoria', 'admin/auditoria.php', '◉'],
        ],
        PERFIL_RECEPCIONISTA => [
            ['Visão geral', 'recepcao/dashboard.php', '▦'],
            ['Pacientes', 'recepcao/pacientes.php', '♡'],
            ['Consultas', 'recepcao/consultas.php', '▣'],
        ],
        PERFIL_MEDICO => [
            ['Visão geral', 'medico/dashboard.php', '▦'],
            ['Agenda', 'medico/agenda.php', '▣'],
            ['Pacientes', 'medico/pacientes.php', '✚'],
        ],
        PERFIL_PACIENTE => [
            ['Visão geral', 'paciente/dashboard.php', '▦'],
        ],
    ];
    return $menus[perfil_actual()] ?? [];
}
