
<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$pageTitle = 'Painel do Administrador';

// 1. Indicadores gerais.
$totalUtilizadores = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM utilizadores
     WHERE deleted_at IS NULL"
)->fetchColumn();

$totalPacientes = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM pacientes
     WHERE deleted_at IS NULL"
)->fetchColumn();

$totalMedicos = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM medicos
     WHERE deleted_at IS NULL"
)->fetchColumn();

$totalConsultasHoje = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM consultas
     WHERE data_consulta = CURDATE()
       AND estado <> 'Cancelada'"
)->fetchColumn();

$consultasPendentes = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM consultas
     WHERE estado IN ('Marcada', 'Remarcada')
       AND data_consulta >= CURDATE()"
)->fetchColumn();

$facturasPendentes = (int) $pdo->query(
    "SELECT COUNT(*)
     FROM facturas
     WHERE estado = 'Pendente'"
)->fetchColumn();

// 2. Consultas mais recentes.
$sql = "
    SELECT
        c.id_consulta,
        c.data_consulta,
        c.hora,
        c.estado,
        up.nome AS nome_paciente,
        um.nome AS nome_medico
    FROM consultas c
    INNER JOIN pacientes p
        ON p.id_paciente = c.id_paciente
    INNER JOIN utilizadores up
        ON up.id_utilizador = p.id_utilizador
    INNER JOIN medicos m
        ON m.id_medico = c.id_medico
    INNER JOIN utilizadores um
        ON um.id_utilizador = m.id_utilizador
    ORDER BY c.data_consulta DESC, c.hora DESC
    LIMIT 8
";

$consultas = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-content">

    <h1>Visão geral</h1>

    <p>
        Bem-vindo,
        <strong><?= e($_SESSION['nome'] ?? 'Administrador') ?></strong>.
    </p>

    <p>Resumo da actividade clínica e administrativa.</p>

    <section class="stats-grid">

        <article class="stat-card">
            <h2>Utilizadores activos</h2>
            <p><?= $totalUtilizadores ?></p>
        </article>

        <article class="stat-card">
            <h2>Pacientes registados</h2>
            <p><?= $totalPacientes ?></p>
        </article>

        <article class="stat-card">
            <h2>Médicos registados</h2>
            <p><?= $totalMedicos ?></p>
        </article>

        <article class="stat-card">
            <h2>Consultas de hoje</h2>
            <p><?= $totalConsultasHoje ?></p>
        </article>

        <article class="stat-card">
            <h2>Consultas por realizar</h2>
            <p><?= $consultasPendentes ?></p>
        </article>

        <article class="stat-card">
            <h2>Facturas pendentes</h2>
            <p><?= $facturasPendentes ?></p>
        </article>

    </section>

    <section class="recent-section">
        <h2>Consultas recentes</h2>

        <?php if (empty($consultas)): ?>

            <p>Ainda não existem consultas registadas.</p>

        <?php else: ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>N.º</th>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th>Data</th>
                            <th>Hora</th>
                            <th>Estado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($consultas as $consulta): ?>
                            <tr>
                                <td>
                                    <?= (int) $consulta['id_consulta'] ?>
                                </td>

                                <td>
                                    <?= e($consulta['nome_paciente']) ?>
                                </td>

                                <td>
                                    <?= e($consulta['nome_medico']) ?>
                                </td>

                                <td>
                                    <?= e($consulta['data_consulta']) ?>
                                </td>

                                <td>
                                    <?= e(substr((string) $consulta['hora'], 0, 5)) ?>
                                </td>

                                <td>
                                    <?= e($consulta['estado']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
