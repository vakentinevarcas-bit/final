<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadiz Go - City of Spontaneity</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <nav id="navbar">
        <div class="container">
            <a href="#" class="logo">Cadiz <span>Go</span></a>
            <ul class="nav-links" id="navLinks">
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#events">Events</a></li>
                <li><a href="#spots">Tourist Spots</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <section id="home" class="hero">
        <video class="hero-video" autoplay muted loop playsinline>
            <source src="images/CadizGoCover.mp4" type="video/mp4">
        </video>
        <div class="hero-content">
            <h1>CadizGo</h1>
            <p>Bilis Cadiz Ugyon Cadiznon</p>
        </div>
    </section>

    <section class="about" id="about">
        <div class="container">
            <div class="about-image">
                <img
                    src=""
                    alt="Wala ko kabalo sng ibutang"
                    loading="lazy"
                />
            </div>
            <div class="about-content">
                <h2>About <span>Cadiz Go</span></h2>
                <p>
                   CadizGO is a smart tourism and local guide platform developed to showcase the attractions, culture, and businesses of Cadiz City, Negros Occidental. It helps both tourists and residents easily explore the city by providing information on beaches, food destinations, shopping areas, and government services.
                </p>        
                <p>
                    Known as the <strong>Seafood Capital of Negros Occidental</strong> and home of the vibrant Dinagsa Festival, Cadiz City offers rich coastal beauty, fresh marine delicacies, and warm local hospitality.
                </p>
                <div class="about-features">
                    <div class="feat"><i class="fas fa-fish"></i> Fresh Seafood</div>
                    <div class="feat"><i class="fas fa-umbrella-beach"></i> Island Getaways</div>
                    <div class="feat"><i class="fas fa-mask"></i> Dinagsa Festival</div>
                    <div class="feat"><i class="fas fa-city"></i> Agro-Industrial Hub</div>
                </div>
            </div>
        </div>
    </section>

    <section class="events-section" id="events" aria-labelledby="eventsTitle">
        <div class="container">
            <h2 class="section-title" id="eventsTitle">Upcoming <span>Events</span></h2>
            <p class="section-sub">See what's happening soon in Cadiz City.</p>
            <div class="public-events-grid" id="publicEventsList" aria-live="polite" aria-busy="true">
                <p class="public-events-message">Loading upcoming events...</p>
            </div>
        </div>
    </section>

    <section class="spots" id="spots">
        <div class="container">
            <h2 class="section-title">Top <span>Tourist Spots</span></h2>
            <p class="section-sub">
                Discover the vibrant sights of Cadiz City — from island sanctuaries to local heritage landmarks.
            </p>
            <div class="spots-grid" id="spotsGrid" aria-live="polite" aria-busy="true">
                <p class="spots-message">Loading tourist spots…</p>
            </div>
        </div>
    </section>

    <section class="contact" id="contact">
        <div class="container">
            <h2 class="section-title">Get in <span>Touch</span></h2>
            <p class="section-sub">
                Have questions about Cadiz City? Reach out to us via email or scan our QR code to connect instantly.
            </p>
            <div class="contact-wrapper">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span><a href="mailto:info@cadizgo.com">info@cadizgo.com</a></span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone-alt"></i>
                        <span><a href="tel:+63344930000">+63 (034) 493-0000</a></span>
                    </div>
                    <button id="showQrBtn" class="btn-primary">
                        <i class="fas fa-qrcode"></i> Show QR Code
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeQrModal">&times;</span>
            <h2>Scan to <span>Connect</span></h2>
            <p>Scan this QR code to send us an email or save our contact details.</p>
            <img id="qrCodeImage" src="" alt="QR Code for Cadiz Go contact" />
            <div class="modal-contact-detail">
                <i class="fas fa-envelope"></i> info@cadizgo.com
            </div>
        </div>
    </div>

    <footer id="footer">
        <div class="container">
            <div class="footer-top">
                <div class="footer-brand">
                    <div class="footer-logo-box">
                        <img src="images/logo.png" alt="Cadiz City Logo" class="footer-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <span class="logo-placeholder-text"><i class="fas fa-image"></i> logo sng aton app</span>
                    </div>
                    <h3>Cadiz <span>Go</span></h3>
                    <p>Your guide to the Seafood Capital of Negros Occidental, Philippines.</p>
                    <p style="margin-top: 12px; color: #cbd5e1; font-size: 0.9rem;">
                        <i class="fas fa-envelope"></i> <a href="mailto:info@cadizgo.com" style="color: #cbd5e1;">info@cadizgo.com</a>
                    </p>
                </div>
                <div class="footer-links">
                    <ul>
                        <li>Explore</li>
                        <li><a href="#spots">Tourist Spots</a></li>
                        <li><a href="#about">About Cadiz</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                    </ul>
                    <ul>
                        <li>City Information</li>
                        <li><a href="#">Dinagsa Festival</a></li>
                        <li><a href="#">Local Cuisine</a></li>
                        <li><a href="#">Hotels & Resorts</a></li>
                    </ul>
                    <ul>
                        <li>Connect</li>
                        <li><a href="#">Facebook</a></li>
                        <li><a href="#">Instagram</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; 6121 Cadiz Go — Cadiz City, Negros Occidental</span>
                <div class="socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <button id="backToTop" aria-label="Back to top">
        <i class="fas fa-chevron-up"></i>
    </button>    
    <script src="js/index.js"></script>
</body>
</html>