const menuToggle = document.getElementById('menuToggle');
const navLinks = document.getElementById('navLinks');

menuToggle.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    const icon = menuToggle.querySelector('i');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-times');
});


document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', () => {
        navLinks.classList.remove('open');
        const icon = menuToggle.querySelector('i');
        icon.classList.add('fa-bars');
        icon.classList.remove('fa-times');
    });
});


const backBtn = document.getElementById('backToTop');

window.addEventListener('scroll', () => {
    if (window.scrollY > 400) {
        backBtn.classList.add('show');
    } else {
        backBtn.classList.remove('show');
    }
});

backBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

const cards = document.querySelectorAll('.spot-card');

const observerOptions = {
    root: null,
    rootMargin: '0px 0px -80px 0px',
    threshold: 0.15,
};

const cardObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            const card = entry.target;
            const delay = parseInt(card.dataset.delay) || 0;
            setTimeout(() => {
                card.classList.add('visible');
            }, delay);
            cardObserver.unobserve(card);
        }
    });
}, observerOptions);

cards.forEach(card => cardObserver.observe(card));


const nav = document.getElementById('navbar');

window.addEventListener('scroll', () => {
    if (window.scrollY > 30) {
        nav.style.background = 'rgba(252, 248, 242, 0.97)';
        nav.style.boxShadow = '0 2px 30px rgba(0,0,0,0.04)';
    } else {
        nav.style.background = 'rgba(252, 248, 242, 0.92)';
        nav.style.boxShadow = 'none';
    }
});

const qrModal = document.getElementById('qrModal');
const showQrBtn = document.getElementById('showQrBtn');
const closeQrBtn = document.getElementById('closeQrModal');
const qrImage = document.getElementById('qrCodeImage');


const email = 'info@cadizgo.com';
const qrData = `mailto:${email}`;
qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(qrData)}&size=200x200&margin=12&bgcolor=ffffff&color=c79a6e`;

function openModal() {
    qrModal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    qrModal.classList.remove('show');
    document.body.style.overflow = '';
}

showQrBtn.addEventListener('click', openModal);
closeQrBtn.addEventListener('click', closeModal);

window.addEventListener('click', (event) => {
    if (event.target === qrModal) {
        closeModal();
    }
});


document.addEventListener('DOMContentLoaded', () => {
    cards.forEach(card => {
        const rect = card.getBoundingClientRect();
        if (rect.top < window.innerHeight - 80) {
            const delay = parseInt(card.dataset.delay) || 0;
            setTimeout(() => {
                card.classList.add('visible');
            }, delay);
        }
    });
});