/* ============================================ */
/* APPOINTMENT BOOKING JAVASCRIPT - FIXED       */
/* ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initAppointmentBooking();
});

function initAppointmentBooking() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput = document.getElementById('appointment_time');
    const form = document.getElementById('appointment-form');
    
    if (!doctorSelect || !dateInput || !timeSlotsContainer) return;
    
    // Set min date to today
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
        dateInput.value = today;
        
        // Set max date to 60 days from now
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 60);
        dateInput.setAttribute('max', maxDate.toISOString().split('T')[0]);
    }
    
    // Load time slots when doctor or date changes
    async function loadTimeSlots() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;
        
        if (!doctorId || !date) {
            timeSlotsContainer.innerHTML = '<p class="text-muted">Please select a doctor and date first</p>';
            if (timeInput) timeInput.value = '';
            return;
        }
        
        timeSlotsContainer.innerHTML = '<p class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading available times...</p>';
        
        try {
            const response = await fetch(`../ajax/get-time-slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.slots && data.slots.length > 0) {
                let html = '<div class="time-slots">';
                data.slots.forEach(slot => {
                    html += `<div class="time-slot" data-time="${slot.value}">${slot.display || slot.start}</div>`;
                });
                html += '</div>';
                timeSlotsContainer.innerHTML = html;
                
                // Add click handlers
                document.querySelectorAll('.time-slot').forEach(slot => {
                    slot.addEventListener('click', function() {
                        document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        if (timeInput) {
                            timeInput.value = this.getAttribute('data-time');
                        }
                    });
                });
                
                // If there's a pre-selected time, highlight it
                if (timeInput && timeInput.value) {
                    document.querySelectorAll('.time-slot').forEach(slot => {
                        if (slot.getAttribute('data-time') === timeInput.value) {
                            slot.classList.add('selected');
                        }
                    });
                }
            } else {
                const message = data.message || data.error || 'No available time slots for this date.';
                timeSlotsContainer.innerHTML = `<p class="text-danger">${message}</p>`;
                if (timeInput) timeInput.value = '';
            }
        } catch (error) {
            console.error('Error loading time slots:', error);
            timeSlotsContainer.innerHTML = '<p class="text-danger">Error loading time slots. Please try again.</p>';
            if (timeInput) timeInput.value = '';
        }
    }
    
    // Event listeners
    doctorSelect.addEventListener('change', function() {
        if (timeInput) timeInput.value = '';
        loadTimeSlots();
    });
    
    dateInput.addEventListener('change', function() {
        if (timeInput) timeInput.value = '';
        loadTimeSlots();
    });
    
    // Check if URL has doctor_id parameter
    const urlParams = new URLSearchParams(window.location.search);
    const doctorIdParam = urlParams.get('doctor_id');
    if (doctorIdParam && doctorSelect) {
        doctorSelect.value = doctorIdParam;
    }
    
    // Initial load if both are selected
    if (doctorSelect.value && dateInput.value) {
        loadTimeSlots();
    }
    
    // Form validation
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!timeInput || !timeInput.value) {
                e.preventDefault();
                alert('Please select a time slot for your appointment.');
                return false;
            }
            
            if (!confirm('Confirm this appointment booking?')) {
                e.preventDefault();
                return false;
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            }
            
            return true;
        });
    }
}

// Add time slot styles dynamically
const timeSlotStyles = `
.time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
}
.time-slot {
    padding: 12px 8px;
    background: white;
    border: 2px solid #cbd5e1;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 15px;
    font-weight: 600;
    color: #334155;
}
.time-slot:hover {
    background: #1a75bc;
    color: white;
    border-color: #1a75bc;
    transform: scale(1.05);
}
.time-slot.selected {
    background: #1a75bc;
    color: white;
    border-color: #1a75bc;
    box-shadow: 0 4px 12px rgba(26, 117, 188, 0.3);
}
.text-muted {
    color: #64748b;
    text-align: center;
    padding: 20px;
}
.text-danger {
    color: #ef4444;
    text-align: center;
    padding: 20px;
}
`;

if (!document.querySelector('#time-slot-styles')) {
    const style = document.createElement('style');
    style.id = 'time-slot-styles';
    style.textContent = timeSlotStyles;
    document.head.appendChild(style);
}

/* ============================================ */
/* APPOINTMENT BOOKING JAVASCRIPT - ENHANCED    */
/* ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initAppointmentBooking();
});

function initAppointmentBooking() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput = document.getElementById('appointment_time');
    const form = document.getElementById('appointment-form');
    const closestInfo = document.getElementById('closest-appointment-info');
    const closestText = document.getElementById('closest-appointment-text');
    const useClosestBtn = document.getElementById('use-closest-appointment');
    
    if (!doctorSelect || !dateInput || !timeSlotsContainer) return;
    
    let currentSlots = [];
    let closestSlot = null;
    
    // Set min date to today
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
        if (!dateInput.value) {
            dateInput.value = today;
        }
        
        const maxDate = new Date();
        maxDate.setDate(maxDate.getDate() + 60);
        dateInput.setAttribute('max', maxDate.toISOString().split('T')[0]);
    }
    
    // Function to find closest available appointment
    async function findClosestAppointment(doctorId) {
        if (!doctorId) return null;
        
        try {
            const response = await fetch(`../ajax/get-closest-appointment.php?doctor_id=${encodeURIComponent(doctorId)}`);
            const data = await response.json();
            
            if (data.success && data.closest) {
                return data.closest;
            }
            return null;
        } catch (error) {
            console.error('Error finding closest appointment:', error);
            return null;
        }
    }
    
    // Function to load time slots
    async function loadTimeSlots() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;
        
        if (!doctorId || !date) {
            timeSlotsContainer.innerHTML = '<p class="patient-text-muted">Please select a doctor and date first</p>';
            if (timeInput) timeInput.value = '';
            return;
        }
        
        timeSlotsContainer.innerHTML = '<p class="patient-text-muted"><i class="fas fa-spinner fa-spin"></i> Loading available times...</p>';
        
        try {
            const response = await fetch(`../ajax/get-time-slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.slots && data.slots.length > 0) {
                currentSlots = data.slots;
                renderTimeSlots(data.slots);
            } else {
                currentSlots = [];
                const message = data.message || data.error || 'No available time slots for this date.';
                timeSlotsContainer.innerHTML = `<p class="patient-text-muted">${message}</p>`;
                if (timeInput) timeInput.value = '';
            }
        } catch (error) {
            console.error('Error loading time slots:', error);
            timeSlotsContainer.innerHTML = '<p class="patient-text-danger">Error loading time slots. Please try again.</p>';
            if (timeInput) timeInput.value = '';
        }
    }
    
    function renderTimeSlots(slots) {
        let html = '<div class="patient-time-slots">';
        slots.forEach(slot => {
            html += `<div class="patient-time-slot" data-time="${slot.value}">${slot.display || slot.start}</div>`;
        });
        html += '</div>';
        timeSlotsContainer.innerHTML = html;
        
        // Add click handlers
        document.querySelectorAll('.patient-time-slot').forEach(slot => {
            slot.addEventListener('click', function() {
                document.querySelectorAll('.patient-time-slot').forEach(s => s.classList.remove('selected'));
                this.classList.add('selected');
                if (timeInput) {
                    timeInput.value = this.getAttribute('data-time');
                }
            });
        });
        
        // If there's a pre-selected time, highlight it
        if (timeInput && timeInput.value) {
            document.querySelectorAll('.patient-time-slot').forEach(slot => {
                if (slot.getAttribute('data-time') === timeInput.value) {
                    slot.classList.add('selected');
                }
            });
        }
    }
    
    // Update closest appointment display
    async function updateClosestAppointment() {
        const doctorId = doctorSelect?.value;
        if (!doctorId) {
            if (closestInfo) closestInfo.style.display = 'none';
            return;
        }
        
        if (closestInfo) closestInfo.style.display = 'block';
        if (closestText) closestText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Finding earliest available appointment...';
        
        closestSlot = await findClosestAppointment(doctorId);
        
        if (closestSlot) {
            const dateObj = new Date(closestSlot.date + 'T' + closestSlot.time);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric' 
            });
            const formattedTime = new Date('2000-01-01T' + closestSlot.time).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit'
            });
            
            if (closestText) {
                closestText.innerHTML = `
                    <i class="fas fa-calendar-check"></i> 
                    <strong>${formattedDate}</strong> at <strong>${formattedTime}</strong> 
                    with Dr. ${closestSlot.doctor_name || ''}
                `;
            }
            if (useClosestBtn) useClosestBtn.style.display = 'inline-block';
        } else {
            if (closestText) {
                closestText.innerHTML = '<i class="fas fa-calendar-times"></i> No available appointments found in the next 60 days.';
            }
            if (useClosestBtn) useClosestBtn.style.display = 'none';
        }
    }
    
    // Event listeners
    doctorSelect.addEventListener('change', function() {
        if (timeInput) timeInput.value = '';
        loadTimeSlots();
        updateClosestAppointment();
    });
    
    dateInput.addEventListener('change', function() {
        if (timeInput) timeInput.value = '';
        loadTimeSlots();
    });
    
    // Use closest appointment button
    if (useClosestBtn) {
        useClosestBtn.addEventListener('click', function() {
            if (closestSlot) {
                dateInput.value = closestSlot.date;
                timeInput.value = closestSlot.time;
                loadTimeSlots();
                
                // Scroll to time slots
                timeSlotsContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight after load
                setTimeout(() => {
                    document.querySelectorAll('.patient-time-slot').forEach(slot => {
                        if (slot.getAttribute('data-time') === closestSlot.time) {
                            slot.classList.add('selected');
                        }
                    });
                }, 500);
            }
        });
    }
    
    // Check URL params
    const urlParams = new URLSearchParams(window.location.search);
    const doctorIdParam = urlParams.get('doctor_id');
    if (doctorIdParam && doctorSelect) {
        doctorSelect.value = doctorIdParam;
        loadTimeSlots();
        updateClosestAppointment();
    }
    
    // Initial closest appointment check
    if (doctorSelect?.value) {
        updateClosestAppointment();
    }
    
    // Form validation
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!timeInput || !timeInput.value) {
                e.preventDefault();
                alert('Please select a time slot for your appointment.');
                return false;
            }
            
            if (!confirm('Confirm this appointment booking?')) {
                e.preventDefault();
                return false;
            }
            
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            }
            
            return true;
        });
    }
}

// Add time slot styles dynamically
const timeSlotStyles = `
.patient-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
}
.patient-time-slot {
    padding: 12px 8px;
    background: white;
    border: 2px solid #cbd5e1;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 15px;
    font-weight: 600;
    color: #334155;
}
.patient-time-slot:hover {
    background: #0d9488;
    color: white;
    border-color: #0d9488;
    transform: scale(1.05);
}
.patient-time-slot.selected {
    background: #0d9488;
    color: white;
    border-color: #0d9488;
    box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
}
.patient-text-muted {
    color: #64748b;
    text-align: center;
    padding: 20px;
}
.patient-text-danger {
    color: #ef4444;
    text-align: center;
    padding: 20px;
}
`;

if (!document.querySelector('#patient-time-slot-styles')) {
    const style = document.createElement('style');
    style.id = 'patient-time-slot-styles';
    style.textContent = timeSlotStyles;
    document.head.appendChild(style);
}