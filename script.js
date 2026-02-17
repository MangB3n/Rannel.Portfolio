// ============================================
// NAVIGATION
// ============================================

const hamburger = document.querySelector('.hamburger');
const mobileMenu = document.querySelector('.mobile-menu');
const navLinks = document.querySelectorAll('.nav-link, .mobile-link');
const navbar = document.querySelector('.navbar');

// Toggle mobile menu
hamburger.addEventListener('click', () => {
    mobileMenu.classList.toggle('active');
    hamburger.classList.toggle('active');
    
    // Animate hamburger
    const spans = hamburger.querySelectorAll('span');
    if (mobileMenu.classList.contains('active')) {
        spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
    } else {
        spans[0].style.transform = 'none';
        spans[1].style.opacity = '1';
        spans[2].style.transform = 'none';
    }
});

// Close mobile menu when clicking on a link
navLinks.forEach(link => {
    link.addEventListener('click', () => {
        mobileMenu.classList.remove('active');
        const spans = hamburger.querySelectorAll('span');
        spans[0].style.transform = 'none';
        spans[1].style.opacity = '1';
        spans[2].style.transform = 'none';
    });
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
    if (window.scrollY > 100) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Active navigation on scroll
const sections = document.querySelectorAll('section[id]');

function setActiveNav() {
    const scrollY = window.pageYOffset;

    sections.forEach(section => {
        const sectionHeight = section.offsetHeight;
        const sectionTop = section.offsetTop - 100;
        const sectionId = section.getAttribute('id');
        
        if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
            document.querySelectorAll(`.nav-link[href*=${sectionId}]`).forEach(link => {
                link.classList.add('active');
            });
        } else {
            document.querySelectorAll(`.nav-link[href*=${sectionId}]`).forEach(link => {
                link.classList.remove('active');
            });
        }
    });
}

window.addEventListener('scroll', setActiveNav);

// ============================================
// SMOOTH SCROLLING
// ============================================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#' && href !== '') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    });
});

// ============================================
// TYPING EFFECT
// ============================================

const typingText = document.querySelector('.typing-text');
const words = ['IT Student & Developer', 'Web Developer', 'Problem Solver', 'Tech Enthusiast'];
let wordIndex = 0;
let charIndex = 0;
let isDeleting = false;

function type() {
    const currentWord = words[wordIndex];
    
    if (isDeleting) {
        typingText.textContent = currentWord.substring(0, charIndex - 1);
        charIndex--;
    } else {
        typingText.textContent = currentWord.substring(0, charIndex + 1);
        charIndex++;
    }
    
    if (!isDeleting && charIndex === currentWord.length) {
        isDeleting = true;
        setTimeout(type, 2000);
    } else if (isDeleting && charIndex === 0) {
        isDeleting = false;
        wordIndex = (wordIndex + 1) % words.length;
        setTimeout(type, 500);
    } else {
        setTimeout(type, isDeleting ? 50 : 100);
    }
}

// Start typing effect after page load
setTimeout(type, 1000);

// ============================================
// SKILLS PROGRESS ANIMATION
// ============================================

const skillCards = document.querySelectorAll('.skill-card');
let skillsAnimated = false;

function animateSkills() {
    const skillsSection = document.querySelector('.skills');
    const skillsSectionTop = skillsSection.offsetTop;
    const skillsSectionHeight = skillsSection.offsetHeight;
    const scrollY = window.pageYOffset + window.innerHeight;
    
    if (scrollY > skillsSectionTop + 100 && !skillsAnimated) {
        skillCards.forEach((card, index) => {
            setTimeout(() => {
                const progressFill = card.querySelector('.progress-fill');
                const progress = progressFill.getAttribute('data-progress');
                progressFill.style.setProperty('--progress-width', progress + '%');
                progressFill.style.width = progress + '%';
            }, index * 100);
        });
        skillsAnimated = true;
    }
}

window.addEventListener('scroll', animateSkills);

// ============================================
// SCROLL ANIMATIONS
// ============================================

const animateOnScroll = () => {
    const elements = document.querySelectorAll('.skill-card, .project-card, .certificate-card, .about-content, .section-header');
    
    elements.forEach(element => {
        const elementTop = element.getBoundingClientRect().top;
        const elementBottom = element.getBoundingClientRect().bottom;
        
        if (elementTop < window.innerHeight - 100 && elementBottom > 0) {
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }
    });
};

// Initial state for animations
document.addEventListener('DOMContentLoaded', () => {
    const elements = document.querySelectorAll('.skill-card, .project-card, .certificate-card');
    elements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    });
});

window.addEventListener('scroll', animateOnScroll);

// ============================================
// SCROLL TO TOP BUTTON
// ============================================

const scrollTopBtn = document.querySelector('.scroll-top');

window.addEventListener('scroll', () => {
    if (window.scrollY > 500) {
        scrollTopBtn.classList.add('active');
    } else {
        scrollTopBtn.classList.remove('active');
    }
});

scrollTopBtn.addEventListener('click', () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// ============================================
// FORM SUBMISSION
// ============================================

const contactForm = document.querySelector('.contact-form');

contactForm.addEventListener('submit', (e) => {
    e.preventDefault();
    
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const subject = document.getElementById('subject').value;
    const message = document.getElementById('message').value;
    
    // Here you would typically send the data to a server
    // For now, we'll just show an alert
    alert(`Thank you, ${name}! Your message has been received. I'll get back to you soon!`);
    
    // Reset form
    contactForm.reset();
});

// ============================================
// CURSOR ANIMATION (Optional Enhancement)
// ============================================

const cursor = document.querySelector('.cursor');
let cursorTimeout;

// Hide cursor after typing animation starts
if (cursor) {
    setInterval(() => {
        cursor.style.opacity = cursor.style.opacity === '0' ? '1' : '0';
    }, 530);
}

// ============================================
// PARALLAX EFFECT FOR HERO SHAPES
// ============================================

window.addEventListener('mousemove', (e) => {
    const shapes = document.querySelectorAll('.shape');
    const x = e.clientX / window.innerWidth;
    const y = e.clientY / window.innerHeight;
    
    shapes.forEach((shape, index) => {
        const speed = (index + 1) * 20;
        const xMove = (x - 0.5) * speed;
        const yMove = (y - 0.5) * speed;
        
        shape.style.transform = `translate(${xMove}px, ${yMove}px)`;
    });
});

// ============================================
// IMAGE LAZY LOADING (Performance Enhancement)
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
});

// ============================================
// CONSOLE MESSAGE (Easter Egg)
// ============================================

console.log('%c👋 Hello Developer!', 'font-size: 20px; color: #6366f1; font-weight: bold;');
console.log('%cLooking for something? Check out my GitHub: https://github.com/MangB3n', 'font-size: 14px; color: #8b5cf6;');
console.log('%cInterested in collaboration? Let\'s connect!', 'font-size: 14px; color: #ec4899;');

// ============================================
// PRELOADER (Optional Enhancement)
// ============================================

window.addEventListener('load', () => {
    const preloader = document.querySelector('.preloader');
    if (preloader) {
        setTimeout(() => {
            preloader.style.opacity = '0';
            setTimeout(() => {
                preloader.style.display = 'none';
            }, 500);
        }, 1000);
    }
});

// ============================================
// PREVENT CONTEXT MENU ON IMAGES (Optional)
// ============================================

document.querySelectorAll('img').forEach(img => {
    img.addEventListener('contextmenu', (e) => {
        // Uncomment to prevent right-click on images
        // e.preventDefault();
    });
});

// ============================================
// ACCESSIBILITY ENHANCEMENTS
// ============================================

// Focus visible for keyboard navigation
document.addEventListener('keydown', (e) => {
    if (e.key === 'Tab') {
        document.body.classList.add('keyboard-navigation');
    }
});

document.addEventListener('mousedown', () => {
    document.body.classList.remove('keyboard-navigation');
});

// ============================================
// PERFORMANCE MONITORING
// ============================================

if ('performance' in window) {
    window.addEventListener('load', () => {
        const perfData = performance.timing;
        const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
        console.log(`%cPage Load Time: ${pageLoadTime}ms`, 'color: #6366f1; font-weight: bold;');
    });
}