<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/medico.php';
$idMedico = exigir_medico();

$pageTitle = 'Agenda';
$menuActivo = 'medico/agenda.php';
$hoje = date('Y-m-d');
$data = qs('data', $hoje);
if (!data_valida($data)) {
    $data = $hoje;
}
$fEstado = qs('estado');

$where = 'c.id_medico = ? AND c.data_consulta = ?';
$params = [$idMedico, $data];
if (in_array($fEstado, ['Marcada', 'Remarcada', 'Cancelada', 'Concluída'], true)) {
    $where .= ' AND c.estado = ?';
    $params[] = $fEstado;
}

$st = $pdo->prepare(
    "SELECT c.id_consulta, c.hora, c.hora_fim, c.estado, c.motivo, up.nome AS paciente, pa.data_nascimento
       FROM consultas c
       JOIN pacientes pa ON pa.id_paciente = c.id_paciente
       JOIN utilizadores up ON up.id_utilizador = pa.id_utilizador
      WHERE $where ORDER BY c.hora"
);
$st->execute($params);
$consultas = $st->fetchAll();

$porAtender = count(array_filter($consultas, fn($c) => in_array($c['estado'], ESTADOS_CONSULTA_ABERTOS, true)));
$concluidas = count(array_filter($consultas, fn($c) => $c['estado'] === 'Concluída'));
$ligacao = fn(string $d) => '?' . http_build_query(['data' => $d, 'estado' => $fEstado]);

require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
    <div><h1>Agenda</h1><p><?= e(dia_semana_pt($data)) ?>, <?= e(fmt_data($data)) ?><?= $data === $hoje ? ' (hoje)' : '' ?></p></div>
</section>
<section class="stats-grid">
    <article class="stat-card"><div class="stat-top"><span>Consultas no dia</span><span class="stat-icon">▣</span></div><div class="stat-value"><?= count($consultas) ?></div><div class="stat-note">Com o filtro actual</div></article>
    <article class="stat-card"><div class="stat-top"><span>Por atender</span><span class="stat-icon">▤</span></div><div class="stat-value"><?= $porAtender ?></div><div class="stat-note">Marcadas ou remarcadas</div></article>
    <article class="stat-card"><div class="stat-top"><span>Concluídas</span><span class="stat-icon">✚</span></div><div class="stat-value"><?= $concluidas ?></div><div class="stat-note">Com registo clínico</div></article>
</section>
<section class="panel">
    <form class="filters" method="get">
        <div class="field"><label for="data">Data</label><input type="date" id="data" name="data" value="<?= e($data) ?>"></div>
        <div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><option value="">Todos</option>
            <?php foreach (['Marcada', 'Remarcada', 'Cancelada', 'Concluída'] as $s): ?><option<?= $fEstado === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn-light" type="submit">Filtrar</button>
        <a class="btn btn-light" href="<?= e($ligacao(date('Y-m-d', strtotime("$data -1 day")))) ?>">← Dia anterior</a>
        <a class="btn btn-light" href="<?= e($ligacao($hoje)) ?>">Hoje</a>
        <a class="btn btn-light" href="<?= e($ligacao(date('Y-m-d', strtotime("$data +1 day")))) ?>">Dia seguinte →</a>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Horário</th><th>Paciente</th><th>Motivo</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php if (!$consultas): ?><tr><td colspan="5" class="empty">Sem consultas neste dia.</td></tr>
        <?php else: foreach ($consultas as $c):
            $aberta = in_array($c['estado'], ESTADOS_CONSULTA_ABERTOS, true); ?>
            <tr>
                <td><strong><?= e(fmt_hora($c['hora'])) ?></strong>–<?= e(fmt_hora($c['hora_fim'])) ?></td>
                <td><?= e($c['paciente']) ?><br><small><?= idade($c['data_nascimento']) ?> anos</small></td>
                <td><?= e($c['motivo'] ?? '—') ?></td>
                <td><?= badge_estado($c['estado']) ?></td>
                <td class="actions">
                    <?php if ($aberta && $data <= $hoje): ?><a class="btn btn-sm" href="consulta.php?id=<?= (int)$c['id_consulta'] ?>">Atender</a>
                    <?php elseif ($c['estado'] === 'Concluída'): ?><a class="btn btn-light btn-sm" href="consulta.php?id=<?= (int)$c['id_consulta'] ?>">Ver registo</a>
                    <?php elseif ($aberta): ?><small>Só a partir do dia</small><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
