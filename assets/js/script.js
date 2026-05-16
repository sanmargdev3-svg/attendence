// Attendance System - JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Attendance System Initialized');
});

// Confirm before deleting records
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this record?')) {
        // Delete logic here
        return true;
    }
    return false;
}

// Validate form inputs
function validateForm() {
    const email = document.querySelector('input[name="email"]');
    const password = document.querySelector('input[name="password"]');
    
    if (!email || !password) {
        return true;
    }
    
    if (email.value === '' || password.value === '') {
        alert('Please fill in all fields');
        return false;
    }
    
    return true;
}
