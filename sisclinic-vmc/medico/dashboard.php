<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role([PERFIL_MEDICO]);

$pageTitle = 'Visão geral';
$st = $pdo->prepare('SELECT id_medico FROM medicos WHERE id_utilizador = ? AND deleted_at IS NULL');
$st->execute([(int)$_SESSION['id_utilizador']]);
$idMedico = (int)$st->fetchColumn();
$hoje = date('Y-m-d');

$st = $pdo->prepare(
    "SELECT c.id_consulta, c.data_consulta, c.hora, c.hora_fim, c.estado, c.motivo, up.nome AS paciente
       FROM consultas c JOIN pacientes pa ON pa.id_paciente = c.id_paciente
       JOIN utilizadores up ON up.id_utilizador = pa.id_utilizador
      WHERE c.id_medico = ? AND c.data_consulta BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
        AND c.estado IN ('Marcada','Remarcada') ORDER BY c.data_consulta, c.hora"
);
$st->execute([$idMedico, $hoje, $hoje]);
$consultas = $st->fetchAll();
$hojeTotal = count(array_filter($consultas, fn($c) => $c['data_consulta'] === $hoje));

require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><h1>Bom dia, Dr(a). <?= e($_SESSION['nome']) ?></h1><p>A sua agenda para os próximos 7 dias.</p></div></section>
<?php if (!$idMedico): ?><div class="alert alert-erro">A sua conta não tem um registo de médico associado. Contacte o administrador.</div><?php endif; ?>
<section class="stats-grid">
    <article class="stat-card"><div class="stat-top"><span>Consultas hoje</span><span class="stat-icon">▣</span></div><div class="stat-value"><?= $hojeTotal ?></div><div class="stat-note"><?= e(date('d/m/Y')) ?></div></article>
    <article class="stat-card"><div class="stat-top"><span>Próximos 7 dias</span><span class="stat-icon">▤</span></div><div class="stat-value"><?= count($consultas) ?></div><div class="stat-note">Marcadas ou remarcadas</div></article>
</section>
<section class="panel"><div class="panel-head"><h2>Agenda</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Data</th><th>Horário</th><th>Paciente</th><th>Motivo</th><th>Estado</th><th></th></tr></thead><tbody>
    <?php if (!$consultas): ?><tr><td colspan="6" class="empty">Sem consultas nos próximos 7 dias.</td></tr>
    <?php else: foreach ($consultas as $c): ?>
        <tr><td><?= e(fmt_data($c['data_consulta'])) ?></td><td><?= e(fmt_hora($c['hora'])) ?>–<?= e(fmt_hora($c['hora_fim'])) ?></td>
            <td><?= e($c['paciente']) ?></td><td><?= e($c['motivo'] ?? '—') ?></td><td><?= badge_estado($c['estado']) ?></td>
            <td class="actions"><?php if ($c['data_consulta'] <= $hoje): ?><a class="btn btn-sm" href="consulta.php?id=<?= (int)$c['id_consulta'] ?>">Atender</a><?php endif; ?></td></tr>
    <?php endforeach; endif; ?>
    </tbody></table></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
