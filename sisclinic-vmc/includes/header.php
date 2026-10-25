
<?php
$pageTitle = $pageTitle ?? 'SisClinic VMC';
$baseUrl = '/TCC-VMC/sisclinic-vmc';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | SisClinic VMC</title>
<link rel="stylesheet"
      href="<?= $baseUrl ?>/public/css/admin.css?v=<?= filemtime(__DIR__ . '/../public/css/admin.css') ?>"></head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <h2>SisClinic VMC</h2>

        <nav>
            <a href="<?= $baseUrl ?>/admin/dashboard.php">Dashboard</a>
            <a href="<?= $baseUrl ?>/admin/utilizadores.php">Utilizadores</a>
            <a href="<?= $baseUrl ?>/admin/pacientes.php">Pacientes</a>
            <a href="<?= $baseUrl ?>/admin/medicos.php">Médicos</a>
            <a href="<?= $baseUrl ?>/admin/consultas.php">Consultas</a>
            <a href="<?= $baseUrl ?>/admin/facturas.php">Facturas</a>
            <a href="<?= $baseUrl ?>/admin/relatorios.php">Relatórios</a>
            <a href="<?= $baseUrl ?>/admin/auditoria.php">Auditoria</a>
            <a href="<?= $baseUrl ?>/logout.php">Terminar sessão</a>
        </nav>
    </aside>

    <div class="main-area">
        <header class="topbar">
            <span><?= e($_SESSION['nome'] ?? 'Utilizador') ?></span>
        </header>
