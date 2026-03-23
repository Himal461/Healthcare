document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize form validation
    initFormValidation();
    
    // Initialize interactive elements
    initInteractiveElements();
    
    // Auto-dismiss alerts
    autoDismissAlerts();
    
    // Initialize date pickers
    initDatePickers();
});

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
    tooltip.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        z-index: 1000;
        white-space: nowrap;
    `;
    
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
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fix the errors in the form.', 'error');
            } else {
                // Add loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
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
    error.style.cssText = 'color: #dc3545; font-size: 12px; margin-top: 5px;';
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
            if (!e.target.matches('a, button, input')) {
                this.classList.toggle('selected');
            }
        });
    });
    
    // Time slot selection
    document.querySelectorAll('.time-slot').forEach(slot => {
        slot.addEventListener('click', function() {
            document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');
            const timeInput = document.getElementById('appointment_time');
            if (timeInput) {
                timeInput.value = this.getAttribute('data-time');
            }
        });
    });
    
    // Alternative slot selection
    document.querySelectorAll('.alternative-item').forEach(item => {
        item.addEventListener('click', function() {
            const date = this.getAttribute('data-date');
            const time = this.getAttribute('data-time');
            const dateInput = document.getElementById('appointment_date');
            const timeInput = document.getElementById('appointment_time');
            
            if (dateInput) dateInput.value = date;
            if (timeInput) timeInput.value = time;
            
            // Submit the form
            const form = document.getElementById('appointment-form');
            if (form) form.submit();
        });
    });
}

function autoDismissAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(-100%)';
            alert.style.transition = 'all 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

function initDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.valueAsDate = new Date();
        }
        
        input.addEventListener('change', function() {
            if (this.id === 'appointment_date') {
                loadTimeSlots(this.value);
            }
        });
    });
}

async function loadTimeSlots(date) {
    const doctorSelect = document.getElementById('doctor_id');
    if (!doctorSelect || !doctorSelect.value) return;
    
    const timeSlotContainer = document.getElementById('time-slots-container');
    if (!timeSlotContainer) return;
    
    timeSlotContainer.innerHTML = '<div class="loading">Loading available times...</div>';
    
    try {
        const response = await fetch(`ajax/get-time-slots.php?doctor_id=${doctorSelect.value}&date=${date}`);
        const data = await response.json();
        
        if (data.success && data.slots.length > 0) {
            let html = '<div class="time-slots">';
            data.slots.forEach(slot => {
                html += `<div class="time-slot" data-time="${slot.value}">${slot.start}</div>`;
            });
            html += '</div>';
            timeSlotContainer.innerHTML = html;
            
            // Re-initialize time slot click handlers
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    const timeInput = document.getElementById('appointment_time');
                    if (timeInput) {
                        timeInput.value = this.getAttribute('data-time');
                    }
                });
            });
        } else {
            timeSlotContainer.innerHTML = '<p class="alert alert-warning">No available time slots for this date. Please select another date.</p>';
        }
    } catch (error) {
        console.error('Error loading time slots:', error);
        timeSlotContainer.innerHTML = '<p class="alert alert-error">Error loading time slots. Please try again.</p>';
    }
}

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

// Export functions for use in other scripts
window.HealthManagement = {
    showNotification,
    formatDate,
    formatDateTime,
    apiCall,
    debounce,
    checkAvailability,
    loadTimeSlots
};

// Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
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
});