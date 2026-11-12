
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$pageTitle = 'Gestão de Pacientes';

$mensagem = '';
$tipoMensagem = 'success';

// ========================================
// PROCESSAR FORMULÁRIOS
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_validate();

    $acao = $_POST['acao'] ?? '';

    // REGISTAR OU ACTUALIZAR PACIENTE
    if ($acao === 'guardar') {

        $idPaciente = (int)($_POST['id_paciente'] ?? 0);

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $senha = $_POST['senha'] ?? '';

        $bi = trim($_POST['bi'] ?? '');
        $sexo = $_POST['sexo'] ?? '';
        $dataNascimento = $_POST['data_nascimento'] ?? '';
        $endereco = trim($_POST['endereco'] ?? '');
        $tipoSanguineo = $_POST['tipo_sanguineo'] ?? '';
        $alergias = trim($_POST['alergias'] ?? '');
        $contactoEmergencia = trim($_POST['contacto_emergencia'] ?? '');
        $telefoneEmergencia = trim($_POST['telefone_emergencia'] ?? '');

        $sexosValidos = ['Masculino', 'Feminino', 'Outro'];

        $tiposSanguineosValidos = [
            '', 'A+', 'A-', 'B+', 'B-',
            'AB+', 'AB-', 'O+', 'O-'
        ];

        if (
            $nome === '' ||
            $email === '' ||
            $username === '' ||
            $bi === '' ||
            $sexo === '' ||
            $dataNascimento === '' ||
            $endereco === ''
        ) {
            $mensagem = 'Preenche todos os campos obrigatórios.';
            $tipoMensagem = 'error';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $mensagem = 'Introduz um email válido.';
            $tipoMensagem = 'error';

        } elseif (!in_array($sexo, $sexosValidos, true)) {

            $mensagem = 'Selecciona um sexo válido.';
            $tipoMensagem = 'error';

        } elseif (!in_array($tipoSanguineo, $tiposSanguineosValidos, true)) {

            $mensagem = 'Selecciona um tipo sanguíneo válido.';
            $tipoMensagem = 'error';

        } elseif (
            !$dataNascimento ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataNascimento) ||
            !checkdate(
                (int)substr($dataNascimento, 5, 2),
                (int)substr($dataNascimento, 8, 2),
                (int)substr($dataNascimento, 0, 4)
            ) ||
            $dataNascimento > date('Y-m-d')
        ) {

            $mensagem = 'A data de nascimento é inválida.';
            $tipoMensagem = 'error';

        } elseif ($idPaciente === 0 && strlen($senha) < 8) {

            $mensagem = 'A palavra-passe deve ter pelo menos 8 caracteres.';
            $tipoMensagem = 'error';

        } elseif ($senha !== '' && strlen($senha) < 8) {

            $mensagem = 'A nova palavra-passe deve ter pelo menos 8 caracteres.';
            $tipoMensagem = 'error';

        } else {

            try {

                $pdo->beginTransaction();

                // LOCALIZAR PERFIL PACIENTE
                $stmtPerfil = $pdo->prepare(
                    "SELECT id_perfil
                     FROM perfis
                     WHERE LOWER(nome) = 'paciente'
                     LIMIT 1"
                );

                $stmtPerfil->execute();
                $idPerfilPaciente = $stmtPerfil->fetchColumn();

                if (!$idPerfilPaciente) {
                    throw new Exception(
                        'O perfil Paciente não existe na tabela perfis.'
                    );
                }

                if ($idPaciente > 0) {

                    // BUSCAR UTILIZADOR ASSOCIADO
                    $stmt = $pdo->prepare(
                        "SELECT id_utilizador
                         FROM pacientes
                         WHERE id_paciente = ?
                         AND deleted_at IS NULL"
                    );

                    $stmt->execute([$idPaciente]);
                    $idUtilizador = $stmt->fetchColumn();

                    if (!$idUtilizador) {
                        throw new Exception('Paciente não encontrado.');
                    }

                    // ACTUALIZAR CONTA
                    if ($senha !== '') {

                        $hash = password_hash($senha, PASSWORD_DEFAULT);

                        $sql = "UPDATE utilizadores
                                SET nome = ?, email = ?, telefone = ?,
                                    username = ?, senha = ?
                                WHERE id_utilizador = ?
                                AND deleted_at IS NULL";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $nome, $email, $telefone,
                            $username, $hash, $idUtilizador
                        ]);

                    } else {

                        $sql = "UPDATE utilizadores
                                SET nome = ?, email = ?, telefone = ?,
                                    username = ?
                                WHERE id_utilizador = ?
                                AND deleted_at IS NULL";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $nome, $email, $telefone,
                            $username, $idUtilizador
                        ]);
                    }

                    // ACTUALIZAR DADOS DO PACIENTE
                    $sql = "UPDATE pacientes
                            SET bi = ?, sexo = ?, data_nascimento = ?,
                                endereco = ?, tipo_sanguineo = ?,
                                alergias = ?, contacto_emergencia = ?,
                                telefone_emergencia = ?
                            WHERE id_paciente = ?";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        $bi,
                        $sexo,
                        $dataNascimento,
                        $endereco,
                        $tipoSanguineo !== '' ? $tipoSanguineo : null,
                        $alergias,
                        $contactoEmergencia,
                        $telefoneEmergencia,
                        $idPaciente
                    ]);

                    $mensagem = 'Dados do paciente actualizados com sucesso.';

                } else {

                    // CRIAR CONTA DE UTILIZADOR
                    $hash = password_hash($senha, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO utilizadores
                            (nome, email, telefone, username, senha, id_perfil)
                            VALUES (?, ?, ?, ?, ?, ?)";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        $nome,
                        $email,
                        $telefone,
                        $username,
                        $hash,
                        $idPerfilPaciente
                    ]);

                    $idUtilizador = (int)$pdo->lastInsertId();

                    // CRIAR REGISTO DO PACIENTE
                    $sql = "INSERT INTO pacientes
                            (
                                id_utilizador, bi, sexo,
                                data_nascimento, endereco,
                                tipo_sanguineo, alergias,
                                contacto_emergencia,
                                telefone_emergencia
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        $idUtilizador,
                        $bi,
                        $sexo,
                        $dataNascimento,
                        $endereco,
                        $tipoSanguineo !== '' ? $tipoSanguineo : null,
                        $alergias,
                        $contactoEmergencia,
                        $telefoneEmergencia
                    ]);

                    $mensagem = 'Paciente registado com sucesso.';
                }

                $pdo->commit();

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Erro ao guardar paciente: ' . $e->getMessage());

                $mensagem = 'Não foi possível guardar. Verifica se o BI, email ou username já estão registados.';
                $tipoMensagem = 'error';

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $mensagem = $e->getMessage();
                $tipoMensagem = 'error';
            }
        }
    }

    // DESACTIVAR PACIENTE
    if ($acao === 'desactivar') {

        $id = (int)($_POST['id_paciente'] ?? 0);

        $stmt = $pdo->prepare(
            "UPDATE pacientes
             SET deleted_at = NOW()
             WHERE id_paciente = ?
             AND deleted_at IS NULL"
        );

        $stmt->execute([$id]);

        $mensagem = 'Registo do paciente desactivado.';
    }

    // REACTIVAR PACIENTE
    if ($acao === 'activar') {

        $id = (int)($_POST['id_paciente'] ?? 0);

        $stmt = $pdo->prepare(
            "UPDATE pacientes
             SET deleted_at = NULL
             WHERE id_paciente = ?"
        );

        $stmt->execute([$id]);

        $mensagem = 'Registo do paciente reactivado.';
    }
}

// ========================================
// BUSCAR PACIENTE PARA EDIÇÃO
// ========================================

$pacienteEditar = null;

if (isset($_GET['editar'])) {

    $idEditar = (int)$_GET['editar'];

    $stmt = $pdo->prepare(
        "SELECT
            p.*,
            u.nome,
            u.email,
            u.telefone,
            u.username
         FROM pacientes p
         INNER JOIN utilizadores u
            ON u.id_utilizador = p.id_utilizador
         WHERE p.id_paciente = ?
         AND p.deleted_at IS NULL
         AND u.deleted_at IS NULL"
    );

    $stmt->execute([$idEditar]);
    $pacienteEditar = $stmt->fetch();

    if (!$pacienteEditar) {
        $mensagem = 'Paciente não encontrado.';
        $tipoMensagem = 'error';
    }
}


// ========================================
// PESQUISAR PACIENTES
// ========================================

$pesquisa = trim($_GET['pesquisa'] ?? '');

$sql = "SELECT
            p.id_paciente,
            p.bi,
            p.sexo,
            p.data_nascimento,
            p.telefone_emergencia,
            p.deleted_at,
            u.nome,
            u.email,
            u.telefone
        FROM pacientes p
        INNER JOIN utilizadores u
            ON u.id_utilizador = p.id_utilizador
        WHERE (
            u.nome LIKE :pesquisa_nome
            OR u.email LIKE :pesquisa_email
            OR p.bi LIKE :pesquisa_bi
        )
        ORDER BY p.id_paciente DESC";

$stmt = $pdo->prepare($sql);

$termoPesquisa = '%' . $pesquisa . '%';
$stmt->execute([
    'pesquisa_nome' => $termoPesquisa,
    'pesquisa_email' => $termoPesquisa,
    'pesquisa_bi' => $termoPesquisa
]);

$pacientes = $stmt->fetchAll();

// Carregar o cabeçalho e o CSS
require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-content">

    <!-- FORMULÁRIO -->

    <section class="recent-section">

        <h2>
            <?= $pacienteEditar ? 'Editar Paciente' : 'Registar Novo Paciente' ?>
        </h2>

        <form method="POST" action="pacientes.php">

            <?= csrf_field() ?>

            <input type="hidden" name="acao" value="guardar">

            <input
                type="hidden"
                name="id_paciente"
                value="<?= e($pacienteEditar['id_paciente'] ?? '') ?>"
            >

            <h3>Dados pessoais e de acesso</h3>

            <div class="form-grid">

                <div class="form-group">
                    <label>Nome completo *</label>
                    <input type="text" name="nome" required
                        value="<?= e($pacienteEditar['nome'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required
                        value="<?= e($pacienteEditar['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone"
                        value="<?= e($pacienteEditar['telefone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Nome de utilizador *</label>
                    <input type="text" name="username" required
                        value="<?= e($pacienteEditar['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>
                        Palavra-passe
                        <?= $pacienteEditar ? '(opcional)' : '*' ?>
                    </label>

                    <input type="password" name="senha"
                        <?= $pacienteEditar ? '' : 'required' ?>
                        minlength="8"
                        autocomplete="new-password">
                </div>

            </div>

            <h3>Informações pessoais</h3>

            <div class="form-grid">

                <div class="form-group">
                    <label>Número do BI *</label>
                    <input type="text" name="bi" required
                        value="<?= e($pacienteEditar['bi'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Sexo *</label>

                    <select name="sexo" required>
                        <option value="">Seleccionar</option>

                        <?php foreach (['Masculino', 'Feminino', 'Outro'] as $sexo): ?>
                            <option value="<?= e($sexo) ?>"
                                <?= ($pacienteEditar['sexo'] ?? '') === $sexo ? 'selected' : '' ?>>
                                <?= e($sexo) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Data de nascimento *</label>
                    <input type="date" name="data_nascimento" required
                        max="<?= date('Y-m-d') ?>"
                        value="<?= e($pacienteEditar['data_nascimento'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Endereço *</label>
                    <input type="text" name="endereco" required
                        value="<?= e($pacienteEditar['endereco'] ?? '') ?>">
                </div>

            </div>

            <h3>Informações clínicas</h3>

            <div class="form-grid">

                <div class="form-group">
                    <label>Tipo sanguíneo</label>

                    <select name="tipo_sanguineo">
                        <option value="">Não informado</option>

                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $tipo): ?>
                            <option value="<?= e($tipo) ?>"
                                <?= ($pacienteEditar['tipo_sanguineo'] ?? '') === $tipo ? 'selected' : '' ?>>
                                <?= e($tipo) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Alergias</label>
                    <input type="text" name="alergias"
                        value="<?= e($pacienteEditar['alergias'] ?? '') ?>"
                        placeholder="Ex.: Não informado">
                </div>

            </div>

            <h3>Contacto de emergência</h3>

            <div class="form-grid">

                <div class="form-group">
                    <label>Nome do contacto</label>
                    <input type="text" name="contacto_emergencia"
                        value="<?= e($pacienteEditar['contacto_emergencia'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Telefone de emergência</label>
                    <input type="text" name="telefone_emergencia"
                        value="<?= e($pacienteEditar['telefone_emergencia'] ?? '') ?>">
                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn-primary">
                    <?= $pacienteEditar ? 'Actualizar Paciente' : 'Registar Paciente' ?>
                </button>

                <?php if ($pacienteEditar): ?>
                    <a href="pacientes.php" class="btn-secondary">
                        Cancelar
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- LISTAGEM -->

    <section class="recent-section">

        <h2>Pacientes registados</h2>

        <form method="GET" action="pacientes.php" class="search-form">

            <input
                type="search"
                name="pesquisa"
                placeholder="Pesquisar por nome, email ou BI..."
                value="<?= e($pesquisa) ?>"
            >

            <button type="submit" class="btn-primary">
                Pesquisar
            </button>

            <?php if ($pesquisa !== ''): ?>
                <a href="pacientes.php" class="btn-secondary">Limpar</a>
            <?php endif; ?>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Paciente</th>
                        <th>BI</th>
                        <th>Telefone</th>
                        <th>Sexo</th>
                        <th>Estado</th>
                        <th>Acções</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($pacientes)): ?>

                        <tr>
                            <td colspan="7">Nenhum paciente encontrado.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($pacientes as $p): ?>

                            <tr>

                                <td><?= e($p['id_paciente']) ?></td>
                                <td><?= e($p['nome']) ?></td>
                                <td><?= e($p['bi']) ?></td>
                                <td><?= e($p['telefone']) ?></td>
                                <td><?= e($p['sexo']) ?></td>

                                <td>
                                    <?php if ($p['deleted_at'] === null): ?>
                                        <span class="status-active">Activo</span>
                                    <?php else: ?>
                                        <span class="status-inactive">Desactivado</span>
                                    <?php endif; ?>
                                </td>

                                <td class="actions-cell">

                                    <?php if ($p['deleted_at'] === null): ?>

                                        <a
                                            href="pacientes.php?editar=<?= e($p['id_paciente']) ?>"
                                            class="btn-edit">
                                            Editar
                                        </a>

                                        <form method="POST" action="pacientes.php"
                                            onsubmit="return confirm('Pretendes desactivar este paciente?');">

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="acao" value="desactivar">

                                            <input type="hidden" name="id_paciente"
                                                value="<?= e($p['id_paciente']) ?>">

                                            <button type="submit" class="btn-delete">
                                                Desactivar
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form method="POST" action="pacientes.php">

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="acao" value="activar">

                                            <input type="hidden" name="id_paciente"
                                                value="<?= e($p['id_paciente']) ?>">

                                            <button type="submit" class="btn-edit">
                                                Reactivar
                                            </button>

                                        </form>

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
