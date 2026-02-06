-- ==================== FRAMES MARKETPLACE - SCHEMA COMPLETO CORRIGIDO ====================
-- Execute este arquivo NO SUPABASE para criar/corrigir todas as tabelas

-- ==================== 1. PROPOSALS TABLE ====================
DROP TABLE IF EXISTS proposals CASCADE;

CREATE TABLE proposals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    editor_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Proposta do Editor
    proposed_price DECIMAL(10,2) NOT NULL,
    delivery_days INTEGER NOT NULL,
    cover_letter TEXT,
    
    -- Status da proposta
    status VARCHAR(50) DEFAULT 'PENDING' CHECK (status IN (
        'PENDING',
        'ACCEPTED',
        'REJECTED',
        'WITHDRAWN'
    )),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    responded_at TIMESTAMP,
    
    -- Constraints
    CONSTRAINT unique_editor_per_project UNIQUE (project_id, editor_id)
);

-- Indexes
CREATE INDEX idx_proposals_project ON proposals(project_id);
CREATE INDEX idx_proposals_editor ON proposals(editor_id);
CREATE INDEX idx_proposals_status ON proposals(status);
CREATE INDEX idx_proposals_created ON proposals(created_at DESC);

-- ==================== 2. ESCROW PAYMENTS TABLE ====================
DROP TABLE IF EXISTS escrow_payments CASCADE;

CREATE TABLE escrow_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    client_id UUID NOT NULL REFERENCES users(id),
    editor_id UUID NOT NULL REFERENCES users(id),
    
    -- Valores
    total_amount DECIMAL(10,2) NOT NULL,
    platform_fee_percent DECIMAL(5,2) DEFAULT 15.00,
    platform_fee_amount DECIMAL(10,2),
    editor_amount DECIMAL(10,2),
    
    -- Status do escrow
    status VARCHAR(50) DEFAULT 'PENDING' CHECK (status IN (
        'PENDING',
        'ESCROWED',
        'IN_REVIEW',
        'RELEASED',
        'REFUNDED',
        'DISPUTED'
    )),
    
    -- Stripe IDs
    stripe_payment_intent_id VARCHAR(255),
    stripe_transfer_id VARCHAR(255),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    escrowed_at TIMESTAMP,
    released_at TIMESTAMP,
    
    notes TEXT
);

-- Trigger para calcular valores automaticamente
CREATE OR REPLACE FUNCTION calculate_escrow_amounts()
RETURNS TRIGGER AS $$
BEGIN
    NEW.platform_fee_amount := NEW.total_amount * (NEW.platform_fee_percent / 100);
    NEW.editor_amount := NEW.total_amount - NEW.platform_fee_amount;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_calculate_escrow
BEFORE INSERT OR UPDATE ON escrow_payments
FOR EACH ROW
EXECUTE FUNCTION calculate_escrow_amounts();

CREATE INDEX idx_escrow_project ON escrow_payments(project_id);
CREATE INDEX idx_escrow_status ON escrow_payments(status);

-- ==================== 3. NOTIFICATIONS TABLE ====================
DROP TABLE IF EXISTS notifications CASCADE;

CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Tipo e conteúdo
    type VARCHAR(50) NOT NULL CHECK (type IN (
        'new_proposal',
        'proposal_accepted',
        'proposal_rejected',
        'new_project',
        'project_completed',
        'payment_released',
        'new_comment',
        'new_message'
    )),
    
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    
    -- Relacionamento
    related_id UUID,
    related_type VARCHAR(50),
    
    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    read_at TIMESTAMP
);

CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_unread ON notifications(user_id, is_read);
CREATE INDEX idx_notifications_created ON notifications(created_at DESC);

-- ==================== 4. ATUALIZAR PROJECTS TABLE ====================
-- Adicionar colunas necessárias se não existirem
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS proposals_count INTEGER DEFAULT 0,
ADD COLUMN IF NOT EXISTS agreed_price DECIMAL(10,2),
ADD COLUMN IF NOT EXISTS rating DECIMAL(2,1),
ADD COLUMN IF NOT EXISTS review_comment TEXT;

-- Trigger para atualizar contador de propostas
CREATE OR REPLACE FUNCTION update_proposals_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE projects 
        SET proposals_count = proposals_count + 1 
        WHERE id = NEW.project_id;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE projects 
        SET proposals_count = GREATEST(proposals_count - 1, 0) 
        WHERE id = OLD.project_id;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_update_proposals_count ON proposals;
CREATE TRIGGER trigger_update_proposals_count
AFTER INSERT OR DELETE ON proposals
FOR EACH ROW EXECUTE FUNCTION update_proposals_count();

-- ==================== 5. ATUALIZAR EDITOR_PROFILES TABLE ====================
-- Adicionar colunas que podem estar faltando
ALTER TABLE editor_profiles
ADD COLUMN IF NOT EXISTS years_of_experience INTEGER DEFAULT 0,
ADD COLUMN IF NOT EXISTS hourly_rate_min DECIMAL(10,2) DEFAULT 50,
ADD COLUMN IF NOT EXISTS hourly_rate_max DECIMAL(10,2) DEFAULT 200,
ADD COLUMN IF NOT EXISTS specialties TEXT[],
ADD COLUMN IF NOT EXISTS preferred_genres TEXT[],
ADD COLUMN IF NOT EXISTS completed_projects_count INTEGER DEFAULT 0,
ADD COLUMN IF NOT EXISTS portfolio_url VARCHAR(500);

-- Verificar se coluna is_featured existe
DO $$ 
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'editor_profiles' AND column_name = 'is_featured'
    ) THEN
        ALTER TABLE editor_profiles ADD COLUMN is_featured BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

COMMENT ON TABLE proposals IS 'Propostas de editores para projetos abertos';
COMMENT ON TABLE escrow_payments IS 'Sistema de pagamento garantido (escrow)';
COMMENT ON TABLE notifications IS 'Notificações do sistema';

-- ==================== VERIFICAÇÃO ====================
-- Verificar se tudo foi criado
SELECT 'proposals' as table_name, COUNT(*) as columns 
FROM information_schema.columns 
WHERE table_name = 'proposals'
UNION ALL
SELECT 'escrow_payments', COUNT(*) 
FROM information_schema.columns 
WHERE table_name = 'escrow_payments'
UNION ALL
SELECT 'notifications', COUNT(*) 
FROM information_schema.columns 
WHERE table_name = 'notifications';
