// validation.js - Client-side validation with email and student registration number availability check

let emailAvailable = true; // updated by async check
let regNumberAvailable = true; // updated by async check

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePassword(password) {
    // At least 8 characters, one uppercase, one lowercase, one number
    const re = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$/;
    return re.test(password);
}

async function checkEmailExists(email) {
    try {
        const res = await fetch(`api/check_email.php?email=${encodeURIComponent(email)}`);
        const data = await res.json();
        if (data && typeof data.exists !== 'undefined') return data.exists;
        return false;
    } catch (e) {
        // on error assume not existing to avoid blocking users; server-side will always enforce
        return false;
    }
}

async function checkStudentRegistrationAvailable(student_registration_number) {
    try {
        const res = await fetch(`api/check_citizenship.php?student_registration_number=${encodeURIComponent(student_registration_number)}`);
        const data = await res.json();
        return data;
    } catch (e) {
        // on error, return neutral response; server-side will always enforce
        return { exists: false, reason: null };
    }
}

function validateForm(form) {
    let isValid = true;
    const errors = [];

    // Check student registration number
    const regNumberInput = form.querySelector('input[name="student_registration_number"]');
    if (regNumberInput) {
        if (!regNumberInput.value.trim()) {
            errors.push('Student registration number is required.');
            isValid = false;
        } else if (!regNumberAvailable) {
            errors.push('Student registration number is not available.');
            isValid = false;
        }
    }

    // Check email
    const email = form.querySelector('input[type="email"]');
    if (email) {
        if (!validateEmail(email.value)) {
            errors.push('Invalid email format.');
            isValid = false;
        } else if (!emailAvailable) {
            errors.push('Email already registered.');
            isValid = false;
        }
    }

    // Check password
    const password = form.querySelector('input[name="password"]');
    if (password && !validatePassword(password.value)) {
        errors.push('Password must be at least 8 characters with uppercase, lowercase, and number.');
        isValid = false;
    }

    // Check confirm password
    const confirmPassword = form.querySelector('input[name="confirm_password"]');
    if (confirmPassword && password && confirmPassword.value !== password.value) {
        errors.push('Passwords do not match.');
        isValid = false;
    }

    if (!isValid) {
        alert(errors.join('\n'));
    }

    return isValid;
}

// Attach to forms and email input
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
    });

    const emailInput = document.getElementById('email');
    const status = document.getElementById('email_status');
    if (emailInput && status) {
        // On blur, check availability
        emailInput.addEventListener('blur', async function() {
            const val = emailInput.value.trim();
            if (!val) return;
            if (!validateEmail(val)) {
                status.textContent = 'Invalid email format.';
                status.style.color = 'red';
                status.style.display = 'block';
                emailAvailable = false;
                return;
            }
            status.textContent = 'Checking...';
            status.style.color = 'black';
            status.style.display = 'block';
            const exists = await checkEmailExists(val);
            if (exists) {
                status.textContent = 'Email already registered.';
                status.style.color = 'red';
                emailAvailable = false;
            } else {
                status.textContent = 'Email available.';
                status.style.color = 'green';
                emailAvailable = true;
                // hide after short delay
                setTimeout(() => { status.style.display = 'none'; }, 2000);
            }
        });
    }

    const regNumberInput = document.getElementById('student_registration_number');
    const regNumberStatus = document.createElement('div');
    regNumberStatus.id = 'registration_number_status';
    regNumberStatus.className = 'small';
    regNumberStatus.style.cssText = 'color: var(--danger); display: none; margin-top: 0.5rem;';
    
    if (regNumberInput) {
        regNumberInput.parentNode.appendChild(regNumberStatus);
        
        // On blur, check availability
        regNumberInput.addEventListener('blur', async function() {
            const val = regNumberInput.value.trim();
            if (!val) {
                regNumberStatus.style.display = 'none';
                return;
            }
            
            regNumberStatus.textContent = 'Checking...';
            regNumberStatus.style.color = 'black';
            regNumberStatus.style.display = 'block';
            
            const result = await checkStudentRegistrationAvailable(val);
            
            if (result.exists) {
                if (result.reason === 'already_registered') {
                    regNumberStatus.textContent = '❌ Student registration number already registered.';
                } else if (result.reason === 'already_used') {
                    regNumberStatus.textContent = '❌ Student registration number already used to register.';
                } else {
                    regNumberStatus.textContent = '❌ Student registration number not available.';
                }
                regNumberStatus.style.color = 'var(--danger)';
                regNumberAvailable = false;
            } else if (result.reason === 'not_allowed') {
                regNumberStatus.textContent = '❌ Student registration number not permitted to register.';
                regNumberStatus.style.color = 'var(--danger)';
                regNumberAvailable = false;
            } else {
                regNumberStatus.textContent = '✓ Student registration number available.';
                regNumberStatus.style.color = 'var(--success)';
                regNumberAvailable = true;
                // hide after short delay
                setTimeout(() => { regNumberStatus.style.display = 'none'; }, 2000);
            }
        });
    }
});
