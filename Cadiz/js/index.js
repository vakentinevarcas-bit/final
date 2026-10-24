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

const spotsGrid = document.getElementById('spotsGrid');

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

function safeSpotPhoto(value) {
    const photo = String(value || '').trim();
    if (/^data:image\/(?:png|jpe?g|gif|webp|avif);base64,[A-Za-z0-9+/]+={0,2}$/i.test(photo)) {
        return photo;
    }
    try {
        const url = new URL(photo, window.location.href);
        return url.protocol === 'https:' || url.protocol === 'http:' ? url.href : '';
    } catch (error) {
        return '';
    }
}

function createSpotCard(landmark, index) {
    const card = document.createElement('article');
    card.className = 'spot-card';
    card.dataset.delay = String((index % 6) * 100);

    const imageContainer = document.createElement('div');
    imageContainer.className = 'card-img';
    const photo = safeSpotPhoto(landmark.photo);
    if (photo) {
        const image = document.createElement('img');
        image.src = photo;
        image.alt = landmark.name ? `${landmark.name} photo` : 'Tourist spot photo';
        image.loading = 'lazy';
        image.addEventListener('error', () => {
            image.remove();
            imageContainer.classList.add('no-image');
        }, { once: true });
        imageContainer.appendChild(image);
    } else {
        imageContainer.classList.add('no-image');
        const icon = document.createElement('i');
        icon.className = 'fas fa-map-marked-alt';
        icon.setAttribute('aria-hidden', 'true');
        imageContainer.appendChild(icon);
    }

    const body = document.createElement('div');
    body.className = 'card-body';
    const title = document.createElement('h3');
    title.textContent = landmark.name || 'Tourist spot';

    const location = document.createElement('div');
    location.className = 'location';
    const pin = document.createElement('i');
    pin.className = 'fas fa-map-pin';
    pin.setAttribute('aria-hidden', 'true');
    const coordinates = Number.isFinite(Number(landmark.lat)) && Number.isFinite(Number(landmark.lng))
        ? `${Number(landmark.lat).toFixed(4)}, ${Number(landmark.lng).toFixed(4)}`
        : 'Cadiz City';
    location.append(pin, document.createTextNode(` ${coordinates}`));

    const rating = document.createElement('div');
    rating.className = 'spot-rating';
    rating.setAttribute('role', 'img');
    const ratingValue = Number(landmark.rating);
    const reviewCount = Number(landmark.reviews);
    const hasRatings = Number.isFinite(ratingValue) && Number.isFinite(reviewCount) && reviewCount > 0;
    const stars = document.createElement('span');
    stars.className = 'spot-rating-stars';
    stars.setAttribute('aria-hidden', 'true');
    const roundedRating = hasRatings ? Math.round(Math.min(5, Math.max(0, ratingValue))) : 0;
    stars.textContent = '★'.repeat(roundedRating) + '☆'.repeat(5 - roundedRating);
    const ratingSummary = document.createElement('span');
    ratingSummary.className = 'spot-rating-summary';
    if (hasRatings) {
        ratingSummary.textContent = `${ratingValue.toFixed(1)} (${reviewCount} ${reviewCount === 1 ? 'review' : 'reviews'})`;
        rating.setAttribute('aria-label', `Rated ${ratingValue.toFixed(1)} out of 5 from ${reviewCount} ${reviewCount === 1 ? 'review' : 'reviews'}`);
    } else {
        ratingSummary.textContent = 'No ratings yet';
        rating.setAttribute('aria-label', 'No ratings yet');
    }
    rating.append(stars, ratingSummary);

    const description = document.createElement('p');
    description.textContent = landmark.description || 'Discover this destination in Cadiz City.';

    const tag = document.createElement('span');
    tag.className = 'tag';
    tag.textContent = String(landmark.category || 'historical').replace(/[_-]/g, ' ');

    body.append(title, location, rating, description, tag);
    card.append(imageContainer, body);
    return card;
}

async function loadTouristSpots() {
    if (!spotsGrid) return;
    try {
        const response = await fetch('Cadiz/landmarks_api.php?action=list', {
            headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error(`Could not load tourist spots (HTTP ${response.status}).`);
        const data = await response.json();
        if (!data.success || !Array.isArray(data.landmarks)) {
            throw new Error(data.message || 'The tourist spot list could not be loaded.');
        }

        spotsGrid.replaceChildren();
        if (data.landmarks.length === 0) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'spots-message';
            emptyMessage.textContent = 'No tourist spots have been added yet.';
            spotsGrid.appendChild(emptyMessage);
            spotsGrid.setAttribute('aria-busy', 'false');
            return;
        }

        data.landmarks.forEach((landmark, index) => {
            const card = createSpotCard(landmark, index);
            spotsGrid.appendChild(card);
            cardObserver.observe(card);
        });
        spotsGrid.setAttribute('aria-busy', 'false');
    } catch (error) {
        console.error('Could not load tourist spots:', error);
        spotsGrid.replaceChildren();
        const message = document.createElement('p');
        message.className = 'spots-message';
        message.textContent = 'Tourist spots could not be loaded. Please refresh the page to try again.';
        spotsGrid.appendChild(message);
        spotsGrid.setAttribute('aria-busy', 'false');
    }
}

loadTouristSpots();

const publicEventsList = document.getElementById('publicEventsList');

function createPublicEventCard(event) {
    const card = document.createElement('article');
    card.className = 'public-event-card';

    const dateParts = String(event.event_date || '').split('-').map(Number);
    const eventDate = new Date(dateParts[0], dateParts[1] - 1, dateParts[2]);
    const dateBadge = document.createElement('div');
    dateBadge.className = 'public-event-date';
    const month = document.createElement('span');
    month.textContent = eventDate.toLocaleDateString(undefined, { month: 'short' });
    const day = document.createElement('strong');
    day.textContent = String(eventDate.getDate());
    dateBadge.append(month, day);

    const details = document.createElement('div');
    details.className = 'public-event-details';
    const title = document.createElement('h3');
    title.textContent = event.title || 'Cadiz event';
    details.appendChild(title);

    const metaParts = [eventDate.toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    })];
    if (event.event_time) {
        const [hours, minutes] = event.event_time.split(':').map(Number);
        const eventTime = new Date(2000, 0, 1, hours, minutes);
        metaParts.push(eventTime.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }));
    }
    if (event.location) metaParts.push(event.location);
    const meta = document.createElement('p');
    meta.className = 'public-event-meta';
    meta.textContent = metaParts.join(' · ');
    details.appendChild(meta);

    if (event.description) {
        const description = document.createElement('p');
        description.className = 'public-event-description';
        description.textContent = event.description;
        details.appendChild(description);
    }
    card.append(dateBadge, details);
    return card;
}

async function loadPublicEvents() {
    if (!publicEventsList) return;
    try {
        const response = await fetch('Cadiz/events_api.php', {
            headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error(`Could not load events (HTTP ${response.status}).`);
        const data = await response.json();
        if (!data.success || !Array.isArray(data.events)) {
            throw new Error(data.message || 'The event list could not be loaded.');
        }

        publicEventsList.replaceChildren();
        const upcomingEvents = data.events.slice(0, 6);
        if (upcomingEvents.length === 0) {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'public-events-message';
            emptyMessage.textContent = 'No upcoming events have been announced yet. Please check back soon.';
            publicEventsList.appendChild(emptyMessage);
        } else {
            upcomingEvents.forEach(event => {
                publicEventsList.appendChild(createPublicEventCard(event));
            });
        }
        publicEventsList.setAttribute('aria-busy', 'false');
    } catch (error) {
        console.error('Could not load upcoming events:', error);
        publicEventsList.replaceChildren();
        const message = document.createElement('p');
        message.className = 'public-events-message';
        message.textContent = 'Upcoming events could not be loaded. Please refresh the page to try again.';
        publicEventsList.appendChild(message);
        publicEventsList.setAttribute('aria-busy', 'false');
    }
}

loadPublicEvents();

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
