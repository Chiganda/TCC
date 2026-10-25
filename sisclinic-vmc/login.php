
<?php

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/bootstrap.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $erro = 'Preenche o utilizador e a palavra-passe.';
    } else {
        $sql = "
            SELECT
                u.id_utilizador,
                u.nome,
                u.username,
                u.senha,
                p.nome AS perfil
            FROM utilizadores u
            INNER JOIN perfis p ON p.id_perfil = u.id_perfil
            WHERE (u.username = :username OR u.email = :email)
              AND u.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'email' => $username,
        ]);

        $utilizador = $stmt->fetch();

        if (
            $utilizador &&
            password_verify($password, $utilizador['senha'])
        ) {
            session_regenerate_id(true);

            $_SESSION['id_utilizador'] =
                (int) $utilizador['id_utilizador'];

            $_SESSION['nome'] = $utilizador['nome'];
            $_SESSION['perfil'] = $utilizador['perfil'];

            header('Location: /TCC-VMC/sisclinic-vmc/admin/dashboard.php');
            exit;
        }

        $erro = 'Credenciais inválidas.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | SisClinic VMC</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
        }

        .login-box {
            background: white;
            width: min(420px, 92%);
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }

        h1 { color: #153a63; }

        label {
            display: block;
            margin: 16px 0 6px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 22px;
            border: 0;
            border-radius: 6px;
            background: #153a63;
            color: white;
            cursor: pointer;
        }

        .erro {
            padding: 10px;
            color: #991b1b;
            background: #fee2e2;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <main class="login-box">
        <h1>SisClinic VMC</h1>
        <p>Inicia sessão para continuar.</p>

        <?php if ($erro !== ''): ?>
            <p class="erro"><?= e($erro) ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <label for="username">Utilizador ou email</label>
            <input
                type="text"
                id="username"
                name="username"
                autocomplete="username"
                required
            >

            <label for="password">Palavra-passe</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>
