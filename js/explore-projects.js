// ==================== EXPLORE PROJECTS JS ====================
// The Job Board - Marketplace de projetos para editores

const API_BASE = 'https://frames.alwaysdata.net/api';
let allProjects = [];
let selectedProjectId = null;

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadOpenProjects();
    loadProposalsCount();
    initializeEventListeners();
});

// ==================== AUTHENTICATION ====================
function checkAuth() {
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    const userData = JSON.parse(localStorage.getItem('user_data') || '{}');
    
    if (!token || userData.role !== 'EDITOR') {
        window.location.href = '/login.html';
        return;
    }
    
    document.getElementById('userName').textContent = userData.display_name || userData.email || 'Editor';
}

function getAuthToken() {
    return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
}

function logout() {
    localStorage.clear();
    sessionStorage.clear();
    window.location.href = '/login.html';
}

// ==================== LOAD OPEN PROJECTS ====================
async function loadOpenProjects() {
    try {
        const token = getAuthToken();
        
        const response = await fetch(`${API_BASE}/projects.php?status=OPEN`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (data.success) {
            allProjects = data.data || [];
            displayProjects(allProjects);
            updateProjectsCount(allProjects.length);
        } else {
            console.error('Error loading projects:', data.message);
            displayProjects([]);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Erro ao carregar projetos', 'error');
    }
}

function displayProjects(projects) {
    const container = document.getElementById('projectsGrid');
    
    if (!projects || projects.length === 0) {
        container.innerHTML = `
            <div class="empty-projects">
                <svg width="80" height="80" viewBox="0 0 80 80" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="10" y="10" width="60" height="60" rx="4"/>
                    <path d="M10 30h60"/>
                    <circle cx="40" cy="50" r="10"/>
                </svg>
                <h3>Nenhum projeto disponível no momento</h3>
                <p>Novos projetos aparecerão aqui quando os clientes os publicarem</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = projects.map(project => `
        <div class="project-card" onclick="openProposalModal('${project.id}')">
            <div class="project-card-header">
                <span class="project-specialty">${formatSpecialty(project.video_specialty)}</span>
                <span class="project-posted">${formatTimeAgo(project.created_at)}</span>
            </div>
            
            <h3>${project.title}</h3>
            <p class="project-description">${truncate(project.description, 150)}</p>
            
            <div class="project-meta">
                <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 13v-8M8 5l-4 4M8 5l4 4"/>
                    </svg>
                    <span class="budget-range">R$ ${formatMoney(project.budget_min)} - R$ ${formatMoney(project.budget_max)}</span>
                </div>
                
                <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="12" height="11" rx="2"/>
                        <path d="M2 7h12M6 3v2M10 3v2"/>
                    </svg>
                    <span>Até ${formatDate(project.deadline)}</span>
                </div>
                
                ${project.proposals_count > 0 ? `
                    <span class="proposals-count">${project.proposals_count} proposta${project.proposals_count > 1 ? 's' : ''}</span>
                ` : ''}
            </div>
            
            <div class="project-tags">
                ${project.aspect_ratio ? `<span class="tag">${project.aspect_ratio}</span>` : ''}
                ${project.video_duration_min && project.video_duration_max ? 
                    `<span class="tag">${project.video_duration_min}-${project.video_duration_max} min</span>` : ''}
                ${project.preferred_software ? `<span class="tag">${project.preferred_software}</span>` : ''}
            </div>
            
            <button class="btn-submit-proposal" onclick="event.stopPropagation(); openProposalModal('${project.id}')">
                Enviar Proposta
            </button>
        </div>
    `).join('');
}

function updateProjectsCount(count) {
    document.getElementById('projectsCount').textContent = `${count} projeto${count !== 1 ? 's' : ''} disponível${count !== 1 ? 'is' : ''}`;
}

// ==================== LOAD PROPOSALS COUNT ====================
async function loadProposalsCount() {
    try {
        const token = getAuthToken();
        
        const response = await fetch(`${API_BASE}/proposals.php?my_proposals=true`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const pendingCount = (data.data || []).filter(p => p.status === 'PENDING').length;
            document.getElementById('proposalsCount').textContent = pendingCount;
        }
    } catch (error) {
        console.error('Error loading proposals count:', error);
    }
}

// ==================== FILTERS ====================
function initializeEventListeners() {
    document.getElementById('logoutBtn').addEventListener('click', logout);
    
    // Filters
    document.getElementById('specialtyFilter').addEventListener('change', applyFilters);
    document.getElementById('budgetFilter').addEventListener('change', applyFilters);
    document.getElementById('sortFilter').addEventListener('change', applyFilters);
    
    // Proposal form
    document.getElementById('proposalForm').addEventListener('submit', submitProposal);
    
    // Calculate editor amount in real-time
    document.getElementById('proposedPrice').addEventListener('input', (e) => {
        const price = parseFloat(e.target.value) || 0;
        const editorAmount = price * 0.85; // 85% para o editor
        document.getElementById('editorAmount').textContent = formatMoney(editorAmount);
    });
}

function applyFilters() {
    const specialty = document.getElementById('specialtyFilter').value;
    const budget = document.getElementById('budgetFilter').value;
    const sort = document.getElementById('sortFilter').value;
    
    let filtered = [...allProjects];
    
    // Filter by specialty
    if (specialty) {
        filtered = filtered.filter(p => p.video_specialty === specialty);
    }
    
    // Filter by budget
    if (budget) {
        const [min, max] = budget.split('-').map(v => v === '+' ? Infinity : parseInt(v));
        filtered = filtered.filter(p => {
            const projectMax = p.budget_max || 0;
            if (max === Infinity) {
                return projectMax >= min;
            }
            return projectMax >= min && projectMax <= max;
        });
    }
    
    // Sort
    if (sort === 'budget_high') {
        filtered.sort((a, b) => (b.budget_max || 0) - (a.budget_max || 0));
    } else if (sort === 'deadline') {
        filtered.sort((a, b) => new Date(a.deadline) - new Date(b.deadline));
    } else {
        filtered.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    }
    
    displayProjects(filtered);
    updateProjectsCount(filtered.length);
}

// ==================== PROPOSAL MODAL ====================
function openProposalModal(projectId) {
    selectedProjectId = projectId;
    document.getElementById('proposalModal').classList.add('active');
    
    // Reset form
    document.getElementById('proposalForm').reset();
    document.getElementById('editorAmount').textContent = '0.00';
}

function closeProposalModal() {
    document.getElementById('proposalModal').classList.remove('active');
    selectedProjectId = null;
}

async function submitProposal(e) {
    e.preventDefault();
    
    if (!selectedProjectId) {
        showToast('Erro: Projeto não selecionado', 'error');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = {
        project_id: selectedProjectId,
        proposed_price: parseFloat(formData.get('proposed_price')),
        delivery_days: parseInt(formData.get('delivery_days')),
        cover_letter: formData.get('cover_letter')
    };
    
    try {
        showLoading(true);
        
        const token = getAuthToken();
        const response = await fetch(`${API_BASE}/proposals.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Proposta enviada com sucesso! 🎉', 'success');
            closeProposalModal();
            
            // Reload projects to update proposals count
            setTimeout(() => {
                loadOpenProjects();
                loadProposalsCount();
            }, 1000);
        } else {
            showToast(result.message || 'Erro ao enviar proposta', 'error');
        }
    } catch (error) {
        console.error('Error submitting proposal:', error);
        showToast('Erro ao conectar com servidor', 'error');
    } finally {
        showLoading(false);
    }
}

// ==================== UTILITY FUNCTIONS ====================
function formatSpecialty(specialty) {
    const map = {
        'VLOG': 'Vlog',
        'COMMERCIAL': 'Comercial',
        'REELS_TIKTOK': 'Reels/TikTok',
        'YOUTUBE': 'YouTube',
        'DOCUMENTARY': 'Documentário',
        'GAMEPLAY': 'Gameplay',
        'GENERAL': 'Geral'
    };
    return map[specialty] || specialty;
}

function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value || 0);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('pt-BR');
}

function formatTimeAgo(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 60) return `${diffMins}min atrás`;
    if (diffHours < 24) return `${diffHours}h atrás`;
    if (diffDays === 1) return 'Ontem';
    if (diffDays < 7) return `${diffDays} dias atrás`;
    return formatDate(dateString);
}

function truncate(str, length) {
    if (!str) return '';
    return str.length > length ? str.substring(0, length) + '...' : str;
}

function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay') || createLoadingOverlay();
    overlay.classList.toggle('active', show);
}

function createLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner"></div><p>Processando...</p>';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(10,10,10,0.9);display:flex;align-items:center;justify-content:center;flex-direction:column;z-index:9999;';
    document.body.appendChild(overlay);
    
    const style = document.createElement('style');
    style.textContent = `
        .spinner { width: 50px; height: 50px; border: 4px solid rgba(0,255,240,0.1); border-top-color: var(--primary-cyan); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);
    
    return overlay;
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast') || createToast();
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function createToast() {
    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    toast.style.cssText = 'position:fixed;top:2rem;right:2rem;background:var(--dark-card);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:1rem 1.5rem;min-width:300px;transform:translateX(400px);transition:transform 0.3s;z-index:10000;';
    document.body.appendChild(toast);
    
    const style = document.createElement('style');
    style.textContent = `
        .toast.show { transform: translateX(0); }
        .toast.success { border-left: 4px solid var(--success); }
        .toast.error { border-left: 4px solid var(--error); }
        .toast.warning { border-left: 4px solid var(--warning); }
    `;
    document.head.appendChild(style);
    
    return toast;
}

console.log('%c🎬 FRAMES - Explore Projects', 'font-size: 18px; color: #00FFF0; font-weight: bold;');
console.log('%cThe Job Board loaded', 'color: #9945FF;');
