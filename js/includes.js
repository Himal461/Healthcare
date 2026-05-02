/* ============================================ */
/* INCLUDES MODULE - SIMPLIFIED FIXED VERSION    */
/* ============================================ */

(function() {
    'use strict';
    
    console.log('✅ Includes JS loaded');
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        console.log('🚀 Initializing includes module...');
        initUserDropdown();
        initNotificationBell();
        initAlerts();
        initBackToTop();
    }

    /* ============================================ */
    /* USER DROPDOWN                               */
    /* ============================================ */
    function initUserDropdown() {
        const btn = document.getElementById('userDropdownBtn');
        const dropdown = document.getElementById('userDropdown');
        const arrow = document.getElementById('dropdownArrow');
        
        if (!btn || !dropdown) {
            console.log('ℹ️ User not logged in');
            return;
        }

        console.log('✅ User dropdown found');
        
        // Remove existing listeners by replacing
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        const finalBtn = document.getElementById('userDropdownBtn');
        const finalDropdown = document.getElementById('userDropdown');
        const finalArrow = document.getElementById('dropdownArrow');
        
        finalDropdown.classList.remove('show');
        
        finalBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('User dropdown clicked');
            
            // Close notification dropdown
            const notifDropdown = document.getElementById('notificationDropdown');
            if (notifDropdown) notifDropdown.classList.remove('show');
            
            // Toggle
            if (finalDropdown.classList.contains('show')) {
                finalDropdown.classList.remove('show');
                if (finalArrow) {
                    finalArrow.classList.remove('fa-chevron-up');
                    finalArrow.classList.add('fa-chevron-down');
                }
            } else {
                finalDropdown.classList.add('show');
                if (finalArrow) {
                    finalArrow.classList.remove('fa-chevron-down');
                    finalArrow.classList.add('fa-chevron-up');
                }
            }
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!finalBtn.contains(e.target) && !finalDropdown.contains(e.target)) {
                finalDropdown.classList.remove('show');
                if (finalArrow) {
                    finalArrow.classList.remove('fa-chevron-up');
                    finalArrow.classList.add('fa-chevron-down');
                }
            }
        });
    }

    /* ============================================ */
    /* NOTIFICATION BELL - SIMPLE FIX              */
    /* ============================================ */
    function initNotificationBell() {
        const bell = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (!bell || !dropdown) {
            console.log('ℹ️ Notification bell not found');
            return;
        }

        console.log('✅ Notification bell found');
        
        // Replace to remove old listeners
        const newBell = bell.cloneNode(true);
        bell.parentNode.replaceChild(newBell, bell);
        
        const finalBell = document.getElementById('notificationBell');
        const finalDropdown = document.getElementById('notificationDropdown');
        
        finalDropdown.style.display = 'none';
        
        finalBell.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🔔 Bell clicked!');
            
            // Close user dropdown
            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) userDropdown.classList.remove('show');
            
            // Toggle notification dropdown
            if (finalDropdown.style.display === 'block') {
                finalDropdown.style.display = 'none';
            } else {
                finalDropdown.style.display = 'block';
            }
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!finalBell.contains(e.target) && !finalDropdown.contains(e.target)) {
                finalDropdown.style.display = 'none';
            }
        });
    }

    /* ============================================ */
    /* ALERTS                                      */
    /* ============================================ */
    function initAlerts() {
        document.querySelectorAll('.includes-close-alert, .close-alert').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const alert = this.closest('.includes-alert, .alert');
                if (alert) alert.remove();
            });
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

})();