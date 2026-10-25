<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/medico.php';
$idMedico = exigir_medico();

$id = (int)qs('id');
$c = consulta_do_medico($id, $idMedico);
if (!$c) {
    flash('erro', 'Consulta não encontrada.');
    redirect('medico/agenda.php');
}

$pageTitle = 'Consulta';
$menuActivo = 'medico/agenda.php';
$aberta = in_array($c['estado'], ESTADOS_CONSULTA_ABERTOS, true);
$futura = $c['data_consulta'] > date('Y-m-d');
$podeEditar = $aberta && !$futura;
$voltar = 'medico/consulta.php?id=' . $id;

$st = $pdo->prepare('SELECT peso, altura, temperatura, pressao, diagnostico, tratamento, observacoes FROM historico_clinico WHERE id_consulta = ?');
$st->execute([$id]);
$f = $st->fetch() ?: [];

if (is_post()) {
    csrf_verify();
    if (!$podeEditar) {
        flash('erro', 'Esta consulta já não pode ser editada.');
        redirect($voltar);
    }

    $f = [
        'peso' => post('peso'), 'altura' => post('altura'), 'temperatura' => post('temperatura'),
        'pressao' => post('pressao'), 'diagnostico' => post('diagnostico'),
        'tratamento' => post('tratamento'), 'observacoes' => post('observacoes'),
    ];
    $concluir = post('accao') === 'concluir';
    $erros = [];

    $peso = num_opcional($f['peso'], 1, 500, 'Peso (kg)', $erros);
    $altura = num_opcional($f['altura'], 0.3, 2.5, 'Altura (m)', $erros);
    $temp = num_opcional($f['temperatura'], 30, 45, 'Temperatura (°C)', $erros);
    if ($f['pressao'] !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/', $f['pressao'])) {
        $erros[] = 'Pressão arterial: use o formato 120/80.';
    }
    foreach (['diagnostico' => 'Diagnóstico', 'tratamento' => 'Tratamento', 'observacoes' => 'Observações'] as $k => $r) {
        if (mb_strlen($f[$k]) > 5000) {
            $erros[] = "$r: máximo de 5000 caracteres.";
        }
    }
    if ($concluir && $f['diagnostico'] === '') {
        $erros[] = 'Indique o diagnóstico para concluir a consulta.';
    }

    if ($erros) {
        foreach ($erros as $m) {
            flash('erro', $m);
        }
    } else {
        $nulo = fn(string $v) => $v === '' ? null : $v;
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                'INSERT INTO historico_clinico (id_consulta, peso, altura, temperatura, pressao, diagnostico, tratamento, observacoes)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE peso = VALUES(peso), altura = VALUES(altura), temperatura = VALUES(temperatura),
                     pressao = VALUES(pressao), diagnostico = VALUES(diagnostico), tratamento = VALUES(tratamento),
                     observacoes = VALUES(observacoes)'
            )->execute([
                $id,
                $peso === null ? null : round($peso, 2),
                $altura === null ? null : round($altura, 2),
                $temp === null ? null : round($temp, 1),
                $nulo($f['pressao']), $nulo($f['diagnostico']), $nulo($f['tratamento']), $nulo($f['observacoes']),
            ]);

            if ($concluir) {
                $up = $pdo->prepare("UPDATE consultas SET estado = 'Concluída' WHERE id_consulta = ? AND id_medico = ? AND estado IN ('Marcada','Remarcada')");
                $up->execute([$id, $idMedico]);
                if ($up->rowCount() !== 1) {
                    throw new RuntimeException('A consulta já não está aberta.');
                }
                notificar((int)$c['u_paciente'], 'A sua consulta de ' . fmt_data($c['data_consulta']) . ' às ' . fmt_hora($c['hora']) . ' foi concluída.');
            }
            $pdo->commit();
            audit($concluir ? 'CONCLUIR_CONSULTA' : 'GUARDAR_REGISTO_CLINICO', 'historico_clinico', null, ['id_consulta' => $id]);

            if ($concluir) {
                flash('ok', 'Consulta concluída e registo clínico guardado.');
                redirect('medico/agenda.php?data=' . $c['data_consulta']);
            }
            flash('ok', 'Rascunho guardado. A consulta continua em aberto.');
            redirect($voltar);
        } catch (PDOException | RuntimeException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('erro', $ex instanceof PDOException ? mensagem_erro_bd($ex) : $ex->getMessage());
        }
    }
}

// Consultas anteriores do mesmo paciente (contexto clínico)
$st = $pdo->prepare(
    "SELECT c.data_consulta, h.diagnostico, um.nome AS medico
       FROM consultas c
       JOIN historico_clinico h ON h.id_consulta = c.id_consulta
       JOIN medicos m ON m.id_medico = c.id_medico
       JOIN utilizadores um ON um.id_utilizador = m.id_utilizador
      WHERE c.id_paciente = ? AND c.id_consulta <> ? AND c.estado = 'Concluída'
      ORDER BY c.data_consulta DESC, c.hora DESC LIMIT 5"
);
$st->execute([(int)$c['id_paciente'], $id]);
$anteriores = $st->fetchAll();

$ro = $podeEditar ? '' : ' disabled';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
    <div><h1>Consulta · <?= e($c['paciente']) ?></h1>
        <p><?= e(fmt_data($c['data_consulta'])) ?> · <?= e(fmt_hora($c['hora'])) ?>–<?= e(fmt_hora($c['hora_fim'])) ?> · <?= badge_estado($c['estado']) ?></p></div>
    <a class="btn btn-light" href="agenda.php?data=<?= e($c['data_consulta']) ?>">← Agenda</a>
</section>
<?php if ($futura && $aberta): ?><div class="info-box" style="margin-bottom:14px">Esta consulta está marcada para uma data futura. O registo clínico só pode ser preenchido a partir desse dia.</div><?php endif; ?>
<?php if ($c['estado'] === 'Cancelada'): ?><div class="info-box" style="margin-bottom:14px">Esta consulta foi cancelada.</div><?php endif; ?>
<?php if ($c['estado'] === 'Concluída'): ?><div class="info-box" style="margin-bottom:14px">Consulta concluída: o registo clínico está bloqueado para edição.</div><?php endif; ?>

<section class="two-columns">
    <article class="panel"><div class="panel-head"><h2>Registo clínico</h2></div><div class="panel-body">
        <?php if ($c['motivo']): ?><p><strong>Motivo da consulta:</strong> <?= e($c['motivo']) ?></p><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field"><label for="peso">Peso (kg)</label><input type="text" inputmode="decimal" id="peso" name="peso" value="<?= e($f['peso'] ?? '') ?>"<?= $ro ?>></div>
                <div class="field"><label for="altura">Altura (m)</label><input type="text" inputmode="decimal" id="altura" name="altura" value="<?= e($f['altura'] ?? '') ?>" placeholder="1.70"<?= $ro ?>></div>
                <div class="field"><label for="temperatura">Temperatura (°C)</label><input type="text" inputmode="decimal" id="temperatura" name="temperatura" value="<?= e($f['temperatura'] ?? '') ?>"<?= $ro ?>></div>
                <div class="field"><label for="pressao">Pressão arterial</label><input type="text" id="pressao" name="pressao" value="<?= e($f['pressao'] ?? '') ?>" placeholder="120/80" maxlength="20"<?= $ro ?>></div>
                <div class="field full"><label for="diagnostico">Diagnóstico <?= $podeEditar ? '*' : '' ?></label><textarea id="diagnostico" name="diagnostico" maxlength="5000"<?= $ro ?>><?= e($f['diagnostico'] ?? '') ?></textarea></div>
                <div class="field full"><label for="tratamento">Tratamento / prescrição</label><textarea id="tratamento" name="tratamento" maxlength="5000"<?= $ro ?>><?= e($f['tratamento'] ?? '') ?></textarea></div>
                <div class="field full"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" maxlength="5000"<?= $ro ?>><?= e($f['observacoes'] ?? '') ?></textarea></div>
            </div>
            <?php if ($podeEditar): ?>
            <div class="form-actions">
                <button class="btn btn-light" type="submit" name="accao" value="guardar">Guardar rascunho</button>
                <button class="btn" type="submit" name="accao" value="concluir" onclick="return confirm('Concluir a consulta? Depois de concluída, o registo não pode ser alterado.')">Concluir consulta</button>
            </div>
            <?php endif; ?>
        </form>
    </div></article>

    <aside>
        <article class="panel patient-card"><div class="panel-head"><h2>Paciente</h2></div><div class="panel-body">
            <dl>
                <dt>Nome</dt><dd><?= e($c['paciente']) ?></dd>
                <dt>Idade</dt><dd><?= idade($c['data_nascimento']) ?> anos · <?= e($c['sexo']) ?></dd>
                <dt>Sangue</dt><dd><?= e($c['tipo_sanguineo'] ?? '—') ?></dd>
                <dt>Telefone</dt><dd><?= e($c['telefone'] ?? '—') ?></dd>
                <dt>Emergência</dt><dd><?= e($c['contacto_emergencia'] ?? '—') ?><?= $c['telefone_emergencia'] ? ' · ' . e($c['telefone_emergencia']) : '' ?></dd>
            </dl>
            <?php if (!empty($c['alergias'])): ?><div class="alert alert-erro" style="margin:12px 0 0"><strong>Alergias:</strong> <?= e($c['alergias']) ?></div><?php endif; ?>
            <p style="margin:12px 0 0"><a class="btn btn-light btn-sm" href="historico-clinico.php?paciente=<?= (int)$c['id_paciente'] ?>">Ver histórico completo</a></p>
        </div></article>
        <article class="panel" style="margin-top:18px"><div class="panel-head"><h2>Consultas anteriores</h2></div><div class="panel-body">
            <?php if (!$anteriores): ?><p class="empty">Sem consultas anteriores concluídas.</p>
            <?php else: foreach ($anteriores as $a): ?>
                <p style="margin:0 0 10px"><strong><?= e(fmt_data($a['data_consulta'])) ?></strong> · <small><?= e($a['medico']) ?></small><br>
                    <?= e(mb_strimwidth((string)($a['diagnostico'] ?? 'Sem diagnóstico registado'), 0, 90, '…')) ?></p>
            <?php endforeach; endif; ?>
        </div></article>
    </aside>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
