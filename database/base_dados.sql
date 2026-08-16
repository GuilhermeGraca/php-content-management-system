-- Criar a base de dados (se ainda não existir) e definir os caracteres para suportar acentos
CREATE DATABASE IF NOT EXISTS bd_soundcloud_pt DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE bd_soundcloud_pt;

-- Criar a tabela de contas/utilizadores
CREATE TABLE IF NOT EXISTS utilizadores (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  palavra_passe VARCHAR(255) NOT NULL,
  perfil ENUM('administrador', 'simpatizante', 'utilizador') DEFAULT 'utilizador',
  conta_ativa TINYINT(1) DEFAULT 0,
  token_validacao VARCHAR(255) DEFAULT NULL,
  id_distrito INT(11) DEFAULT NULL,
  data_registo DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- Criar a tabela de categorias principais (Distritos)
CREATE TABLE IF NOT EXISTS categorias (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL UNIQUE
);


-- Criar a tabela de estilos musicais (Categorias Secundárias, criadas pelos Simpatizantes)
CREATE TABLE IF NOT EXISTS estilos_musicais (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL UNIQUE,
  id_criador INT(11) NOT NULL,
  data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_criador) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- Inserir alguns estilos musicais iniciais
INSERT INTO estilos_musicais (nome, id_criador) VALUES 
  ('Rock', 1), ('Trap', 1), ('Fado', 1), ('Hip-Hop', 1), ('Acústico', 1), ('Eletrónica', 1);

-- Criar tabela de conteudos (Multimédia)
CREATE TABLE IF NOT EXISTS conteudos (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  id_utilizador INT(11) NOT NULL,
  id_categoria INT(11) NOT NULL,
  id_estilo INT(11) DEFAULT NULL,
  titulo VARCHAR(255) NOT NULL,
  descricao TEXT DEFAULT NULL,
  caminho_ficheiro VARCHAR(255) NOT NULL,
  tipo_ficheiro VARCHAR(50) NOT NULL,
  caminho_capa VARCHAR(255) DEFAULT NULL,
  visibilidade ENUM('publico', 'privado') DEFAULT 'publico',
  data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id) ON DELETE CASCADE,
  FOREIGN KEY (id_categoria) REFERENCES categorias(id) ON DELETE RESTRICT,
  FOREIGN KEY (id_estilo) REFERENCES estilos_musicais(id) ON DELETE SET NULL
);

-- Criar tabela de concertos (Integração com OpenStreetMap)
CREATE TABLE IF NOT EXISTS concertos (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  id_utilizador INT(11) NOT NULL,
  local_nome VARCHAR(255) NOT NULL,
  latitude DECIMAL(10, 7) NOT NULL,
  longitude DECIMAL(10, 7) NOT NULL,
  data_concerto DATETIME NOT NULL,
  data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- Subscrições (Interesses dos utilizadores)
CREATE TABLE IF NOT EXISTS subscricoes (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  id_utilizador INT(11) NOT NULL,
  id_distrito INT(11) DEFAULT NULL,
  id_estilo INT(11) DEFAULT NULL,
  data_subscricao DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id) ON DELETE CASCADE,
  FOREIGN KEY (id_distrito) REFERENCES categorias(id) ON DELETE CASCADE,
  FOREIGN KEY (id_estilo) REFERENCES estilos_musicais(id) ON DELETE CASCADE
);

-- Notificações in-app
CREATE TABLE IF NOT EXISTS notificacoes (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  id_utilizador INT(11) NOT NULL,
  mensagem VARCHAR(500) NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  lida TINYINT(1) DEFAULT 0,
  data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_utilizador) REFERENCES utilizadores(id) ON DELETE CASCADE
);

-- Adicionar a FK do distrito na tabela utilizadores (após categorias existirem)
ALTER TABLE utilizadores ADD FOREIGN KEY (id_distrito) REFERENCES categorias(id) ON DELETE SET NULL;