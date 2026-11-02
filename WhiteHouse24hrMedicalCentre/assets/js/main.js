// assets/js/main.js

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.transition = 'opacity 1s';
            alert.style.opacity = '0';
            setTimeout(function() {
                if (alert && alert.parentNode) {
                    alert.remove();
                }
            }, 1000);
        });
    }, 5000);
});

// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    return strength;
}

// Confirm before delete
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this? This action cannot be undone.');
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

// Format time
function formatTime(timeString) {
    const date = new Date(`2000-01-01 ${timeString}`);
    return date.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ============================================
// MODAL FUNCTIONS
// ============================================

// Open the inpatient admission modal
function openAdmitModal(patientId, visitId) {
    const modal = document.getElementById('admitModal');
    if (modal) {
        // Set the patient ID if the field exists
        const patientField = document.getElementById('admit_patient_id');
        if (patientField) {
            patientField.value = patientId;
        }

        // Set the visit ID if the field exists
        const visitField = document.getElementById('admit_visit_id');
        if (visitField) {
            visitField.value = visitId;
        }

        // Show the modal
        modal.style.display = 'flex';
        modal.classList.add('active');

        // Prevent body scrolling
        document.body.style.overflow = 'hidden';
    }
}

// Close the inpatient admission modal
function closeAdmitModal() {
    const modal = document.getElementById('admitModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');

        // Re-enable body scrolling
        document.body.style.overflow = '';
    }
}

// Close modal when clicking outside the modal content
window.addEventListener('click', function(event) {
    const modal = document.getElementById('admitModal');
    if (modal && event.target === modal) {
        closeAdmitModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('admitModal');
        if (modal && modal.classList.contains('active')) {
            closeAdmitModal();
        }
    }
});

// ============================================
// ADD USER FORM TOGGLE (for users.php)
// ============================================

function showAddUserForm() {
    const form = document.getElementById('addUserForm');
    if (form) {
        form.style.display = 'block';
        form.scrollIntoView({ behavior: 'smooth' });
    }
}

function hideAddUserForm() {
    const form = document.getElementById('addUserForm');
    if (form) {
        form.style.display = 'none';
    }
}

// ============================================
// RESET PASSWORD (for users.php)
// ============================================

function resetPassword(userId, username) {
    const newPassword = prompt("Reset password for user: " + username + "\n\nEnter new password (minimum 6 characters):", "Password123");

    if (newPassword && newPassword.length >= 6) {
        const confirmPassword = prompt("Confirm new password for " + username + ":");

        if (confirmPassword === newPassword) {
            if (confirm("Reset password for '" + username + "'?\n\nNew password: " + newPassword)) {
                window.location.href = "?reset_password=" + userId + "&new_pass=" + encodeURIComponent(newPassword);
            }
        } else {
            alert("Passwords do not match!");
        }
    } else if (newPassword) {
        alert("Password must be at least 6 characters!");
    }
}

// ============================================
// DUPLICATE PATIENT CHECK (for register-patient.php)
// ============================================

let duplicateCheckTimer;

function checkForDuplicate() {
    clearTimeout(duplicateCheckTimer);

    duplicateCheckTimer = setTimeout(() => {
        const firstName = document.getElementById('first_name')?.value;
        const lastName = document.getElementById('last_name')?.value;
        const phone = document.getElementById('phone')?.value;

        if ((firstName && firstName.length > 2) || (lastName && lastName.length > 2) || (phone && phone.length > 5)) {
            fetch(`check-duplicate-patient.php?first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}&phone=${encodeURIComponent(phone)}`)
                .then(response => response.json())
                .then(data => {
                    const alertDiv = document.getElementById('duplicateAlert');
                    const messageSpan = document.getElementById('duplicateMessage');

                    if (data.found && data.patients && data.patients.length > 0) {
                        let html = `<strong>⚠️ Potential duplicate found!</strong> This patient appears to have visited before.<br><br>`;
                        html += `<div class="existing-records-title"><strong>Existing records:</strong></div>`;
                        html += `<ul class="existing-records-list">`;
                        data.patients.forEach(p => {
                            html += `<li>${p.patient_number} - ${p.first_name} ${p.last_name} (${p.phone || 'No phone'}) - ${p.visit_count} visit(s)</li>`;
                        });
                        html += `</ul>`;
                        html += `<div class="quick-register-link">
                                    <a href="register-patient.php?quick=${data.patients[0].patient_id}" class="btn btn-small btn-success">Click here to quick check-in this returning patient</a>
                                </div>`;
                        messageSpan.innerHTML = html;
                        alertDiv.style.display = 'block';
                    } else {
                        alertDiv.style.display = 'none';
                    }
                })
                .catch(err => console.log('Duplicate check error:', err));
        }
    }, 800);
}

// ============================================
// PATIENT SEARCH (for admit-patient.php)
// ============================================

function setupPatientSearch() {
    const searchInput = document.getElementById('patient_search');
    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const search = this.value.trim();
        const resultsDiv = document.getElementById('search_results');

        if (!resultsDiv) return;

        if (search.length < 2) {
            resultsDiv.innerHTML = '';
            return;
        }

        fetch(`search-patients-ajax.php?q=${encodeURIComponent(search)}`)
            .then(response => response.json())
            .then(data => {
                let html = '';
                if (data.length > 0) {
                    data.forEach(patient => {
                        html += `<div class="search-result-item" onclick="selectExistingPatient(${patient.patient_id}, '${patient.first_name} ${patient.last_name}')">
                            <strong>${patient.patient_number}</strong> - ${patient.first_name} ${patient.last_name} (${patient.phone || 'No phone'})
                        </div>`;
                    });
                } else {
                    html = '<div class="search-result-item" style="cursor: default; color: #7f8c8d;">No existing patient found. Fill in the form below to register new.</div>';
                }
                resultsDiv.innerHTML = html;
            })
            .catch(err => console.log('Patient search error:', err));
    });
}

function selectExistingPatient(id, name) {
    const existingPatientId = document.getElementById('existing_patient_id');
    const searchInput = document.getElementById('patient_search');
    const newPatientFields = document.getElementById('new_patient_fields');
    const resultsDiv = document.getElementById('search_results');

    if (existingPatientId) existingPatientId.value = id;
    if (searchInput) searchInput.value = name;
    if (resultsDiv) resultsDiv.innerHTML = '';
    if (newPatientFields) newPatientFields.style.opacity = '0.5';

    // Mark required fields as not required since we're using existing patient
    if (newPatientFields) {
        const requiredFields = newPatientFields.querySelectorAll('[required]');
        requiredFields.forEach(field => field.removeAttribute('required'));
    }
}

// Reset new patient fields when search is cleared
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('patient_search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            if (this.value === '') {
                const existingPatientId = document.getElementById('existing_patient_id');
                const newPatientFields = document.getElementById('new_patient_fields');

                if (existingPatientId) existingPatientId.value = '';
                if (newPatientFields) {
                    newPatientFields.style.opacity = '1';
                    // Re-add required attributes
                    const fields = newPatientFields.querySelectorAll('input, select, textarea');
                    fields.forEach(field => {
                        if (field.hasAttribute('data-required')) {
                            field.setAttribute('required', '');
                        }
                    });
                }
            }
        });
    }

    // Initialize patient search for admit page
    setupPatientSearch();
});

// ============================================
// INITIALIZATION ON PAGE LOAD
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Auto-capitalize first letter of name fields
    const nameFields = document.querySelectorAll('input[name="first_name"], input[name="last_name"]');
    nameFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value) {
                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1).toLowerCase();
            }
        });
    });
});
