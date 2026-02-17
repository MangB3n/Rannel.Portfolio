// Form validation and submission
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.querySelector('.contact-form form');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const name = this.querySelector('input[name="name"]').value;
            const email = this.querySelector('input[name="email"]').value;
            const subject = this.querySelector('input[name="subject"]').value;
            const message = this.querySelector('textarea[name="message"]').value;
            
            // Basic validation
            if (!name || !email || !subject || !message) {
                showAlert('Please fill in all fields', 'error');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showAlert('Please enter a valid email address', 'error');
                return;
            }
            
            
            showAlert('Message sent successfully!', 'success');
            this.reset();
        });
    }
    
    // Function to show alert messages
    function showAlert(message, type) {
        
        const existingAlert = document.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        
        const alert = document.createElement('div');
        alert.className = `alert ${type}`;
        alert.textContent = message;
        
        
        const form = document.querySelector('.contact-form form');
        form.insertBefore(alert, form.firstChild);
        
        
        setTimeout(() => {
            alert.remove();
        }, 3000);
    }
});