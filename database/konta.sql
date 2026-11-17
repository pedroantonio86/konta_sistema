-- =====================================================================
-- Konta: cria o banco, as tabelas e os dados de exemplo (uso local/XAMPP).
-- ATENÇÃO: apaga e recria o banco "konta" (pode ser importado várias vezes).
-- Para hospedagem (sem DROP/CREATE DATABASE) use konta_hospedagem.sql,
-- que é cópia deste arquivo a partir do marcador "INICIO-PORTAVEL".
-- =====================================================================
DROP DATABASE IF EXISTS konta;
CREATE DATABASE konta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE konta;

-- ===================== INICIO-PORTAVEL =====================
SET NAMES utf8mb4;
-- Mesmo fuso do PHP (Teresina, UTC-3): CURDATE()/NOW() dos dados de exemplo
-- precisam bater com date() usado pelo semáforo.
SET time_zone = '-03:00';

-- Ordem inversa das dependências (filhas antes das mães) para o DROP funcionar.
DROP TABLE IF EXISTS cobrancas;
DROP TABLE IF EXISTS avaliacoes;
DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS obrigacoes;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS empresas;

-- ---------------------------------------------------------------------
-- empresas: clientes do escritório.
-- CNPJ sem máscara; aceita o formato alfanumérico (12 posições A-Z/0-9
-- + 2 dígitos verificadores numéricos), vigente desde jul/2026.
-- ---------------------------------------------------------------------
CREATE TABLE empresas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    razao_social  VARCHAR(150) NOT NULL,
    nome_fantasia VARCHAR(150) NULL,
    cnpj          CHAR(14)     NOT NULL,
    email         VARCHAR(150) NULL,
    whatsapp      VARCHAR(11)  NULL COMMENT 'DDD + número, sem o 55',
    regime        ENUM('MEI','SIMPLES','PRESUMIDO','REAL') NOT NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_empresas_cnpj  UNIQUE (cnpj),
    CONSTRAINT ck_empresas_cnpj  CHECK (cnpj REGEXP '^[0-9A-Z]{12}[0-9]{2}$'),
    CONSTRAINT ck_empresas_whats CHECK (whatsapp IS NULL OR whatsapp REGEXP '^[0-9]{10,11}$'),
    INDEX idx_empresas_razao (razao_social)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- usuarios: contador (sem empresa) ou cliente (ligado a uma empresa).
-- ON DELETE CASCADE: o login do cliente só existe por causa da empresa.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL COMMENT 'password_hash(); 255 permite trocar de algoritmo',
    perfil     ENUM('contador','cliente') NOT NULL,
    empresa_id INT UNSIGNED NULL,
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_email UNIQUE (email),
    CONSTRAINT fk_usuarios_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas (id) ON DELETE CASCADE,
    CONSTRAINT ck_usuarios_perfil CHECK (
        (perfil = 'contador' AND empresa_id IS NULL) OR
        (perfil = 'cliente'  AND empresa_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- obrigacoes: guias/declarações. "Vencida" NÃO é gravada: é calculada a
-- partir de status + vencimento (ver app/Services/Semaforo.php).
-- ON DELETE RESTRICT: histórico fiscal não some junto com a empresa.
-- ---------------------------------------------------------------------
CREATE TABLE obrigacoes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id    INT UNSIGNED NOT NULL,
    tipo          ENUM('DAS','DARF','INSS','FGTS','ISS','ICMS','DCTFWEB','OUTRO') NOT NULL,
    competencia   DATE NOT NULL COMMENT 'sempre o dia 01 do mês de referência',
    valor         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vencimento    DATE NOT NULL,
    status        ENUM('pendente','paga') NOT NULL DEFAULT 'pendente',
    pago_em       DATETIME NULL,
    observacao    VARCHAR(500) NULL,
    criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_obrigacoes_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas (id) ON DELETE RESTRICT,
    -- impede lançar a mesma guia duas vezes; também serve de índice p/ empresa_id
    CONSTRAINT uq_obrigacao UNIQUE (empresa_id, tipo, competencia),
    CONSTRAINT ck_obrigacoes_valor CHECK (valor >= 0),
    CONSTRAINT ck_obrigacoes_comp  CHECK (DAYOFMONTH(competencia) = 1),
    CONSTRAINT ck_obrigacoes_pagto CHECK ((status = 'paga') = (pago_em IS NOT NULL)),
    -- consultas do painel: status = 'pendente' + intervalo de vencimento
    INDEX idx_obrigacoes_status_venc (status, vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- documentos: arquivos anexados à obrigação (guardados em storage/uploads
-- com nome aleatório). CASCADE apaga a LINHA; o arquivo físico é apagado
-- pelo código antes do DELETE.
-- ---------------------------------------------------------------------
CREATE TABLE documentos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obrigacao_id  INT UNSIGNED NOT NULL,
    usuario_id    INT UNSIGNED NULL,
    nome_original VARCHAR(255) NOT NULL,
    arquivo       CHAR(36)     NOT NULL COMMENT '32 hex aleatórios + .pdf/.jpg/.png',
    mime          VARCHAR(30)  NOT NULL,
    tamanho       INT UNSIGNED NOT NULL,
    enviado_em    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_documentos_arquivo UNIQUE (arquivo),
    CONSTRAINT fk_documentos_obrigacao FOREIGN KEY (obrigacao_id)
        REFERENCES obrigacoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_documentos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE SET NULL,
    CONSTRAINT ck_documentos_tam  CHECK (tamanho BETWEEN 1 AND 5242880),
    CONSTRAINT ck_documentos_mime CHECK (mime IN ('application/pdf','image/jpeg','image/png'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- avaliacoes: nota do teste com usuário real. SET NULL mantém a nota
-- mesmo que o usuário seja removido.
-- ---------------------------------------------------------------------
CREATE TABLE avaliacoes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    nota       TINYINT UNSIGNED NOT NULL,
    comentario VARCHAR(1000) NULL,
    criado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_avaliacoes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE SET NULL,
    CONSTRAINT ck_avaliacoes_nota CHECK (nota BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- cobrancas: registro de cada cobrança feita pela central de cobrança
-- (para mostrar "cobrado há N dias" e não cobrar duas vezes no mesmo dia).
-- CASCADE: o registro só faz sentido com a empresa; SET NULL: mantém o
-- registro mesmo que o usuário que cobrou seja removido.
-- ---------------------------------------------------------------------
CREATE TABLE cobrancas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    cobrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cobrancas_empresa FOREIGN KEY (empresa_id)
        REFERENCES empresas (id) ON DELETE CASCADE,
    CONSTRAINT fk_cobrancas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE SET NULL,
    -- "última cobrança de cada empresa": busca por empresa, data mais recente
    INDEX idx_cobrancas_empresa_data (empresa_id, cobrado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- DADOS DE EXEMPLO
-- Datas relativas a CURDATE() para o painel sempre ter números na demo.
-- Senha de todos os usuários: Konta@2026
-- WhatsApp fictícios: troque pelo seu número para testar a cobrança.
-- =====================================================================
INSERT INTO empresas (id, razao_social, nome_fantasia, cnpj, email, whatsapp, regime) VALUES
 (1, 'Padaria Pão Quente Ltda',           'Pão Quente',     '11222333000181', 'contato@paoquente.test', '86900000001', 'SIMPLES'),
 (2, 'J. R. Reparos Automotivos',         'JR Reparos',     '44555666000181', NULL,                     '86900000002', 'MEI'),
 (3, 'Clínica Sorriso Odontologia Ltda',  'Clínica Sorriso','77888999000181', 'adm@sorriso.test',       '86900000003', 'PRESUMIDO'),
 (4, 'Tech Piauí Sistemas S.A.',          'Tech Piauí',     '12ABC34501DE35', 'financeiro@techpi.test', NULL,          'REAL');

INSERT INTO usuarios (id, nome, email, senha_hash, perfil, empresa_id) VALUES
 (1, 'Ana Contadora',     'contador@konta.test', '$2y$10$ETz0FC3zDU5u6FTKbsaTTO94g.6QievQ4F43GgKdAAp7kKaKdpwY.', 'contador', NULL),
 (2, 'Carlos (Padaria)',  'padaria@konta.test',  '$2y$10$ETz0FC3zDU5u6FTKbsaTTO94g.6QievQ4F43GgKdAAp7kKaKdpwY.', 'cliente',  1),
 (3, 'Joana (Clínica)',   'clinica@konta.test',  '$2y$10$ETz0FC3zDU5u6FTKbsaTTO94g.6QievQ4F43GgKdAAp7kKaKdpwY.', 'cliente',  3);
-- Empresa 2 fica sem login de propósito: serve para demonstrar o cadastro de acesso.

-- Competências: m1 = mês passado, m2 = retrasado, m0 = mês atual.
INSERT INTO obrigacoes (empresa_id, tipo, competencia, valor, vencimento, status, pago_em, observacao) VALUES
 -- Padaria (Simples)
 (1, 'DAS',     DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01'),   412.30, CURDATE() - INTERVAL 35 DAY, 'paga',     NOW() - INTERVAL 36 DAY, NULL),
 (1, 'DAS',     DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),   387.40, CURDATE() - INTERVAL 3 DAY,  'pendente', NULL, NULL),             -- vencida
 (1, 'FGTS',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),  1250.00, CURDATE() + INTERVAL 2 DAY,  'pendente', NULL, NULL),             -- alerta
 (1, 'INSS',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),   980.15, CURDATE() + INTERVAL 6 DAY,  'pendente', NULL, NULL),             -- alerta
 (1, 'DCTFWEB', DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),     0.00, CURDATE() + INTERVAL 12 DAY, 'pendente', NULL, 'Declaração, sem valor a pagar'), -- em dia
 -- JR Reparos (MEI)
 (2, 'DAS',     DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01'),    75.90, CURDATE() - INTERVAL 40 DAY, 'paga',     NOW() - INTERVAL 41 DAY, NULL),
 (2, 'DAS',     DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),    75.90, CURDATE() - INTERVAL 10 DAY, 'pendente', NULL, NULL),             -- vencida
 (2, 'DAS',     DATE_FORMAT(CURDATE(),                    '%Y-%m-01'),    75.90, CURDATE() + INTERVAL 20 DAY, 'pendente', NULL, NULL),             -- em dia
 -- Clínica Sorriso (Lucro Presumido)
 (3, 'DARF',    DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01'),  2980.00, CURDATE() - INTERVAL 30 DAY, 'paga',     NOW() - INTERVAL 31 DAY, 'IRPJ/CSLL'),
 (3, 'DARF',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),  3120.00, CURDATE() - INTERVAL 1 DAY,  'paga',     NOW(), 'IRPJ/CSLL'),      -- paga no mês
 (3, 'INSS',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),  2210.50, CURDATE() - INTERVAL 2 DAY,  'paga',     NOW(), NULL),             -- paga no mês
 (3, 'ISS',     DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),   640.00, CURDATE() + INTERVAL 4 DAY,  'pendente', NULL, NULL),             -- alerta
 (3, 'FGTS',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),   890.00, CURDATE(),                   'pendente', NULL, NULL),             -- alerta (vence hoje)
 (3, 'DCTFWEB', DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),     0.00, CURDATE() + INTERVAL 15 DAY, 'pendente', NULL, 'Declaração, sem valor a pagar'), -- em dia
 -- Tech Piauí (Lucro Real, CNPJ alfanumérico, sem WhatsApp)
 (4, 'DARF',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'), 15400.00, CURDATE() + INTERVAL 9 DAY,  'pendente', NULL, 'IRPJ estimativa'),-- em dia
 (4, 'ICMS',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),  8750.25, CURDATE() - INTERVAL 5 DAY,  'pendente', NULL, NULL),             -- vencida
 (4, 'INSS',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),  6430.00, CURDATE() + INTERVAL 1 DAY,  'pendente', NULL, NULL),             -- alerta
 (4, 'FGTS',    DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),  2100.00, CURDATE() - INTERVAL 3 DAY,  'paga',     NOW(), NULL),             -- paga no mês
 (4, 'OUTRO',   DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'),   350.00, CURDATE() + INTERVAL 25 DAY, 'pendente', NULL, 'Taxa de alvará municipal'); -- em dia

-- Avaliações de exemplo (apague antes do teste com usuário real:
-- DELETE FROM avaliacoes;)
INSERT INTO avaliacoes (usuario_id, nota, comentario) VALUES
 (2, 5, '[Exemplo] Muito mais fácil que a planilha.'),
 (3, 4, '[Exemplo] Gostei do semáforo; queria ver o histórico do ano.');

-- Cobranças de exemplo: a Padaria foi cobrada há 2 dias (e antes, há 9);
-- JR Reparos e Clínica nunca foram cobradas.
INSERT INTO cobrancas (empresa_id, usuario_id, cobrado_em) VALUES
 (1, 1, NOW() - INTERVAL 9 DAY),
 (1, 1, NOW() - INTERVAL 2 DAY);
