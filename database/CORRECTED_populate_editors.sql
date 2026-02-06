-- ==================== POPULAR EDITORES DE EXEMPLO - CORRIGIDO ====================
-- Execute DEPOIS de criar o schema (CORRECTED_schema.sql)

-- 1. Criar usuários editores (com senha: "editor123")
INSERT INTO users (email, password_hash, role, is_verified, is_active, created_at)
VALUES 
    ('editor1@frames.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EDITOR', TRUE, TRUE, NOW()),
    ('editor2@frames.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EDITOR', TRUE, TRUE, NOW()),
    ('editor3@frames.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EDITOR', TRUE, TRUE, NOW()),
    ('editor4@frames.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'EDITOR', TRUE, TRUE, NOW())
ON CONFLICT (email) DO NOTHING;

-- 2. Criar perfis dos editores
-- IMPORTANTE: Usando apenas colunas que SABEMOS que existem
INSERT INTO editor_profiles (
    user_id,
    display_name,
    bio,
    primary_software,
    average_rating,
    total_reviews,
    avatar_url,
    is_featured
)
SELECT 
    u.id,
    'Lucas Monteiro',
    'Especialista em edição de vlogs e conteúdo para YouTube com 5 anos de experiência. Foco em narrativa dinâmica e engajamento.',
    'PREMIERE_PRO',
    4.9,
    127,
    'https://i.pravatar.cc/150?img=12',
    TRUE
FROM users u
WHERE u.email = 'editor1@frames.com'
ON CONFLICT (user_id) DO UPDATE SET
    display_name = EXCLUDED.display_name,
    bio = EXCLUDED.bio,
    is_featured = EXCLUDED.is_featured;

INSERT INTO editor_profiles (
    user_id,
    display_name,
    bio,
    primary_software,
    average_rating,
    total_reviews,
    avatar_url,
    is_featured
)
SELECT 
    u.id,
    'Mariana Silva',
    'Editora criativa especializada em Reels e TikTok. Tendências virais e storytelling rápido são minha especialidade!',
    'CAPCUT',
    4.8,
    89,
    'https://i.pravatar.cc/150?img=5',
    TRUE
FROM users u
WHERE u.email = 'editor2@frames.com'
ON CONFLICT (user_id) DO UPDATE SET
    display_name = EXCLUDED.display_name,
    bio = EXCLUDED.bio,
    is_featured = EXCLUDED.is_featured;

INSERT INTO editor_profiles (
    user_id,
    display_name,
    bio,
    primary_software,
    average_rating,
    total_reviews,
    avatar_url,
    is_featured
)
SELECT 
    u.id,
    'Rafael Costa',
    'Editor profissional de comerciais e branded content. Trabalho com grandes marcas há 7 anos.',
    'DAVINCI_RESOLVE',
    5.0,
    203,
    'https://i.pravatar.cc/150?img=33',
    TRUE
FROM users u
WHERE u.email = 'editor3@frames.com'
ON CONFLICT (user_id) DO UPDATE SET
    display_name = EXCLUDED.display_name,
    bio = EXCLUDED.bio,
    is_featured = EXCLUDED.is_featured;

INSERT INTO editor_profiles (
    user_id,
    display_name,
    bio,
    primary_software,
    average_rating,
    total_reviews,
    avatar_url,
    is_featured
)
SELECT 
    u.id,
    'Juliana Alves',
    'Especialista em edição de gameplay e streaming. FPS, RPG, e conteúdo de e-sports são minha paixão!',
    'PREMIERE_PRO',
    4.7,
    76,
    'https://i.pravatar.cc/150?img=9',
    FALSE
FROM users u
WHERE u.email = 'editor4@frames.com'
ON CONFLICT (user_id) DO UPDATE SET
    display_name = EXCLUDED.display_name,
    bio = EXCLUDED.bio,
    is_featured = EXCLUDED.is_featured;

-- 3. Atualizar colunas extras se existirem
DO $$ 
BEGIN
    -- Tentar atualizar years_of_experience se a coluna existir
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'editor_profiles' AND column_name = 'years_of_experience'
    ) THEN
        UPDATE editor_profiles ep
        SET years_of_experience = 5
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor1@frames.com';
        
        UPDATE editor_profiles ep
        SET years_of_experience = 3
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor2@frames.com';
        
        UPDATE editor_profiles ep
        SET years_of_experience = 7
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor3@frames.com';
        
        UPDATE editor_profiles ep
        SET years_of_experience = 4
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor4@frames.com';
    END IF;

    -- Atualizar completed_projects_count se existir
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'editor_profiles' AND column_name = 'completed_projects_count'
    ) THEN
        UPDATE editor_profiles ep
        SET completed_projects_count = 156
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor1@frames.com';
        
        UPDATE editor_profiles ep
        SET completed_projects_count = 112
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor2@frames.com';
        
        UPDATE editor_profiles ep
        SET completed_projects_count = 287
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor3@frames.com';
        
        UPDATE editor_profiles ep
        SET completed_projects_count = 94
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor4@frames.com';
    END IF;

    -- Atualizar hourly_rate se existir
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'editor_profiles' AND column_name = 'hourly_rate_min'
    ) THEN
        UPDATE editor_profiles ep
        SET hourly_rate_min = 80, hourly_rate_max = 150
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor1@frames.com';
        
        UPDATE editor_profiles ep
        SET hourly_rate_min = 60, hourly_rate_max = 120
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor2@frames.com';
        
        UPDATE editor_profiles ep
        SET hourly_rate_min = 150, hourly_rate_max = 300
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor3@frames.com';
        
        UPDATE editor_profiles ep
        SET hourly_rate_min = 70, hourly_rate_max = 140
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor4@frames.com';
    END IF;

    -- Atualizar portfolio_url se existir
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'editor_profiles' AND column_name = 'portfolio_url'
    ) THEN
        UPDATE editor_profiles ep
        SET portfolio_url = 'https://youtube.com/@lucasmonteiro'
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor1@frames.com';
        
        UPDATE editor_profiles ep
        SET portfolio_url = 'https://tiktok.com/@marianasilva'
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor2@frames.com';
        
        UPDATE editor_profiles ep
        SET portfolio_url = 'https://rafaelcosta.com'
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor3@frames.com';
        
        UPDATE editor_profiles ep
        SET portfolio_url = 'https://twitch.tv/julianaalves'
        FROM users u
        WHERE ep.user_id = u.id AND u.email = 'editor4@frames.com';
    END IF;
END $$;

-- 4. Verificar se foram criados
SELECT 
    u.email,
    ep.display_name,
    ep.bio,
    ep.average_rating,
    ep.total_reviews,
    ep.is_featured
FROM editor_profiles ep
JOIN users u ON ep.user_id = u.id
WHERE u.role = 'EDITOR'
ORDER BY ep.is_featured DESC, ep.average_rating DESC;

-- Resultado esperado: 4 editores listados
