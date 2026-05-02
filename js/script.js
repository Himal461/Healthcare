/* ============================================ */
/* HEALTHCARE MANAGEMENT SYSTEM                 */
/* Main JavaScript - NO DROPDOWN CONFLICT       */
/* ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    console.log('📜 Script.js initializing...');
    
    // CRITICAL: Check if new includes system is active
    const isNewHeader = document.querySelector('.includes-user-menu') !== null;
    
    // ONLY run legacy dropdown if old header is detected
    if (!isNewHeader) {
        console.log('⚠️ Legacy header detected - initializing legacy dropdown');
        initLegacyDropdown();
    } else {
        console.log('✅ New includes system active - dropdown handled by includes.js');
    }
    
    initAlerts();
    initModalHandlers();
    initBackToTop();
    
    console.log('✅ Script.js ready');
});

/* ============================================ */
/* LEGACY DROPDOWN - Only for old pages         */
/* ============================================ */
function initLegacyDropdown() {
    const btn = document.getElementById('userDropdownBtn');
    const dropdown = document.getElementById('userDropdown');
    
    // CRITICAL: Exit if elements already handled by includes.js
    if (!btn || !dropdown) return;
    
    // Check if already initialized by includes.js
    if (dropdown.hasAttribute('data-includes-initialized')) return;
    
    console.log('📦 Setting up legacy dropdown');
    
    dropdown.style.display = 'none';
    
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        } else {
            dropdown.style.display = 'block';
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

/* ============================================ */
/* ALERTS                                      */
/* ============================================ */
function initAlerts() {
    document.querySelectorAll('.close-alert').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'all 0.3s ease';
                setTimeout(function() {
                    if (alert.parentNode) alert.remove();
                }, 300);
            }
        });
    });
}

/* ============================================ */
/* MODAL HANDLERS                              */
/* ============================================ */
function initModalHandlers() {
    window.openModal = window.openModal || function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'flex';
    };
    
    window.closeModal = window.closeModal || function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    };
    
    window.addEventListener('click', function(e) {
        if (e.target.classList && 
            (e.target.classList.contains('modal') || 
             e.target.classList.contains('admin-modal') ||
             e.target.classList.contains('doctor-modal') ||
             e.target.classList.contains('staff-modal') ||
             e.target.classList.contains('nurse-modal') ||
             e.target.classList.contains('patient-modal') ||
             e.target.classList.contains('accountant-modal'))) {
            e.target.style.display = 'none';
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal, .admin-modal, .doctor-modal, .staff-modal, .nurse-modal, .patient-modal, .accountant-modal').forEach(function(m) {
                m.style.display = 'none';
            });
        }
    });
}

/* ============================================ */
/* BACK TO TOP                                 */
/* ============================================ */
function initBackToTop() {
    const backToTop = document.getElementById('backToTop');
    if (backToTop) {
        backToTop.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
}

/* ============================================ */
/* UTILITY FUNCTIONS                           */
/* ============================================ */
window.formatCurrency = function(amount) {
    if (amount === null || amount === undefined) return '$0.00';
    return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
};

window.formatDate = function(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'Invalid Date';
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
};

window.formatDateTime = function(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'Invalid Date';
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', month: 'long', day: 'numeric',
        hour: 'numeric', minute: '2-digit'
    });
};

window.confirmAction = function(message) {
    return confirm(message || 'Are you sure?');
};

console.log('📜 Script.js loaded');