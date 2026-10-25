<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/medico.php';
$idMedico = exigir_medico();

$pageTitle = 'Pacientes';
$menuActivo = 'medico/pacientes.php';
$q = qs('q');

$from = "FROM consultas c
         JOIN pacientes pa ON pa.id_paciente = c.id_paciente
         JOIN utilizadores up ON up.id_utilizador = pa.id_utilizador";
$where = "c.id_medico = ? AND c.estado <> 'Cancelada'";
$params = [$idMedico];
if ($q !== '') {
    $where .= ' AND up.nome LIKE ?';
    $params[] = '%' . $q . '%';
}

$total = contar($pdo, "SELECT COUNT(DISTINCT c.id_paciente) $from WHERE $where", $params);
$porPagina = 15;
$pagina = min(pagina_atual(), max(1, (int)ceil($total / $porPagina)));
$offset = ($pagina - 1) * $porPagina;

$st = $pdo->prepare(
    "SELECT pa.id_paciente, up.nome, pa.sexo, pa.data_nascimento, COUNT(*) AS total, MAX(c.data_consulta) AS ultima
       $from WHERE $where
      GROUP BY pa.id_paciente, up.nome, pa.sexo, pa.data_nascimento
      ORDER BY up.nome LIMIT $porPagina OFFSET $offset"
);
$st->execute($params);
$pacientes = $st->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<section class="page-heading"><div><h1>Pacientes</h1><p>Pacientes com consultas consigo.</p></div></section>
<section class="panel">
    <form class="filters" method="get">
        <div class="field"><label for="q">Pesquisar</label><input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Nome do paciente"></div>
        <button class="btn btn-light" type="submit">Filtrar</button>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>Paciente</th><th>Idade</th><th>Consultas</th><th>Última consulta</th><th></th></tr></thead><tbody>
        <?php if (!$pacientes): ?><tr><td colspan="5" class="empty">Nenhum paciente encontrado.</td></tr>
        <?php else: foreach ($pacientes as $p): ?>
            <tr>
                <td><strong><?= e($p['nome']) ?></strong><br><small><?= e($p['sexo']) ?></small></td>
                <td><?= idade($p['data_nascimento']) ?> anos</td>
                <td><?= (int)$p['total'] ?></td>
                <td><?= e(fmt_data($p['ultima'])) ?></td>
                <td class="actions"><a class="btn btn-light btn-sm" href="historico-clinico.php?paciente=<?= (int)$p['id_paciente'] ?>">Histórico clínico</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    <?= pager($total, $porPagina, $pagina) ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
