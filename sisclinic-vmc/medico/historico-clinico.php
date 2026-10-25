<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/medico.php';
$idMedico = exigir_medico();

$idPaciente = (int)qs('paciente');
if ($idPaciente <= 0 || !medico_atende_paciente($idMedico, $idPaciente)) {
    flash('erro', 'Só pode consultar o histórico de pacientes com quem tem consultas.');
    redirect('medico/pacientes.php');
}

$pageTitle = 'Histórico clínico';
$menuActivo = 'medico/pacientes.php';

$st = $pdo->prepare(
    'SELECT up.nome, up.telefone, pa.sexo, pa.data_nascimento, pa.tipo_sanguineo, pa.alergias
       FROM pacientes pa JOIN utilizadores up ON up.id_utilizador = pa.id_utilizador WHERE pa.id_paciente = ?'
);
$st->execute([$idPaciente]);
$p = $st->fetch();
if (!$p) {
    redirect('medico/pacientes.php');
}

$total = contar($pdo, "SELECT COUNT(*) FROM consultas WHERE id_paciente = ? AND estado = 'Concluída'", [$idPaciente]);
$porPagina = 10;
$pagina = min(pagina_atual(), max(1, (int)ceil($total / $porPagina)));
$offset = ($pagina - 1) * $porPagina;

$st = $pdo->prepare(
    "SELECT c.id_consulta, c.data_consulta, c.hora, c.motivo, um.nome AS medico, e.nome AS especialidade,
            h.peso, h.altura, h.temperatura, h.pressao, h.diagnostico, h.tratamento, h.observacoes
       FROM consultas c
       JOIN medicos m ON m.id_medico = c.id_medico
       JOIN utilizadores um ON um.id_utilizador = m.id_utilizador
       JOIN especialidades e ON e.id_especialidade = m.id_especialidade
       LEFT JOIN historico_clinico h ON h.id_consulta = c.id_consulta
      WHERE c.id_paciente = ? AND c.estado = 'Concluída'
      ORDER BY c.data_consulta DESC, c.hora DESC LIMIT $porPagina OFFSET $offset"
);
$st->execute([$idPaciente]);
$entradas = $st->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
    <div><h1>Histórico clínico · <?= e($p['nome']) ?></h1>
        <p><?= idade($p['data_nascimento']) ?> anos · <?= e($p['sexo']) ?> · Sangue: <?= e($p['tipo_sanguineo'] ?? '—') ?></p></div>
    <a class="btn btn-light" href="pacientes.php">← Pacientes</a>
</section>
<?php if (!empty($p['alergias'])): ?><div class="alert alert-erro"><strong>Alergias:</strong> <?= e($p['alergias']) ?></div><?php endif; ?>

<?php if (!$entradas): ?><section class="panel"><div class="panel-body"><p class="empty">Este paciente ainda não tem consultas concluídas.</p></div></section>
<?php else: ?><section class="timeline">
    <?php foreach ($entradas as $h): $imc = imc($h['peso'], $h['altura']); ?>
    <article class="entry">
        <h3><?= e(fmt_data($h['data_consulta'])) ?> · <?= e(fmt_hora($h['hora'])) ?> <small>— <?= e($h['medico']) ?> (<?= e($h['especialidade']) ?>)</small></h3>
        <?php if ($h['motivo']): ?><p><strong>Motivo:</strong> <?= e($h['motivo']) ?></p><?php endif; ?>
        <div class="vitals">
            <?php if ($h['peso'] !== null): ?><span class="vital">Peso: <?= e($h['peso']) ?> kg</span><?php endif; ?>
            <?php if ($h['altura'] !== null): ?><span class="vital">Altura: <?= e($h['altura']) ?> m</span><?php endif; ?>
            <?php if ($imc !== null): ?><span class="vital">IMC: <?= e($imc) ?></span><?php endif; ?>
            <?php if ($h['temperatura'] !== null): ?><span class="vital">Temp.: <?= e($h['temperatura']) ?> °C</span><?php endif; ?>
            <?php if (!empty($h['pressao'])): ?><span class="vital">Pressão: <?= e($h['pressao']) ?></span><?php endif; ?>
        </div>
        <p class="text-block"><strong>Diagnóstico:</strong> <?= e($h['diagnostico'] ?? '—') ?></p>
        <p class="text-block"><strong>Tratamento:</strong> <?= e($h['tratamento'] ?? '—') ?></p>
        <?php if (!empty($h['observacoes'])): ?><p class="text-block"><strong>Observações:</strong> <?= e($h['observacoes']) ?></p><?php endif; ?>
    </article>
    <?php endforeach; ?>
</section>
<?= pager($total, $porPagina, $pagina) ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
