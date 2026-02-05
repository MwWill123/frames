// ==================== DOM ELEMENTS ====================
const sidebar = document.getElementById('sidebar');
const toggleSidebarBtn = document.getElementById('toggleSidebar');
const closeSidebarBtn = document.getElementById('closeSidebar');
const searchInput = document.getElementById('searchInput');
const searchBtn = document.getElementById('searchBtn');
const editorsCarousel = document.getElementById('editorsCarousel');
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');
const bgGallery = document.getElementById('bgGallery');

// ==================== INITIALIZATION ====================
document.addEventListener('DOMContentLoaded', () => {
    initBackgroundGallery();
    initEditorCards();
    initEventListeners();
    initFormatCategories();
});

// ==================== BACKGROUND GALLERY ====================
function initBackgroundGallery() {
    backgroundImages.forEach((imageUrl) => {
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = 'Background';
        img.className = 'bg-image';
        bgGallery.appendChild(img);
    });
}

// ==================== EDITOR CARDS ====================
function initEditorCards() {
    editorsData.forEach((editor) => {
        const card = createEditorCard(editor);
        editorsCarousel.appendChild(card);
    });
}

function createEditorCard(editor) {
    const card = document.createElement('div');
    card.className = 'editor-card';
    card.setAttribute('data-id', editor.id);
    
    card.innerHTML = `
        <div class="editor-card-image">
            <img src="${editor.image}" alt="${editor.name}">
            <div class="editor-badge">${editor.name}</div>
            <div class="editor-stats">
                <span class="stat-badge">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                        <path d="M6 1L7.5 4.5L11 5L8.5 7.5L9 11L6 9L3 11L3.5 7.5L1 5L4.5 4.5L6 1Z"/>
                    </svg>
                    ${editor.rating}
                </span>
                <span class="stat-badge">${editor.reviews}</span>
            </div>
        </div>
        <div class="editor-card-content">
            <div class="editor-info">
                <div>
                    <div class="editor-name">${editor.name}</div>
                    <div class="editor-title">${editor.title}</div>
                </div>
                <div class="editor-software">${editor.software}</div>
            </div>
        </div>
    `;
    
    // Add click animation
    card.addEventListener('click', () => {
        card.style.transform = 'scale(0.95)';
        setTimeout(() => {
            card.style.transform = '';
        }, 200);
        
        // Log editor selection (can be used for navigation)
        console.log('Editor selected:', editor);
    });
    
    return card;
}

// ==================== CAROUSEL NAVIGATION ====================
function initCarouselNavigation() {
    const scrollAmount = 340; // card width + gap
    
    prevBtn.addEventListener('click', () => {
        editorsCarousel.scrollBy({
            left: -scrollAmount,
            behavior: 'smooth'
        });
    });
    
    nextBtn.addEventListener('click', () => {
        editorsCarousel.scrollBy({
            left: scrollAmount,
            behavior: 'smooth'
        });
    });
    
    // Update button visibility based on scroll position
    editorsCarousel.addEventListener('scroll', updateCarouselButtons);
    updateCarouselButtons();
}

function updateCarouselButtons() {
    const scrollLeft = editorsCarousel.scrollLeft;
    const maxScroll = editorsCarousel.scrollWidth - editorsCarousel.clientWidth;
    
    prevBtn.style.opacity = scrollLeft > 0 ? '1' : '0.3';
    prevBtn.style.pointerEvents = scrollLeft > 0 ? 'auto' : 'none';
    
    nextBtn.style.opacity = scrollLeft < maxScroll - 10 ? '1' : '0.3';
    nextBtn.style.pointerEvents = scrollLeft < maxScroll - 10 ? 'auto' : 'none';
}

// ==================== SIDEBAR ====================
function toggleSidebar() {
    const isActive = sidebar.classList.contains('active');
    
    if (isActive) {
        sidebar.classList.remove('active');
        console.log('Sidebar fechada');
    } else {
        sidebar.classList.add('active');
        console.log('Sidebar aberta');
    }
}

function closeSidebar() {
    sidebar.classList.remove('active');
    console.log('Sidebar fechada');
}

// ==================== SEARCH FUNCTIONALITY ====================
function handleSearch() {
    const searchTerm = searchInput.value.trim().toLowerCase();
    
    if (!searchTerm) {
        showNotification('Digite algo para pesquisar', 'warning');
        return;
    }
    
    // Filter editors based on search
    const filteredEditors = editorsData.filter(editor => 
        editor.name.toLowerCase().includes(searchTerm) ||
        editor.title.toLowerCase().includes(searchTerm) ||
        editor.software.toLowerCase().includes(searchTerm)
    );
    
    // Update carousel with filtered results
    editorsCarousel.innerHTML = '';
    
    if (filteredEditors.length > 0) {
        filteredEditors.forEach(editor => {
            const card = createEditorCard(editor);
            editorsCarousel.appendChild(card);
        });
        showNotification(`${filteredEditors.length} resultado(s) encontrado(s)`, 'success');
    } else {
        editorsCarousel.innerHTML = '<p style="color: var(--text-secondary); text-align: center; width: 100%; padding: 2rem;">Nenhum resultado encontrado</p>';
        showNotification('Nenhum resultado encontrado', 'error');
    }
}

// ==================== FORMAT CATEGORIES ====================
function initFormatCategories() {
    const formatItems = document.querySelectorAll('.format-item');
    
    formatItems.forEach(item => {
        item.addEventListener('click', function() {
            // Remove active class from all items
            formatItems.forEach(i => i.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Get format type
            const format = this.getAttribute('data-format');
            
            // Show notification
            const formatName = this.querySelector('p').textContent;
            showNotification(`Formato selecionado: ${formatName}`, 'success');
            
            // Log format selection (can be used for filtering)
            console.log('Format selected:', format);
        });
    });
}

// ==================== FILTER TOGGLES ====================
function initFilterToggles() {
    const toggles = document.querySelectorAll('.toggle-switch');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const label = this.closest('.filter-label');
            const filterName = label.querySelector('span').textContent;
            const isChecked = this.checked;
            
            console.log(`Filter "${filterName}" is now ${isChecked ? 'ON' : 'OFF'}`);
            
            // Apply filter logic here
            applyFilters();
        });
    });
}

function applyFilters() {
    // Get all active filters
    const activeFilters = [];
    const toggles = document.querySelectorAll('.toggle-switch:checked');
    
    toggles.forEach(toggle => {
        const label = toggle.closest('.filter-label');
        const filterName = label.querySelector('span').textContent;
        activeFilters.push(filterName);
    });
    
    console.log('Active filters:', activeFilters);
    
    // Filter editors based on active filters
    // This is a placeholder - implement actual filtering logic
    showNotification(`${activeFilters.length} filtro(s) ativo(s)`, 'info');
}

// ==================== NOTIFICATIONS ====================
function showNotification(message, type = 'info') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 2rem;
        background: ${type === 'success' ? 'rgba(0, 255, 240, 0.2)' : 
                     type === 'error' ? 'rgba(255, 69, 0, 0.2)' : 
                     type === 'warning' ? 'rgba(255, 165, 0, 0.2)' : 
                     'rgba(153, 69, 255, 0.2)'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        border: 1px solid ${type === 'success' ? 'var(--primary-cyan)' : 
                           type === 'error' ? 'rgba(255, 69, 0, 0.5)' : 
                           type === 'warning' ? 'rgba(255, 165, 0, 0.5)' : 
                           'var(--primary-purple)'};
        z-index: 10000;
        animation: slideIn 0.3s ease;
        backdrop-filter: blur(10px);
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add notification animations to document
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// ==================== EVENT LISTENERS ====================
function initEventListeners() {
    // Sidebar toggle
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
            console.log('Toggle sidebar button clicked');
        });
    }
    
    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeSidebar();
        });
    }
    
    // Close sidebar when clicking outside
    document.addEventListener('click', (e) => {
        if (sidebar && sidebar.classList.contains('active') && 
            !sidebar.contains(e.target) && 
            !toggleSidebarBtn.contains(e.target)) {
            closeSidebar();
        }
    });
    
    // Search functionality
    searchBtn.addEventListener('click', handleSearch);
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    });
    
    // Clear search button (optional enhancement)
    searchInput.addEventListener('input', () => {
        if (searchInput.value === '') {
            // Reset to all editors
            editorsCarousel.innerHTML = '';
            initEditorCards();
        }
    });
    
    // Carousel navigation
    initCarouselNavigation();
    
    // Filter toggles
    initFilterToggles();
    
    // Smooth scroll for navigation links
    document.querySelectorAll('.footer-nav a').forEach(link => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href');
            if (href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    // If section doesn't exist, scroll to top
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }
        });
    });
}

// ==================== SCROLL EFFECTS ====================
// Header removed - no scroll effects needed

// ==================== MOUSE PARALLAX EFFECT ====================
document.addEventListener('mousemove', (e) => {
    const mouseX = e.clientX / window.innerWidth;
    const mouseY = e.clientY / window.innerHeight;
    
    const bgImages = document.querySelectorAll('.bg-image');
    bgImages.forEach((img, index) => {
        const speed = (index + 1) * 0.5;
        const x = (mouseX - 0.5) * speed;
        const y = (mouseY - 0.5) * speed;
        img.style.transform = `translate(${x}px, ${y}px)`;
    });
});

// ==================== CONSOLE WELCOME MESSAGE ====================
console.log('%c🎬 FRAMES ', 'font-size: 24px; font-weight: bold; color: #00FFF0; text-shadow: 0 0 10px #00FFF0;');
console.log('%cUnlock Your Visual Story', 'font-size: 14px; color: #9945FF;');
console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #00FFF0;');
console.log('System initialized successfully ✓');
