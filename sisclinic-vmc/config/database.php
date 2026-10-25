
<?php

$host = '127.0.0.1';
$base_dados = 'vmcdb';
$utilizador = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$base_dados;charset=utf8mb4",
        $utilizador,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Erro na ligação MySQL: ' . $e->getMessage());

    http_response_code(500);
    exit('Não foi possível ligar à base de dados.');
}
