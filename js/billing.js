/* ============================================ */
/* BILLING SYSTEM - UNIFIED JAVASCRIPT          */
/* ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initBillCalculator();
});

function initBillCalculator() {
    const consultationInput = document.getElementById('consultation_fee');
    const additionalInput = document.getElementById('additional_charges');
    
    if (!consultationInput) return;
    
    // Calculate function
    window.calculateBillTotal = function() {
        const consultationFee = parseFloat(consultationInput.value) || 0;
        const additionalCharges = parseFloat(additionalInput?.value) || 0;
        
        const subtotal = consultationFee + additionalCharges;
        const serviceCharge = subtotal * 0.03;
        const gst = subtotal * 0.13;
        const total = subtotal + serviceCharge + gst;
        
        // Update display elements
        updateElement('display_consultation', consultationFee.toFixed(2));
        updateElement('display_additional', additionalCharges.toFixed(2));
        updateElement('display_subtotal', subtotal.toFixed(2));
        updateElement('display_service', serviceCharge.toFixed(2));
        updateElement('display_gst', gst.toFixed(2));
        updateElement('display_total', total.toFixed(2));
        
        // Update hidden input if exists
        const totalInput = document.getElementById('total_amount');
        if (totalInput) {
            totalInput.value = total.toFixed(2);
        }
        
        // Update readonly text input if exists
        const totalDisplay = document.getElementById('total_amount_display');
        if (totalDisplay) {
            totalDisplay.value = '$' + total.toFixed(2);
        }
        
        return total;
    };
    
    // Helper function to safely update element
    function updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }
    
    // Attach event listeners
    consultationInput.addEventListener('input', window.calculateBillTotal);
    if (additionalInput) {
        additionalInput.addEventListener('input', window.calculateBillTotal);
    }
    
    // Initial calculation
    window.calculateBillTotal();
    
    // Form validation
    const billForm = document.getElementById('bill-form');
    if (billForm) {
        billForm.addEventListener('submit', function(e) {
            const consultationFee = parseFloat(consultationInput.value) || 0;
            
            if (consultationFee <= 0) {
                e.preventDefault();
                alert('Please enter a valid consultation fee greater than 0.');
                return false;
            }
            
            const total = window.calculateBillTotal();
            if (total <= 0) {
                e.preventDefault();
                alert('Total amount must be greater than 0.');
                return false;
            }
            
            if (!confirm('Create bill for $' + total.toFixed(2) + '?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
}

// For dynamic forms (like consultation page)
window.updateBillSummary = function() {
    if (typeof window.calculateBillTotal === 'function') {
        return window.calculateBillTotal();
    }
    
    // Fallback calculation for consultation page
    const consultationFee = parseFloat(document.getElementById('consultation_fee_hidden')?.value) || 
                           parseFloat(document.getElementById('consultation_fee')?.value) || 0;
    
    let additionalTotal = 0;
    const additionalChargesList = document.getElementById('additional-charges-list');
    
    if (additionalChargesList) {
        additionalChargesList.innerHTML = '';
        document.querySelectorAll('.charge-row').forEach(row => {
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
    if (element) {
        element.textContent = value;
    }
}