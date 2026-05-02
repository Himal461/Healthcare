/* ============================================ */
/* ADMIN MODULE - UNIFIED JAVASCRIPT            */
/* ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initAdminModals();
    initAdminAlerts();
    initAdminFilters();
});

/* ============================================ */
/* MODAL FUNCTIONS                             */
/* ============================================ */
function initAdminModals() {
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
        }
    };
    
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    };
    
    window.onclick = function(event) {
        if (event.target.classList && event.target.classList.contains('admin-modal')) {
            event.target.style.display = 'none';
        }
    };
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.admin-modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
}

/* ============================================ */
/* ALERT FUNCTIONS                             */
/* ============================================ */
function initAdminAlerts() {
    document.querySelectorAll('.close-alert').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.admin-alert').remove();
        });
    });
    
    setTimeout(() => {
        document.querySelectorAll('.admin-alert:not(.alert-dismissible)').forEach(alert => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(-100%)';
            alert.style.transition = 'all 0.3s ease';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
}

/* ============================================ */
/* FILTER FUNCTIONS                            */
/* ============================================ */
function initAdminFilters() {
    const monthSelector = document.querySelector('.admin-month-selector select');
    if (monthSelector) {
        monthSelector.addEventListener('change', function() {
            this.form.submit();
        });
    }
}

/* ============================================ */
/* TOGGLE ROLE FORM                            */
/* ============================================ */
window.toggleRoleForm = function() {
    const role = document.getElementById('staff_role')?.value || document.getElementById('role')?.value;
    
    const staffFields = document.getElementById('staff-fields');
    const doctorFields = document.getElementById('doctor-fields');
    const nurseFields = document.getElementById('nurse-fields');
    const adminFields = document.getElementById('admin-fields');
    const accountantFields = document.getElementById('accountant-fields');
    const patientFields = document.getElementById('patient-fields');
    
    if (staffFields) staffFields.style.display = 'none';
    if (doctorFields) doctorFields.style.display = 'none';
    if (nurseFields) nurseFields.style.display = 'none';
    if (adminFields) adminFields.style.display = 'none';
    if (accountantFields) accountantFields.style.display = 'none';
    if (patientFields) patientFields.style.display = 'block';
    
    if (role === 'doctor' || role === 'nurse' || role === 'staff' || role === 'admin' || role === 'accountant') {
        if (staffFields) staffFields.style.display = 'block';
    }
    
    if (role === 'doctor') {
        if (doctorFields) doctorFields.style.display = 'block';
        if (patientFields) patientFields.style.display = 'none';
    } else if (role === 'nurse') {
        if (nurseFields) nurseFields.style.display = 'block';
        if (patientFields) patientFields.style.display = 'none';
    } else if (role === 'admin') {
        if (adminFields) adminFields.style.display = 'block';
        if (patientFields) patientFields.style.display = 'none';
    } else if (role === 'accountant') {
        if (accountantFields) accountantFields.style.display = 'block';
        if (patientFields) patientFields.style.display = 'none';
    } else if (role === 'staff') {
        if (patientFields) patientFields.style.display = 'none';
    }
    
    const positionField = document.getElementById('position');
    if (positionField) {
        if (role === 'doctor') positionField.value = 'Doctor';
        else if (role === 'nurse') positionField.value = 'Nurse';
        else if (role === 'admin') positionField.value = 'Administrator';
        else if (role === 'accountant') positionField.value = 'Accountant';
        else if (role === 'staff') positionField.value = '';
    }
};

/* ============================================ */
/* OPEN EDIT STAFF MODAL                       */
/* ============================================ */
window.openEditStaffModal = function(staff) {
    document.getElementById('edit_staff_id').value = staff.staffId;
    document.getElementById('edit_user_id').value = staff.userId;
    document.getElementById('edit_staff_role').value = staff.role;
    document.getElementById('edit_first_name').value = staff.firstName || '';
    document.getElementById('edit_last_name').value = staff.lastName || '';
    document.getElementById('edit_email').value = staff.email || '';
    document.getElementById('edit_phone_number').value = staff.phoneNumber || '';
    document.getElementById('edit_license_number').value = staff.licenseNumber || '';
    document.getElementById('edit_department').value = staff.department || '';
    document.getElementById('edit_position').value = staff.position || '';
    document.getElementById('edit_salary').value = staff.salary || '2500.00';
    
    const doctorFields = document.getElementById('edit-doctor-fields');
    const nurseFields = document.getElementById('edit-nurse-fields');
    const accountantFields = document.getElementById('edit-accountant-fields');
    
    if (doctorFields) doctorFields.style.display = 'none';
    if (nurseFields) nurseFields.style.display = 'none';
    if (accountantFields) accountantFields.style.display = 'none';
    
    if (staff.role === 'doctor') {
        if (doctorFields) doctorFields.style.display = 'block';
        document.getElementById('edit_specialization').value = staff.specialization || '';
        document.getElementById('edit_consultation_fee').value = staff.consultationFee || '';
        document.getElementById('edit_years_of_experience').value = staff.yearsOfExperience || '';
        document.getElementById('edit_education').value = staff.education || '';
        document.getElementById('edit_biography').value = staff.biography || '';
        document.getElementById('edit_is_available').checked = staff.isAvailable == 1;
    } else if (staff.role === 'nurse') {
        if (nurseFields) nurseFields.style.display = 'block';
        document.getElementById('edit_nursing_specialty').value = staff.nursingSpecialty || '';
        document.getElementById('edit_certification').value = staff.certification || '';
    } else if (staff.role === 'accountant') {
        if (accountantFields) accountantFields.style.display = 'block';
        document.getElementById('edit_qualification').value = staff.qualification || '';
        document.getElementById('edit_accountant_certification').value = staff.accountant_cert || '';
        document.getElementById('edit_accountant_specialization').value = staff.accountant_specialization || 'General Accounting';
        document.getElementById('edit_accountant_experience').value = staff.accountant_experience || 0;
    }
    
    openModal('editStaffModal');
};

window.closeEditModal = function() {
    closeModal('editStaffModal');
};

/* ============================================ */
/* SYSTEM MAINTENANCE                          */
/* ============================================ */
window.clearCache = function() {
    if (confirm('Clear system cache?')) {
        fetch('../ajax/clear-cache.php', { method: 'POST' })
            .then(r => r.json())
            .then(d => { alert(d.success ? 'Cache cleared!' : 'Failed: ' + d.error); });
    }
};

window.optimizeDatabase = function() {
    if (confirm('Optimize database?')) {
        fetch('../ajax/optimize-db.php', { method: 'POST' })
            .then(r => r.json())
            .then(d => { alert(d.success ? 'Database optimized!' : 'Failed: ' + d.error); });
    }
};

window.backupDatabase = function() {
    if (confirm('Download backup?')) {
        window.location.href = '../ajax/backup-db.php';
    }
};

/* ============================================ */
/* PAYMENT MODAL                               */
/* ============================================ */
window.openPaymentModal = function(billId, amount) {
    document.getElementById('payment_bill_id').value = billId;
    document.getElementById('payment_amount').value = amount;
    document.getElementById('bill_amount_display').value = '$' + amount.toFixed(2);
    openModal('paymentModal');
};

/* ============================================ */
/* EXPORT REPORT                               */
/* ============================================ */
window.exportReport = function() {
    const type = document.querySelector('[name="report_type"]').value;
    const from = document.querySelector('[name="date_from"]').value;
    const to = document.querySelector('[name="date_to"]').value;
    window.location.href = `export-report.php?type=${type}&date_from=${from}&date_to=${to}`;
};

console.log('Admin JS loaded successfully');