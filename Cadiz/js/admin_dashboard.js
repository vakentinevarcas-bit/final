const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const overlay = document.getElementById('overlay');
const main = document.getElementById('main');

function closeSidebar() {
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('open', 'expanded');
    if (main) main.classList.remove('sidebar-expanded');
    if (sidebarToggle) {
        sidebarToggle.setAttribute('aria-expanded', 'false');
        sidebarToggle.setAttribute('aria-label', 'Expand navigation');
    }
    overlay.classList.remove('active');
}

if (sidebarToggle && sidebar && main) {
    sidebarToggle.addEventListener('click', () => {
        const expanded = sidebar.classList.toggle('expanded');
        main.classList.toggle('sidebar-expanded', expanded);
        overlay.classList.toggle('active', expanded && window.innerWidth <= 768);
        sidebarToggle.setAttribute('aria-expanded', String(expanded));
        sidebarToggle.setAttribute('aria-label', expanded ? 'Collapse navigation' : 'Expand navigation');
    });
}

if (overlay) {
    overlay.addEventListener('click', closeSidebar);
}


document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('expanded')) {
        closeSidebar();
    }
});

const navItems = document.querySelectorAll('.nav-item[data-page]');
const pageSections = {
    dashboard: document.getElementById('page-dashboard'),
    category: document.getElementById('page-category'),
    website: document.getElementById('page-website'),
    map: document.getElementById('page-map'),
    chat: document.getElementById('page-chat'),
    feedback: document.getElementById('page-feedback'),
};
const pageTitle = document.getElementById('pageTitle');

function saveCurrentPage(pageId) {
    try {
        sessionStorage.setItem('admin_dashboard_current_page', pageId);
    } catch (err) {
        // ignore storage failures
    }
}

function restoreSavedPage() {
    try {
        const savedPage = sessionStorage.getItem('admin_dashboard_current_page');
        if (savedPage && pageSections[savedPage]) {
            return savedPage;
        }
    } catch (err) {
        // ignore storage failures
    }
    return 'dashboard';
}

function navigateTo(pageId) {
    const target = pageSections[pageId];
    if (!target) return;

    if (main) main.classList.toggle('map-view', pageId === 'map');

    document.querySelectorAll('.page-section').forEach(section => {
        section.classList.toggle('active', section === target);
    });

    navItems.forEach(item => item.classList.remove('active'));
    const activeNav = document.querySelector(`.nav-item[data-page="${pageId}"]`);
    if (activeNav) activeNav.classList.add('active');

    const titles = {
        dashboard: 'Dashboard',
        category: 'Landmarks',
        website: 'Website',
        map: 'Map',
        chat: 'Chat',
        feedback: 'Feed back'
    };
    if (pageTitle) pageTitle.textContent = titles[pageId] || 'Dashboard';

    saveCurrentPage(pageId);
    window.history.replaceState(null, '', '#page-' + pageId);

    if (window.innerWidth <= 768) closeSidebar();

    if (pageId === 'map' && window.MapApp && window.MapApp.invalidate) {
        window.MapApp.invalidate();
    }
}

navItems.forEach(item => {
    item.addEventListener('click', (e) => {
        e.preventDefault();
        const page = item.dataset.page;
        if (page) navigateTo(page);
    });
});

const hashPage = window.location.hash.replace(/^#page-/, '');
const initialPage = pageSections[hashPage] ? hashPage : restoreSavedPage();
navigateTo(initialPage);

const categoryLabels = {
    historical: 'Historical',
    shopping: 'Shopping',
    beaches: 'Beaches',
    food: 'Food & Drinks',
    hotel: 'Hotel'
};
function restoreLandmarkCategory() {
    try {
        const savedCategory = sessionStorage.getItem('admin_dashboard_landmark_category');
        if (savedCategory && Object.prototype.hasOwnProperty.call(categoryLabels, savedCategory)) {
            return savedCategory;
        }
    } catch (err) {
        // Ignore storage failures and use the default category.
    }
    return 'historical';
}

let selectedLandmarkCategory = restoreLandmarkCategory();
let categoryLoadId = 0;
let feedbackByPlace = {};

function setSelectedCategory(category) {
    selectedLandmarkCategory = category;
    try {
        sessionStorage.setItem('admin_dashboard_landmark_category', category);
    } catch (err) {
        // The selected category still works for the current page if storage is unavailable.
    }
    document.querySelectorAll('.category-stat-card').forEach(card => {
        const selected = card.dataset.category === category;
        card.classList.toggle('is-selected', selected);
        card.setAttribute('aria-pressed', String(selected));
    });
}

setSelectedCategory(selectedLandmarkCategory);

function normalizeLandmarkCategory(category) {
    const normalized = String(category || '').trim().toLowerCase();
    return normalized === 'beach' ? 'beaches' : normalized;
}

function safeLandmarkPhoto(value) {
    const photo = String(value || '').trim();
    if (/^data:image\/(?:png|jpe?g|gif|webp|avif);base64,[A-Za-z0-9+/]+={0,2}$/i.test(photo)) {
        return photo;
    }

    try {
        const url = new URL(photo, window.location.href);
        if (url.protocol === 'https:' || url.protocol === 'http:') return url.href;
    } catch (error) {
        return '';
    }

    return '';
}

function updateLandmarkCategoryCounts(landmarks) {
    const counts = Object.keys(categoryLabels).reduce((result, category) => {
        result[category] = 0;
        return result;
    }, {});

    landmarks.forEach(landmark => {
        const category = normalizeLandmarkCategory(landmark.category);
        if (Object.prototype.hasOwnProperty.call(counts, category)) {
            counts[category] += 1;
        }
    });

    document.querySelectorAll('[data-category-count]').forEach(element => {
        const count = counts[element.dataset.categoryCount] || 0;
        element.textContent = count.toLocaleString();
    });
}

function renderCategoryLandmarks(landmarks) {
    const list = document.getElementById('categoryLandmarkList');
    if (!list) return;

    const places = landmarks.filter(landmark =>
        normalizeLandmarkCategory(landmark.category) === selectedLandmarkCategory
    );
    const total = document.getElementById('categoryLandmarkTotal');
    if (total) {
        total.textContent = `${places.length} ${places.length === 1 ? 'landmark' : 'landmarks'}`;
    }
    list.replaceChildren();

    if (!places.length) {
        const emptyMessage = document.createElement('div');
        emptyMessage.className = 'category-landmark-empty';
        emptyMessage.textContent = `No ${categoryLabels[selectedLandmarkCategory]} landmarks have been added yet.`;
        list.appendChild(emptyMessage);
        return;
    }

    places.forEach(landmark => {
        const item = document.createElement('article');
        item.className = 'category-landmark-item';

        const photoContainer = document.createElement('div');
        photoContainer.className = 'category-landmark-photo';
        const photoUrl = safeLandmarkPhoto(landmark.photo);
        if (photoUrl) {
            const image = document.createElement('img');
            image.src = photoUrl;
            image.alt = landmark.name ? `${landmark.name} photo` : 'Landmark photo';
            image.loading = 'lazy';
            image.addEventListener('error', () => {
                photoContainer.classList.add('category-landmark-photo-empty');
                image.remove();
            }, { once: true });
            photoContainer.appendChild(image);
        } else {
            photoContainer.classList.add('category-landmark-photo-empty');
        }
        item.appendChild(photoContainer);

        const details = document.createElement('div');
        details.className = 'category-landmark-details';
        const titleRow = document.createElement('div');
        titleRow.className = 'category-landmark-title-row';
        const name = document.createElement('h3');
        name.textContent = landmark.name || 'Unnamed landmark';
        titleRow.appendChild(name);

        const categoryTag = document.createElement('span');
        categoryTag.className = 'category-landmark-tag';
        categoryTag.textContent = categoryLabels[selectedLandmarkCategory];
        titleRow.appendChild(categoryTag);
        details.appendChild(titleRow);

        const ratingRow = document.createElement('button');
        ratingRow.type = 'button';
        ratingRow.className = 'category-landmark-rating';
        ratingRow.setAttribute('aria-expanded', 'false');
        const reviewCount = Math.max(0, Number.parseInt(landmark.reviews, 10) || 0);
        const ratingValue = Math.min(5, Math.max(0, Number(landmark.rating) || 0));
        const hasRatings = reviewCount > 0;
        const filledStars = hasRatings ? Math.floor(ratingValue) : 0;
        const hasHalfStar = hasRatings && ratingValue - filledStars >= 0.25;
        const stars = document.createElement('span');
        stars.className = 'category-landmark-stars';
        stars.setAttribute(
            'aria-label',
            hasRatings ? `${ratingValue.toFixed(1)} out of 5 stars` : 'Not rated'
        );

        for (let index = 0; index < 5; index += 1) {
            const star = document.createElement('i');
            if (index < filledStars) {
                star.className = 'fas fa-star';
            } else if (index === filledStars && hasHalfStar) {
                star.className = 'fas fa-star-half-alt';
            } else {
                star.className = 'far fa-star';
            }
            star.setAttribute('aria-hidden', 'true');
            stars.appendChild(star);
        }
        ratingRow.appendChild(stars);

        const ratingText = document.createElement('span');
        ratingText.className = 'category-landmark-rating-text';
        ratingText.textContent = hasRatings
            ? `${ratingValue.toFixed(1)} (${reviewCount} ${reviewCount === 1 ? 'rating' : 'ratings'})`
            : 'Not rated';
        ratingRow.appendChild(ratingText);

        const reviewers = feedbackByPlace[String(landmark.name || '').trim().toLowerCase()] || [];
        ratingRow.disabled = reviewers.length === 0;
        if (reviewers.length) {
            ratingRow.classList.add('has-reviewers');
            ratingRow.setAttribute('aria-label', `${ratingText.textContent}. Click or hover to see who rated this place.`);
            ratingRow.addEventListener('click', event => {
                event.stopPropagation();
                const isOpen = ratingRow.classList.toggle('is-open');
                ratingRow.setAttribute('aria-expanded', String(isOpen));
            });

            const tooltip = document.createElement('span');
            tooltip.className = 'category-landmark-reviewers';
            tooltip.setAttribute('role', 'tooltip');
            reviewers.forEach(review => {
                const reviewer = document.createElement('span');
                reviewer.className = 'category-landmark-reviewer';
                const reviewerName = document.createElement('strong');
                reviewerName.textContent = review.user_name || 'Anonymous';
                const reviewerRating = document.createElement('span');
                reviewerRating.className = 'category-landmark-reviewer-rating';
                reviewerRating.textContent = `${'★'.repeat(Math.max(0, Math.min(5, Number(review.rating) || 0)))}${'☆'.repeat(5 - Math.max(0, Math.min(5, Number(review.rating) || 0)))}`;
                reviewer.append(reviewerName, reviewerRating);
                tooltip.appendChild(reviewer);
            });
            ratingRow.appendChild(tooltip);
        }
        details.appendChild(ratingRow);

        const description = document.createElement('p');
        description.textContent = landmark.description || 'No description provided.';
        details.appendChild(description);
        item.appendChild(details);
        list.appendChild(item);
    });
}

document.addEventListener('click', event => {
    document.querySelectorAll('.category-landmark-rating.is-open').forEach(rating => {
        if (!rating.contains(event.target)) {
            rating.classList.remove('is-open');
            rating.setAttribute('aria-expanded', 'false');
        }
    });
});

document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.category-landmark-rating.is-open').forEach(rating => {
        rating.classList.remove('is-open');
        rating.setAttribute('aria-expanded', 'false');
    });
});

function loadCategoryLandmarks() {
    const list = document.getElementById('categoryLandmarkList');
    if (!list) return;
    const requestId = ++categoryLoadId;
    list.textContent = 'Loading landmarks…';

    Promise.all([
        fetch('../Cadiz/landmarks_api.php?action=list').then(response => {
            if (!response.ok) throw new Error('Could not load landmarks.');
            return response.json();
        }),
        fetch('feedback_api.php', { headers: { Accept: 'application/json' } }).then(response => {
            if (!response.ok) throw new Error('Could not load landmark ratings.');
            return response.json();
        })
    ])
        .then(([data, feedbackData]) => {
            if (!data.success || !Array.isArray(data.landmarks)) {
                throw new Error(data.message || 'Could not load landmarks.');
            }
            if (!feedbackData.success || !Array.isArray(feedbackData.feedback)) {
                throw new Error(feedbackData.message || 'Could not load landmark ratings.');
            }
            if (requestId !== categoryLoadId) return;
            feedbackByPlace = {};
            feedbackData.feedback.forEach(review => {
                const place = String(review.place_name || '').trim().toLowerCase();
                if (!place) return;
                if (!feedbackByPlace[place]) feedbackByPlace[place] = [];
                feedbackByPlace[place].push(review);
            });
            updateLandmarkCategoryCounts(data.landmarks);
            renderCategoryLandmarks(data.landmarks);
        })
        .catch(error => {
            if (requestId !== categoryLoadId) return;
            console.error('Error loading category landmarks:', error);
            list.textContent = 'Landmarks could not be loaded. Please try again.';
        });
}

document.querySelectorAll('.category-stat-card').forEach(card => {
    card.addEventListener('click', () => {
        setSelectedCategory(card.dataset.category);
        const categoryName = categoryLabels[selectedLandmarkCategory];
        const title = document.getElementById('categoryPageTitle');
        const description = document.getElementById('categoryPageDescription');
        if (title) title.textContent = categoryName;
        if (description) description.textContent = `Landmarks listed under ${categoryName}.`;
        navigateTo('category');
        if (pageTitle) pageTitle.textContent = categoryName;
        loadCategoryLandmarks();
    });
});

const backToDashboard = document.getElementById('backToDashboard');
if (backToDashboard) {
    backToDashboard.addEventListener('click', () => navigateTo('dashboard'));
}

window.addEventListener('cadiz:landmarks-updated', event => {
    const landmarks = event.detail;
    if (!Array.isArray(landmarks)) return;
    updateLandmarkCategoryCounts(landmarks);
    if (pageSections.category && pageSections.category.classList.contains('active')) {
        categoryLoadId += 1;
        renderCategoryLandmarks(landmarks);
    }
});

if (initialPage === 'category') {
    const title = document.getElementById('categoryPageTitle');
    const description = document.getElementById('categoryPageDescription');
    if (title) title.textContent = categoryLabels[selectedLandmarkCategory];
    if (description) description.textContent = `Landmarks listed under ${categoryLabels[selectedLandmarkCategory]}.`;
    if (pageTitle) pageTitle.textContent = categoryLabels[selectedLandmarkCategory];
    loadCategoryLandmarks();
}

function initDashboardDragAndDrop() {
    const cardGroups = [
        document.querySelectorAll('.stats-grid .stat-card:not(.users-stat-card):not(.category-stat-card)'),
        document.querySelectorAll('.charts-row .chart-card')
    ];

    let draggedCard = null;
    let ghostCard = null;
    let pointerOffsetX = 0;
    let pointerOffsetY = 0;
    let dragStarted = false;

    function clearDragState() {
        if (draggedCard) {
            draggedCard.classList.remove('dragging');
            draggedCard.style.visibility = '';
            draggedCard.style.opacity = '';
            draggedCard.style.transform = '';
            draggedCard.style.width = '';
            draggedCard.style.zIndex = '';
        }

        document.querySelectorAll('.dashboard-card.drag-over').forEach(item => item.classList.remove('drag-over'));

        if (ghostCard && ghostCard.parentNode) {
            ghostCard.parentNode.removeChild(ghostCard);
        }

        draggedCard = null;
        ghostCard = null;
        dragStarted = false;
    }

    function getDropTargetFromPoint(clientX, clientY) {
        const element = document.elementFromPoint(clientX, clientY);
        if (!element) return null;

        const card = element.closest('.dashboard-card');
        if (!card || card === draggedCard) return null;

        return card;
    }

    function reorderCard(targetCard) {
        if (!draggedCard || !targetCard) return;

        const parent = draggedCard.parentElement;
        const targetParent = targetCard.parentElement;
        if (!parent || !targetParent || parent !== targetParent) return;

        const cards = Array.from(parent.children);
        const fromIndex = cards.indexOf(draggedCard);
        const toIndex = cards.indexOf(targetCard);

        if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) return;

        if (fromIndex < toIndex) {
            parent.insertBefore(draggedCard, targetCard.nextSibling);
        } else {
            parent.insertBefore(draggedCard, targetCard);
        }
    }

    function onPointerMove(event) {
        if (!draggedCard || !dragStarted) return;

        if (ghostCard) {
            ghostCard.style.left = `${event.clientX - pointerOffsetX}px`;
            ghostCard.style.top = `${event.clientY - pointerOffsetY}px`;
        }

        const target = getDropTargetFromPoint(event.clientX, event.clientY);
        document.querySelectorAll('.dashboard-card.drag-over').forEach(item => item.classList.remove('drag-over'));

        if (target) {
            target.classList.add('drag-over');
            if (target !== draggedCard) {
                reorderCard(target);
            }
        }
    }

    function onPointerUp() {
        if (!draggedCard) return;
        clearDragState();
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        document.removeEventListener('pointercancel', onPointerUp);
    }

    cardGroups.forEach(group => {
        group.forEach(card => {
            card.classList.add('dashboard-card');
            card.style.cursor = 'grab';

            card.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) return;
                event.preventDefault();

                draggedCard = card;
                draggedCard.classList.add('dragging');
                draggedCard.style.width = `${draggedCard.offsetWidth}px`;
                draggedCard.style.zIndex = '20';
                draggedCard.style.opacity = '0.8';
                draggedCard.style.position = 'relative';
                draggedCard.style.visibility = 'hidden';

                ghostCard = draggedCard.cloneNode(true);
                ghostCard.classList.add('drag-ghost');
                ghostCard.style.position = 'fixed';
                ghostCard.style.left = `${event.clientX}px`;
                ghostCard.style.top = `${event.clientY}px`;
                ghostCard.style.width = `${draggedCard.offsetWidth}px`;
                ghostCard.style.pointerEvents = 'none';
                ghostCard.style.opacity = '0.9';
                ghostCard.style.transform = 'scale(1.02)';
                ghostCard.style.boxShadow = '0 16px 32px rgba(79, 70, 229, 0.18)';
                ghostCard.style.zIndex = '9999';
                ghostCard.style.cursor = 'grabbing';
                document.body.appendChild(ghostCard);

                const rect = draggedCard.getBoundingClientRect();
                pointerOffsetX = event.clientX - rect.left;
                pointerOffsetY = event.clientY - rect.top;
                dragStarted = true;

                document.addEventListener('pointermove', onPointerMove);
                document.addEventListener('pointerup', onPointerUp);
                document.addEventListener('pointercancel', onPointerUp);
            });
        });
    });
}

initDashboardDragAndDrop();

const container = document.getElementById('barChart');
if (container) {
    const analytics = window.websiteAnalytics || {};
    const barData = Array.isArray(analytics.weeklyActivity) ? analytics.weeklyActivity : [];

    container.replaceChildren();
    if (!barData.length) {
        container.textContent = 'No page views recorded yet.';
        container.style.alignItems = 'center';
        container.style.justifyContent = 'center';
    } else {
        const maxVal = Math.max(1, ...barData.map(item => Number(item.value) || 0));
        barData.forEach(item => {
            const wrapper = document.createElement('div');
            wrapper.className = 'bar-wrapper';
            const bar = document.createElement('div');
            const value = Math.max(0, Number(item.value) || 0);
            const height = value ? Math.max(12, (value / maxVal) * 140) : 4;
            bar.className = 'bar';
            bar.style.setProperty('--h', height + 'px');
            bar.style.height = height + 'px';
            bar.style.minHeight = value ? '' : '4px';
            bar.title = `${value.toLocaleString()} page views`;
            bar.setAttribute('aria-label', `${item.label}: ${value} page views`);
            const label = document.createElement('div');
            label.className = 'bar-label';
            label.textContent = item.label;
            wrapper.append(bar, label);
            container.appendChild(wrapper);
        });
    }
}

const visitorLegend = document.getElementById('visitorTypeLegend');
const doughnut = document.querySelector('.doughnut-svg');
if (visitorLegend && doughnut) {
    const visitorTypes = (window.websiteAnalytics && window.websiteAnalytics.visitorTypes) || {};
    const registered = Math.max(0, Number(visitorTypes.registered) || 0);
    const guest = Math.max(0, Number(visitorTypes.guest) || 0);
    const total = registered + guest;
    const registeredPercent = total ? Math.round((registered / total) * 100) : 0;
    doughnut.style.background = total
        ? `conic-gradient(#4f46e5 0% ${registeredPercent}%, #eef0ff ${registeredPercent}% 100%)`
        : '#eef0ff';
    visitorLegend.replaceChildren();

    [
        { label: 'Registered views', value: registered, className: '' },
        { label: 'Guest views', value: guest, className: 'gray' }
    ].forEach(item => {
        const row = document.createElement('div');
        row.className = 'item';
        const dot = document.createElement('span');
        dot.className = 'dot' + (item.className ? ` ${item.className}` : '');
        const text = document.createElement('span');
        text.textContent = `${item.label} · ${item.value.toLocaleString()} (${total ? Math.round((item.value / total) * 100) : 0}%)`;
        row.append(dot, text);
        visitorLegend.appendChild(row);
    });
}

window.addEventListener('resize', () => {
    if (window.innerWidth > 768 && sidebar && sidebar.classList.contains('open')) {
        closeSidebar();
    }
});

if (false) {
    const userListContainer = document.getElementById('userListContainer');
    const chatMessages = document.getElementById('chatMessages');
    const chatEmpty = document.getElementById('chatEmpty');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatName = document.getElementById('chatName');
    const chatEmail = document.getElementById('chatEmail');
    const chatStatus = document.getElementById('chatStatus');
    const chatAvatar = document.getElementById('chatAvatar');

    const users = [{
        id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        status: 'online',
        avatar: 'JD',
        color: '#4f46e5',
        lastMsg: 'Hey, how are you?',
        time: '10:30 AM'
    }, {
        id: 2,
        name: 'Sarah K.',
        email: 'sarah@example.com',
        status: 'online',
        avatar: 'SK',
        color: '#0f9d6b',
        lastMsg: 'Sounds good!',
        time: '9:15 AM'
    }, {
        id: 3,
        name: 'Mike R.',
        email: 'mike@example.com',
        status: 'away',
        avatar: 'MR',
        color: '#e6a020',
        lastMsg: 'I\'ll check it out.',
        time: 'Yesterday'
    }, {
        id: 4,
        name: 'Jessica W.',
        email: 'jessica@example.com',
        status: 'offline',
        avatar: 'JW',
        color: '#e54a4a',
        lastMsg: 'Thanks! See you soon.',
        time: 'Yesterday'
    }, {
        id: 5,
        name: 'Alex P.',
        email: 'alex@example.com',
        status: 'online',
        avatar: 'AP',
        color: '#7c3aed',
        lastMsg: 'Let\'s schedule a meeting.',
        time: '2 days ago'
    }];

    const messagesData = {
        1: [
            { from: 'user', text: 'Hello! How can I help you?', time: '10:00 AM' },
            { from: 'me', text: 'Hi John, I have a question about the dashboard.', time: '10:05 AM' },
            { from: 'user', text: 'Sure, what do you need to know?', time: '10:10 AM' },
            { from: 'me', text: 'How can I add a new landmark?', time: '10:15 AM' },
            { from: 'user', text: 'Just click the "Add Landmark" button on the Map page.', time: '10:20 AM' },
        ],
        2: [
            { from: 'me', text: 'Hey Sarah, are you free for a call?', time: '9:00 AM' },
            { from: 'user', text: 'Yes, I am. Let me know the time.', time: '9:05 AM' },
            { from: 'me', text: 'How about 2 PM?', time: '9:10 AM' },
            { from: 'user', text: 'Sounds good!', time: '9:15 AM' },
        ],
        3: [
            { from: 'user', text: 'I saw the new update. Looks great!', time: 'Yesterday, 4:20 PM' },
            { from: 'me', text: 'Thanks, Mike! We worked hard on it.', time: 'Yesterday, 4:25 PM' },
            { from: 'user', text: 'I\'ll check it out.', time: 'Yesterday, 4:30 PM' },
        ],
        4: [
            { from: 'me', text: 'Hi Jessica, did you get the report?', time: 'Yesterday, 11:00 AM' },
            { from: 'user', text: 'Yes, I did. Thanks! See you soon.', time: 'Yesterday, 11:15 AM' },
        ],
        5: [
            { from: 'user', text: 'Let\'s schedule a meeting for next week.', time: '2 days ago, 9:00 AM' },
            { from: 'me', text: 'Sure, what day works for you?', time: '2 days ago, 9:10 AM' },
            { from: 'user', text: 'How about Wednesday?', time: '2 days ago, 9:15 AM' },
            { from: 'me', text: 'Wednesday is perfect.', time: '2 days ago, 9:20 AM' },
        ],
    };

    let currentUserId = null;

    function renderUsers() {
        userListContainer.innerHTML = '';
        users.forEach(user => {
            const div = document.createElement('div');
            div.className = 'user-item';
            if (user.id === currentUserId) div.classList.add('active');
            div.dataset.userId = user.id;
            div.innerHTML = `
                            <div class="avatar" style="background:${user.color};">
                                ${user.avatar}
                                <span class="status-dot ${user.status}"></span>
                            </div>
                            <div class="info">
                                <span class="name">${user.name}</span>
                                <span class="last-msg">${user.lastMsg}</span>
                            </div>
                            <span class="time">${user.time}</span>
                        `;
            div.addEventListener('click', () => {
                selectUser(user.id);
            });
            userListContainer.appendChild(div);
        });
    }

    function selectUser(userId) {
        const user = users.find(u => u.id === userId);
        if (!user) return;
        currentUserId = userId;
        renderUsers();

        chatName.textContent = user.name;
        chatEmail.textContent = user.email;
        chatAvatar.textContent = user.avatar;
        chatAvatar.style.background = user.color;

        const statusMap = {
            online: 'Online',
            offline: 'Offline',
            away: 'Away'
        };
        chatStatus.textContent = statusMap[user.status] || 'Online';
        chatStatus.className = 'status-text ' + user.status;

        const messages = messagesData[userId] || [];
        chatMessages.innerHTML = '';
        chatEmpty.style.display = 'none';

        if (messages.length === 0) {
            chatMessages.innerHTML =
                `<div class="chat-empty" style="display:flex;"><i class="fas fa-comment-dots"></i><h3>No messages</h3><p>Start the conversation!</p></div>`;
            return;
        }

        messages.forEach(msg => {
            const div = document.createElement('div');
            div.className = `message ${msg.from === 'me' ? 'sent' : 'received'}`;
            div.innerHTML = `${msg.text} <span class="time">${msg.time}</span>`;
            chatMessages.appendChild(div);
        });
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function sendMessage() {
        const text = chatInput.value.trim();
        if (!text || currentUserId === null) return;
        const now = new Date();
        const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        if (!messagesData[currentUserId]) messagesData[currentUserId] = [];
        messagesData[currentUserId].push({ from: 'me', text: text, time: time });

        const user = users.find(u => u.id === currentUserId);
        if (user) {
            user.lastMsg = text;
            user.time = time;
        }

        chatInput.value = '';
        selectUser(currentUserId);
    }

    renderUsers();
    if (users.length > 0) {
        selectUser(users[0].id);
    }

    sendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
}

(() => {
    const btnAdd = document.getElementById('btnAdd');
    const btnAddLabel = document.getElementById('btnAddLabel');
    const landmarkCount = document.getElementById('landmarkCount');
    const mapElement = document.getElementById('map');

    const modalOverlay = document.getElementById('modalOverlay');
    const modalTitle = document.getElementById('modalTitle');
    const modalSubtitle = document.getElementById('modalSubtitle');
    const landmarkName = document.getElementById('landmarkName');
    const landmarkDesc = document.getElementById('landmarkDesc');
    const landmarkCategory = document.getElementById('landmarkCategory');
    const modalSave = document.getElementById('modalSave');
    const modalCancel = document.getElementById('modalCancel');

    const photoInput = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');
    const previewImage = document.getElementById('previewImage');
    const removePhotoBtn = document.getElementById('removePhotoBtn');
    const photoUploadArea = document.getElementById('photoUploadArea');

    const toastContainer = document.getElementById('toastContainer');

    const coordLat = document.getElementById('coordLat');
    const coordLng = document.getElementById('coordLng');

    if (!btnAdd || !btnAddLabel || !mapElement || !modalOverlay || !modalTitle || !modalSubtitle || !landmarkName || !landmarkDesc || !landmarkCategory || !modalSave || !modalCancel || !photoInput || !photoPreview || !previewImage || !removePhotoBtn || !photoUploadArea || !toastContainer || !coordLat || !coordLng) {
        return;
    }

    let landmarks = [];
    let selectedId = null;
    let nextId = 1;
    let pendingPosition = null;
    let editMode = false;
    let editTargetId = null;
    let isAddingMode = false;
    let tempPhotoData = null;
    let photoChanged = false;
    let photoProcessing = false;
    let photoSelectionId = 0;
    let placementModeActive = false;

    let map = null;
    let markerMap = {};
    let pendingMarker = null;
    let togglePlacementMode = null;

    function generateId() { return nextId++; }

    function getLandmark(id) { return landmarks.find(l => l.id === id); }

    function showToast(message, type = 'info') {
        const container = toastContainer;
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> ${message}`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(30px) scale(0.94)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 350);
        }, 3200);
    }

    function setStatus(text, type = 'idle') {
        const statusText = document.getElementById('statusText');
        const statusDot = document.querySelector('#page-map .status-badge .dot');
        if (!statusText || !statusDot) return;
        statusText.textContent = text;
        statusDot.className = `dot dot-${type}`;
    }

    function updateLandmarkCount() {
        if (landmarkCount) {
            landmarkCount.textContent = landmarks.length;
        }
        window.dispatchEvent(new CustomEvent('cadiz:landmarks-updated', { detail: landmarks }));
    }

    function updateCoordsDisplay(lat, lng) {
        if (lat !== undefined && lng !== undefined) {
            coordLat.textContent = lat.toFixed(5);
            coordLng.textContent = lng.toFixed(5);
        }
    }

    function setPendingPosition(latlng) {
        pendingPosition = L.latLng(latlng.lat, latlng.lng);
        if (pendingMarker) map.removeLayer(pendingMarker);
        pendingMarker = L.marker(pendingPosition, {
            icon: L.divIcon({
                className: 'pending-marker-icon',
                html: '<div class="pending-marker-pin"></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            }),
            draggable: true,
            autoPan: true
        }).addTo(map);
        pendingMarker.on('dragend', function () {
            pendingPosition = this.getLatLng();
            updateCoordsDisplay(pendingPosition.lat, pendingPosition.lng);
            setStatus('Position adjusted. Click "Add Details" to continue.', 'pending');
        });
        if (btnAddLabel) btnAddLabel.textContent = 'Add Details';
        updateCoordsDisplay(pendingPosition.lat, pendingPosition.lng);
    }

    function createMarkerIcon() {
        return L.divIcon({
            className: 'custom-marker',
            html: `<div style="background-color:#2b6ef0; width:24px; height:24px; border-radius:50%; border:3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>`,
            iconSize: [24, 24],
            iconAnchor: [12, 12],
            popupAnchor: [0, -16],
        });
    }

    function buildPopupHTML(lm) {
        let photoHtml = '';
        if (lm.photo) {
            photoHtml = `<img src="${lm.photo}" class="popup-photo" alt="${lm.name}" />`;
        }
        const categoryLabel = lm.category ? lm.category.charAt(0).toUpperCase() + lm.category.slice(1) : 'Historical';
        return `
                        <div>
                            ${photoHtml}
                            <strong>${lm.name}</strong> <span style="font-size:11px;background:#e2e8f0;color:#4f46e5;font-weight:600;padding:2px 6px;border-radius:4px;margin-left:4px;">${categoryLabel}</span>
                            ${lm.description ? `<div class="popup-desc">${lm.description}</div>` : ''}
                            <div style="font-size:0.7rem;color:#8a95a8;margin-top:4px;font-family:monospace;">
                                ${lm.lat.toFixed(5)}, ${lm.lng.toFixed(5)}
                            </div>
                            <div class="popup-actions">
                                <button class="popup-action popup-action-edit" type="button" data-popup-action="edit" data-landmark-id="${lm.id}">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                                <button class="popup-action popup-action-delete" type="button" data-popup-action="delete" data-landmark-id="${lm.id}">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    `;
    }

    function addMarkerForLandmark(lm) {
        const icon = createMarkerIcon();
        const marker = L.marker([lm.lat, lm.lng], { icon: icon, draggable: true })
            .addTo(map)
            .bindPopup(buildPopupHTML(lm), {
                maxWidth: 280,
                className: 'custom-popup'
            });

        marker.on('click', function (e) {
            L.DomEvent.stopPropagation(e);
            selectLandmark(lm.id);
            this.openPopup();
        });

        // ── Drag existing landmark to reposition ──────────────────────────
        marker.on('dragstart', function () {
            this.closePopup();
            setStatus(`Moving: "${lm.name}"…`, 'pending');
        });

        marker.on('drag', function () {
            const pos = this.getLatLng();
            updateCoordsDisplay(pos.lat, pos.lng);
        });

        marker.on('dragend', function () {
            const newPos = this.getLatLng();
            const oldLat = lm.lat;
            const oldLng = lm.lng;

            // Update local data
            lm.lat = newPos.lat;
            lm.lng = newPos.lng;
            updateCoordsDisplay(newPos.lat, newPos.lng);

            // Refresh popup with new coordinates
            this.setPopupContent(buildPopupHTML(lm));

            // Save to backend
            fetch('../Cadiz/landmarks_api.php?action=update', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    id: lm.id,
                    name: lm.name,
                    description: lm.description,
                    lat: newPos.lat,
                    lng: newPos.lng,
                    category: lm.category
                })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showToast(`Moved "${lm.name}" to new position`, 'success');
                    setStatus(`Moved: "${lm.name}"`, 'selected');
                } else {
                    // Revert on failure
                    lm.lat = oldLat;
                    lm.lng = oldLng;
                    marker.setLatLng([oldLat, oldLng]);
                    marker.setPopupContent(buildPopupHTML(lm));
                    showToast('Failed to move landmark: ' + data.message, 'error');
                    setStatus('Move failed — reverted', 'idle');
                }
            })
            .catch(function(err) {
                console.error(err);
                // Revert on network error
                lm.lat = oldLat;
                lm.lng = oldLng;
                marker.setLatLng([oldLat, oldLng]);
                marker.setPopupContent(buildPopupHTML(lm));
                showToast('Network error moving landmark', 'error');
                setStatus('Move failed — reverted', 'idle');
            });

            selectLandmark(lm.id);
        });

        markerMap[lm.id] = marker;
        return marker;
    }

    function removeMarkerForLandmark(id) {
        if (markerMap[id]) {
            map.removeLayer(markerMap[id]);
            delete markerMap[id];
        }
    }

    function refreshMarkerPopup(id) {
        const marker = markerMap[id];
        if (marker) {
            const lm = getLandmark(id);
            if (lm) {
                marker.setPopupContent(buildPopupHTML(lm));
            }
        }
    }

    function syncMarkers() {
        Object.keys(markerMap).forEach(id => {
            map.removeLayer(markerMap[id]);
            delete markerMap[id];
        });
        landmarks.forEach(lm => addMarkerForLandmark(lm));
        if (pendingMarker) {
            map.removeLayer(pendingMarker);
            pendingMarker = null;
        }
        if (pendingPosition) {
            pendingMarker = L.circleMarker(pendingPosition, {
                radius: 12,
                color: '#2b6ef0',
                weight: 3,
                opacity: 0.8,
                fillColor: '#2b6ef0',
                fillOpacity: 0.25,
                className: 'pending-marker',
            }).addTo(map);
        }
    }

    function selectLandmark(id) {
        if (id === selectedId) return;
        selectedId = id;
        if (id !== null) {
            const lm = getLandmark(id);
            if (lm) {
                setStatus(`Selected: "${lm.name}"`, 'selected');
                updateCoordsDisplay(lm.lat, lm.lng);
                if (markerMap[id]) {
                    markerMap[id].openPopup();
                }
            } else {
                selectedId = null;
                setStatus('Ready', 'idle');
            }
        } else {
            setStatus('Ready', 'idle');
            map.closePopup();
        }
    }

    function deselectLandmark() {
        selectLandmark(null);
        map.closePopup();
    }

    function addLandmark(name, description, lat, lng, photo, id = null, category = 'historical') {
        const lm = {
            id: id !== null ? id : generateId(),
            name: name.trim() || 'Unnamed',
            description: description ? description.trim() : '',
            lat: lat,
            lng: lng,
            photo: photo || null,
            category: category
        };
        landmarks.push(lm);
        addMarkerForLandmark(lm);
        updateLandmarkCount();
        showToast(`Added "${lm.name}"`, 'success');
        return lm;
    }

    function updateLandmark(id, name, description, lat, lng, photo, category = 'historical') {
        const lm = getLandmark(id);
        if (!lm) { showToast('Landmark not found', 'error'); return false; }
        lm.name = name.trim() || 'Unnamed';
        lm.description = description ? description.trim() : '';
        lm.lat = lat;
        lm.lng = lng;
        lm.photo = photo || null;
        lm.category = category;

        removeMarkerForLandmark(id);
        addMarkerForLandmark(lm);
        updateLandmarkCount();
        showToast(`Updated "${lm.name}"`, 'success');
        updateCoordsDisplay(lat, lng);
        return true;
    }

    function deleteLandmark(id) {
        const idx = landmarks.findIndex(l => l.id === id);
        if (idx === -1) { showToast('Landmark not found', 'error'); return false; }
        const name = landmarks[idx].name;
        if (!window.confirm(`Delete "${name}"? This action cannot be undone.`)) return false;
        
        fetch('../Cadiz/landmarks_api.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                landmarks.splice(idx, 1);
                removeMarkerForLandmark(id);
                if (selectedId === id) {
                    selectedId = null;
                    setStatus('Ready', 'idle');
                }
                updateLandmarkCount();
                showToast(`Deleted "${name}"`, 'info');
            } else {
                showToast('Failed to delete landmark: ' + data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error deleting landmark', 'error');
        });
        return true;
    }

    function handlePhotoSelect(file) {
        if (!file) return;
        if (!file.type || !file.type.startsWith('image/')) {
            showToast('Please select an image file', 'error');
            return;
        }

        const selectionId = ++photoSelectionId;
        photoProcessing = true;
        modalSave.disabled = true;
        showToast('Compressing photo before upload…', 'info');

        compressLandmarkPhoto(file)
            .then(function (dataUrl) {
                if (selectionId !== photoSelectionId) return;
                tempPhotoData = dataUrl;
                photoChanged = true;
                previewImage.src = dataUrl;
                photoPreview.classList.add('show');
            })
            .catch(function (err) {
                if (selectionId !== photoSelectionId) return;
                console.error('Could not compress landmark photo:', err);
                showToast(err.message || 'Could not process this photo. Try another image.', 'error');
            })
            .finally(function () {
                if (selectionId !== photoSelectionId) return;
                photoProcessing = false;
                modalSave.disabled = false;
            });
    }

    function compressLandmarkPhoto(file) {
        const maxDataUrlLength = 480 * 1024;
        const maxDimension = 1280;
        const qualitySteps = [0.82, 0.72, 0.62, 0.52, 0.42];

        return new Promise(function (resolve, reject) {
            const objectUrl = URL.createObjectURL(file);
            const image = new Image();
            image.onload = function () {
                URL.revokeObjectURL(objectUrl);
                let width = image.naturalWidth;
                let height = image.naturalHeight;
                if (!width || !height) {
                    reject(new Error('The selected image could not be read.'));
                    return;
                }

                const scale = Math.min(1, maxDimension / Math.max(width, height));
                width = Math.max(1, Math.round(width * scale));
                height = Math.max(1, Math.round(height * scale));
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                if (!context) {
                    reject(new Error('Photo compression is not supported by this browser.'));
                    return;
                }

                while (Math.max(width, height) >= 64) {
                    canvas.width = width;
                    canvas.height = height;
                    context.drawImage(image, 0, 0, width, height);

                    for (const quality of qualitySteps) {
                        const dataUrl = canvas.toDataURL('image/jpeg', quality);
                        if (dataUrl.length <= maxDataUrlLength) {
                            resolve(dataUrl);
                            return;
                        }
                    }

                    width = Math.round(width * 0.8);
                    height = Math.round(height * 0.8);
                }
                reject(new Error('Photo is still too large after compression. Please choose a smaller image.'));
            };
            image.onerror = function () {
                URL.revokeObjectURL(objectUrl);
                reject(new Error('The selected image could not be opened.'));
            };
            image.src = objectUrl;
        });
    }

    function clearPhotoPreview() {
        photoSelectionId++;
        photoProcessing = false;
        modalSave.disabled = false;
        tempPhotoData = null;
        previewImage.src = '';
        photoPreview.classList.remove('show');
        photoInput.value = '';
    }

    function openModal(mode, landmarkData = null) {
        editMode = (mode === 'edit');
        clearPhotoPreview();
        photoChanged = false;

        if (editMode && landmarkData) {
            editTargetId = landmarkData.id;
            modalTitle.innerHTML = '<i class="fas fa-pen"></i> Edit Landmark';
            modalSubtitle.textContent = 'Update the details.';
            landmarkName.value = landmarkData.name || '';
            landmarkDesc.value = landmarkData.description || '';
            landmarkCategory.value = landmarkData.category || 'historical';
            modalSave.innerHTML = '<i class="fas fa-save"></i> Update';
            setStatus(`Editing: "${landmarkData.name}"`, 'selected');
            isAddingMode = false;
            updateCoordsDisplay(landmarkData.lat, landmarkData.lng);

            if (landmarkData.photo) {
                tempPhotoData = landmarkData.photo;
                previewImage.src = landmarkData.photo;
                photoPreview.classList.add('show');
            }
        } else {
            editTargetId = null;
            modalTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Add Tourist Spot';
            modalSubtitle.textContent = pendingPosition ?
                'Position set. Fill in the details and save.' :
                'Use the map to place a pin, then add the tourist spot details.';
            landmarkName.value = '';
            landmarkDesc.value = '';
            landmarkCategory.value = 'historical';
            modalSave.innerHTML = '<i class="fas fa-check"></i> Save';
            isAddingMode = true;
            if (pendingPosition) {
                setStatus('Position ready — fill in details', 'added');
                updateCoordsDisplay(pendingPosition.lat, pendingPosition.lng);
            } else {
                setStatus('Choose a location on the map', 'pending');
                showToast('Choose a location on the map before entering the tourist spot details.', 'info');
            }
        }
        modalOverlay.classList.add('active');
        setTimeout(() => landmarkName.focus(), 150);
    }

    function closeModal() {
        modalOverlay.classList.remove('active');
        isAddingMode = false;
        clearPhotoPreview();
        photoChanged = false;
        if (!editMode) {
            if (pendingPosition) {
                setStatus('Position set. Click "Add Tourist Spot" to continue.', 'pending');
                updateCoordsDisplay(pendingPosition.lat, pendingPosition.lng);
            } else {
                btnAddLabel.textContent = 'Add Tourist Spot';
                setStatus('Ready', 'idle');
            }
        } else {
            if (selectedId !== null) {
                const lm = getLandmark(selectedId);
                if (lm) {
                    setStatus(`Selected: "${lm.name}"`, 'selected');
                    updateCoordsDisplay(lm.lat, lm.lng);
                }
            }
        }
        if (pendingMarker && !pendingPosition) {
            map.removeLayer(pendingMarker);
            pendingMarker = null;
        }
    }

    function handleModalSave() {
        if (photoProcessing) {
            showToast('Wait for photo compression to finish before saving.', 'info');
            return;
        }

        const name = landmarkName.value.trim();
        if (!name) {
            showToast('Enter a name for this tourist spot.', 'error');
            landmarkName.focus();
            return;
        }
        const desc = landmarkDesc.value.trim();
        const photo = tempPhotoData;
        const category = landmarkCategory.value;

        if (editMode && editTargetId !== null) {
            const lm = getLandmark(editTargetId);
            if (!lm) { showToast('Error: landmark not found', 'error'); return; }
            
            const updatePayload = {
                id: editTargetId,
                name: name,
                description: desc,
                lat: lm.lat,
                lng: lm.lng,
                category: category
            };
            if (photoChanged) updatePayload.photo = photo;

            fetch('../Cadiz/landmarks_api.php?action=update', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(updatePayload)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateLandmark(editTargetId, name, desc, lm.lat, lm.lng, photoChanged ? photo : lm.photo, category);
                    closeModal();
                    selectLandmark(editTargetId);
                    setStatus(`Updated: "${name}"`, 'selected');
                    updateCoordsDisplay(lm.lat, lm.lng);
                } else {
                    showToast('Failed to update landmark: ' + data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Network error updating landmark', 'error');
            });
            return;
        }

        if (!pendingPosition) {
            showToast('Choose a location on the map before adding this tourist spot.', 'error');
            return;
        }

        fetch('../Cadiz/landmarks_api.php?action=add', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name: name,
                description: desc,
                lat: pendingPosition.lat,
                lng: pendingPosition.lng,
                photo: photo,
                category: category
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const lm = addLandmark(name, desc, pendingPosition.lat, pendingPosition.lng, photo, data.id, category);
                pendingPosition = null;
                if (pendingMarker) {
                    map.removeLayer(pendingMarker);
                    pendingMarker = null;
                }
                closeModal();
                selectLandmark(lm.id);
                setStatus(`Added: "${lm.name}"`, 'selected');
                updateCoordsDisplay(lm.lat, lm.lng);
            } else {
                showToast('Failed to add landmark: ' + data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error adding landmark', 'error');
        });
    }

    function initMap() {
        if (map) return;

        const cadizLat = 10.9489;
        const cadizLng = 123.3037;

        map = L.map('map', {
            zoomControl: true,
            dragging: true,
            touchZoom: true,
            doubleClickZoom: true,
            scrollWheelZoom: true,
            zoomAnimation: true,
            fadeAnimation: true,
            attributionControl: true
        }).setView([cadizLat, cadizLng], 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        map.dragging.enable();
        map.touchZoom.enable();
        map.doubleClickZoom.enable();
        map.scrollWheelZoom.enable();
        let _isDraggingPin = false;
        let _dragMarker = null;

        mapElement.addEventListener('click', function (event) {
            const target = event.target;
            if (!(target instanceof Element)) return;
            const actionButton = target.closest('[data-popup-action][data-landmark-id]');
            if (!actionButton) return;

            event.preventDefault();
            event.stopPropagation();
            const landmarkId = actionButton.getAttribute('data-landmark-id');
            const lm = landmarks.find(item => String(item.id) === landmarkId);
            if (!lm) {
                showToast('Landmark not found', 'error');
                return;
            }

            if (actionButton.getAttribute('data-popup-action') === 'edit') {
                openModal('edit', lm);
            } else if (actionButton.getAttribute('data-popup-action') === 'delete') {
                deleteLandmark(lm.id);
            }
        }, true);

        map.on('moveend', function () {
            const center = map.getCenter();
            updateCoordsDisplay(center.lat, center.lng);
        });

        map.on('click', function (e) {
            const latlng = e.latlng;
            updateCoordsDisplay(latlng.lat, latlng.lng);
            if (placementModeActive) {
                setPendingPosition(latlng);
                exitPlacementMode();
                setStatus('Position set. Click "Add Tourist Spot" to enter details.', 'pending');
                return;
            }
            deselectLandmark();
        });

        // ── Press E to toggle placement mode, then drag & drop ─────────────
        // Press E once → enter placement mode (crosshair cursor)
        // Click & drag on the map → pin follows cursor
        // Release mouse → pin drops at that position
        // Press E again or Escape → exit placement mode
        function enterPlacementMode() {
            placementModeActive = true;
            map.dragging.disable();
            map.getContainer().style.cursor = 'crosshair';
            setStatus('Drag and drop on the map to place a tourist spot', 'pending');
            showToast('Click and drag on the map, then release to place the pin.', 'info');
        }

        function exitPlacementMode() {
            placementModeActive = false;
            _isDraggingPin = false;
            map.dragging.enable();
            map.getContainer().style.cursor = '';
            // Remove drag preview marker if still present
            if (_dragMarker) {
                map.removeLayer(_dragMarker);
                _dragMarker = null;
            }
            if (!pendingPosition) btnAddLabel.textContent = 'Add Tourist Spot';
            if (!pendingPosition) {
                setStatus('Ready', 'idle');
            }
        }

        togglePlacementMode = function () {
            if (placementModeActive) {
                exitPlacementMode();
            } else {
                enterPlacementMode();
            }
        };

        document.addEventListener('keydown', function (e) {
            if (e.key === 'e' || e.key === 'E') {
                // Don't activate if typing in an input/textarea or modal is open
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
                if (e.ctrlKey || e.metaKey) return; // let Ctrl+E pass through
                if (modalOverlay.classList.contains('active')) return;

                if (placementModeActive) {
                    exitPlacementMode();
                } else {
                    enterPlacementMode();
                }
            }
        });

        // Mouse down → start dragging a preview pin
        map.getContainer().addEventListener('mousedown', function (e) {
            if (!placementModeActive) return;
            if (e.button !== 0) return;
            if (modalOverlay.classList.contains('active')) return;

            e.preventDefault();
            e.stopPropagation();
            _isDraggingPin = true;

            const rect = map.getContainer().getBoundingClientRect();
            const point = L.point(e.clientX - rect.left, e.clientY - rect.top);
            const latlng = map.containerPointToLatLng(point);

            // Create a preview marker that follows the cursor
            if (_dragMarker) map.removeLayer(_dragMarker);
            _dragMarker = L.marker(latlng, {
                icon: L.divIcon({
                    className: 'pending-marker-icon',
                    html: '<div class="pending-marker-pin" style="opacity:0.7;"></div>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                }),
                interactive: false
            }).addTo(map);

            map.getContainer().style.cursor = 'grabbing';
            setStatus('Dragging… release to drop the pin', 'pending');
        });

        // Mouse move → update preview marker position while dragging
        map.getContainer().addEventListener('mousemove', function (e) {
            if (!_isDraggingPin || !_dragMarker) return;

            const rect = map.getContainer().getBoundingClientRect();
            const point = L.point(e.clientX - rect.left, e.clientY - rect.top);
            const latlng = map.containerPointToLatLng(point);
            _dragMarker.setLatLng(latlng);
            updateCoordsDisplay(latlng.lat, latlng.lng);
        });

        // Mouse up → drop the pin at release position
        map.getContainer().addEventListener('mouseup', function (e) {
            if (!_isDraggingPin) return;
            if (e.button !== 0) return;

            _isDraggingPin = false;

            const rect = map.getContainer().getBoundingClientRect();
            const point = L.point(e.clientX - rect.left, e.clientY - rect.top);
            const latlng = map.containerPointToLatLng(point);

            // Remove preview marker
            if (_dragMarker) {
                map.removeLayer(_dragMarker);
                _dragMarker = null;
            }

            // Place the actual draggable pending marker
            setPendingPosition(latlng);
            isAddingMode = false;

            // Exit placement mode
            exitPlacementMode();

            setStatus('Pin dropped! Drag to adjust, then click "Add Details".', 'pending');
            showToast('Pin dropped! Drag to fine-tune, then click Add Details', 'info');

            // Pulse animation feedback
            if (pendingMarker) {
                const el = pendingMarker.getElement && pendingMarker.getElement();
                if (el) {
                    el.style.transition = 'transform 0.2s ease';
                    el.style.transform = 'scale(1.4)';
                    setTimeout(function () { el.style.transform = 'scale(1)'; }, 200);
                }
            }
        });

        updateCoordsDisplay(cadizLat, cadizLng);

        syncMarkers();

        window.MapApp = window.MapApp || {};
        window.MapApp.map = map;
    }

    btnAdd.addEventListener('click', function () {
        window.location.href = 'map.php';
    });

    modalCancel.addEventListener('click', closeModal);
    modalSave.addEventListener('click', handleModalSave);
    modalOverlay.addEventListener('click', function (e) {
        if (e.target === modalOverlay) closeModal();
    });

    photoInput.addEventListener('change', function (e) {
        if (this.files && this.files[0]) {
            handlePhotoSelect(this.files[0]);
        }
    });

    photoUploadArea.addEventListener('click', function (e) {
        if (e.target.closest('.remove-photo')) return;
        photoInput.click();
    });

    removePhotoBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        clearPhotoPreview();
        photoChanged = true;
    });

    photoUploadArea.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.style.borderColor = '#2b6ef0';
        this.style.background = '#f0f4ff';
    });

    photoUploadArea.addEventListener('dragleave', function (e) {
        e.preventDefault();
        this.style.borderColor = '#d1d9e6';
        this.style.background = '#fafbfc';
    });

    photoUploadArea.addEventListener('drop', function (e) {
        e.preventDefault();
        this.style.borderColor = '#d1d9e6';
        this.style.background = '#fafbfc';
        const files = e.dataTransfer.files;
        if (files && files[0] && files[0].type.startsWith('image/')) {
            handlePhotoSelect(files[0]);
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            photoInput.files = dt.files;
        } else {
            showToast('Please drop an image file', 'error');
        }
    });

    document.querySelectorAll('#landmarkName, #landmarkDesc').forEach(el => {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target === landmarkName) {
                e.preventDefault();
                landmarkDesc.focus();
            }
            if (e.key === 'Enter' && e.target === landmarkDesc) {
                e.preventDefault();
                modalSave.click();
            }
        });
    });

    document.addEventListener('keydown', function (e) {
        const ctrl = e.ctrlKey || e.metaKey;
        if (e.key === 'Escape') {
            if (modalOverlay.classList.contains('active')) {
                closeModal();
            } else {
                if (placementModeActive && togglePlacementMode) {
                    togglePlacementMode();
                }
                deselectLandmark();
                pendingPosition = null;
                if (pendingMarker) {
                    map.removeLayer(pendingMarker);
                    pendingMarker = null;
                }
                btnAddLabel.textContent = 'Add Tourist Spot';
                setStatus('Ready', 'idle');
            }
            return;
        }
        if ((e.key === 'Delete' || e.key === 'Backspace') && !modalOverlay.classList.contains('active') &&
            selectedId !== null) {
            const lm = getLandmark(selectedId);
            if (lm) {
                deleteLandmark(selectedId);
            }
        }
        if (ctrl && e.key === 'a') {
            e.preventDefault();
            if (btnAdd) btnAdd.click();
        }
        if (ctrl && e.key === 'e' && selectedId !== null) {
            const lm = getLandmark(selectedId);
            if (lm) {
                e.preventDefault();
                openModal('edit', lm);
            }
        }
    });

    function loadLandmarks() {
        fetch('../Cadiz/landmarks_api.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    landmarks = data.landmarks;
                    
                    // Determine nextId
                    nextId = 1;
                    landmarks.forEach(lm => {
                        if (lm.id >= nextId) nextId = lm.id + 1;
                    });
                    
                    syncMarkers();
                    if (landmarks.length > 0) {
                        selectLandmark(landmarks[0].id);
                    } else {
                        selectLandmark(null);
                    }
                    setStatus('Ready — click map or use buttons', 'idle');
                    updateLandmarkCount();
                } else {
                    showToast('Failed to load landmarks: ' + data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Network error loading landmarks', 'error');
            });
    }

    window.MapApp = window.MapApp || {};
    window.MapApp.invalidate = function () {
        if (this.map) {
            setTimeout(() => this.map.invalidateSize(), 150);
        }
    };

    initMap();
    loadLandmarks();

    window.__map = { landmarks, map, markerMap, pendingPosition, addLandmark, deleteLandmark, selectLandmark, loadLandmarks };
})();

(function () {
    const calendar = document.getElementById('eventsCalendar');
    const form = document.getElementById('eventCreateForm');
    if (!calendar || !form) return;

    const eventsUrl = '../Cadiz/events_api.php';
    const monthLabel = document.getElementById('calendarMonthLabel');
    const selectedDateLabel = document.getElementById('selectedEventDate');
    const selectedDateEvents = document.getElementById('selectedDateEvents');
    const dateInput = document.getElementById('eventDate');
    const formMessage = document.getElementById('eventFormMessage');
    const saveButton = document.getElementById('saveEventButton');
    const csrfToken = window.adminEventsConfig && window.adminEventsConfig.csrfToken;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    let selectedDate = toDateKey(today);
    let events = [];

    function toDateKey(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function parseDate(dateKey) {
        const parts = dateKey.split('-').map(Number);
        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function eventsForDate(dateKey) {
        return events.filter(event => event.event_date === dateKey);
    }

    function formatDate(dateKey, options) {
        return parseDate(dateKey).toLocaleDateString(undefined, options);
    }

    function renderCalendar() {
        if (monthLabel) {
            monthLabel.textContent = currentMonth.toLocaleDateString(undefined, {
                month: 'long',
                year: 'numeric'
            });
        }
        calendar.replaceChildren();
        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(label => {
            const weekday = document.createElement('span');
            weekday.className = 'calendar-weekday';
            weekday.textContent = label;
            calendar.appendChild(weekday);
        });

        const firstWeekday = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1).getDay();
        const daysInMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0).getDate();
        for (let blank = 0; blank < firstWeekday; blank += 1) {
            const spacer = document.createElement('span');
            spacer.className = 'calendar-day-spacer';
            calendar.appendChild(spacer);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
            const dateKey = toDateKey(date);
            const matchingEvents = eventsForDate(dateKey);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'calendar-day';
            button.textContent = String(day);
            button.setAttribute('aria-label', formatDate(dateKey, {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            }));
            if (dateKey === toDateKey(today)) button.classList.add('is-today');
            if (dateKey === selectedDate) button.classList.add('is-selected');
            if (matchingEvents.length) {
                button.classList.add('has-event');
                const eventNames = matchingEvents.map(event => event.title).join(', ');
                button.title = `${eventNames} — select to view details`;
                button.setAttribute('aria-label', `${button.getAttribute('aria-label')}: ${eventNames}`);
            }
            button.disabled = date < today;
            button.addEventListener('click', () => selectDate(dateKey));
            calendar.appendChild(button);
        }
    }

    function renderSelectedDateEvents() {
        if (!selectedDateEvents || !selectedDateLabel) return;
        selectedDateLabel.textContent = formatDate(selectedDate, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
        selectedDateEvents.replaceChildren();
        const matchingEvents = eventsForDate(selectedDate);
        if (!matchingEvents.length) {
            selectedDateEvents.textContent = 'No events scheduled for this date yet.';
            return;
        }

        matchingEvents.forEach(event => {
            const card = document.createElement('article');
            card.className = 'calendar-event-item';
            const title = document.createElement('strong');
            title.textContent = event.title;
            card.appendChild(title);
            if (event.event_time || event.location) {
                const meta = document.createElement('span');
                meta.className = 'calendar-event-meta';
                const time = event.event_time ? event.event_time.slice(0, 5) : '';
                meta.textContent = [time, event.location].filter(Boolean).join(' · ');
                card.appendChild(meta);
            }
            if (event.description) {
                const description = document.createElement('p');
                description.textContent = event.description;
                card.appendChild(description);
            }
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'calendar-event-remove';
            removeButton.textContent = 'Remove';
            removeButton.setAttribute('aria-label', `Remove ${event.title}`);
            removeButton.addEventListener('click', () => deleteEvent(event.id));
            card.appendChild(removeButton);
            selectedDateEvents.appendChild(card);
        });
    }

    function selectDate(dateKey) {
        selectedDate = dateKey;
        dateInput.value = dateKey;
        formMessage.textContent = '';
        renderCalendar();
        renderSelectedDateEvents();
    }

    async function requestEvents() {
        const response = await fetch(eventsUrl, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success || !Array.isArray(data.events)) {
            throw new Error(data.message || 'Upcoming events could not be loaded.');
        }
        events = data.events;
        renderCalendar();
        renderSelectedDateEvents();
    }

    async function sendEventRequest(payload) {
        const response = await fetch(eventsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrfToken || ''
            },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'The event request failed.');
        }
        return data;
    }

    async function deleteEvent(id) {
        if (!window.confirm('Remove this event from Cadiz Go?')) return;
        try {
            const data = await sendEventRequest({ action: 'delete', id });
            await requestEvents();
            formMessage.textContent = data.message;
        } catch (error) {
            formMessage.textContent = error.message;
        }
    }

    document.getElementById('calendarPreviousMonth').addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
        renderCalendar();
    });
    document.getElementById('calendarNextMonth').addEventListener('click', () => {
        currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
        renderCalendar();
    });
    dateInput.min = toDateKey(today);
    dateInput.value = selectedDate;
    dateInput.addEventListener('change', () => {
        if (!dateInput.value) return;
        const chosenDate = parseDate(dateInput.value);
        currentMonth = new Date(chosenDate.getFullYear(), chosenDate.getMonth(), 1);
        selectDate(dateInput.value);
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        formMessage.textContent = '';
        saveButton.disabled = true;
        try {
            const formData = new FormData(form);
            const data = await sendEventRequest({
                action: 'create',
                title: formData.get('title'),
                event_date: formData.get('event_date'),
                event_time: formData.get('event_time'),
                location: formData.get('location'),
                description: formData.get('description')
            });
            form.reset();
            dateInput.value = selectedDate;
            formMessage.textContent = data.message;
            await requestEvents();
        } catch (error) {
            formMessage.textContent = error.message;
        } finally {
            saveButton.disabled = false;
        }
    });

    renderCalendar();
    requestEvents().catch(error => {
        console.error('Unable to load upcoming events:', error);
        selectedDateEvents.textContent = 'Events could not be loaded. Please refresh the page.';
    });
})();

    (function () {
        const recentBody = document.getElementById('recentFeedbackBody');
        const allBody = document.getElementById('allFeedbackBody');
        const totalElement = document.getElementById('feedbackTotal');
        const averageElement = document.getElementById('feedbackAverage');

        if (!recentBody || !allBody) return;

        function createFeedbackRow(entry) {
            const row = document.createElement('tr');
            const userCell = document.createElement('td');
            const userName = document.createElement('strong');
            userName.textContent = entry.user_name || 'Anonymous';
            userCell.appendChild(userName);

            const placeCell = document.createElement('td');
            const place = document.createElement('span');
            place.style.cssText = 'font-size:13px;font-weight:600;color:#4f46e5;background:#eef0ff;padding:4px 10px;border-radius:12px;white-space:nowrap;';
            place.textContent = entry.place_name || 'General Feedback';
            placeCell.appendChild(place);

            const commentCell = document.createElement('td');
            commentCell.textContent = entry.comment || '';

            const ratingCell = document.createElement('td');
            const stars = document.createElement('span');
            stars.className = 'stars';
            const rating = Math.max(0, Math.min(5, Number(entry.rating) || 0));
            for (let i = 1; i <= 5; i += 1) {
                const star = document.createElement('i');
                star.className = i <= rating ? 'fas fa-star star-filled' : 'far fa-star star-empty';
                star.style.cssText = 'margin-right:2px;color:' + (i <= rating ? '#ffc107' : '#ccc');
                stars.appendChild(star);
            }
            ratingCell.appendChild(stars);

            const dateCell = document.createElement('td');
            dateCell.textContent = entry.date_label || '';

            [userCell, placeCell, commentCell, ratingCell, dateCell].forEach(cell => {
                cell.style.padding = '12px 16px';
                row.appendChild(cell);
            });
            return row;
        }

        function renderFeedbackRows(body, feedback) {
            body.replaceChildren();
            if (feedback.length === 0) {
                const row = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = 5;
                cell.textContent = 'No feedback submitted yet.';
                cell.style.cssText = 'text-align:center;color:#8a95a8;padding:20px;';
                row.appendChild(cell);
                body.appendChild(row);
                return;
            }
            feedback.forEach(entry => body.appendChild(createFeedbackRow(entry)));
        }

        function refreshFeedback() {
            fetch('feedback_api.php', { headers: { Accept: 'application/json' } })
                .then(response => {
                    if (!response.ok) throw new Error('Feedback refresh failed: HTTP ' + response.status);
                    return response.json();
                })
                .then(data => {
                    if (!data.success || !Array.isArray(data.feedback)) {
                        throw new Error(data.message || 'Feedback refresh returned an invalid response.');
                    }
                    renderFeedbackRows(allBody, data.feedback);
                    renderFeedbackRows(recentBody, data.feedback.slice(0, 4));
                    if (totalElement) totalElement.textContent = Number(data.total).toLocaleString();
                    if (averageElement) averageElement.textContent = Number(data.average).toFixed(1);
                })
                .catch(error => console.error(error));
        }

        refreshFeedback();
        window.setInterval(refreshFeedback, 15000);
    })();
