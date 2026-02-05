// ==================== LOGIN.JS - FRAMES AUTHENTICATION ====================

const API_BASE = '/api';

// DOM Elements
const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const tabBtns = document.querySelectorAll('.tab-btn');
const loadingOverlay = document.getElementById('loadingOverlay');
const toast = document.getElementById('toast');
const passwordStrength = document.getElementById('passwordStrength');
const registerPassword = document.getElementById('registerPassword');

// ==================== TAB SWITCHING ====================
tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        
        // Update active tab
        tabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Show corresponding form
        document.querySelectorAll('.auth-form').forEach(form => {
            form.classList.remove('active');
        });
        
        if (tab === 'login') {
            loginForm.classList.add('active');
        } else {
            registerForm.classList.add('active');
        }
    });
});

// ==================== PASSWORD STRENGTH CHECKER ====================
if (registerPassword) {
    registerPassword.addEventListener('input', (e) => {
        const password = e.target.value;
        const strength = checkPasswordStrength(password);
        
        passwordStrength.className = 'password-strength';
        
        if (password.length > 0) {
            passwordStrength.classList.add(strength);
        }
    });
}

function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;
    
    if (strength <= 1) return 'weak';
    if (strength <= 2) return 'medium';
    return 'strong';
}

// ==================== LOGIN FORM HANDLER ====================
loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const remember = loginForm.querySelector('input[name="remember"]').checked;
    
    // Validate
    if (!email || !password) {
        showToast('Por favor, preencha todos os campos', 'error');
        return;
    }
    
    showLoading(true);
    
    try {
<<<<<<< HEAD
        const response = await fetch(`${API_BASE}/auth.php`, {
=======
        const response = await fetch(`/php/auth.php`, {
>>>>>>> 4f4e104576569a17e58fc20a4d37cd88e9a2743f
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'login',
                email,
                password,
                remember
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store token
            if (remember) {
                localStorage.setItem('auth_token', data.token);
            } else {
                sessionStorage.setItem('auth_token', data.token);
            }
            
            // Store user data
            localStorage.setItem('user_data', JSON.stringify(data.user));
            
            showToast('Login realizado com sucesso!', 'success');
            
            // Redirect based on role
            setTimeout(() => {
                window.location.href = data.redirect;
            }, 1000);
        } else {
            showToast(data.message || 'Erro ao fazer login', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showToast('Erro ao conectar com o servidor', 'error');
    } finally {
        showLoading(false);
    }
});

// ==================== REGISTER FORM HANDLER ====================
registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const displayName = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const passwordConfirm = document.getElementById('registerPasswordConfirm').value;
    const role = registerForm.querySelector('input[name="role"]:checked').value;
    const termsAccepted = registerForm.querySelector('input[name="terms"]').checked;
    
    // Validate
    if (!displayName || !email || !password || !passwordConfirm) {
        showToast('Por favor, preencha todos os campos', 'error');
        return;
    }
    
    if (password !== passwordConfirm) {
        showToast('As senhas não coincidem', 'error');
        return;
    }
    
    if (password.length < 8) {
        showToast('A senha deve ter no mínimo 8 caracteres', 'error');
        return;
    }
    
    if (!termsAccepted) {
        showToast('Você deve aceitar os termos de uso', 'error');
        return;
    }
    
    showLoading(true);
    
    try {
<<<<<<< HEAD
        const response = await fetch(`${API_BASE}/auth.php`, {
=======
        const response = await fetch(`/php/auth.php`, {
>>>>>>> 4f4e104576569a17e58fc20a4d37cd88e9a2743f
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'register',
                email,
                password,
                role,
                displayName
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Conta criada com sucesso! Verifique seu email.', 'success');
            
            // Switch to login tab after 2 seconds
            setTimeout(() => {
                document.querySelector('.tab-btn[data-tab="login"]').click();
                document.getElementById('loginEmail').value = email;
            }, 2000);
        } else {
            showToast(data.message || 'Erro ao criar conta', 'error');
        }
    } catch (error) {
        console.error('Register error:', error);
        showToast('Erro ao conectar com o servidor', 'error');
    } finally {
        showLoading(false);
    }
});

// ==================== SOCIAL LOGIN ====================
document.querySelectorAll('.btn-google').forEach(btn => {
    btn.addEventListener('click', async () => {
        showToast('Autenticação com Google em breve!', 'warning');
        // TODO: Implement Google OAuth
        // window.location.href = '/api/oauth/google';
    });
});

document.querySelectorAll('.btn-apple').forEach(btn => {
    btn.addEventListener('click', async () => {
        showToast('Autenticação com Apple em breve!', 'warning');
        // TODO: Implement Apple Sign-in
        // window.location.href = '/api/oauth/apple';
    });
});

// ==================== FORGOT PASSWORD ====================
document.querySelectorAll('.forgot-link').forEach(link => {
    link.addEventListener('click', async (e) => {
        e.preventDefault();
        
        const email = document.getElementById('loginEmail').value;
        
        if (!email) {
            showToast('Digite seu email primeiro', 'warning');
            return;
        }
        
        showLoading(true);
        
        try {
<<<<<<< HEAD
            const response = await fetch(`${API_BASE}/auth.php`, {
=======
            const response = await fetch(`/php/auth.php`, {
>>>>>>> 4f4e104576569a17e58fc20a4d37cd88e9a2743f
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'forgot_password',
                    email
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Link de recuperação enviado para seu email', 'success');
            } else {
                showToast(data.message || 'Erro ao enviar email', 'error');
            }
        } catch (error) {
            console.error('Forgot password error:', error);
            showToast('Erro ao conectar com o servidor', 'error');
        } finally {
            showLoading(false);
        }
    });
});

// ==================== UTILITY FUNCTIONS ====================

function showLoading(show) {
    if (show) {
        loadingOverlay.classList.add('active');
    } else {
        loadingOverlay.classList.remove('active');
    }
}

function showToast(message, type = 'info') {
    toast.textContent = message;
    toast.className = `toast ${type}`;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// ==================== AUTO-FILL FROM URL PARAMS ====================
window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Check for email verification
    const verifyToken = urlParams.get('verify');
    if (verifyToken) {
        verifyEmail(verifyToken);
    }
    
    // Check for password reset
    const resetToken = urlParams.get('reset');
    if (resetToken) {
        showPasswordResetForm(resetToken);
    }
    
    // Auto-switch to register if coming from homepage
    const action = urlParams.get('action');
    if (action === 'register') {
        document.querySelector('.tab-btn[data-tab="register"]').click();
    }
});

async function verifyEmail(token) {
    showLoading(true);
    
    try {
<<<<<<< HEAD
        const response = await fetch(`${API_BASE}/auth.php`, {
=======
        const response = await fetch(`/php/auth.php`, {
>>>>>>> 4f4e104576569a17e58fc20a4d37cd88e9a2743f
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'verify_email',
                token
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Email verificado com sucesso! Você já pode fazer login.', 'success');
        } else {
            showToast('Link de verificação inválido ou expirado', 'error');
        }
    } catch (error) {
        console.error('Email verification error:', error);
        showToast('Erro ao verificar email', 'error');
    } finally {
        showLoading(false);
    }
}

function showPasswordResetForm(token) {
    // Create password reset modal
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-content">
            <h2>Redefinir Senha</h2>
            <form id="resetPasswordForm">
                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" id="newPassword" placeholder="Mínimo 8 caracteres" required>
                </div>
                <div class="form-group">
                    <label>Confirmar Senha</label>
                    <input type="password" id="confirmNewPassword" placeholder="Digite novamente" required>
                </div>
                <button type="submit" class="btn-primary">Redefinir Senha</button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('resetPasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmNewPassword').value;
        
        if (newPassword !== confirmPassword) {
            showToast('As senhas não coincidem', 'error');
            return;
        }
        
        if (newPassword.length < 8) {
            showToast('A senha deve ter no mínimo 8 caracteres', 'error');
            return;
        }
        
        showLoading(true);
        
        try {
<<<<<<< HEAD
            const response = await fetch(`${API_BASE}/auth.php`, {
=======
            const response = await fetch(`/php/auth.php`, {
>>>>>>> 4f4e104576569a17e58fc20a4d37cd88e9a2743f
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reset_password',
                    token,
                    newPassword
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Senha redefinida com sucesso!', 'success');
                modal.remove();
                
                setTimeout(() => {
                    window.location.href = '/login.html';
                }, 2000);
            } else {
                showToast(data.message || 'Erro ao redefinir senha', 'error');
            }
        } catch (error) {
            console.error('Reset password error:', error);
            showToast('Erro ao conectar com o servidor', 'error');
        } finally {
            showLoading(false);
        }
    });
}

// ==================== CHECK IF ALREADY LOGGED IN ====================
window.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    
    if (token) {
        // Verify token is still valid
        fetch(`${API_BASE}/auth.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                action: 'verify_token'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Already logged in, redirect
                const userData = JSON.parse(localStorage.getItem('user_data'));
                const role = userData?.role || 'CLIENT';
                
                const redirectMap = {
                    'ADMIN': '/admin/dashboard.html',
                    'EDITOR': '/editor/dashboard.html',
                    'CLIENT': '/client/dashboard.html'
                };
                
                window.location.href = redirectMap[role];
            }
        })
        .catch(err => {
            console.error('Token verification failed:', err);
        });
    }
});

console.log('%c🎬 FRAMES Login System', 'font-size: 20px; color: #00FFF0; font-weight: bold;');
console.log('%cAuthentication ready', 'color: #9945FF;');
