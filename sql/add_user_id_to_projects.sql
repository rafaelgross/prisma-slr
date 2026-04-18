-- =============================================================
-- PRISMA-SLR: Isolamento de projetos por usuário
-- Execute APÓS criar a tabela users (add_users_table.sql)
-- =============================================================

USE prisma_slr;

-- Adiciona coluna user_id em projects (nullable para suportar projetos legados)
ALTER TABLE projects
  ADD COLUMN user_id INT NULL DEFAULT NULL AFTER id,
  ADD INDEX idx_user_id (user_id),
  ADD CONSTRAINT fk_projects_user
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
