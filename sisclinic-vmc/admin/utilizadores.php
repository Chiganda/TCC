
<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$pageTitle = 'Gestão de Utilizadores';

$mensagem = '';
$tipoMensagem = 'success';

// ========================================
// PROCESSAR FORMULÁRIOS
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_validate();

    $acao = $_POST['acao'] ?? '';

    // REGISTAR OU EDITAR UTILIZADOR
    if ($acao === 'guardar') {

        $id = (int) ($_POST['id_utilizador'] ?? 0);

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $idPerfil = (int) ($_POST['id_perfil'] ?? 0);

        if (
            $nome === '' ||
            $email === '' ||
            $username === '' ||
            $idPerfil <= 0
        ) {
            $mensagem = 'Preenche todos os campos obrigatórios.';
            $tipoMensagem = 'error';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $mensagem = 'Introduz um endereço de email válido.';
            $tipoMensagem = 'error';

        } elseif ($id === 0 && strlen($senha) < 8) {

            $mensagem = 'A palavra-passe deve ter pelo menos 8 caracteres.';
            $tipoMensagem = 'error';

        } elseif ($senha !== '' && strlen($senha) < 8) {

            $mensagem = 'A nova palavra-passe deve ter pelo menos 8 caracteres.';
            $tipoMensagem = 'error';

        } else {

            try {

                // Verificar se o perfil existe
                $stmtPerfil = $pdo->prepare(
                    "SELECT COUNT(*) FROM perfis WHERE id_perfil = ?"
                );
                $stmtPerfil->execute([$idPerfil]);

                if ((int) $stmtPerfil->fetchColumn() === 0) {
                    throw new Exception('O perfil seleccionado não existe.');
                }

                if ($id > 0) {

                    // EDITAR UTILIZADOR
                    if ($senha !== '') {

                        $hash = password_hash($senha, PASSWORD_DEFAULT);

                        $sql = "UPDATE utilizadores
                                SET nome = ?, email = ?, telefone = ?,
                                    username = ?, senha = ?, id_perfil = ?
                                WHERE id_utilizador = ?
                                AND deleted_at IS NULL";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $nome,
                            $email,
                            $telefone,
                            $username,
                            $hash,
                            $idPerfil,
                            $id
                        ]);

                    } else {

                        $sql = "UPDATE utilizadores
                                SET nome = ?, email = ?, telefone = ?,
                                    username = ?, id_perfil = ?
                                WHERE id_utilizador = ?
                                AND deleted_at IS NULL";

                        $stmt = $pdo->prepare($sql);

                        $stmt->execute([
                            $nome,
                            $email,
                            $telefone,
                            $username,
                            $idPerfil,
                            $id
                        ]);
                    }

                    $mensagem = 'Utilizador actualizado com sucesso.';

                } else {

                    // REGISTAR NOVO UTILIZADOR
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
                        $idPerfil
                    ]);

                    $mensagem = 'Utilizador registado com sucesso.';
                }

            } catch (PDOException $e) {

                error_log('Erro ao guardar utilizador: ' . $e->getMessage());

                $mensagem = 'Não foi possível guardar. Verifica se o email ou username já estão registados.';
                $tipoMensagem = 'error';

            } catch (Exception $e) {

                $mensagem = $e->getMessage();
                $tipoMensagem = 'error';
            }
        }
    }

    // DESACTIVAR UTILIZADOR
    if ($acao === 'desactivar') {

        $id = (int) ($_POST['id_utilizador'] ?? 0);

        if ($id === (int) $_SESSION['id_utilizador']) {

            $mensagem = 'Não podes desactivar a tua própria conta.';
            $tipoMensagem = 'error';

        } else {

            $stmt = $pdo->prepare(
                "UPDATE utilizadores
                 SET deleted_at = NOW()
                 WHERE id_utilizador = ?
                 AND deleted_at IS NULL"
            );

            $stmt->execute([$id]);

            $mensagem = 'Conta desactivada com sucesso.';
        }
    }

    // REACTIVAR UTILIZADOR
    if ($acao === 'activar') {

        $id = (int) ($_POST['id_utilizador'] ?? 0);

        $stmt = $pdo->prepare(
            "UPDATE utilizadores
             SET deleted_at = NULL
             WHERE id_utilizador = ?"
        );

        $stmt->execute([$id]);

        $mensagem = 'Conta activada com sucesso.';
    }
}

// ========================================
// BUSCAR PERFIS
// ========================================

$perfis = $pdo->query(
    "SELECT id_perfil, nome
     FROM perfis
     ORDER BY nome"
)->fetchAll();

// ========================================
// BUSCAR UTILIZADOR PARA EDIÇÃO
// ========================================

$utilizadorEditar = null;

if (isset($_GET['editar'])) {

    $idEditar = (int) $_GET['editar'];

    $stmt = $pdo->prepare(
        "SELECT id_utilizador, nome, email, telefone,
                username, id_perfil
         FROM utilizadores
         WHERE id_utilizador = ?
         AND deleted_at IS NULL"
    );

    $stmt->execute([$idEditar]);

    $utilizadorEditar = $stmt->fetch();

    if (!$utilizadorEditar) {
        $mensagem = 'Utilizador não encontrado ou desactivado.';
        $tipoMensagem = 'error';
    }
}

// ========================================
// LISTAR UTILIZADORES
// ========================================

$sql = "SELECT
            u.id_utilizador,
            u.nome,
            u.email,
            u.telefone,
            u.username,
            u.deleted_at,
            p.nome AS perfil
        FROM utilizadores u
        LEFT JOIN perfis p
            ON p.id_perfil = u.id_perfil
        ORDER BY u.id_utilizador DESC";

$utilizadores = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-content">

    <h1>Gestão de Utilizadores</h1>

    <p>Regista, consulta e administra as contas de acesso ao SisClinic VMC.</p>

    <?php if ($mensagem !== ''): ?>
        <div class="alert <?= e($tipoMensagem) ?>">
            <?= e($mensagem) ?>
        </div>
    <?php endif; ?>

    <!-- FORMULÁRIO -->
    <section class="recent-section">

        <h2>
            <?= $utilizadorEditar ? 'Editar Utilizador' : 'Registar Novo Utilizador' ?>
        </h2>

        <form method="POST" action="utilizadores.php">

            <?= csrf_field() ?>

            <input type="hidden" name="acao" value="guardar">

            <input
                type="hidden"
                name="id_utilizador"
                value="<?= e($utilizadorEditar['id_utilizador'] ?? '') ?>"
            >

            <div class="form-grid">

                <div class="form-group">
                    <label>Nome completo *</label>
                    <input
                        type="text"
                        name="nome"
                        required
                        value="<?= e($utilizadorEditar['nome'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input
                        type="email"
                        name="email"
                        required
                        value="<?= e($utilizadorEditar['email'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Telefone</label>
                    <input
                        type="text"
                        name="telefone"
                        value="<?= e($utilizadorEditar['telefone'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Nome de utilizador *</label>
                    <input
                        type="text"
                        name="username"
                        required
                        value="<?= e($utilizadorEditar['username'] ?? '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Perfil *</label>

                    <select name="id_perfil" required>

                        <option value="">Seleccionar perfil</option>

                        <?php foreach ($perfis as $perfil): ?>

                            <option
                                value="<?= e($perfil['id_perfil']) ?>"
                                <?= (
                                    isset($utilizadorEditar['id_perfil']) &&
                                    $utilizadorEditar['id_perfil'] == $perfil['id_perfil']
                                ) ? 'selected' : '' ?>
                            >
                                <?= e($perfil['nome']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>
                        Palavra-passe
                        <?= $utilizadorEditar ? '(deixa vazio para manter)' : '*' ?>
                    </label>

                    <input
                        type="password"
                        name="senha"
                        <?= $utilizadorEditar ? '' : 'required' ?>
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

            </div>

            <div class="form-actions">

                <button type="submit" class="btn-primary">
                    <?= $utilizadorEditar ? 'Actualizar Utilizador' : 'Guardar Utilizador' ?>
                </button>

                <?php if ($utilizadorEditar): ?>
                    <a href="utilizadores.php" class="btn-secondary">
                        Cancelar edição
                    </a>
                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- TABELA -->
    <section class="recent-section">

        <h2>Lista de Utilizadores</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Perfil</th>
                        <th>Estado</th>
                        <th>Acções</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($utilizadores)): ?>

                        <tr>
                            <td colspan="7">Nenhum utilizador registado.</td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($utilizadores as $u): ?>

                            <tr>

                                <td><?= e($u['id_utilizador']) ?></td>

                                <td><?= e($u['nome']) ?></td>

                                <td><?= e($u['email']) ?></td>

                                <td><?= e($u['username']) ?></td>

                                <td><?= e($u['perfil'] ?? 'Sem perfil') ?></td>

                                <td>
                                    <?php if ($u['deleted_at'] === null): ?>
                                        <span class="status-active">Activo</span>
                                    <?php else: ?>
                                        <span class="status-inactive">Desactivado</span>
                                    <?php endif; ?>
                                </td>

                                <td class="actions-cell">

                                    <?php if ($u['deleted_at'] === null): ?>

                                        <a
                                            href="utilizadores.php?editar=<?= e($u['id_utilizador']) ?>"
                                            class="btn-edit"
                                        >
                                            Editar
                                        </a>

                                        <?php if ((int)$u['id_utilizador'] !== (int)$_SESSION['id_utilizador']): ?>

                                            <form method="POST" action="utilizadores.php"
                                                  onsubmit="return confirm('Tens a certeza de que pretendes desactivar esta conta?');">

                                                <?= csrf_field() ?>

                                                <input type="hidden" name="acao" value="desactivar">

                                                <input
                                                    type="hidden"
                                                    name="id_utilizador"
                                                    value="<?= e($u['id_utilizador']) ?>"
                                                >

                                                <button type="submit" class="btn-delete">
                                                    Desactivar
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <form method="POST" action="utilizadores.php">

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="acao" value="activar">

                                            <input
                                                type="hidden"
                                                name="id_utilizador"
                                                value="<?= e($u['id_utilizador']) ?>"
                                            >

                                            <button type="submit" class="btn-edit">
                                                Activar
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
