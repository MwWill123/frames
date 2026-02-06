-- ==================== POPULATE SAMPLE EDITORS ====================
-- Execute este SQL no Supabase para adicionar editores de exemplo

-- Primeiro, crie usuários editores
INSERT INTO users (email, password_hash, role, is_verified, is_active, created_at)
VALUES 
    ('editor1@frames.com', '$2y$10$abcdefghijklmnopqrstuvwxyz123456', 'EDITOR', TRUE, TRUE, NOW()),
    ('editor2@frames.com', '$2y$10$abcdefghijklmnopqrstuvwxyz123456', 'EDITOR', TRUE, TRUE, NOW()),
    ('editor3@frames.com', '$2y$10$abcdefghijklmnopqrstuvwxyz123456', 'EDITOR', TRUE, TRUE, NOW()),
    ('editor4@frames.com', '$2y$10$abcdefghijklmnopqrstuvwxyz123456', 'EDITOR', TRUE, TRUE, NOW())
ON CONFLICT (email) DO NOTHING;

-- Agora, crie os perfis dos editores
-- IMPORTANTE: Substitua os UUIDs pelos IDs reais dos usuários criados acima
-- Para pegar os IDs: SELECT id, email FROM users WHERE role = 'EDITOR';

-- Exemplo de INSERT (substitua os UUIDs):
INSERT INTO editor_profiles (
    user_id,
    display_name,
    bio,
    primary_software,
    years_of_experience,
    average_rating,
    total_reviews,
    completed_projects_count,
    avatar_url,
    portfolio_url,
    is_featured,
    hourly_rate_min,
    hourly_rate_max,
    specialties,
    preferred_genres
) VALUES 
(
    (SELECT id FROM users WHERE email = 'editor1@frames.com'),
    'Lucas Monteiro',
    'Especialista em edição de vlogs e conteúdo para YouTube com 5 anos de experiência. Foco em narrativa dinâmica e engajamento.',
    'PREMIERE_PRO',
    5,
    4.9,
    127,
    156,
    'https://i.pravatar.cc/150?img=12',
    'https://youtube.com/@lucasmonteiro',
    TRUE,
    80,
    150,
    '{"VLOG", "YOUTUBE", "DOCUMENTARY"}',
    '{"Travel", "Lifestyle", "Tech"}'
),
(
    (SELECT id FROM users WHERE email = 'editor2@frames.com'),
    'Mariana Silva',
    'Editora criativa especializada em Reels e TikTok. Tendências virais e storytelling rápido são minha especialidade!',
    'CAPCUT',
    3,
    4.8,
    89,
    112,
    'https://i.pravatar.cc/150?img=5',
    'https://tiktok.com/@marianasilva',
    TRUE,
    60,
    120,
    '{"REELS_TIKTOK", "COMMERCIAL"}',
    '{"Fashion", "Beauty", "Dance"}'
),
(
    (SELECT id FROM users WHERE email = 'editor3@frames.com'),
    'Rafael Costa',
    'Editor profissional de comerciais e branded content. Trabalho com grandes marcas há 7 anos.',
    'DAVINCI_RESOLVE',
    7,
    5.0,
    203,
    287,
    'https://i.pravatar.cc/150?img=33',
    'https://rafaelcosta.com',
    TRUE,
    150,
    300,
    '{"COMMERCIAL", "CORPORATE"}',
    '{"Advertising", "Brand", "Product"}'
),
(
    (SELECT id FROM users WHERE email = 'editor4@frames.com'),
    'Juliana Alves',
    'Especialista em edição de gameplay e streaming. FPS, RPG, e conteúdo de e-sports são minha paixão!',
    'PREMIERE_PRO',
    4,
    4.7,
    76,
    94,
    'https://i.pravatar.cc/150?img=9',
    'https://twitch.tv/julianaalves',
    FALSE,
    70,
    140,
    '{"GAMEPLAY", "REELS_TIKTOK"}',
    '{"Gaming", "Esports", "Streaming"}'
)
ON CONFLICT (user_id) DO NOTHING;

-- Verificar se foram criados
SELECT 
    u.email,
    ep.display_name,
    ep.bio,
    ep.average_rating,
    ep.is_featured
FROM editor_profiles ep
JOIN users u ON ep.user_id = u.id
WHERE u.role = 'EDITOR';

-- ==================== ATUALIZAR ESQUEMA (se necessário) ====================

-- Adicionar colunas se não existirem
ALTER TABLE projects 
ADD COLUMN IF NOT EXISTS agreed_price DECIMAL(10,2),
ADD COLUMN IF NOT EXISTS rating DECIMAL(2,1),
ADD COLUMN IF NOT EXISTS review_comment TEXT;

-- Adicionar colunas em editor_profiles se não existirem
ALTER TABLE editor_profiles
ADD COLUMN IF NOT EXISTS hourly_rate_min DECIMAL(10,2) DEFAULT 50,
ADD COLUMN IF NOT EXISTS hourly_rate_max DECIMAL(10,2) DEFAULT 200,
ADD COLUMN IF NOT EXISTS specialties TEXT[],
ADD COLUMN IF NOT EXISTS preferred_genres TEXT[];

COMMENT ON TABLE editor_profiles IS 'Perfis de editores com portfolio e ratings';
COMMENT ON COLUMN editor_profiles.is_featured IS 'Se true, aparece nos editores recomendados';
