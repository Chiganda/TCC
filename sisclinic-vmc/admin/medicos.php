
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$pageTitle = 'Gestão de Médicos';

$mensagem = '';
$tipoMensagem = 'success';

// ========================================
// PROCESSAR FORMULÁRIOS
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_validate();

    $acao = $_POST['acao'] ?? '';

    // REGISTAR OU ACTUALIZAR MÉDICO
    if ($acao === 'guardar') {

        $idMedico = (int)($_POST['id_medico'] ?? 0);

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $senha = $_POST['senha'] ?? '';

        $idEspecialidade = (int)($_POST['id_especialidade'] ?? 0);
        $numeroOrdem = trim($_POST['numero_ordem'] ?? '');

        if (
            $nome === '' ||
            $email === '' ||
            $username === '' ||
            $idEspecialidade <= 0 ||
            $numeroOrdem === ''
        ) {

            $mensagem = 'Preenche todos os campos obrigatórios.';
            $tipoMensagem = 'error';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $mensagem = 'Introduz um endereço de email válido.';
            $tipoMensagem = 'error';

        } elseif ($idMedico === 0 && strlen($senha) < 8) {

            $mensagem = 'A palavra-passe deve ter pelo menos 8 caracteres.';
            $tipoMensagem = 'error';

        } elseif ($senha !== '' && strlen($senha) < 8) {

            $mensagem = 'A nova palavra-passe deve ter pelo menos 8 caracteres.';
            $tipoMensagem = 'error';

        } else {

            try {

                $pdo->beginTransaction();

                // VERIFICAR ESPECIALIDADE
                $stmt = $pdo->prepare(
                    "SELECT id_especialidade
                     FROM especialidades
                     WHERE id_especialidade = ?
                     LIMIT 1"
                );

                $stmt->execute([$idEspecialidade]);

                if (!$stmt->fetchColumn()) {
                    throw new Exception('A especialidade seleccionada não existe.');
                }

                // LOCALIZAR PERFIL MÉDICO
                $stmt = $pdo->prepare(
                    "SELECT id_perfil
                     FROM perfis
                     WHERE LOWER(nome) IN ('medico', 'médico')
                     LIMIT 1"
                );

                $stmt->execute();

                $idPerfilMedico = $stmt->fetchColumn();

                if (!$idPerfilMedico) {
                    throw new Exception('O perfil Médico não existe.');
                }

                if ($idMedico > 0) {

                    // BUSCAR UTILIZADOR ASSOCIADO
                    $stmt = $pdo->prepare(
                        "SELECT m.id_utilizador
                         FROM medicos m
                         INNER JOIN utilizadores u
                            ON u.id_utilizador = m.id_utilizador
                         WHERE m.id_medico = ?
                         AND m.deleted_at IS NULL
                         AND u.deleted_at IS NULL"
                    );

                    $stmt->execute([$idMedico]);

                    $idUtilizador = $stmt->fetchColumn();

                    if (!$idUtilizador) {
                        throw new Exception('Médico não encontrado.');
                    }

                    // ACTUALIZAR CONTA COM PALAVRA-PASSE
                    if ($senha !== '') {

                        $hash = password_hash($senha, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare(
                            "UPDATE utilizadores
                             SET nome = ?, email = ?, telefone = ?,
                                 username = ?, senha = ?
                             WHERE id_utilizador = ?"
                        );

                        $stmt->execute([
                            $nome,
                            $email,
                            $telefone,
                            $username,
                            $hash,
                            $idUtilizador
                        ]);

                    } else {

                        // ACTUALIZAR CONTA SEM ALTERAR PALAVRA-PASSE
                        $stmt = $pdo->prepare(
                            "UPDATE utilizadores
                             SET nome = ?, email = ?, telefone = ?,
                                 username = ?
                             WHERE id_utilizador = ?"
                        );

                        $stmt->execute([
                            $nome,
                            $email,
                            $telefone,
                            $username,
                            $idUtilizador
                        ]);
                    }

                    // ACTUALIZAR DADOS DO MÉDICO
                    $stmt = $pdo->prepare(
                        "UPDATE medicos
                         SET id_especialidade = ?, numero_ordem = ?
                         WHERE id_medico = ?"
                    );

                    $stmt->execute([
                        $idEspecialidade,
                        $numeroOrdem,
                        $idMedico
                    ]);

                    $mensagem = 'Dados do médico actualizados com sucesso.';

                } else {

                    // CRIAR CONTA DO MÉDICO
                    $hash = password_hash($senha, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare(
                        "INSERT INTO utilizadores
                         (nome, email, telefone, username, senha, id_perfil)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $nome,
                        $email,
                        $telefone,
                        $username,
                        $hash,
                        $idPerfilMedico
                    ]);

                    $idUtilizador = (int)$pdo->lastInsertId();

                    // CRIAR REGISTO DO MÉDICO
                    $stmt = $pdo->prepare(
                        "INSERT INTO medicos
                         (id_utilizador, id_especialidade, numero_ordem)
                         VALUES (?, ?, ?)"
                    );

                    $stmt->execute([
                        $idUtilizador,
                        $idEspecialidade,
                        $numeroOrdem
                    ]);

                    $mensagem = 'Médico registado com sucesso.';
                }

                $pdo->commit();

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Erro ao guardar médico: ' . $e->getMessage());

                $mensagem = 'Não foi possível guardar. Verifica se o email, username ou número de ordem já estão registados.';
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

    // DESACTIVAR MÉDICO
    if ($acao === 'desactivar') {

        $id = (int)($_POST['id_medico'] ?? 0);

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id_utilizador
                 FROM medicos
                 WHERE id_medico = ?
                 AND deleted_at IS NULL"
            );

            $stmt->execute([$id]);

            $idUtilizador = $stmt->fetchColumn();

            if (!$idUtilizador) {
                throw new Exception('Médico não encontrado ou já desactivado.');
            }

            $stmt = $pdo->prepare(
                "UPDATE medicos
                 SET deleted_at = NOW()
                 WHERE id_medico = ?"
            );

            $stmt->execute([$id]);

            $stmt = $pdo->prepare(
                "UPDATE utilizadores
                 SET deleted_at = NOW()
                 WHERE id_utilizador = ?"
            );

            $stmt->execute([$idUtilizador]);

            $pdo->commit();

            $mensagem = 'Médico desactivado com sucesso.';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $mensagem = 'Não foi possível desactivar o médico.';
            $tipoMensagem = 'error';

            error_log('Erro ao desactivar médico: ' . $e->getMessage());
        }
    }

    // REACTIVAR MÉDICO
    if ($acao === 'activar') {

        $id = (int)($_POST['id_medico'] ?? 0);

        try {

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id_utilizador
                 FROM medicos
                 WHERE id_medico = ?
                 AND deleted_at IS NOT NULL"
            );

            $stmt->execute([$id]);

            $idUtilizador = $stmt->fetchColumn();

            if (!$idUtilizador) {
                throw new Exception('Médico não encontrado ou já activo.');
            }

            $stmt = $pdo->prepare(
                "UPDATE utilizadores
                 SET deleted_at = NULL
                 WHERE id_utilizador = ?"
            );

            $stmt->execute([$idUtilizador]);

            $stmt = $pdo->prepare(
                "UPDATE medicos
                 SET deleted_at = NULL
                 WHERE id_medico = ?"
            );

            $stmt->execute([$id]);

            $pdo->commit();

            $mensagem = 'Médico reactivado com sucesso.';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $mensagem = 'Não foi possível reactivar o médico.';
            $tipoMensagem = 'error';

            error_log('Erro ao reactivar médico: ' . $e->getMessage());
        }
    }
}

// ========================================
// BUSCAR MÉDICO PARA EDIÇÃO
// ========================================

$medicoEditar = null;

if (isset($_GET['editar'])) {

    $idEditar = (int)$_GET['editar'];

    $stmt = $pdo->prepare(
        "SELECT
            m.id_medico,
            m.id_especialidade,
            m.numero_ordem,
            u.nome,
            u.email,
            u.telefone,
            u.username
         FROM medicos m
         INNER JOIN utilizadores u
            ON u.id_utilizador = m.id_utilizador
         WHERE m.id_medico = ?
         AND m.deleted_at IS NULL
         AND u.deleted_at IS NULL"
    );

    $stmt->execute([$idEditar]);

    $medicoEditar = $stmt->fetch();

    if (!$medicoEditar) {
        $mensagem = 'Médico não encontrado.';
        $tipoMensagem = 'error';
    }
}

// ========================================
// BUSCAR ESPECIALIDADES
// ========================================

$stmt = $pdo->query(
    "SELECT id_especialidade, nome
     FROM especialidades
     ORDER BY nome ASC"
);

$especialidades = $stmt->fetchAll();

// ========================================
// PESQUISAR MÉDICOS
// ========================================

$pesquisa = trim($_GET['pesquisa'] ?? '');

$sql = "SELECT
            m.id_medico,
            m.numero_ordem,
            m.deleted_at,
            u.nome,
            u.email,
            u.telefone,
            e.nome AS especialidade
        FROM medicos m
        INNER JOIN utilizadores u
            ON u.id_utilizador = m.id_utilizador
        INNER JOIN especialidades e
            ON e.id_especialidade = m.id_especialidade
        WHERE (
            u.nome LIKE :pesquisa_nome
            OR u.email LIKE :pesquisa_email
            OR m.numero_ordem LIKE :pesquisa_ordem
            OR e.nome LIKE :pesquisa_especialidade
        )
        ORDER BY m.id_medico DESC";

$stmt = $pdo->prepare($sql);

$termoPesquisa = '%' . $pesquisa . '%';

$stmt->execute([
    'pesquisa_nome' => $termoPesquisa,
    'pesquisa_email' => $termoPesquisa,
    'pesquisa_ordem' => $termoPesquisa,
    'pesquisa_especialidade' => $termoPesquisa
]);

$medicos = $stmt->fetchAll();

// ========================================
// CARREGAR CABEÇALHO
// ========================================

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-content">

    <h1>Gestão de Médicos</h1>

    <p>Registo, consulta e gestão dos médicos da clínica.</p>

    <?php if ($mensagem !== ''): ?>
        <div class="alert <?= e($tipoMensagem) ?>">
            <?= e($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- FORMULÁRIO -->

    <section class="recent-section">

        <h2>
            <?= $medicoEditar ? 'Editar Médico' : 'Registar Novo Médico' ?>
        </h2>

        <?php if (empty($especialidades)): ?>

            <div class="alert error">
                Ainda não existem especialidades registadas.
                Regista primeiro as especialidades para poderes adicionar médicos.
            </div>

        <?php else: ?>

            <form method="POST" action="medicos.php">

                <?= csrf_field() ?>

                <input type="hidden" name="acao" value="guardar">

                <input
                    type="hidden"
                    name="id_medico"
                    value="<?= e($medicoEditar['id_medico'] ?? '') ?>"
                >

                <h3>Dados pessoais e de acesso</h3>

                <div class="form-grid">

                    <div class="form-group">
                        <label>Nome completo *</label>
                        <input
                            type="text"
                            name="nome"
                            required
                            value="<?= e($medicoEditar['nome'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input
                            type="email"
                            name="email"
                            required
                            value="<?= e($medicoEditar['email'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Telefone</label>
                        <input
                            type="text"
                            name="telefone"
                            value="<?= e($medicoEditar['telefone'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>Nome de utilizador *</label>
                        <input
                            type="text"
                            name="username"
                            required
                            value="<?= e($medicoEditar['username'] ?? '') ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label>
                            Palavra-passe
                            <?= $medicoEditar ? '(opcional)' : '*' ?>
                        </label>

                        <input
                            type="password"
                            name="senha"
                            <?= $medicoEditar ? '' : 'required' ?>
                            minlength="8"
                            autocomplete="new-password"
                        >
                    </div>

                </div>

                <h3>Dados profissionais</h3>

                <div class="form-grid">

                    <div class="form-group">
                        <label>Especialidade *</label>

                        <select name="id_especialidade" required>

                            <option value="">Seleccionar especialidade</option>

                            <?php foreach ($especialidades as $especialidade): ?>

                                <option
                                    value="<?= e($especialidade['id_especialidade']) ?>"
                                    <?= (int)($medicoEditar['id_especialidade'] ?? 0) === (int)$especialidade['id_especialidade'] ? 'selected' : '' ?>
                                >
                                    <?= e($especialidade['nome']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label>Número de ordem profissional *</label>

                        <input
                            type="text"
                            name="numero_ordem"
                            required
                            value="<?= e($medicoEditar['numero_ordem'] ?? '') ?>"
                        >
                    </div>

                </div>

                <div class="form-actions">

                    <button type="submit" class="btn-primary">
                        <?= $medicoEditar ? 'Actualizar Médico' : 'Registar Médico' ?>
                    </button>

                    <?php if ($medicoEditar): ?>

                        <a href="medicos.php" class="btn-secondary">
                            Cancelar
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        <?php endif; ?>

    </section>

    <!-- LISTAGEM -->

    <section class="recent-section">

        <h2>Médicos registados</h2>

        <form method="GET" action="medicos.php" class="search-form">

            <input
                type="search"
                name="pesquisa"
                placeholder="Pesquisar por nome, email, especialidade ou número de ordem..."
                value="<?= e($pesquisa) ?>"
            >

            <button type="submit" class="btn-primary">
                Pesquisar
            </button>

            <?php if ($pesquisa !== ''): ?>

                <a href="medicos.php" class="btn-secondary">
                    Limpar
                </a>

            <?php endif; ?>

        </form>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Médico</th>
                        <th>Especialidade</th>
                        <th>Número de ordem</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Estado</th>
                        <th>Acções</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($medicos)): ?>

                        <tr>
                            <td colspan="8">Nenhum médico encontrado.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($medicos as $m): ?>

                            <tr>

                                <td><?= e($m['id_medico']) ?></td>

                                <td><?= e($m['nome']) ?></td>

                                <td><?= e($m['especialidade']) ?></td>

                                <td><?= e($m['numero_ordem']) ?></td>

                                <td><?= e($m['email']) ?></td>

                                <td><?= e($m['telefone']) ?></td>

                                <td>
                                    <?php if ($m['deleted_at'] === null): ?>

                                        <span class="status-active">Activo</span>

                                    <?php else: ?>

                                        <span class="status-inactive">Desactivado</span>

                                    <?php endif; ?>
                                </td>

                                <td class="actions-cell">

                                    <?php if ($m['deleted_at'] === null): ?>

                                        <a
                                            href="medicos.php?editar=<?= e($m['id_medico']) ?>"
                                            class="btn-edit"
                                        >
                                            Editar
                                        </a>

                                        <form
                                            method="POST"
                                            action="medicos.php"
                                            onsubmit="return confirm('Pretendes desactivar este médico?');"
                                        >

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="acao" value="desactivar">

                                            <input
                                                type="hidden"
                                                name="id_medico"
                                                value="<?= e($m['id_medico']) ?>"
                                            >

                                            <button type="submit" class="btn-delete">
                                                Desactivar
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form method="POST" action="medicos.php">

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="acao" value="activar">

                                            <input
                                                type="hidden"
                                                name="id_medico"
                                                value="<?= e($m['id_medico']) ?>"
                                            >

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
