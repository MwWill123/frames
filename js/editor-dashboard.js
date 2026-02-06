// ==================== EDITOR DASHBOARD JS ====================

const API_BASE = 'https://frames.alwaysdata.net/api';
let currentProjects = [];
let uploadingFile = null;

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadDashboardData();
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

function logout() {
    localStorage.clear();
    sessionStorage.clear();
    window.location.href = '/login.html';
}

function getAuthToken() {
    return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
}

// ==================== LOAD DASHBOARD DATA ====================
async function loadDashboardData() {
    try {
        const token = getAuthToken();
        
        // Load stats
        await loadStats(token);
        
        // Load projects
        await loadProjects(token);
        
        // Load activity
        await loadActivity(token);
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Erro ao carregar dados do dashboard', 'error');
    }
}

async function loadStats(token) {
    try {
        const response = await fetch(`${API_BASE}/projects.php?action=stats`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateStats(data.data);
        } else {
            updateStats({ active: 0, completed: 0, earnings: 0, rating: 0 });
        }
    } catch (error) {
        console.error('Stats error:', error);
        updateStats({ active: 0, completed: 0, earnings: 0, rating: 0 });
    }
}

function updateStats(stats) {
    document.getElementById('activeProjects').textContent = stats.active || 0;
    document.getElementById('completedProjects').textContent = stats.completed || 0;
    document.getElementById('monthlyEarnings').textContent = `R$ ${formatMoney(stats.earnings || 0)}`;
    document.getElementById('averageRating').textContent = (stats.rating || 0).toFixed(1);
}

async function loadProjects(token) {
    try {
        const response = await fetch(`${API_BASE}/projects.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentProjects = data.data || [];
            displayProjectsKanban(currentProjects);
        }
    } catch (error) {
        console.error('Projects error:', error);
    }
}

function displayProjectsKanban(projects) {
    // Clear all columns
    ['newRequests', 'editing', 'review', 'completed'].forEach(id => {
        document.getElementById(id).innerHTML = '';
    });
    
    // Sort projects by status
    const newProjects = projects.filter(p => p.status === 'OPEN');
    const editingProjects = projects.filter(p => p.status === 'IN_PROGRESS');
    const reviewProjects = projects.filter(p => p.status === 'IN_REVIEW');
    const completedProjects = projects.filter(p => p.status === 'COMPLETED');
    
    // Render cards
    renderKanbanCards('newRequests', newProjects);
    renderKanbanCards('editing', editingProjects);
    renderKanbanCards('review', reviewProjects);
    renderKanbanCards('completed', completedProjects);
    
    // Update counts
    document.querySelectorAll('.kanban-column')[0].querySelector('.count').textContent = newProjects.length;
    document.querySelectorAll('.kanban-column')[1].querySelector('.count').textContent = editingProjects.length;
    document.querySelectorAll('.kanban-column')[2].querySelector('.count').textContent = reviewProjects.length;
    document.querySelectorAll('.kanban-column')[3].querySelector('.count').textContent = completedProjects.length;
}

function renderKanbanCards(containerId, projects) {
    const container = document.getElementById(containerId);
    
    if (!projects || projects.length === 0) {
        container.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 2rem; font-size: 0.9rem;">Nenhum projeto</p>';
        return;
    }
    
    container.innerHTML = projects.map(project => `
        <div class="kanban-card" data-id="${project.id}">
            <h4>${project.title}</h4>
            <p>${truncate(project.description, 80)}</p>
            <div class="card-meta">
                <span>💰 R$ ${formatMoney(project.budget_max || 0)}</span>
                <span>📅 ${formatDate(project.deadline)}</span>
            </div>
            <div class="card-actions">
                ${containerId === 'newRequests' ? `
                    <button class="btn-small btn-primary" onclick="acceptProject('${project.id}')">
                        Aceitar
                    </button>
                ` : ''}
                ${containerId === 'editing' ? `
                    <button class="btn-small btn-primary" onclick="openUploadModal('${project.id}')">
                        Upload Vídeo
                    </button>
                ` : ''}
                ${containerId === 'review' ? `
                    <button class="btn-small btn-secondary" onclick="viewProject('${project.id}')">
                        Ver Comentários
                    </button>
                ` : ''}
                <button class="btn-small btn-secondary" onclick="viewProjectDetails('${project.id}')">
                    Detalhes
                </button>
            </div>
        </div>
    `).join('');
}

async function loadActivity(token) {
    const activities = [
        { icon: '📝', text: 'Novo projeto "Vlog Viagem" recebido', time: '2 horas atrás' },
        { icon: '✅', text: 'Projeto "Review Produto" aprovado', time: '5 horas atrás' },
        { icon: '💬', text: 'Novo comentário em "Tutorial Gaming"', time: '1 dia atrás' }
    ];
    
    const container = document.getElementById('activityList');
    container.innerHTML = activities.map(activity => `
        <div class="activity-item">
            <span class="activity-icon">${activity.icon}</span>
            <div class="activity-content">
                <p>${activity.text}</p>
                <span class="activity-time">${activity.time}</span>
            </div>
        </div>
    `).join('');
}

// ==================== PROJECT ACTIONS ====================
async function acceptProject(projectId) {
    if (!confirm('Deseja aceitar este projeto?')) return;
    
    try {
        const token = getAuthToken();
        
        const response = await fetch(`${API_BASE}/projects.php`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                project_id: projectId,
                status: 'IN_PROGRESS'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Projeto aceito com sucesso!', 'success');
            loadProjects(token);
        } else {
            showToast(result.message || 'Erro ao aceitar projeto', 'error');
        }
    } catch (error) {
        console.error('Error accepting project:', error);
        showToast('Erro ao conectar com servidor', 'error');
    }
}

function viewProjectDetails(projectId) {
    // Redirect to project details page
    window.location.href = `/editor/project-details.html?id=${projectId}`;
}

function viewProject(projectId) {
    // Redirect to review room
    window.location.href = `/client/review-room.html?project=${projectId}`;
}

// ==================== UPLOAD VIDEO ====================
function openUploadModal(projectId) {
    document.getElementById('uploadModal').classList.add('active');
    
    // Load projects for select
    loadProjectsForUpload(projectId);
}

async function loadProjectsForUpload(selectedId) {
    try {
        const token = getAuthToken();
        const response = await fetch(`${API_BASE}/projects.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        const data = await response.json();
        
        if (data.success) {
            const select = document.getElementById('projectSelect');
            select.innerHTML = '<option value="">Selecione o projeto</option>';
            
            const projects = data.data || [];
            projects.forEach(project => {
                if (project.status === 'IN_PROGRESS' || project.status === 'IN_REVIEW') {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.title;
                    if (project.id === selectedId) option.selected = true;
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// ==================== EVENT LISTENERS ====================
function initializeEventListeners() {
    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', logout);
    
    // Upload button
    document.getElementById('uploadVideoBtn')?.addEventListener('click', () => openUploadModal());
    
    // Upload modal
    const uploadArea = document.getElementById('uploadArea');
    const videoInput = document.getElementById('videoInput');
    
    uploadArea?.addEventListener('click', () => videoInput.click());
    
    uploadArea?.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'var(--primary-cyan)';
    });
    
    uploadArea?.addEventListener('dragleave', () => {
        uploadArea.style.borderColor = 'rgba(255, 255, 255, 0.1)';
    });
    
    uploadArea?.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = 'rgba(255, 255, 255, 0.1)';
        
        if (e.dataTransfer.files.length > 0) {
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });
    
    videoInput?.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });
    
    // Start upload button
    document.getElementById('startUploadBtn')?.addEventListener('click', startUpload);
    
    // Close modal
    document.getElementById('closeUploadModal')?.addEventListener('click', closeUploadModal);
}

function handleFileSelect(file) {
    if (!file.type.startsWith('video/')) {
        showToast('Por favor, selecione um arquivo de vídeo', 'error');
        return;
    }
    
    uploadingFile = file;
    
    const uploadArea = document.getElementById('uploadArea');
    uploadArea.innerHTML = `
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="var(--success)" stroke-width="2">
            <path d="M32 48V16m0 0l-12 12m12-12l12 12"/>
            <path d="M8 56h48"/>
        </svg>
        <p style="color: var(--success);">Arquivo selecionado!</p>
        <p style="font-size: 0.9rem;">${file.name} (${formatFileSize(file.size)})</p>
    `;
}

async function startUpload() {
    if (!uploadingFile) {
        showToast('Selecione um arquivo primeiro', 'warning');
        return;
    }
    
    const projectId = document.getElementById('projectSelect').value;
    if (!projectId) {
        showToast('Selecione um projeto', 'warning');
        return;
    }
    
    const fileType = document.getElementById('fileTypeSelect').value;
    const notes = document.getElementById('uploadNotes').value;
    
    try {
        showLoading(true);
        
        const token = getAuthToken();
        const formData = new FormData();
        formData.append('file', uploadingFile);
        formData.append('project_id', projectId);
        formData.append('type', fileType);
        formData.append('notes', notes);
        
        const response = await fetch(`${API_BASE}/upload.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Vídeo enviado com sucesso!', 'success');
            closeUploadModal();
            
            // Update project status to IN_REVIEW
            await fetch(`${API_BASE}/projects.php`, {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    project_id: projectId,
                    status: 'IN_REVIEW'
                })
            });
            
            // Reload dashboard
            loadDashboardData();
        } else {
            showToast(result.message || 'Erro ao fazer upload', 'error');
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('Erro ao conectar com servidor', 'error');
    } finally {
        showLoading(false);
    }
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
    uploadingFile = null;
    
    // Reset form
    const uploadArea = document.getElementById('uploadArea');
    uploadArea.innerHTML = `
        <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M32 48V16m0 0l-12 12m12-12l12 12"/>
            <path d="M8 56h48"/>
        </svg>
        <p>Arraste o vídeo aqui ou clique para selecionar</p>
        <input type="file" id="videoInput" accept="video/*" hidden>
        <p class="upload-hint">MP4, MOV, AVI até 10GB</p>
    `;
}

// ==================== UTILITY FUNCTIONS ====================
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

function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('pt-BR');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function truncate(str, length) {
    if (!str) return '';
    return str.length > length ? str.substring(0, length) + '...' : str;
}

// Add kanban card styles
const kanbanStyles = document.createElement('style');
kanbanStyles.textContent = `
    .kanban-board { display: flex; gap: 1.5rem; overflow-x: auto; padding: 1rem 0; }
    .kanban-column { min-width: 300px; background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem; }
    .kanban-header { display: flex; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .kanban-header h4 { margin: 0; font-size: 1rem; }
    .kanban-header .count { background: var(--primary-cyan); color: var(--dark-bg); padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
    .kanban-cards { display: flex; flex-direction: column; gap: 1rem; }
    .kanban-card { background: var(--dark-card); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px; padding: 1rem; cursor: pointer; transition: all 0.3s; }
    .kanban-card:hover { border-color: var(--primary-cyan); transform: translateY(-2px); }
    .kanban-card h4 { margin: 0 0 0.5rem 0; font-size: 0.95rem; }
    .kanban-card p { color: var(--text-secondary); font-size: 0.85rem; margin: 0 0 1rem 0; }
    .card-meta { display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 1rem; }
    .card-actions { display: flex; gap: 0.5rem; }
    .btn-small { padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 6px; border: none; cursor: pointer; transition: all 0.3s; }
    .activity-list { display: flex; flex-direction: column; gap: 1rem; }
    .activity-item { display: flex; gap: 1rem; padding: 1rem; background: rgba(255,255,255,0.03); border-radius: 8px; }
    .activity-icon { font-size: 1.5rem; }
    .activity-content p { margin: 0 0 0.25rem 0; }
    .activity-time { font-size: 0.8rem; color: var(--text-secondary); }
`;
document.head.appendChild(kanbanStyles);

console.log('%c🎬 FRAMES Editor Dashboard', 'font-size: 18px; color: #00FFF0; font-weight: bold;');
console.log('%cEditor dashboard loaded', 'color: #9945FF;');
