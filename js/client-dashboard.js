// ==================== CLIENT DASHBOARD JS - FIXED ====================

const API_BASE = 'https://frames.alwaysdata.net/api';
let currentStep = 1;
let projectData = {};

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadDashboardData();
    initializeEventListeners();
    initializeWizard();
});

// ==================== AUTHENTICATION ====================
function checkAuth() {
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    const userData = JSON.parse(localStorage.getItem('user_data') || '{}');
    
    if (!token || userData.role !== 'CLIENT') {
        window.location.href = '/login.html';
        return;
    }
    
    document.getElementById('userName').textContent = userData.display_name || userData.email || 'Cliente';
}

function logout() {
    localStorage.clear();
    sessionStorage.clear();
    window.location.href = '/login.html';
}

// ==================== LOAD DASHBOARD DATA ====================
async function loadDashboardData() {
    try {
        const token = getAuthToken();
        
        // Load stats
        const statsResponse = await fetch(`${API_BASE}/projects.php?action=stats`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const stats = await statsResponse.json();
        
        if (stats.success) {
            updateStats(stats.data);
        } else {
            console.error('Stats error:', stats.message);
            // Set zeros if error
            updateStats({ active: 0, completed: 0, spent: 0, editors: 0 });
        }
        
        // Load recent projects
        const projectsResponse = await fetch(`${API_BASE}/projects.php`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const projects = await projectsResponse.json();
        
        if (projects.success) {
            displayRecentProjects(projects.data || []);
        }
        
        // Load editors
        const editorsResponse = await fetch(`${API_BASE}/editors.php?featured=true&limit=4`);
        const editors = await editorsResponse.json();
        
        if (editors.success) {
            displayRecommendedEditors(editors.data || []);
        }
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Erro ao carregar dados do dashboard', 'error');
        // Set default values
        updateStats({ active: 0, completed: 0, spent: 0, editors: 0 });
    }
}

function updateStats(stats) {
    document.getElementById('activeProjects').textContent = stats.active || 0;
    document.getElementById('completedProjects').textContent = stats.completed || 0;
    document.getElementById('totalSpent').textContent = `R$ ${formatMoney(stats.spent || 0)}`;
    document.getElementById('editorsWorked').textContent = stats.editors || 0;
}

function displayRecentProjects(projects) {
    const container = document.getElementById('recentProjects');
    
    if (!projects || projects.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="8" y="12" width="48" height="40" rx="4"/>
                    <path d="M8 24h48"/>
                    <circle cx="32" cy="36" r="8"/>
                </svg>
                <p>Nenhum projeto ainda</p>
                <button class="btn-primary" onclick="openNewProjectModal()">
                    Criar Primeiro Projeto
                </button>
            </div>
        `;
        return;
    }
    
    container.innerHTML = projects.slice(0, 5).map(project => `
        <div class="project-card" style="background: var(--dark-card); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 1.5rem; margin-bottom: 1rem;">
            <div class="project-header" style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <h4 style="margin: 0;">${project.title}</h4>
                <span class="status-badge" style="background: rgba(0, 255, 240, 0.1); color: var(--primary-cyan); padding: 0.3rem 0.8rem; border-radius: 6px; font-size: 0.75rem;">${project.status}</span>
            </div>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0.5rem 0;">${truncate(project.description, 100)}</p>
            <div class="project-meta" style="display: flex; gap: 1rem; font-size: 0.85rem; color: var(--text-secondary); margin-top: 1rem;">
                <span>📅 ${formatDate(project.deadline)}</span>
                <span>💰 R$ ${formatMoney(project.budget_max || 0)}</span>
            </div>
        </div>
    `).join('');
}

function displayRecommendedEditors(editors) {
    const container = document.getElementById('recommendedEditors');
    
    if (!editors || editors.length === 0) {
        container.innerHTML = '<p style="color: var(--text-secondary);">Nenhum editor disponível no momento</p>';
        return;
    }
    
    container.innerHTML = editors.map(editor => `
        <div class="editor-card" style="background: var(--dark-card); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 1.5rem; text-align: center;">
            <img src="${editor.image || 'https://via.placeholder.com/80'}" alt="${editor.name}" style="width: 80px; height: 80px; border-radius: 50%; margin-bottom: 1rem; border: 2px solid var(--primary-cyan);">
            <h4 style="margin: 0.5rem 0;">${editor.name}</h4>
            <p style="color: var(--text-secondary); font-size: 0.85rem;">${editor.title || 'Editor Profissional'}</p>
            <div style="margin: 1rem 0;">
                <span style="color: var(--primary-cyan);">${'★'.repeat(Math.floor(editor.rating || 4))}${'☆'.repeat(5 - Math.floor(editor.rating || 4))}</span>
                <span style="font-size: 0.8rem; color: var(--text-secondary);"> ${editor.rating || 4}.0 (${editor.reviews || 0})</span>
            </div>
            <button class="btn-primary" style="width: 100%; padding: 0.7rem; font-size: 0.9rem;">Ver Perfil</button>
        </div>
    `).join('');
}

// ==================== NEW PROJECT MODAL ====================
function openNewProjectModal() {
    document.getElementById('newProjectModal').classList.add('active');
    currentStep = 1;
    projectData = {};
    showWizardStep(1);
}

function closeNewProjectModal() {
    document.getElementById('newProjectModal').classList.remove('active');
    document.getElementById('newProjectForm').reset();
}

// ==================== WIZARD NAVIGATION ====================
function initializeWizard() {
    const nextBtn = document.getElementById('nextStepBtn');
    const prevBtn = document.getElementById('prevStepBtn');
    const submitBtn = document.getElementById('submitProjectBtn');
    const form = document.getElementById('newProjectForm');
    
    if (!nextBtn || !prevBtn || !submitBtn || !form) return;
    
    nextBtn.addEventListener('click', () => {
        if (validateStep(currentStep)) {
            saveStepData(currentStep);
            if (currentStep < 4) {
                currentStep++;
                showWizardStep(currentStep);
            }
        }
    });
    
    prevBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            showWizardStep(currentStep);
        }
    });
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (validateStep(4)) {
            saveStepData(4);
            await submitProject();
        }
    });
}

function showWizardStep(step) {
    document.querySelectorAll('.wizard-step').forEach((el, index) => {
        el.classList.remove('active', 'completed');
        if (index + 1 === step) {
            el.classList.add('active');
        } else if (index + 1 < step) {
            el.classList.add('completed');
        }
    });
    
    document.querySelectorAll('.wizard-content').forEach((el, index) => {
        el.classList.remove('active');
        if (index + 1 === step) {
            el.classList.add('active');
        }
    });
    
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const submitBtn = document.getElementById('submitProjectBtn');
    
    prevBtn.classList.toggle('hidden', step === 1);
    nextBtn.classList.toggle('hidden', step === 4);
    submitBtn.classList.toggle('hidden', step !== 4);
}

function validateStep(step) {
    const currentContent = document.querySelector(`.wizard-content[data-step="${step}"]`);
    if (!currentContent) return false;
    
    const inputs = currentContent.querySelectorAll('input[required], textarea[required], select[required]');
    
    let valid = true;
    inputs.forEach(input => {
        if (input.type === 'radio') {
            const name = input.name;
            const checked = currentContent.querySelector(`input[name="${name}"]:checked`);
            if (!checked) {
                valid = false;
                showToast('Por favor, selecione uma opção', 'warning');
            }
        } else if (!input.value) {
            input.style.borderColor = 'var(--error)';
            valid = false;
        } else {
            input.style.borderColor = '';
        }
    });
    
    return valid;
}

function saveStepData(step) {
    const formData = new FormData(document.getElementById('newProjectForm'));
    
    formData.forEach((value, key) => {
        if (key.endsWith('[]')) {
            const arrayKey = key.slice(0, -2);
            if (!projectData[arrayKey]) projectData[arrayKey] = [];
            projectData[arrayKey].push(value);
        } else {
            projectData[key] = value;
        }
    });
}

async function submitProject() {
    showLoading(true);
    
    try {
        const token = getAuthToken();
        
        const payload = {
            action: 'create',
            title: projectData.title,
            description: projectData.description,
            specialty: projectData.specialty,
            deadline: projectData.deadline,
            budget_min: projectData.budget_min || 500,
            budget_max: projectData.budget_max || 1000,
            budget_type: projectData.budget_type || 'fixed',
            aspect_ratio: projectData.aspect_ratio || '16:9',
            duration_min: projectData.duration_min || null,
            duration_max: projectData.duration_max || null
        };
        
        console.log('Sending project:', payload);
        
        const response = await fetch(`${API_BASE}/projects.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        console.log('Response:', result);
        
        if (result.success) {
            showToast('Projeto criado com sucesso!', 'success');
            closeNewProjectModal();
            
            // Reload dashboard
            setTimeout(() => {
                loadDashboardData();
            }, 1000);
        } else {
            showToast(result.message || 'Erro ao criar projeto', 'error');
        }
    } catch (error) {
        console.error('Error submitting project:', error);
        showToast('Erro ao conectar com o servidor', 'error');
    } finally {
        showLoading(false);
    }
}

// ==================== EVENT LISTENERS ====================
function initializeEventListeners() {
    // Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', logout);
    
    // New project
    const newProjectBtn = document.getElementById('newProjectBtn');
    if (newProjectBtn) newProjectBtn.addEventListener('click', openNewProjectModal);
    
    // Close modal
    const closeModalBtn = document.getElementById('closeModal');
    if (closeModalBtn) closeModalBtn.addEventListener('click', closeNewProjectModal);
    
    // Close on overlay click
    const modal = document.getElementById('newProjectModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target.id === 'newProjectModal') {
                closeNewProjectModal();
            }
        });
    }
    
    // Budget suggestions
    document.querySelectorAll('.budget-suggestion').forEach(btn => {
        btn.addEventListener('click', () => {
            const min = btn.dataset.min;
            const max = btn.dataset.max;
            document.querySelector('input[name="budget_min"]').value = min;
            document.querySelector('input[name="budget_max"]').value = max;
        });
    });
}

// ==================== UTILITY FUNCTIONS ====================
function getAuthToken() {
    return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
}

function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay') || createLoadingOverlay();
    overlay.classList.toggle('active', show);
}

function createLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner"></div><p>Carregando...</p>';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(10,10,10,0.9);display:flex;align-items:center;justify-content:center;flex-direction:column;z-index:9999;';
    document.body.appendChild(overlay);
    return overlay;
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast') || createToast();
    toast.textContent = message;
    toast.className = `toast ${type} show`;
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

function createToast() {
    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    toast.style.cssText = 'position:fixed;top:2rem;right:2rem;background:var(--dark-card);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:1rem 1.5rem;min-width:300px;transform:translateX(400px);transition:transform 0.3s;z-index:10000;';
    document.body.appendChild(toast);
    
    const style = document.createElement('style');
    style.textContent = '.toast.show{transform:translateX(0);}.toast.success{border-left:4px solid var(--success);}.toast.error{border-left:4px solid var(--error);}.toast.warning{border-left:4px solid var(--warning);}';
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
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function truncate(str, length) {
    if (!str) return '';
    return str.length > length ? str.substring(0, length) + '...' : str;
}

console.log('%c🎬 FRAMES Client Dashboard', 'font-size: 18px; color: #00FFF0; font-weight: bold;');
console.log('%cDashboard loaded successfully', 'color: #9945FF;');
