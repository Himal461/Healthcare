/* ============================================ */
/* ROOT MODULE - UNIFIED JAVASCRIPT             */
/* ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initRootAlerts();
    initRootForms();
    initRootModals();
});

/* ============================================ */
/* ALERT FUNCTIONS                             */
/* ============================================ */
function initRootAlerts() {
    document.querySelectorAll('.close-alert').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.root-alert').remove();
        });
    });
    
    setTimeout(() => {
        document.querySelectorAll('.root-alert:not(.alert-dismissible)').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(-100%)';
            alert.style.transition = 'all 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
}

/* ============================================ */
/* FORM VALIDATION                             */
/* ============================================ */
function initRootForms() {
    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username')?.value.trim();
            const password = document.getElementById('password')?.value;
            
            if (!username) {
                e.preventDefault();
                alert('Please enter your username or email.');
                return false;
            }
            
            if (!password) {
                e.preventDefault();
                alert('Please enter your password.');
                return false;
            }
            
            return true;
        });
    }
    
    // Register form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password')?.value;
            const confirm = document.getElementById('confirm_password')?.value;
            const termsCheckbox = document.querySelector('input[name="terms"]');
            
            if (termsCheckbox && !termsCheckbox.checked) {
                e.preventDefault();
                alert('Please agree to the Terms of Service and Privacy Policy.');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
            
            if (password && password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            return true;
        });
    }
    
    // Forgot password form
    const forgotForm = document.getElementById('forgot-form');
    if (forgotForm) {
        forgotForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email')?.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!email || !emailPattern.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            return true;
        });
    }
    
    // Reset password form
    const resetForm = document.getElementById('reset-form');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            const password = document.getElementById('new_password')?.value;
            const confirm = document.getElementById('confirm_password')?.value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
            
            if (password && password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            return true;
        });
    }
    
    // Contact form
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email')?.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailPattern.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            return true;
        });
    }
    
    // Profile form
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]')?.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailPattern.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            if (!confirm('Save profile changes?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
}

/* ============================================ */
/* MODAL FUNCTIONS                             */
/* ============================================ */
function initRootModals() {
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'flex';
    };
    
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    };
    
    window.onclick = function(event) {
        if (event.target.classList && event.target.classList.contains('root-modal')) {
            event.target.style.display = 'none';
        }
    };
}

/* ============================================ */
/* TOGGLE PASSWORD VISIBILITY                  */
/* ============================================ */
window.togglePassword = function(inputId, iconElement) {
    const input = document.getElementById(inputId);
    if (input) {
        if (input.type === 'password') {
            input.type = 'text';
            iconElement.classList.remove('fa-eye');
            iconElement.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            iconElement.classList.remove('fa-eye-slash');
            iconElement.classList.add('fa-eye');
        }
    }
};

/* ============================================ */
/* BACK TO TOP                                 */
/* ============================================ */
window.scrollToTop = function() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

/* ============================================ */
/* PRINT FUNCTION                              */
/* ============================================ */
window.printPage = function() {
    window.print();
};

/* ============================================ */
/* DOCTOR PROFILE PAGE STYLES                   */
/* ============================================ */

.doctor-profile-main-card {
    background: white;
    border-radius: 20px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.doctor-profile-header {
    padding: 30px 35px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 25px;
    flex-wrap: wrap;
}

.doctor-profile-avatar {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, #1e3a5f 0%, #0f2440 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(30, 58, 95, 0.2);
}

.doctor-profile-avatar i {
    font-size: 80px;
    color: white;
}

.doctor-profile-title h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
}

.doctor-profile-specialty-badge {
    display: inline-block;
    background: #1e3a5f;
    color: white;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
    margin-right: 15px;
}

.doctor-profile-status {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
}

.doctor-profile-status.available {
    background: #dcfce7;
    color: #166534;
}

.doctor-profile-status.unavailable {
    background: #fee2e2;
    color: #991b1b;
}

.doctor-profile-body {
    padding: 35px;
}

.doctor-profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 35px;
}

.doctor-info-section {
    margin-bottom: 30px;
}

.doctor-info-section:last-child {
    margin-bottom: 0;
}

.doctor-info-section h3 {
    color: #1e293b;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
}

.doctor-info-section h3 i {
    color: #1e3a5f;
}

.doctor-info-item {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.doctor-info-item:last-child {
    border-bottom: none;
}

.doctor-info-item .info-label {
    font-weight: 600;
    color: #64748b;
    min-width: 130px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.doctor-info-item .info-label i {
    width: 18px;
    color: #1e3a5f;
}

.doctor-info-item .info-value {
    color: #1e293b;
    font-weight: 500;
}

.doctor-info-item .info-value a {
    color: #1e3a5f;
    text-decoration: none;
}

.doctor-info-item .info-value a:hover {
    text-decoration: underline;
}

.doctor-education-content,
.doctor-biography-content {
    background: #f8fafc;
    padding: 18px 20px;
    border-radius: 12px;
    line-height: 1.7;
    color: #334155;
}

.doctor-stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.doctor-stat-mini-card {
    background: #f8fafc;
    border-radius: 14px;
    padding: 18px 15px;
    text-align: center;
    border: 1px solid #eef2f6;
    transition: all 0.2s ease;
}

.doctor-stat-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(30, 58, 95, 0.08);
    border-color: #1e3a5f;
}

.stat-mini-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #e8f0fe 0%, #d0e0f5 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}

.stat-mini-icon i {
    font-size: 22px;
    color: #1e3a5f;
}

.stat-mini-content {
    display: flex;
    flex-direction: column;
}

.stat-mini-number {
    font-size: 24px;
    font-weight: 700;
    color: #1e3a5f;
    line-height: 1.2;
}

.stat-mini-label {
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
}

.doctor-availability-mini-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.availability-mini-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: #f8fafc;
    border-radius: 10px;
    border-left: 4px solid #10b981;
}

.availability-mini-item.day-off {
    border-left-color: #ef4444;
}

.avail-date {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    color: #1e293b;
}

.avail-date i {
    color: #1e3a5f;
}

.avail-time {
    color: #10b981;
    font-weight: 500;
    font-size: 13px;
}

.day-off-badge {
    background: #fee2e2;
    color: #991b1b;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.doctor-profile-actions {
    display: flex;
    gap: 15px;
    margin-top: 35px;
    padding-top: 25px;
    border-top: 2px solid #e2e8f0;
    flex-wrap: wrap;
}

.root-btn-large {
    padding: 16px 32px;
    font-size: 16px;
}

.root-btn-view-profile {
    margin-bottom: 10px;
}

.text-muted {
    color: #64748b;
    font-style: italic;
}

/* Responsive for Doctor Profile */
@media (max-width: 1024px) {
    .doctor-profile-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .doctor-stats-mini-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .doctor-profile-header {
        flex-direction: column;
        text-align: center;
        padding: 25px 20px;
    }
    
    .doctor-profile-avatar {
        width: 100px;
        height: 100px;
    }
    
    .doctor-profile-avatar i {
        font-size: 60px;
    }
    
    .doctor-profile-title h2 {
        font-size: 24px;
    }
    
    .doctor-profile-body {
        padding: 25px 20px;
    }
    
    .doctor-info-item {
        flex-direction: column;
        gap: 5px;
    }
    
    .doctor-info-item .info-label {
        min-width: auto;
    }
    
    .doctor-stats-mini-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .doctor-profile-actions {
        flex-direction: column;
    }
    
    .doctor-profile-actions .root-btn {
        width: 100%;
        justify-content: center;
    }
    
    .availability-mini-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}

@media (max-width: 480px) {
    .doctor-profile-title h2 {
        font-size: 20px;
    }
    
    .doctor-stats-mini-grid {
        grid-template-columns: 1fr;
    }
    
    .doctor-profile-avatar {
        width: 80px;
        height: 80px;
    }
    
    .doctor-profile-avatar i {
        font-size: 50px;
    }
}

/* ============================================ */
/* ROOT DOCTOR CARD UPDATES                     */
/* ============================================ */

.root-doctor-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 15px;
}

.root-btn-view-profile {
    background: transparent;
    border: 2px solid #1e3a5f;
    color: #1e3a5f;
}

.root-btn-view-profile:hover {
    background: #1e3a5f;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 58, 95, 0.2);
}