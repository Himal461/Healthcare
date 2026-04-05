/* ============================================
   HEALTHCARE MANAGEMENT SYSTEM
   Main JavaScript File
   ============================================ */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initUserDropdown();
    initTooltips();
    initFormValidation();
    initInteractiveElements();
    autoDismissAlerts();
    initDatePickers();
    initTimeSlotLoaders();
    initAlternativeSlots();
    initAvailabilityToggles();
    initPasswordStrength();
});

/* ============================================
   USER DROPDOWN
   ============================================ */
function initUserDropdown() {
    const userDropdownBtn = document.getElementById('userDropdownBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userDropdownBtn && userDropdown) {
        userDropdownBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userDropdownBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });
    }
}

/* ============================================
   TOOLTIPS
   ============================================ */
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltipText = this.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = tooltipText;
    
    document.body.appendChild(tooltip);
    
    const rect = this.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    tooltip.style.left = (rect.left + (rect.width - tooltip.offsetWidth) / 2) + 'px';
    
    this.tooltip = tooltip;
}

function hideTooltip() {
    if (this.tooltip) {
        this.tooltip.remove();
        this.tooltip = null;
    }
}

/* ============================================
   FORM VALIDATION
   ============================================ */
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    highlightField(field, 'This field is required.');
                } else {
                    removeHighlight(field);
                }
            });
            
            // Password confirmation validation
            const password = this.querySelector('#new_password, #password');
            const confirmPassword = this.querySelector('#confirm_password');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                isValid = false;
                highlightField(confirmPassword, 'Passwords do not match.');
            }
            
            // Email validation
            const email = this.querySelector('input[type="email"]');
            if (email && email.value && !isValidEmail(email.value)) {
                isValid = false;
                highlightField(email, 'Please enter a valid email address.');
            }
            
            // Terms checkbox validation
            const termsCheckbox = this.querySelector('input[name="terms"]');
            if (termsCheckbox && !termsCheckbox.checked) {
                isValid = false;
                alert('Please agree to the Terms of Service and Privacy Policy');
                e.preventDefault();
                return;
            }
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fix the errors in the form.', 'error');
            } else {
                // Add loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.classList.contains('loading')) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    
                    // Store original text to restore if needed (will be restored on page reload)
                    submitBtn.setAttribute('data-original-text', originalText);
                }
            }
        });
    });
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function highlightField(field, message) {
    field.style.borderColor = '#dc3545';
    
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    const error = document.createElement('div');
    error.className = 'field-error';
    error.textContent = message;
    field.parentNode.appendChild(error);
}

function removeHighlight(field) {
    field.style.borderColor = '';
    const error = field.parentNode.querySelector('.field-error');
    if (error) {
        error.remove();
    }
}

/* ============================================
   INTERACTIVE ELEMENTS
   ============================================ */
function initInteractiveElements() {
    // Close alert buttons
    document.querySelectorAll('.close-alert').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.alert').remove();
        });
    });
    
    // Table row selection
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.matches('a, button, input, .btn, .dropdown')) {
                this.classList.toggle('selected');
            }
        });
    });
    
    // Dropdown buttons for role change
    document.querySelectorAll('.dropdown-toggle').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdownId = this.getAttribute('data-dropdown');
            const dropdown = document.getElementById(dropdownId);
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });
            
            dropdown.classList.toggle('show');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
}

/* ============================================
   AUTO DISMISS ALERTS
   ============================================ */
function autoDismissAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.alert-dismissible)');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(-100%)';
            alert.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                if (alert.parentNode) alert.remove();
            }, 300);
        }, 5000);
    });
}

/* ============================================
   DATE PICKERS
   ============================================ */
function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value && input.hasAttribute('min')) {
            input.valueAsDate = new Date();
        }
        
        input.addEventListener('change', function() {
            if (this.id === 'appointment_date' && window.loadTimeSlots) {
                loadTimeSlots(this.value);
            }
        });
    });
}

/* ============================================
   TIME SLOT LOADERS
   ============================================ */
function initTimeSlotLoaders() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput = document.getElementById('appointment_time');
    
    if (doctorSelect && dateInput && timeSlotsContainer) {
        doctorSelect.addEventListener('change', () => loadTimeSlots());
        dateInput.addEventListener('change', () => loadTimeSlots());
        
        // Load if both are already selected
        if (doctorSelect.value && dateInput.value) {
            loadTimeSlots();
        }
    }
}

async function loadTimeSlots() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput = document.getElementById('appointment_time');
    
    if (!doctorSelect || !dateInput || !timeSlotsContainer) return;
    
    const doctorId = doctorSelect.value;
    const date = dateInput.value;
    
    if (!doctorId || !date) {
        timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a doctor and date first</p>';
        return;
    }
    
    timeSlotsContainer.innerHTML = '<p class="text-muted">Loading available times...</p>';
    
    try {
        const response = await fetch(`ajax/get-time-slots.php?doctor_id=${doctorId}&date=${date}`);
        const data = await response.json();
        
        if (data.success && data.slots && data.slots.length > 0) {
            let html = '<div class="time-slots">';
            data.slots.forEach(slot => {
                html += `<div class="time-slot" data-time="${slot.value}">${slot.start}</div>`;
            });
            html += '</div>';
            timeSlotsContainer.innerHTML = html;
            
            // Add click handlers to time slots
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    if (timeInput) {
                        timeInput.value = this.getAttribute('data-time');
                    }
                    // Clear availability result
                    const availabilityResult = document.getElementById('availability-result');
                    if (availabilityResult) {
                        availabilityResult.innerHTML = '';
                    }
                });
            });
        } else {
            timeSlotsContainer.innerHTML = '<p class="text-muted">No available time slots for this date</p>';
        }
    } catch (error) {
        console.error('Error loading time slots:', error);
        timeSlotsContainer.innerHTML = '<p class="text-muted">Error loading time slots. Please try again.</p>';
    }
}

/* ============================================
   ALTERNATIVE SLOTS
   ============================================ */
function initAlternativeSlots() {
    document.querySelectorAll('.alternative-item').forEach(item => {
        item.addEventListener('click', function() {
            const altDate = this.getAttribute('data-date');
            const altTime = this.getAttribute('data-time');
            const dateInput = document.getElementById('appointment_date');
            const timeInput = document.getElementById('appointment_time');
            
            if (dateInput) dateInput.value = altDate;
            if (timeInput) timeInput.value = altTime;
            
            // Reload time slots
            if (window.loadTimeSlots) {
                loadTimeSlots();
            }
            
            // Highlight the selected alternative
            setTimeout(() => {
                document.querySelectorAll('.time-slot').forEach(slot => {
                    if (slot.getAttribute('data-time') === altTime) {
                        slot.classList.add('selected');
                    }
                });
            }, 100);
            
            const availabilityResult = document.getElementById('availability-result');
            if (availabilityResult) {
                availabilityResult.innerHTML = '<div class="alert alert-success">Alternative time selected! You can now book this appointment.</div>';
            }
        });
    });
}

/* ============================================
   AVAILABILITY TOGGLES
   ============================================ */
function initAvailabilityToggles() {
    document.querySelectorAll('input[type="checkbox"][name*="_available"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const dayDiv = this.closest('.availability-day');
            const timesDiv = dayDiv.querySelector('.availability-times');
            if (timesDiv) {
                timesDiv.style.display = this.checked ? 'block' : 'none';
            }
        });
    });
}

/* ============================================
   PASSWORD STRENGTH
   ============================================ */
function initPasswordStrength() {
    const passwordInput = document.getElementById('new_password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.querySelector('.strength-bar');
            const strengthText = document.querySelector('.strength-text');
            
            if (!strengthBar || !strengthText) return;
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            const width = (strength / 5) * 100;
            strengthBar.style.width = width + '%';
            
            if (strength <= 2) {
                strengthBar.style.background = '#dc3545';
                strengthText.textContent = 'Weak password';
                strengthText.style.color = '#dc3545';
            } else if (strength <= 4) {
                strengthBar.style.background = '#ffc107';
                strengthText.textContent = 'Medium password';
                strengthText.style.color = '#ffc107';
            } else {
                strengthBar.style.background = '#28a745';
                strengthText.textContent = 'Strong password';
                strengthText.style.color = '#28a745';
            }
        });
    }
}

/* ============================================
   AVAILABILITY FUNCTIONS
   ============================================ */
function toggleDay(day) {
    const checkbox = document.querySelector(`input[name="day_${day}_available"]`);
    if (!checkbox) return;
    
    const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
    const startSelect = document.querySelector(`select[name="day_${day}_start"]`);
    const endSelect = document.querySelector(`select[name="day_${day}_end"]`);
    
    if (checkbox.checked) {
        if (timesDiv) timesDiv.style.display = 'block';
        if (startSelect) startSelect.disabled = false;
        if (endSelect) endSelect.disabled = false;
    } else {
        if (timesDiv) timesDiv.style.display = 'none';
        if (startSelect) startSelect.disabled = true;
        if (endSelect) endSelect.disabled = true;
    }
}

function setDefaultWeek() {
    // Set Monday to Friday (indices 1-5)
    for (let i = 1; i <= 5; i++) {
        const checkbox = document.querySelector(`input[name="day_${i}_available"]`);
        if (checkbox) {
            checkbox.checked = true;
            const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
            if (timesDiv) timesDiv.style.display = 'block';
            
            const startSelect = document.querySelector(`select[name="day_${i}_start"]`);
            if (startSelect) {
                startSelect.value = '09:00';
                startSelect.disabled = false;
            }
            
            const endSelect = document.querySelector(`select[name="day_${i}_end"]`);
            if (endSelect) {
                endSelect.value = '17:00';
                endSelect.disabled = false;
            }
        }
    }
    
    // Uncheck Saturday and Sunday (indices 0 and 6)
    for (let i of [0, 6]) {
        const checkbox = document.querySelector(`input[name="day_${i}_available"]`);
        if (checkbox) {
            checkbox.checked = false;
            const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
            if (timesDiv) timesDiv.style.display = 'none';
            
            const startSelect = document.querySelector(`select[name="day_${i}_start"]`);
            const endSelect = document.querySelector(`select[name="day_${i}_end"]`);
            if (startSelect) startSelect.disabled = true;
            if (endSelect) endSelect.disabled = true;
        }
    }
}

function clearAll() {
    if (confirm('Are you sure you want to clear all availability settings?')) {
        for (let i = 0; i <= 6; i++) {
            const checkbox = document.querySelector(`input[name="day_${i}_available"]`);
            if (checkbox) {
                checkbox.checked = false;
                const timesDiv = checkbox.closest('.availability-day').querySelector('.availability-times');
                if (timesDiv) timesDiv.style.display = 'none';
                
                const startSelect = document.querySelector(`select[name="day_${i}_start"]`);
                const endSelect = document.querySelector(`select[name="day_${i}_end"]`);
                if (startSelect) startSelect.disabled = true;
                if (endSelect) endSelect.disabled = true;
            }
        }
    }
}

/* ============================================
   MODAL FUNCTIONS
   ============================================ */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

// Generic close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList && event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

/* ============================================
   BILLING FUNCTIONS
   ============================================ */
function calculateTotal() {
    const amount = parseFloat(document.getElementById('amount')?.value) || 0;
    const tax = parseFloat(document.getElementById('tax')?.value) || 0;
    const discount = parseFloat(document.getElementById('discount')?.value) || 0;
    const total = amount + tax - discount;
    const totalField = document.getElementById('total_amount');
    if (totalField) {
        totalField.value = '$' + total.toFixed(2);
    }
}

/* ============================================
   NOTIFICATION FUNCTIONS
   ============================================ */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
        <button class="close-alert">&times;</button>
    `;
    
    const container = document.querySelector('main .container');
    if (container) {
        container.insertBefore(notification, container.firstChild);
        
        notification.querySelector('.close-alert').addEventListener('click', () => {
            notification.remove();
        });
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}

/* ============================================
   CHECK AVAILABILITY
   ============================================ */
async function checkAvailability() {
    const doctorId = document.getElementById('doctor_id')?.value;
    const date = document.getElementById('appointment_date')?.value;
    const time = document.getElementById('appointment_time')?.value;
    
    if (!doctorId || !date || !time) return;
    
    const datetime = `${date} ${time}`;
    const resultContainer = document.getElementById('availability-result');
    
    if (resultContainer) {
        resultContainer.innerHTML = '<div class="loading">Checking availability...</div>';
        
        try {
            const response = await fetch(`ajax/check-availability.php?doctor_id=${doctorId}&datetime=${encodeURIComponent(datetime)}`);
            const data = await response.json();
            
            if (data.available) {
                resultContainer.innerHTML = '<div class="alert alert-success">✓ Time slot is available!</div>';
            } else if (data.alternatives && data.alternatives.length > 0) {
                let html = '<div class="alternative-slots">';
                html += '<h4><i class="fas fa-clock"></i> Suggested Alternative Times:</h4>';
                html += '<div class="alternative-list">';
                data.alternatives.forEach(alt => {
                    html += `<div class="alternative-item" data-date="${alt.date}" data-time="${alt.time_value}">
                                ${alt.date_formatted} at ${alt.time}
                            </div>`;
                });
                html += '</div></div>';
                resultContainer.innerHTML = html;
                
                // Re-initialize alternative item handlers
                document.querySelectorAll('.alternative-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const dateInput = document.getElementById('appointment_date');
                        const timeInput = document.getElementById('appointment_time');
                        if (dateInput) dateInput.value = this.getAttribute('data-date');
                        if (timeInput) timeInput.value = this.getAttribute('data-time');
                        checkAvailability();
                    });
                });
            } else {
                resultContainer.innerHTML = '<div class="alert alert-error">✗ This time slot is not available. Please select another time.</div>';
            }
        } catch (error) {
            console.error('Error checking availability:', error);
            resultContainer.innerHTML = '<div class="alert alert-error">Error checking availability. Please try again.</div>';
        }
    }
}

/* ============================================
   PRINT FUNCTIONS
   ============================================ */
function printPrescription() {
    const printContent = document.getElementById('prescription-details');
    if (!printContent) return;
    
    const originalTitle = document.title;
    document.title = 'Prescription Details';
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Prescription Details</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .prescription-detail-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
                h4 { color: #1a75bc; margin-bottom: 10px; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
                .status-active { background: #d4edda; color: #155724; }
                @media print {
                    body { margin: 0; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>
            <h2>Prescription Details</h2>
            ${printContent.innerHTML}
            <p style="margin-top: 30px; font-size: 12px; color: #666; text-align: center;">
                Generated on ${new Date().toLocaleString()}
            </p>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

/* ============================================
   FORMATTING FUNCTIONS
   ============================================ */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

function formatDateTime(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

/* ============================================
   DEBOUNCE FUNCTION
   ============================================ */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/* ============================================
   API CALL FUNCTION
   ============================================ */
async function apiCall(endpoint, data = {}, method = 'POST') {
    try {
        const response = await fetch(endpoint, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: method !== 'GET' ? JSON.stringify(data) : null
        });
        
        return await response.json();
    } catch (error) {
        console.error('API call failed:', error);
        return { success: false, error: 'Network error' };
    }
}

/* ============================================
   BACK TO TOP
   ============================================ */
document.getElementById('backToTop')?.addEventListener('click', function(e) {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

/* ============================================
   EXPORT FUNCTIONS FOR GLOBAL USE
   ============================================ */
window.HealthManagement = {
    showNotification,
    formatDate,
    formatDateTime,
    apiCall,
    debounce,
    checkAvailability,
    loadTimeSlots,
    calculateTotal,
    toggleDay,
    setDefaultWeek,
    clearAll,
    openModal,
    closeModal,
    printPrescription
};