/* ============================================ */
/* DOCTOR MODULE - UNIFIED JAVASCRIPT           */
/* ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initDoctorModals();
    initAvailabilityToggles();
    initConsultationForm();
    initTimeSlotLoaders();
});

/* ============================================ */
/* MODAL FUNCTIONS                             */
/* ============================================ */
function initDoctorModals() {
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'flex';
    };
    
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = 'none';
    };
    
    window.onclick = function(event) {
        if (event.target.classList && event.target.classList.contains('doctor-modal')) {
            event.target.style.display = 'none';
        }
    };
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.doctor-modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
}

/* ============================================ */
/* AVAILABILITY TOGGLES                        */
/* ============================================ */
function initAvailabilityToggles() {
    // Initialize all toggles on page load
    for (let i = 0; i <= 6; i++) {
        toggleDay(i);
    }
}

window.toggleDay = function(day) {
    const checkbox = document.querySelector(`input[name="day_${day}_available"]`);
    if (!checkbox) return;
    
    const dayDiv = checkbox.closest('.doctor-availability-day');
    const timesDiv = dayDiv.querySelector('.doctor-availability-times');
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
};

/* ============================================ */
/* CONSULTATION FORM                           */
/* ============================================ */
function initConsultationForm() {
    if (document.getElementById('consultation_fee_hidden')) {
        updateBillSummary();
    }
}

window.addMedication = function() {
    const container = document.getElementById('medications');
    if (!container) return;
    
    const div = document.createElement('div');
    div.className = 'doctor-med-row';
    div.innerHTML = `
        <div class="doctor-med-fields">
            <input name="medication_name[]" class="doctor-form-control" placeholder="Medication Name">
            <input name="dosage[]" class="doctor-form-control" placeholder="Dosage">
            <input name="frequency[]" class="doctor-form-control" placeholder="Frequency">
            <input name="start_date[]" type="date" class="doctor-form-control" value="${new Date().toISOString().split('T')[0]}">
            <input name="instructions[]" class="doctor-form-control" placeholder="Instructions">
        </div>
        <button type="button" class="doctor-btn-delete" onclick="removeRow(this)" title="Remove medication">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    container.appendChild(div);
};

window.addCharge = function() {
    const container = document.getElementById('charges');
    if (!container) return;
    
    const div = document.createElement('div');
    div.className = 'doctor-charge-row';
    div.innerHTML = `
        <div class="doctor-charge-fields">
            <input name="charge_name[]" class="doctor-form-control" placeholder="Charge Name" oninput="updateBillSummary()">
            <input name="charge_amount[]" type="number" step="0.01" class="doctor-form-control" placeholder="Amount ($)" oninput="updateBillSummary()">
        </div>
        <button type="button" class="doctor-btn-delete" onclick="removeRow(this)" title="Remove charge">
            <i class="fas fa-trash-alt"></i>
        </button>
    `;
    container.appendChild(div);
    updateBillSummary();
};

window.removeRow = function(btn) {
    btn.closest('.doctor-med-row, .doctor-charge-row').remove();
    updateBillSummary();
};

window.updateBillSummary = function() {
    const consultationFee = parseFloat(document.getElementById('consultation_fee_hidden')?.value) || 0;
    let additionalTotal = 0;
    const additionalChargesList = document.getElementById('additional-charges-list');
    
    if (additionalChargesList) {
        additionalChargesList.innerHTML = '';
        document.querySelectorAll('.doctor-charge-row').forEach(row => {
            const chargeName = row.querySelector('input[name="charge_name[]"]')?.value || '';
            const chargeAmount = parseFloat(row.querySelector('input[name="charge_amount[]"]')?.value) || 0;
            if (chargeAmount > 0 && chargeName) {
                additionalTotal += chargeAmount;
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${escapeHtml(chargeName)}:</td><td class="text-right">$${chargeAmount.toFixed(2)}</td>`;
                additionalChargesList.appendChild(tr);
            }
        });
    }
    
    const subtotal = consultationFee + additionalTotal;
    const service = subtotal * 0.03;
    const gst = subtotal * 0.13;
    const total = subtotal + service + gst;
    
    updateElement('subtotal', subtotal.toFixed(2));
    updateElement('service', service.toFixed(2));
    updateElement('gst', gst.toFixed(2));
    updateElement('total', total.toFixed(2));
    
    return total;
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
}

/* ============================================ */
/* TIME SLOT LOADERS                           */
/* ============================================ */
function initTimeSlotLoaders() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const timeSlotsContainer = document.getElementById('time-slots-container');
    const timeInput = document.getElementById('appointment_time');
    
    if (doctorSelect && dateInput && timeSlotsContainer) {
        const loadSlots = () => loadTimeSlots(doctorSelect, dateInput, timeSlotsContainer, timeInput);
        doctorSelect.addEventListener('change', loadSlots);
        dateInput.addEventListener('change', loadSlots);
        
        if (dateInput && !dateInput.value) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
        }
        
        if (doctorSelect.value && dateInput.value) {
            loadSlots();
        }
    }
}

async function loadTimeSlots(doctorSelect, dateInput, timeSlotsContainer, timeInput) {
    const doctorId = doctorSelect.value;
    const date = dateInput.value;
    
    if (!doctorId || !date) {
        timeSlotsContainer.innerHTML = '<p class="doctor-text-muted">Please select a doctor and date first</p>';
        return;
    }
    
    timeSlotsContainer.innerHTML = '<p class="doctor-text-muted"><i class="fas fa-spinner fa-spin"></i> Loading available times...</p>';
    
    try {
        const response = await fetch(`../ajax/get-time-slots.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}`);
        const data = await response.json();
        
        if (data.success && data.slots && data.slots.length > 0) {
            let html = '<div class="doctor-time-slots">';
            data.slots.forEach(slot => {
                html += `<div class="doctor-time-slot" data-time="${slot.value}">${slot.display || slot.start}</div>`;
            });
            html += '</div>';
            timeSlotsContainer.innerHTML = html;
            
            document.querySelectorAll('.doctor-time-slot').forEach(slot => {
                slot.addEventListener('click', function() {
                    document.querySelectorAll('.doctor-time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    if (timeInput) {
                        timeInput.value = this.getAttribute('data-time');
                    }
                });
            });
            
            if (timeInput && timeInput.value) {
                document.querySelectorAll('.doctor-time-slot').forEach(slot => {
                    if (slot.getAttribute('data-time') === timeInput.value) {
                        slot.classList.add('selected');
                    }
                });
            }
        } else {
            const message = data.message || 'No available time slots for this date.';
            timeSlotsContainer.innerHTML = `<p class="doctor-text-muted">${message}</p>`;
            if (timeInput) timeInput.value = '';
        }
    } catch (error) {
        console.error('Error loading time slots:', error);
        timeSlotsContainer.innerHTML = '<p class="doctor-text-danger">Error loading time slots. Please try again.</p>';
    }
}

/* ============================================ */
/* VIEW FUNCTIONS                              */
/* ============================================ */
window.viewRecord = function(recordId) {
    window.location.href = `medical-records-view.php?id=${recordId}`;
};

window.viewPrescription = function(prescriptionId) {
    window.location.href = `prescriptions.php?prescription_id=${prescriptionId}`;
};

window.viewTestDetails = function(testId) {
    window.location.href = `lab-test-details.php?id=${testId}`;
};

/* ============================================ */
/* STATUS MODAL                                */
/* ============================================ */
window.openStatusModal = function(appointmentId, currentStatus) {
    const modal = document.getElementById('statusModal');
    if (!modal) return;
    
    document.getElementById('modal_appointment_id').value = appointmentId;
    document.getElementById('modal_status').value = currentStatus;
    openModal('statusModal');
};

/* ============================================ */
/* RESULT MODAL                                */
/* ============================================ */
window.openResultModal = function(testId) {
    const modal = document.getElementById('resultModal');
    if (!modal) return;
    
    document.getElementById('result_test_id').value = testId;
    openModal('resultModal');
};

/* ============================================ */
/* TIME SLOT STYLES                            */
/* ============================================ */
const timeSlotStyles = `
.doctor-time-slots {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
}
.doctor-time-slot {
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
.doctor-time-slot:hover {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
    transform: scale(1.05);
}
.doctor-time-slot.selected {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}
.doctor-text-danger {
    color: #ef4444;
    text-align: center;
    padding: 20px;
}
`;

if (!document.querySelector('#doctor-time-slot-styles')) {
    const style = document.createElement('style');
    style.id = 'doctor-time-slot-styles';
    style.textContent = timeSlotStyles;
    document.head.appendChild(style);
}