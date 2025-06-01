<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NDA Armed Forces Selection Board</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #1C6B4C;
            --regimental-red: #A62828;
            --gold-accent: #F7D774;
            --jet-black: #1F1F1F;
            --soft-white: #F8F9FA;
            --ash-grey: #D9D9D9;
            --slate-blue: #2B3D54;
            --success-green: #2DC26C;
            --warning-yellow: #FFCB05;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--soft-white);
            color: var(--jet-black);
        }
        
        .navbar {
            background-color: var(--primary-green);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 1rem;
        }
        
        .navbar-brand img {
            height: 50px;
        }
        
        .navbar-nav .nav-link {
            color: var(--soft-white);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: var(--gold-accent);
        }
        
        .btn-login {
            background-color: transparent;
            border: 2px solid var(--gold-accent);
            color: var(--gold-accent);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background-color: var(--gold-accent);
            color: var(--primary-green);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--slate-blue) 0%, var(--primary-green) 100%);
            color: var(--soft-white);
            padding: 5rem 0;
        }
        
        .hero-content h1 {
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        
        .hero-content p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .hero-image {
            position: relative;
        }
        
        .hero-image img {
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .about-section {
            padding: 5rem 0;
            background-color: var(--soft-white);
        }
        
        .section-title {
            position: relative;
            margin-bottom: 3rem;
            color: var(--primary-green);
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 0;
            width: 80px;
            height: 4px;
            background-color: var(--gold-accent);
        }
        
        .about-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            height: 100%;
            transition: transform 0.3s ease;
        }
        
        .about-card:hover {
            transform: translateY(-5px);
        }
        
        .about-icon {
            font-size: 2.5rem;
            color: var(--primary-green);
            margin-bottom: 1.5rem;
        }
        
        footer {
            background-color: var(--jet-black);
            color: var(--soft-white);
            padding: 2rem 0;
        }
        
        .footer-logo {
            height: 40px;
            margin-bottom: 1rem;
        }
        
        /* Login Modal Styles */
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            background-color: var(--primary-green);
            color: var(--soft-white);
            border-radius: 10px 10px 0 0;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--slate-blue);
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 5px;
            border: 1px solid var(--ash-grey);
        }
        
        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.25rem rgba(28, 107, 76, 0.25);
        }
        
        .btn-submit {
            background-color: var(--primary-green);
            color: white;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            background-color: var(--slate-blue);
            color: white;
        }
        
        .btn-cancel {
            background-color: var(--ash-grey);
            color: var(--jet-black);
            padding: 0.75rem 2rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background-color: var(--jet-black);
            color: white;
        }
        
        .avatar-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1rem;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .hero-section {
                padding: 3rem 0;
            }
            
            .hero-image {
                margin-top: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .navbar-brand img {
                height: 40px;
            }
            
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .about-card {
                margin-bottom: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="img/nda-logo.png" alt="NDA Logo" class="img-fluid">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                </ul>
                <div class="d-flex ms-3">
                    <button class="btn btn-login me-2" data-bs-toggle="modal" data-bs-target="#officerLoginModal">
                        <i class="fas fa-user-shield me-2"></i>Officer Login
                    </button>
                    <button class="btn btn-login" data-bs-toggle="modal" data-bs-target="#adminLoginModal">
                        <i class="fas fa-user-cog me-2"></i>Admin Login
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content" data-aos="fade-right" data-aos-duration="1000">
                    <h1>Nigerian Defence Academy</h1>
                    <h2 class="mb-4">Armed Forces Selection Board</h2>
                    <p class="mb-4">A streamlined web-based screening system for efficient candidate evaluation, documentation verification, and selection process management.</p>
                </div>
                <div class="col-lg-6 hero-image" data-aos="fade-left" data-aos-duration="1000" data-aos-delay="200">
                    <img src="img/screen.jpeg" alt="NDA Cadets" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">About The System</h2>
            <div class="row">
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Secure Screening</h4>
                        <p>Our system ensures a secure, transparent, and efficient screening process for all candidates applying to the Nigerian Defence Academy.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h4>Streamlined Process</h4>
                        <p>From documentation verification to board interviews, our platform simplifies candidate evaluation and progress tracking across all stages.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="about-card">
                        <div class="about-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>Data-Driven Selection</h4>
                        <p>State-based screening officers and board chairmen can efficiently monitor, evaluate, and make informed decisions based on comprehensive data.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <img src="img/nda-logo.png" alt="NDA Logo" class="footer-logo">
                    <p>© 2025 Nigerian Defence Academy. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <p>Armed Forces Selection Board Screening System</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Officer Login Modal -->
    <div class="modal fade" id="officerLoginModal" tabindex="-1" aria-labelledby="officerLoginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="officerLoginModalLabel">Screening Officer Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <form id="officerLoginForm" action="officer_login.php" method="post">
                        <div class="mb-3">
                            <label for="officerUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="officerUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="officerPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="officerPassword" name="password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-submit">Login</button>
                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <div class="modal fade" id="adminLoginModal" tabindex="-1" aria-labelledby="adminLoginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminLoginModalLabel">Board Chairman Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <form id="adminLoginForm" action="admin_login.php" method="post">
                        <div class="mb-3">
                            <label for="adminUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="adminUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="adminPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="adminPassword" name="password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-submit">Login</button>
                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init();

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>