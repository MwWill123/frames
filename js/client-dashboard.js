// ==================== CLIENT DASHBOARD JS ====================

const API_BASE = 'https://frames-will.infinityfree.me/api';
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
    
    // Set user info
    document.getElementById('userName').textContent = userData.profile?.display_name || 'Cliente';
}

function logout() {
    localStorage.removeItem('auth_token');
    sessionStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
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
        }
        
        // Load recent projects
        const projectsResponse = await fetch(`${API_BASE}/projects.php?limit=5`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const projects = await projectsResponse.json();
        
        if (projects.success) {
            displayRecentProjects(projects.data);
        }
        
        // Load recommended editors
        const editorsResponse = await fetch(`${API_BASE}/editors.php?featured=true&limit=4`, {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        const editors = await editorsResponse.json();
        
        if (editors.success) {
            displayRecommendedEditors(editors.data);
        }
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Erro ao carregar dados', 'error');
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
    
    if (projects.length === 0) {
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
    
    container.innerHTML = projects.map(project => `
        <div class="project-card" data-id="${project.id}">
            <div class="project-header">
                <h4>${project.title}</h4>
                <span class="status-badge ${project.status.toLowerCase()}">${getStatusLabel(project.status)}</span>
            </div>
            <p class="project-desc">${truncate(project.description, 100)}</p>
            <div class="project-meta">
                <span><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="8" cy="8" r="6"/>
                    <path d="M8 4v4l2 2"/>
                </svg> ${formatDate(project.deadline)}</span>
                <span><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 2v12M4 6L8 2l4 4M12 10L8 14l-4-4"/>
                </svg> R$ ${formatMoney(project.budget_max)}</span>
            </div>
            <div class="project-actions">
                <button class="btn-secondary" onclick="viewProject('${project.id}')">Ver Detalhes</button>
            </div>
        </div>
    `).join('');
}

function displayRecommendedEditors(editors) {
    const container = document.getElementById('recommendedEditors');
    
    container.innerHTML = editors.map(editor => `
        <div class="editor-card" data-id="${editor.id}">
            <div class="editor-avatar">
                <img src="${editor.avatar_url || 'https://via.placeholder.com/80'}" alt="${editor.display_name}">
                ${editor.is_verified ? '<span class="verified-badge">✓</span>' : ''}
            </div>
            <h4>${editor.display_name}</h4>
            <p class="editor-tagline">${editor.tagline || ''}</p>
            <div class="editor-rating">
                <span class="stars">${'★'.repeat(Math.floor(editor.average_rating))}${'☆'.repeat(5 - Math.floor(editor.average_rating))}</span>
                <span>${editor.average_rating.toFixed(1)} (${editor.total_reviews} reviews)</span>
            </div>
            <div class="editor-skills">
                ${editor.specialties.slice(0, 3).map(s => `<span class="skill-tag">${s}</span>`).join('')}
            </div>
            <button class="btn-primary" onclick="viewEditor('${editor.id}')">Ver Perfil</button>
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
    // Update step indicators
    document.querySelectorAll('.wizard-step').forEach((el, index) => {
        el.classList.remove('active', 'completed');
        if (index + 1 === step) {
            el.classList.add('active');
        } else if (index + 1 < step) {
            el.classList.add('completed');
        }
    });
    
    // Update content
    document.querySelectorAll('.wizard-content').forEach((el, index) => {
        el.classList.remove('active');
        if (index + 1 === step) {
            el.classList.add('active');
        }
    });
    
    // Update buttons
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const submitBtn = document.getElementById('submitProjectBtn');
    
    prevBtn.classList.toggle('hidden', step === 1);
    nextBtn.classList.toggle('hidden', step === 4);
    submitBtn.classList.toggle('hidden', step !== 4);
}

function validateStep(step) {
    const currentContent = document.querySelector(`.wizard-content[data-step="${step}"]`);
    const inputs = currentContent.querySelectorAll('input[required], textarea[required], select[required]');
    
    let valid = true;
    inputs.forEach(input => {
        if (!input.value && input.type !== 'radio') {
            input.style.borderColor = 'var(--error)';
            valid = false;
        } else if (input.type === 'radio') {
            const name = input.name;
            const checked = currentContent.querySelector(`input[name="${name}"]:checked`);
            if (!checked) {
                valid = false;
                showToast('Por favor, selecione uma opção', 'warning');
            }
        } else {
            input.style.borderColor = '';
        }
    });
    
    return valid;
}

function saveStepData(step) {
    const currentContent = document.querySelector(`.wizard-content[data-step="${step}"]`);
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
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.add('active');
    
    try {
        const token = getAuthToken();
        
        const response = await fetch(`${API_BASE}/projects.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'create',
                ...projectData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Projeto criado com sucesso!', 'success');
            closeNewProjectModal();
            loadDashboardData(); // Reload dashboard
            
            // Redirect to project page
            setTimeout(() => {
                window.location.href = `/client/project.html?id=${result.project_id}`;
            }, 1500);
        } else {
            showToast(result.message || 'Erro ao criar projeto', 'error');
        }
    } catch (error) {
        console.error('Error submitting project:', error);
        showToast('Erro ao conectar com o servidor', 'error');
    } finally {
        if (loadingOverlay) loadingOverlay.classList.remove('active');
    }
}

// ==================== EVENT LISTENERS ====================
function initializeEventListeners() {
    // Logout
    document.getElementById('logoutBtn').addEventListener('click', logout);
    
    // New project button
    document.getElementById('newProjectBtn').addEventListener('click', openNewProjectModal);
    
    // Close modal
    document.getElementById('closeModal').addEventListener('click', closeNewProjectModal);
    
    // Close modal on overlay click
    document.getElementById('newProjectModal').addEventListener('click', (e) => {
        if (e.target.id === 'newProjectModal') {
            closeNewProjectModal();
        }
    });
    
    // Budget suggestions
    document.querySelectorAll('.budget-suggestion').forEach(btn => {
        btn.addEventListener('click', () => {
            const min = btn.dataset.min;
            const max = btn.dataset.max;
            document.querySelector('input[name="budget_min"]').value = min;
            document.querySelector('input[name="budget_max"]').value = max;
        });
    });
    
    // Add reference URL
    const addUrlBtn = document.querySelector('.btn-add-url');
    if (addUrlBtn) {
        addUrlBtn.addEventListener('click', () => {
            const container = document.getElementById('referenceUrls');
            const newInput = document.createElement('div');
            newInput.className = 'url-input-group';
            newInput.innerHTML = `
                <input type="url" name="reference_url[]" placeholder="https://youtube.com/watch?v=...">
                <button type="button" class="btn-icon btn-remove-url">−</button>
            `;
            container.appendChild(newInput);
            
            newInput.querySelector('.btn-remove-url').addEventListener('click', () => {
                newInput.remove();
            });
        });
    }
    
    // File upload
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('fileInput');
    
    if (fileUploadArea && fileInput) {
        fileUploadArea.addEventListener('click', () => fileInput.click());
        
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.style.borderColor = 'var(--primary-cyan)';
        });
        
        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.style.borderColor = '';
        });
        
        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.style.borderColor = '';
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });
    }
    
    // Navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const view = item.getAttribute('href').substring(1);
            switchView(view);
            
            document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
        });
    });
}

function handleFiles(files) {
    const uploadedFilesList = document.getElementById('uploadedFiles');
    
    Array.from(files).forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2H6a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7l-5-5z"/>
                <path d="M13 2v5h5"/>
            </svg>
            <span class="file-name">${file.name}</span>
            <span class="file-size">${formatFileSize(file.size)}</span>
            <button type="button" class="btn-remove-file">×</button>
        `;
        
        uploadedFilesList.appendChild(fileItem);
        
        fileItem.querySelector('.btn-remove-file').addEventListener('click', () => {
            fileItem.remove();
        });
        
        // Upload file
        uploadFile(file);
    });
}

async function uploadFile(file) {
    const token = getAuthToken();
    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', 'REFERENCE');
    
    try {
        const response = await fetch(`${API_BASE}/upload.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`
            },
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Store file URL for later use
            if (!projectData.uploaded_files) projectData.uploaded_files = [];
            projectData.uploaded_files.push(result.file_url);
        }
    } catch (error) {
        console.error('Upload error:', error);
        showToast('Erro ao fazer upload do arquivo', 'error');
    }
}

// ==================== VIEW SWITCHING ====================
function switchView(view) {
    document.querySelectorAll('.dashboard-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    const viewMap = {
        'dashboard': 'dashboardView',
        'projects': 'projectsView',
        'search': 'searchView',
        'messages': 'messagesView',
        'payments': 'paymentsView'
    };
    
    const viewElement = document.getElementById(viewMap[view]);
    if (viewElement) {
        viewElement.classList.remove('hidden');
    }
    
    // Update page title
    const titles = {
        'dashboard': 'Dashboard',
        'projects': 'Meus Projetos',
        'search': 'Buscar Editores',
        'messages': 'Mensagens',
        'payments': 'Pagamentos'
    };
    
    document.querySelector('.page-title').textContent = titles[view] || 'Dashboard';
}

// ==================== UTILITY FUNCTIONS ====================
function getAuthToken() {
    return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
}

function showToast(message, type = 'info') {
    // Reuse toast from login or create inline
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
    document.body.appendChild(toast);
    return toast;
}

function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function truncate(str, length) {
    return str.length > length ? str.substring(0, length) + '...' : str;
}

function getStatusLabel(status) {
    const labels = {
        'OPEN': 'Aberto',
        'IN_PROGRESS': 'Em Andamento',
        'IN_REVIEW': 'Em Revisão',
        'REVISION_REQUESTED': 'Revisão Solicitada',
        'COMPLETED': 'Concluído',
        'CANCELLED': 'Cancelado',
        'DISPUTED': 'Em Disputa'
    };
    return labels[status] || status;
}

function viewProject(projectId) {
    window.location.href = `/client/project.html?id=${projectId}`;
}

function viewEditor(editorId) {
    window.location.href = `/editor/profile.html?id=${editorId}`;
}

console.log('%c🎬 FRAMES Client Dashboard', 'font-size: 18px; color: #00FFF0; font-weight: bold;');
console.log('%cDashboard loaded successfully', 'color: #9945FF;');
