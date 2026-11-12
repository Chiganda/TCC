
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

// Apenas administrador e recepcionista podem gerir consultas.
$perfil = strtolower(trim((string)($_SESSION['perfil'] ?? '')));

if (!in_array($perfil, ['administrador', 'admin', 'recepcionista'], true)) {
    http_response_code(403);
    exit('Acesso negado. Não tens permissão para gerir consultas.');
}

$mensagem = '';
$erro = '';
$consultaEditar = null;

/*
|--------------------------------------------------------------------------
| PROCESSAMENTO DOS FORMULÁRIOS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_validate();

        $acao = $_POST['acao'] ?? '';

        // AGENDAR OU REMARCAR CONSULTA
        if ($acao === 'guardar') {
            $idConsulta = (int)($_POST['id_consulta'] ?? 0);
            $idPaciente = (int)($_POST['id_paciente'] ?? 0);
            $idMedico = (int)($_POST['id_medico'] ?? 0);
            $dataConsulta = trim($_POST['data_consulta'] ?? '');
            $hora = trim($_POST['hora'] ?? '');
            $duracao = (int)($_POST['duracao_minutos'] ?? 30);
            $motivo = trim($_POST['motivo'] ?? '');

            if (
                $idPaciente <= 0 ||
                $idMedico <= 0 ||
                $dataConsulta === '' ||
                $hora === '' ||
                $motivo === ''
            ) {
                throw new Exception('Preenche todos os campos obrigatórios.');
            }

            if ($duracao < 10 || $duracao > 240) {
                throw new Exception('A duração deve estar entre 10 e 240 minutos.');
            }

            $data = DateTime::createFromFormat('!Y-m-d', $dataConsulta);

            if (!$data || $data->format('Y-m-d') !== $dataConsulta) {
                throw new Exception('A data da consulta é inválida.');
            }

            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
                throw new Exception('A hora da consulta é inválida.');
            }

            // Confirmar se o paciente está activo
            $stmt = $pdo->prepare(
                "SELECT p.id_paciente
                 FROM pacientes p
                 INNER JOIN utilizadores u
                    ON u.id_utilizador = p.id_utilizador
                 WHERE p.id_paciente = ?
                   AND p.deleted_at IS NULL
                   AND u.deleted_at IS NULL"
            );
            $stmt->execute([$idPaciente]);

            if (!$stmt->fetch()) {
                throw new Exception('O paciente seleccionado não está activo.');
            }

            // Confirmar se o médico está activo
            $stmt = $pdo->prepare(
                "SELECT id_medico
                 FROM medicos
                 WHERE id_medico = ?
                   AND deleted_at IS NULL"
            );
            $stmt->execute([$idMedico]);

            if (!$stmt->fetch()) {
                throw new Exception('O médico seleccionado não está activo.');
            }

            if ($idConsulta === 0) {
                // NOVA CONSULTA
                $stmt = $pdo->prepare(
                    "INSERT INTO consultas
                    (
                        id_paciente,
                        id_medico,
                        data_consulta,
                        hora,
                        duracao_minutos,
                        estado,
                        motivo
                    )
                    VALUES (?, ?, ?, ?, ?, 'Marcada', ?)"
                );

                $stmt->execute([
                    $idPaciente,
                    $idMedico,
                    $dataConsulta,
                    $hora,
                    $duracao,
                    $motivo
                ]);

                $mensagem = 'Consulta agendada com sucesso.';

            } else {
                // CONFIRMAR SE A CONSULTA PODE SER REMARCADA
                $stmt = $pdo->prepare(
                    "SELECT id_consulta, estado
                     FROM consultas
                     WHERE id_consulta = ?"
                );
                $stmt->execute([$idConsulta]);

                $existente = $stmt->fetch();

                if (!$existente) {
                    throw new Exception('Consulta não encontrada.');
                }

                if (!in_array($existente['estado'], ['Marcada', 'Remarcada'], true)) {
                    throw new Exception('Esta consulta não pode ser remarcada.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE consultas
                     SET id_paciente = ?,
                         id_medico = ?,
                         data_consulta = ?,
                         hora = ?,
                         duracao_minutos = ?,
                         estado = 'Remarcada',
                         motivo = ?
                     WHERE id_consulta = ?"
                );

                $stmt->execute([
                    $idPaciente,
                    $idMedico,
                    $dataConsulta,
                    $hora,
                    $duracao,
                    $motivo,
                    $idConsulta
                ]);

                $mensagem = 'Consulta remarcada com sucesso.';
            }

            header('Location: consultas.php?sucesso=' . urlencode($mensagem));
            exit;
        }

        // CANCELAR OU CONCLUIR CONSULTA
        if (in_array($acao, ['cancelar', 'concluir'], true)) {
            $idConsulta = (int)($_POST['id_consulta'] ?? 0);

            if ($idConsulta <= 0) {
                throw new Exception('Consulta inválida.');
            }

            $novoEstado = $acao === 'cancelar'
                ? 'Cancelada'
                : 'Concluída';

            $stmt = $pdo->prepare(
                "UPDATE consultas
                 SET estado = ?
                 WHERE id_consulta = ?
                   AND estado IN ('Marcada', 'Remarcada')"
            );

            $stmt->execute([$novoEstado, $idConsulta]);

            if ($stmt->rowCount() === 0) {
                throw new Exception(
                    'Consulta não encontrada ou já não permite esta operação.'
                );
            }

            $mensagem = $acao === 'cancelar'
                ? 'Consulta cancelada com sucesso.'
                : 'Consulta marcada como concluída com sucesso.';

            header('Location: consultas.php?sucesso=' . urlencode($mensagem));
            exit;
        }

    } catch (Throwable $e) {
        error_log('Erro na gestão de consultas: ' . $e->getMessage());

        if ($e instanceof PDOException) {
            $detalhe = $e->errorInfo[2] ?? '';

            // Mensagens das validações dos triggers da base de dados.
            if (stripos($detalhe, 'disponibilidade') !== false) {
                $erro = 'O médico não está disponível nesse horário.';
            } elseif (
                stripos($detalhe, 'sobreposição') !== false ||
                stripos($detalhe, 'conflito') !== false ||
                stripos($detalhe, 'horário') !== false
            ) {
                $erro = 'Existe um conflito de horário. Verifica a disponibilidade do médico.';
            } else {
                $erro = 'Não foi possível guardar a consulta. Verifica os dados e o horário seleccionado.';
            }
        } else {
            $erro = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

if (isset($_GET['sucesso'])) {
    $mensagem = trim($_GET['sucesso']);
}

/*
|--------------------------------------------------------------------------
| CARREGAR PACIENTES ACTIVOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        p.id_paciente,
        u.nome,
        p.bi
     FROM pacientes p
     INNER JOIN utilizadores u
        ON u.id_utilizador = p.id_utilizador
     WHERE p.deleted_at IS NULL
       AND u.deleted_at IS NULL
     ORDER BY u.nome ASC"
);

$pacientes = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| CARREGAR MÉDICOS ACTIVOS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        m.id_medico,
        u.nome,
        e.nome AS especialidade
     FROM medicos m
     INNER JOIN utilizadores u
        ON u.id_utilizador = m.id_utilizador
     INNER JOIN especialidades e
        ON e.id_especialidade = m.id_especialidade
     WHERE m.deleted_at IS NULL
       AND u.deleted_at IS NULL
     ORDER BY u.nome ASC"
);

$medicos = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| CARREGAR CONSULTA PARA EDIÇÃO
|--------------------------------------------------------------------------
*/

if (isset($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];

    $stmt = $pdo->prepare(
        "SELECT *
         FROM consultas
         WHERE id_consulta = ?
           AND estado IN ('Marcada', 'Remarcada')"
    );

    $stmt->execute([$idEditar]);
    $consultaEditar = $stmt->fetch();

    if (!$consultaEditar) {
        $erro = 'Consulta não encontrada ou não pode ser editada.';
    }
}

/*
|--------------------------------------------------------------------------
| PESQUISAR CONSULTAS
|--------------------------------------------------------------------------
*/

$pesquisa = trim($_GET['pesquisa'] ?? '');

$sql = "SELECT
            c.id_consulta,
            c.data_consulta,
            c.hora,
            c.hora_fim,
            c.duracao_minutos,
            c.estado,
            c.motivo,
            up.nome AS paciente,
            um.nome AS medico,
            e.nome AS especialidade
        FROM consultas c
        INNER JOIN pacientes p
            ON p.id_paciente = c.id_paciente
        INNER JOIN utilizadores up
            ON up.id_utilizador = p.id_utilizador
        INNER JOIN medicos m
            ON m.id_medico = c.id_medico
        INNER JOIN utilizadores um
            ON um.id_utilizador = m.id_utilizador
        INNER JOIN especialidades e
            ON e.id_especialidade = m.id_especialidade
        WHERE (
            up.nome LIKE :paciente
            OR um.nome LIKE :medico
            OR e.nome LIKE :especialidade
            OR c.estado LIKE :estado
        )
        ORDER BY c.data_consulta DESC, c.hora DESC";

$termo = '%' . $pesquisa . '%';

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'paciente' => $termo,
    'medico' => $termo,
    'especialidade' => $termo,
    'estado' => $termo
]);

$consultas = $stmt->fetchAll();

$pageTitle = 'Gestão de Consultas';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-content">

    <h1>Gestão de Consultas</h1>

    <p>Agendamento e acompanhamento das consultas médicas.</p>

    <?php if ($mensagem): ?>
        <div class="alert success"><?= e($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert error"><?= e($erro) ?></div>
    <?php endif; ?>

    <?php if (empty($pacientes) || empty($medicos)): ?>
        <div class="alert error">
            Para agendar consultas, é necessário ter pelo menos um paciente
            e um médico activos no sistema.
        </div>
    <?php endif; ?>

    <!-- FORMULÁRIO DE AGENDAMENTO -->

    <section class="recent-section">

        <h3>
            <?= $consultaEditar ? 'Remarcar Consulta' : 'Agendar Nova Consulta' ?>
        </h3>

        <form method="POST" action="consultas.php">

            <?= csrf_field() ?>

            <input type="hidden" name="acao" value="guardar">

            <input
                type="hidden"
                name="id_consulta"
                value="<?= e($consultaEditar['id_consulta'] ?? 0) ?>"
            >

            <div class="form-grid">

                <div class="form-group">
                    <label>Paciente *</label>

                    <select name="id_paciente" required>
                        <option value="">Seleccionar paciente</option>

                        <?php foreach ($pacientes as $paciente): ?>
                            <option
                                value="<?= e($paciente['id_paciente']) ?>"
                                <?= (
                                    isset($consultaEditar['id_paciente']) &&
                                    (int)$consultaEditar['id_paciente'] ===
                                    (int)$paciente['id_paciente']
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($paciente['nome']) ?>
                                <?= $paciente['bi'] ? ' - ' . e($paciente['bi']) : '' ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Médico *</label>

                    <select name="id_medico" required>
                        <option value="">Seleccionar médico</option>

                        <?php foreach ($medicos as $medico): ?>
                            <option
                                value="<?= e($medico['id_medico']) ?>"
                                <?= (
                                    isset($consultaEditar['id_medico']) &&
                                    (int)$consultaEditar['id_medico'] ===
                                    (int)$medico['id_medico']
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($medico['nome']) ?>
                                - <?= e($medico['especialidade']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Data da consulta *</label>

                    <input
                        type="date"
                        name="data_consulta"
                        required
                        value="<?= e($consultaEditar['data_consulta'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Hora da consulta *</label>

                    <input
                        type="time"
                        name="hora"
                        required
                        value="<?= e(isset($consultaEditar['hora']) ? substr($consultaEditar['hora'], 0, 5) : '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Duração (minutos) *</label>

                    <input
                        type="number"
                        name="duracao_minutos"
                        min="10"
                        max="240"
                        required
                        value="<?= e($consultaEditar['duracao_minutos'] ?? 30) ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Motivo da consulta *</label>

                    <textarea
                        name="motivo"
                        rows="3"
                        required
                    ><?= e($consultaEditar['motivo'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn-primary"
                    <?= (empty($pacientes) || empty($medicos)) ? 'disabled' : '' ?>
                >
                    <?= $consultaEditar ? 'Guardar Remarcação' : 'Agendar Consulta' ?>
                </button>

                <?php if ($consultaEditar): ?>
                    <a href="consultas.php" class="btn-secondary">
                        Cancelar edição
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- LISTA DE CONSULTAS -->

    <section class="recent-section">

        <h3>Consultas Registadas</h3>

        <form method="GET" class="search-form">

            <input
                type="text"
                name="pesquisa"
                placeholder="Pesquisar paciente, médico, especialidade ou estado..."
                value="<?= e($pesquisa) ?>"
            >

            <button type="submit" class="btn-primary">
                Pesquisar
            </button>

            <a href="consultas.php" class="btn-secondary">
                Limpar
            </a>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Especialidade</th>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Duração</th>
                        <th>Estado</th>
                        <th>Acções</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($consultas)): ?>

                        <tr>
                            <td colspan="9">Nenhuma consulta encontrada.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($consultas as $consulta): ?>

                            <tr>

                                <td><?= e($consulta['id_consulta']) ?></td>

                                <td><?= e($consulta['paciente']) ?></td>

                                <td><?= e($consulta['medico']) ?></td>

                                <td><?= e($consulta['especialidade']) ?></td>

                                <td>
                                    <?= e(date('d/m/Y', strtotime($consulta['data_consulta']))) ?>
                                </td>

                                <td><?= e(substr($consulta['hora'], 0, 5)) ?></td>

                                <td><?= e($consulta['duracao_minutos']) ?> min</td>

                                <td>
                                    <?php
                                    $classeEstado = match ($consulta['estado']) {
                                        'Marcada' => 'status-active',
                                        'Remarcada' => 'status-pending',
                                        'Concluída' => 'status-completed',
                                        'Cancelada' => 'status-inactive',
                                        default => ''
                                    };
                                    ?>

                                    <span class="<?= e($classeEstado) ?>">
                                        <?= e($consulta['estado']) ?>
                                    </span>
                                </td>

                                <td class="actions-cell">

                                    <?php if (in_array($consulta['estado'], ['Marcada', 'Remarcada'], true)): ?>

                                        <a
                                            href="consultas.php?editar=<?= e($consulta['id_consulta']) ?>"
                                            class="btn-edit"
                                        >
                                            Remarcar
                                        </a>

                                        <form method="POST" action="consultas.php"
                                              onsubmit="return confirm('Desejas cancelar esta consulta?');">

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="acao" value="cancelar">

                                            <input
                                                type="hidden"
                                                name="id_consulta"
                                                value="<?= e($consulta['id_consulta']) ?>"
                                            >

                                            <button type="submit" class="btn-delete">
                                                Cancelar
                                            </button>
                                        </form>

                                        <form method="POST" action="consultas.php"
                                              onsubmit="return confirm('Confirmas que esta consulta foi concluída?');">

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="acao" value="concluir">

                                            <input
                                                type="hidden"
                                                name="id_consulta"
                                                value="<?= e($consulta['id_consulta']) ?>"
                                            >

                                            <button type="submit" class="btn-primary">
                                                Concluir
                                            </button>
                                        </form>

                                    <?php else: ?>

                                        <span>Sem acções</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
