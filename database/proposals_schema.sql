-- ==================== PROPOSALS TABLE ====================
-- Sistema de propostas para marketplace

CREATE TABLE IF NOT EXISTS proposals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    editor_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    -- Proposta do Editor
    proposed_price DECIMAL(10,2) NOT NULL,
    delivery_days INTEGER NOT NULL,
    cover_letter TEXT,
    
    -- Portfolio preview URLs (array of video URLs)
    portfolio_samples TEXT[],
    
    -- Status da proposta
    status VARCHAR(50) DEFAULT 'PENDING' CHECK (status IN (
        'PENDING',      -- Aguardando decisão do cliente
        'ACCEPTED',     -- Aceita pelo cliente
        'REJECTED',     -- Rejeitada pelo cliente
        'WITHDRAWN'     -- Retirada pelo editor
    )),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    responded_at TIMESTAMP,
    
    -- Constraints
    CONSTRAINT unique_editor_per_project UNIQUE (project_id, editor_id)
);

-- Indexes para performance
CREATE INDEX idx_proposals_project ON proposals(project_id);
CREATE INDEX idx_proposals_editor ON proposals(editor_id);
CREATE INDEX idx_proposals_status ON proposals(status);
CREATE INDEX idx_proposals_created ON proposals(created_at DESC);

-- ==================== ESCROW PAYMENTS TABLE ====================
-- Sistema de pagamento garantido (escrow)

CREATE TABLE IF NOT EXISTS escrow_payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    client_id UUID NOT NULL REFERENCES users(id),
    editor_id UUID NOT NULL REFERENCES users(id),
    
    -- Valores
    total_amount DECIMAL(10,2) NOT NULL,
    platform_fee_percent DECIMAL(5,2) DEFAULT 15.00,
    platform_fee_amount DECIMAL(10,2) GENERATED ALWAYS AS (total_amount * platform_fee_percent / 100) STORED,
    editor_amount DECIMAL(10,2) GENERATED ALWAYS AS (total_amount - (total_amount * platform_fee_percent / 100)) STORED,
    
    -- Status do escrow
    status VARCHAR(50) DEFAULT 'PENDING' CHECK (status IN (
        'PENDING',          -- Aguardando pagamento do cliente
        'ESCROWED',         -- Valor retido na plataforma
        'IN_REVIEW',        -- Cliente revisando trabalho
        'RELEASED',         -- Liberado para o editor
        'REFUNDED',         -- Devolvido ao cliente
        'DISPUTED'          -- Em disputa
    )),
    
    -- Stripe payment intent ID
    stripe_payment_intent_id VARCHAR(255),
    stripe_transfer_id VARCHAR(255),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT NOW(),
    escrowed_at TIMESTAMP,
    released_at TIMESTAMP,
    
    -- Notas
    notes TEXT
);

CREATE INDEX idx_escrow_project ON escrow_payments(project_id);
CREATE INDEX idx_escrow_status ON escrow_payments(status);

-- ==================== UPDATE PROJECTS TABLE ====================
-- Adicionar coluna para status de propostas

ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS proposals_count INTEGER DEFAULT 0;

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

CREATE TRIGGER trigger_update_proposals_count
AFTER INSERT OR DELETE ON proposals
FOR EACH ROW EXECUTE FUNCTION update_proposals_count();

-- ==================== NOTIFICATIONS TABLE ====================
-- Sistema de notificações

CREATE TABLE IF NOT EXISTS notifications (
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

-- ==================== SAMPLE DATA FOR TESTING ====================
-- Comentar depois de testar

-- INSERT INTO proposals (project_id, editor_id, proposed_price, delivery_days, cover_letter)
-- VALUES (
--     'PROJECT_UUID_AQUI',
--     'EDITOR_UUID_AQUI',
--     500.00,
--     3,
--     'Olá! Sou especializado em edição de vlogs e tenho 5 anos de experiência. Posso entregar um trabalho profissional no prazo!'
-- );

COMMENT ON TABLE proposals IS 'Propostas de editores para projetos abertos';
COMMENT ON TABLE escrow_payments IS 'Sistema de pagamento garantido (escrow) para segurança de cliente e editor';
COMMENT ON TABLE notifications IS 'Notificações do sistema para usuários';
