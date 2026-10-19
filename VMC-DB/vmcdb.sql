-- ============================================================
-- VMCDB - Sistema de Gestão de Clínica Médica
-- Script consolidado (schema final, versão única)
-- ============================================================

CREATE DATABASE IF NOT EXISTS vmcdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE vmcdb;

-- ============================================================
-- PERFIS
-- ============================================================
CREATE TABLE perfis (
    id_perfil INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UTILIZADORES
-- Soft delete via deleted_at (não usa mais ENUM estado)
-- ============================================================
CREATE TABLE utilizadores (
    id_utilizador INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefone VARCHAR(30),
    username VARCHAR(50) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    foto VARCHAR(255),
    id_perfil INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (id_perfil) REFERENCES perfis(id_perfil)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_utilizadores_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View de compatibilidade para código que ainda espere um "estado" textual
CREATE OR REPLACE VIEW vw_utilizadores_estado AS
SELECT
    id_utilizador,
    nome,
    email,
    CASE WHEN deleted_at IS NULL THEN 'Activo' ELSE 'Inactivo' END AS estado
FROM utilizadores;

-- ============================================================
-- PACIENTES
-- ============================================================
CREATE TABLE pacientes (
    id_paciente INT AUTO_INCREMENT PRIMARY KEY,
    id_utilizador INT NOT NULL UNIQUE,
    bi VARCHAR(20) UNIQUE,
    sexo ENUM('Masculino','Feminino') NOT NULL,
    data_nascimento DATE NOT NULL,
    endereco TEXT,
    tipo_sanguineo ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-'),
    alergias TEXT,
    contacto_emergencia VARCHAR(100),
    telefone_emergencia VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_pacientes_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ESPECIALIDADES (normalizado)
-- ============================================================
CREATE TABLE especialidades (
    id_especialidade INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MEDICOS
-- ============================================================
CREATE TABLE medicos (
    id_medico INT AUTO_INCREMENT PRIMARY KEY,
    id_utilizador INT NOT NULL UNIQUE,
    id_especialidade INT NOT NULL,
    numero_ordem VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (id_especialidade) REFERENCES especialidades(id_especialidade)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_medicos_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DISPONIBILIDADE DOS MEDICOS
-- ============================================================
CREATE TABLE disponibilidade_medicos (
    id_disponibilidade INT AUTO_INCREMENT PRIMARY KEY,
    id_medico INT NOT NULL,
    dia_semana ENUM('Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    CHECK (hora_fim > hora_inicio),
    FOREIGN KEY (id_medico) REFERENCES medicos(id_medico)
        ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY uq_disponibilidade (id_medico, dia_semana, hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONSULTAS
-- duracao_minutos + hora_fim (coluna gerada) já na definição
-- ============================================================
CREATE TABLE consultas (
    id_consulta INT AUTO_INCREMENT PRIMARY KEY,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL,
    data_consulta DATE NOT NULL,
    hora TIME NOT NULL,
    duracao_minutos INT NOT NULL DEFAULT 30 CHECK (duracao_minutos > 0),
    hora_fim TIME GENERATED ALWAYS AS (
        ADDTIME(hora, SEC_TO_TIME(duracao_minutos * 60))
    ) STORED,
    estado ENUM('Marcada','Remarcada','Cancelada','Concluída') NOT NULL DEFAULT 'Marcada',
    motivo TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (id_medico) REFERENCES medicos(id_medico)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_consultas_data (data_consulta),
    INDEX idx_consultas_estado (estado),
    INDEX idx_consultas_medico_data_hora (id_medico, data_consulta, hora, hora_fim),
    INDEX idx_consultas_paciente_data_hora (id_paciente, data_consulta, hora, hora_fim),
    UNIQUE KEY uq_medico_horario (id_medico, data_consulta, hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- HISTORICO CLINICO
-- ============================================================
CREATE TABLE historico_clinico (
    id_historico INT AUTO_INCREMENT PRIMARY KEY,
    id_consulta INT NOT NULL UNIQUE,
    peso DECIMAL(5,2) CHECK (peso > 0),
    altura DECIMAL(4,2) CHECK (altura > 0),
    temperatura DECIMAL(4,1),
    pressao VARCHAR(20),
    diagnostico TEXT,
    tratamento TEXT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_consulta) REFERENCES consultas(id_consulta)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- CONFIGURACAO IVA
-- ============================================================
CREATE TABLE configuracao_iva (
    id INT AUTO_INCREMENT PRIMARY KEY,
    percentagem DECIMAL(5,2) NOT NULL CHECK (percentagem BETWEEN 0 AND 100),
    data_inicio DATE NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FACTURAS
-- numero_factura preenchido automaticamente pelo trigger
-- trg_factura_numeracao_sequencial se vier vazio
-- ============================================================
CREATE TABLE facturas (
    id_factura INT AUTO_INCREMENT PRIMARY KEY,
    numero_factura VARCHAR(50) NOT NULL DEFAULT '' UNIQUE,
    id_paciente INT NOT NULL,
    id_consulta INT,
    data_factura DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (subtotal >= 0),
    percentagem_iva DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (percentagem_iva BETWEEN 0 AND 100),
    valor_iva DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (valor_iva >= 0),
    total DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (total >= 0),
    estado ENUM('Paga','Pendente','Cancelada') NOT NULL DEFAULT 'Pendente',
    caminho_pdf VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_paciente) REFERENCES pacientes(id_paciente)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    FOREIGN KEY (id_consulta) REFERENCES consultas(id_consulta)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_facturas_estado (estado),
    INDEX idx_facturas_data (data_factura),
    INDEX idx_facturas_paciente_data (id_paciente, data_factura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ITENS FACTURA (subtotal calculado automaticamente)
-- ============================================================
CREATE TABLE itens_factura (
    id_item INT AUTO_INCREMENT PRIMARY KEY,
    id_factura INT NOT NULL,
    descricao VARCHAR(200) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1 CHECK (quantidade > 0),
    preco DECIMAL(10,2) NOT NULL CHECK (preco >= 0),
    subtotal DECIMAL(10,2) GENERATED ALWAYS AS (quantidade * preco) STORED,
    FOREIGN KEY (id_factura) REFERENCES facturas(id_factura)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PAGAMENTOS
-- ============================================================
CREATE TABLE pagamentos (
    id_pagamento INT AUTO_INCREMENT PRIMARY KEY,
    id_factura INT NOT NULL,
    valor_pago DECIMAL(10,2) NOT NULL CHECK (valor_pago > 0),
    forma_pagamento ENUM('Dinheiro','POS','Transferência','M-Pesa','e-Mola') NOT NULL,
    data_pagamento DATETIME NOT NULL,
    id_utilizador INT NOT NULL,
    observacoes TEXT,
    FOREIGN KEY (id_factura) REFERENCES facturas(id_factura)
        ON UPDATE CASCADE ON DELETE CASCADE,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_pagamentos_data (data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NOTIFICACOES
-- ============================================================
CREATE TABLE notificacoes (
    id_notificacao INT AUTO_INCREMENT PRIMARY KEY,
    id_utilizador INT NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    data_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador)
        ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX idx_notificacoes_lida (lida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDITORIA
-- ============================================================
CREATE TABLE auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utilizador INT,
    acao VARCHAR(100) NOT NULL,
    tabela VARCHAR(100) NOT NULL,
    valor_anterior JSON,
    valor_novo JSON,
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id_utilizador)
        ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX idx_auditoria_tabela (tabela),
    INDEX idx_auditoria_data (data_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE recepcionistas (

id_recepcionista INT AUTO_INCREMENT PRIMARY KEY,

id_utilizador INT NOT NULL UNIQUE,

FOREIGN KEY(id_utilizador)
REFERENCES utilizadores(id_utilizador)
ON UPDATE CASCADE
ON DELETE CASCADE

);

CREATE TABLE servicos(

id_servico INT AUTO_INCREMENT PRIMARY KEY,

nome VARCHAR(150),

preco DECIMAL(10,2),

activo BOOLEAN DEFAULT TRUE

);
-- ============================================================
-- FACTURA_SEQUENCIA
-- Controlo de numeração sequencial por ano (FT-2026-000001, ...)
-- ============================================================
CREATE TABLE factura_sequencia (
    ano INT PRIMARY KEY,
    ultimo_numero INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGERS
-- ============================================================
DELIMITER $$

-- ------------------------------------------------------------
-- Pacientes: valida que a data de nascimento é plausível
-- (CURDATE() não pode ser usado num CHECK por não ser
--  determinística, por isso a validação é feita aqui)
-- ------------------------------------------------------------
CREATE TRIGGER trg_pacientes_valida_nascimento_insert
BEFORE INSERT ON pacientes
FOR EACH ROW
BEGIN
    IF NEW.data_nascimento > CURDATE()
       OR NEW.data_nascimento < DATE_SUB(CURDATE(), INTERVAL 130 YEAR) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data de nascimento inválida';
    END IF;
END$$

CREATE TRIGGER trg_pacientes_valida_nascimento_update
BEFORE UPDATE ON pacientes
FOR EACH ROW
BEGIN
    IF NEW.data_nascimento > CURDATE()
       OR NEW.data_nascimento < DATE_SUB(CURDATE(), INTERVAL 130 YEAR) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data de nascimento inválida';
    END IF;
END$$

-- ------------------------------------------------------------
-- Disponibilidade dos médicos: impede intervalos sobrepostos
-- ------------------------------------------------------------
CREATE TRIGGER trg_disponibilidade_sem_sobreposicao_insert
BEFORE INSERT ON disponibilidade_medicos
FOR EACH ROW
BEGIN
    DECLARE v_overlap INT DEFAULT 0;

    SELECT COUNT(*) INTO v_overlap
    FROM disponibilidade_medicos
    WHERE id_medico = NEW.id_medico
      AND dia_semana = NEW.dia_semana
      AND hora_inicio < NEW.hora_fim
      AND hora_fim > NEW.hora_inicio;

    IF v_overlap > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Este intervalo de disponibilidade sobrepõe-se a outro já existente para o médico';
    END IF;
END$$

CREATE TRIGGER trg_disponibilidade_sem_sobreposicao_update
BEFORE UPDATE ON disponibilidade_medicos
FOR EACH ROW
BEGIN
    DECLARE v_overlap INT DEFAULT 0;

    SELECT COUNT(*) INTO v_overlap
    FROM disponibilidade_medicos
    WHERE id_medico = NEW.id_medico
      AND dia_semana = NEW.dia_semana
      AND id_disponibilidade <> OLD.id_disponibilidade
      AND hora_inicio < NEW.hora_fim
      AND hora_fim > NEW.hora_inicio;

    IF v_overlap > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Este intervalo de disponibilidade sobrepõe-se a outro já existente para o médico';
    END IF;
END$$

-- ------------------------------------------------------------
-- Consultas: dentro da disponibilidade + sem sobreposição
-- (v_hora_fim calculado manualmente porque hora_fim é uma
--  coluna GENERATED STORED e ainda não está disponível em
--  NEW dentro de um trigger BEFORE)
-- ------------------------------------------------------------
CREATE TRIGGER trg_consulta_valida_insert
BEFORE INSERT ON consultas
FOR EACH ROW
BEGIN
    DECLARE v_dia_semana VARCHAR(10);
    DECLARE v_hora_fim TIME;
    DECLARE v_disponivel INT DEFAULT 0;
    DECLARE v_overlap_medico INT DEFAULT 0;
    DECLARE v_overlap_paciente INT DEFAULT 0;

    SET v_hora_fim = ADDTIME(NEW.hora, SEC_TO_TIME(NEW.duracao_minutos * 60));

    SET v_dia_semana = CASE DAYOFWEEK(NEW.data_consulta)
        WHEN 1 THEN 'Domingo'
        WHEN 2 THEN 'Segunda'
        WHEN 3 THEN 'Terça'
        WHEN 4 THEN 'Quarta'
        WHEN 5 THEN 'Quinta'
        WHEN 6 THEN 'Sexta'
        WHEN 7 THEN 'Sábado'
    END;

    SELECT COUNT(*) INTO v_disponivel
    FROM disponibilidade_medicos
    WHERE id_medico = NEW.id_medico
      AND dia_semana = v_dia_semana
      AND hora_inicio <= NEW.hora
      AND hora_fim >= v_hora_fim;

    IF v_disponivel = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O horário da consulta está fora da disponibilidade do médico';
    END IF;

    SELECT COUNT(*) INTO v_overlap_medico
    FROM consultas
    WHERE id_medico = NEW.id_medico
      AND data_consulta = NEW.data_consulta
      AND estado <> 'Cancelada'
      AND hora < v_hora_fim
      AND hora_fim > NEW.hora;

    IF v_overlap_medico > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Já existe uma consulta marcada para este médico neste horário';
    END IF;

    SELECT COUNT(*) INTO v_overlap_paciente
    FROM consultas
    WHERE id_paciente = NEW.id_paciente
      AND data_consulta = NEW.data_consulta
      AND estado <> 'Cancelada'
      AND hora < v_hora_fim
      AND hora_fim > NEW.hora;

    IF v_overlap_paciente > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'O paciente já tem uma consulta marcada neste horário';
    END IF;
END$$

CREATE TRIGGER trg_consulta_valida_update
BEFORE UPDATE ON consultas
FOR EACH ROW
BEGIN
    DECLARE v_dia_semana VARCHAR(10);
    DECLARE v_hora_fim TIME;
    DECLARE v_disponivel INT DEFAULT 0;
    DECLARE v_overlap_medico INT DEFAULT 0;
    DECLARE v_overlap_paciente INT DEFAULT 0;

    IF NEW.data_consulta <> OLD.data_consulta
       OR NEW.hora <> OLD.hora
       OR NEW.duracao_minutos <> OLD.duracao_minutos
       OR NEW.id_medico <> OLD.id_medico
       OR NEW.id_paciente <> OLD.id_paciente
    THEN
        SET v_hora_fim = ADDTIME(NEW.hora, SEC_TO_TIME(NEW.duracao_minutos * 60));

        SET v_dia_semana = CASE DAYOFWEEK(NEW.data_consulta)
            WHEN 1 THEN 'Domingo'
            WHEN 2 THEN 'Segunda'
            WHEN 3 THEN 'Terça'
            WHEN 4 THEN 'Quarta'
            WHEN 5 THEN 'Quinta'
            WHEN 6 THEN 'Sexta'
            WHEN 7 THEN 'Sábado'
        END;

        SELECT COUNT(*) INTO v_disponivel
        FROM disponibilidade_medicos
        WHERE id_medico = NEW.id_medico
          AND dia_semana = v_dia_semana
          AND hora_inicio <= NEW.hora
          AND hora_fim >= v_hora_fim;

        IF v_disponivel = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'O horário da consulta está fora da disponibilidade do médico';
        END IF;

        SELECT COUNT(*) INTO v_overlap_medico
        FROM consultas
        WHERE id_medico = NEW.id_medico
          AND data_consulta = NEW.data_consulta
          AND estado <> 'Cancelada'
          AND id_consulta <> OLD.id_consulta
          AND hora < v_hora_fim
          AND hora_fim > NEW.hora;

        IF v_overlap_medico > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Já existe uma consulta marcada para este médico neste horário';
        END IF;

        SELECT COUNT(*) INTO v_overlap_paciente
        FROM consultas
        WHERE id_paciente = NEW.id_paciente
          AND data_consulta = NEW.data_consulta
          AND estado <> 'Cancelada'
          AND id_consulta <> OLD.id_consulta
          AND hora < v_hora_fim
          AND hora_fim > NEW.hora;

        IF v_overlap_paciente > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'O paciente já tem uma consulta marcada neste horário';
        END IF;
    END IF;
END$$

-- ------------------------------------------------------------
-- Configuração de IVA: garante que só há uma taxa activa
-- ------------------------------------------------------------
CREATE TRIGGER trg_iva_activo_insert
AFTER INSERT ON configuracao_iva
FOR EACH ROW
BEGIN
    IF NEW.activo = TRUE THEN
        UPDATE configuracao_iva
        SET activo = FALSE
        WHERE id <> NEW.id AND activo = TRUE;
    END IF;
END$$

CREATE TRIGGER trg_iva_activo_update
AFTER UPDATE ON configuracao_iva
FOR EACH ROW
BEGIN
    IF NEW.activo = TRUE AND OLD.activo = FALSE THEN
        UPDATE configuracao_iva
        SET activo = FALSE
        WHERE id <> NEW.id AND activo = TRUE;
    END IF;
END$$

-- ------------------------------------------------------------
-- Numeração sequencial de facturas (FT-AAAA-NNNNNN)
-- ------------------------------------------------------------
CREATE TRIGGER trg_factura_numeracao_sequencial
BEFORE INSERT ON facturas
FOR EACH ROW
BEGIN
    DECLARE v_ano INT;
    DECLARE v_seq INT;

    IF NEW.numero_factura IS NULL OR NEW.numero_factura = '' THEN
        SET v_ano = YEAR(NEW.data_factura);

        INSERT INTO factura_sequencia (ano, ultimo_numero)
        VALUES (v_ano, 1)
        ON DUPLICATE KEY UPDATE ultimo_numero = ultimo_numero + 1;

        SELECT ultimo_numero INTO v_seq
        FROM factura_sequencia
        WHERE ano = v_ano;

        SET NEW.numero_factura = CONCAT('FT-', v_ano, '-', LPAD(v_seq, 6, '0'));
    END IF;
END$$

-- ------------------------------------------------------------
-- Pagamentos: impede exceder o saldo em falta da factura
-- ------------------------------------------------------------
CREATE TRIGGER trg_pagamento_valida_valor
BEFORE INSERT ON pagamentos
FOR EACH ROW
BEGIN
    DECLARE v_total DECIMAL(10,2);
    DECLARE v_pago DECIMAL(10,2);

    SELECT total INTO v_total FROM facturas WHERE id_factura = NEW.id_factura;
    SELECT COALESCE(SUM(valor_pago), 0) INTO v_pago FROM pagamentos WHERE id_factura = NEW.id_factura;

    IF (v_pago + NEW.valor_pago) > v_total THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Valor do pagamento excede o saldo em falta da factura';
    END IF;
END$$

-- ------------------------------------------------------------
-- Marca a factura como paga quando o total é atingido
-- ------------------------------------------------------------
CREATE TRIGGER trg_factura_marca_paga
AFTER INSERT ON pagamentos
FOR EACH ROW
BEGIN
    DECLARE v_total DECIMAL(10,2);
    DECLARE v_pago DECIMAL(10,2);

    SELECT total INTO v_total FROM facturas WHERE id_factura = NEW.id_factura;
    SELECT COALESCE(SUM(valor_pago), 0) INTO v_pago FROM pagamentos WHERE id_factura = NEW.id_factura;

    IF v_pago >= v_total THEN
        UPDATE facturas SET estado = 'Paga' WHERE id_factura = NEW.id_factura;
    END IF;
END$$

-- ------------------------------------------------------------
-- Auditoria: facturas, consultas e pagamentos
-- ------------------------------------------------------------
CREATE TRIGGER trg_auditoria_facturas_update
AFTER UPDATE ON facturas
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (acao, tabela, valor_anterior, valor_novo)
    VALUES (
        'UPDATE',
        'facturas',
        JSON_OBJECT('estado', OLD.estado, 'total', OLD.total),
        JSON_OBJECT('estado', NEW.estado, 'total', NEW.total)
    );
END$$

CREATE TRIGGER trg_auditoria_consultas_update
AFTER UPDATE ON consultas
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (acao, tabela, valor_anterior, valor_novo)
    VALUES (
        'UPDATE',
        'consultas',
        JSON_OBJECT('estado', OLD.estado, 'data_consulta', OLD.data_consulta, 'hora', OLD.hora),
        JSON_OBJECT('estado', NEW.estado, 'data_consulta', NEW.data_consulta, 'hora', NEW.hora)
    );
END$$

CREATE TRIGGER trg_auditoria_pagamentos_insert
AFTER INSERT ON pagamentos
FOR EACH ROW
BEGIN
    INSERT INTO auditoria (id_utilizador, acao, tabela, valor_novo)
    VALUES (
        NEW.id_utilizador,
        'INSERT',
        'pagamentos',
        JSON_OBJECT('id_factura', NEW.id_factura, 'valor_pago', NEW.valor_pago, 'forma_pagamento', NEW.forma_pagamento)
    );
END$$

DELIMITER ;
