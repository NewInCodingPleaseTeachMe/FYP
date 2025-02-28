<?php
session_set_cookie_params(0, '/');
session_start();

// Check if the user is logged in
$logged_in = isset($_SESSION["user_id"]);
$role = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
$user_name = isset($_SESSION["name"]) ? $_SESSION["name"] : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background-color: #f8f9fa;
            padding: 6rem 0;
            position: relative;
        }
        
        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%230d6efd' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }
        
        .feature-card {
            border-radius: 0.75rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
        }

        .team-card {
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
        }
        
        .team-social {
            position: absolute;
            bottom: -40px;
            left: 0;
            right: 0;
            transition: bottom 0.3s;
        }
        
        .team-card:hover .team-social {
            bottom: 0;
        }
        
        .team-img {
            height: 280px;
            object-fit: cover;
        }
        
        .section-title {
            position: relative;
            display: inline-block;
            margin-bottom: 3rem;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 50px;
            height: 3px;
            background-color: #0d6efd;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 2px;
            height: 100%;
            background-color: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #0d6efd;
            border: 3px solid #fff;
            box-shadow: 0 0 0 1px #0d6efd;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .footer {
            background-color: #212529;
            color: #f8f9fa;
            padding: 4rem 0 1rem;
        }
        
        .footer a {
            color: #adb5bd;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .footer a:hover {
            color: #fff;
        }
        
        .footer-heading {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .footer-link {
            display: block;
            margin-bottom: 0.75rem;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            margin-right: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .social-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <?php if ($logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">Dashboard</a>
                    </li>
                    <?php endif; ?>
                    <?php if ($role === "teacher" || $role === "admin"): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="course_resources.php">Course Resources</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About Us</a>
                    </li>
                </ul>
                
                <div class="d-flex">
                    <?php if ($logged_in): ?>
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($user_name) ?> 
                        <span class="badge bg-light text-primary ms-1"><?= ucfirst($role) ?></span>
                    </span>
                    <a href="../modules/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                    <?php else: ?>
                    <a href="../login.php" class="btn btn-outline-light me-2">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </a>
                    <a href="../register.php" class="btn btn-light">
                        <i class="bi bi-person-plus me-1"></i>Register
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-pattern"></div>
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Connect Campus,<br>Share Knowledge</h1>
                    <p class="lead mb-4">CampusConnect is a learning management system designed for educational institutions, dedicated to providing an efficient and convenient platform for sharing teaching resources.</p>
                    <?php if (!$logged_in): ?>
                    <div class="d-grid gap-2 d-md-flex">
                        <a href="../views/register.php" class="btn btn-primary btn-lg px-4 me-md-2">Join Now</a>
                        <a href="../views/login.php" class="btn btn-outline-secondary btn-lg px-4">Login</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <img src="../assets/images/CampusConnect_Platform.jpg" alt="CampusConnect Platform" class="img-fluid rounded shadow-lg">
                </div>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold mb-3">About CampusConnect</h2>
                    <p class="lead text-muted mb-4">Making educational resource sharing simple and efficient</p>
                    <hr class="my-4">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <p>CampusConnect is a comprehensive learning management system designed for modern educational environments. Our mission is to facilitate seamless interaction and knowledge sharing between teachers and students by providing an intuitive and efficient platform.</p>
                    <p>Founded in 2023, CampusConnect originated from a simple belief: educational resources should be easily accessible, and teaching tools should be user-friendly. Whether in the classroom or remote environment, our platform ensures the continuity and effectiveness of the educational process.</p>
                </div>
                <div class="col-md-6">
                    <p>As a comprehensive educational solution, CampusConnect offers various functions such as course management, resource sharing, assignment submission, and online assessment. Our platform supports multiple file formats, ensuring teachers can share all types of teaching resources from documents to multimedia.</p>
                    <p>We value user experience and system security, constantly optimizing platform functions to ensure secure storage and transmission of information. We believe that through CampusConnect, we can enhance the educational experience and contribute to building a more interconnected learning environment.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Core Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold">Core Features</h2>
                    <p class="lead text-muted">Explore the powerful features CampusConnect offers for teachers and students</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 feature-card border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle p-3 mb-4" style="width: fit-content">
                                <i class="bi bi-folder2-open fs-3"></i>
                            </div>
                            <h4>Resource Management</h4>
                            <p class="text-muted">Easily upload, organize, and share course resources, supporting multiple file formats, including documents, presentations, images, and videos.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 feature-card border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle p-3 mb-4" style="width: fit-content">
                                <i class="bi bi-journal-richtext fs-3"></i>
                            </div>
                            <h4>Course Management</h4>
                            <p class="text-muted">Create and manage course content, set course parameters, monitor student progress, and provide personalized learning experiences.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 feature-card border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle p-3 mb-4" style="width: fit-content">
                                <i class="bi bi-people fs-3"></i>
                            </div>
                            <h4>User Role Management</h4>
                            <p class="text-muted">Flexible user role settings providing different levels of system access and functionality for administrators, teachers, and students.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 feature-card border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle p-3 mb-4" style="width: fit-content">
                                <i class="bi bi-link-45deg fs-3"></i>
                            </div>
                            <h4>External Resource Integration</h4>
                            <p class="text-muted">Easily share educational resources from the web, integrate external links, and expand the range of learning materials.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 feature-card border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle p-3 mb-4" style="width: fit-content">
                                <i class="bi bi-shield-check fs-3"></i>
                            </div>
                            <h4>Security and Privacy</h4>
                            <p class="text-muted">Strong security measures ensure the confidentiality of user data and educational resources, meeting the data protection standards of modern educational institutions.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 feature-card border-0">
                        <div class="card-body p-4">
                            <div class="feature-icon bg-primary bg-gradient text-white rounded-circle p-3 mb-4" style="width: fit-content">
                                <i class="bi bi-phone fs-3"></i>
                            </div>
                            <h4>Responsive Design</h4>
                            <p class="text-muted">Fully responsive interface design ensures an excellent user experience on any device, from desktop computers to mobile devices.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Development Timeline Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold">Development Timeline</h2>
                    <p class="lead text-muted">The growth journey of CampusConnect</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="timeline">
                        <div class="timeline-item">
                            <h5>January 2023 - Project Launch</h5>
                            <p class="text-muted">CampusConnect project officially launched, beginning requirements analysis and system design.</p>
                        </div>
                        <div class="timeline-item">
                            <h5>April 2023 - First Version Release</h5>
                            <p class="text-muted">CampusConnect 1.0 version released, providing basic course management and resource sharing functions.</p>
                        </div>
                        <div class="timeline-item">
                            <h5>July 2023 - Feature Expansion</h5>
                            <p class="text-muted">Added external resource linking functionality, support for more file formats, and optimized user interface.</p>
                        </div>
                        <div class="timeline-item">
                            <h5>October 2023 - Mobile Support</h5>
                            <p class="text-muted">Completed responsive design transformation, providing comprehensive mobile device support, enhancing user experience.</p>
                        </div>
                        <div class="timeline-item">
                            <h5>January 2024 - Security Enhancement</h5>
                            <p class="text-muted">Comprehensive upgrade of system security, optimization of data protection strategy, and improved platform stability.</p>
                        </div>
                        <div class="timeline-item">
                            <h5>Present - Continuous Innovation</h5>
                            <p class="text-muted">We are continuously improving platform functions, listening to user feedback, and planning new features and improvements for future development.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Introduction Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold">Our Team</h2>
                    <p class="lead text-muted">Meet the creators behind CampusConnect</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card team-card border-0">
                        <img src="../assets/images/雷军.jpg" class="team-img" alt="Team Member">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-1">Lei Jun</h5>
                            <p class="text-muted mb-3">Founder & CEO</p>
                            <p class="card-text small">Educational technology expert with 10 years of experience in educational software development, dedicated to innovative educational solutions.</p>
                        </div>
                        <div class="team-social bg-primary text-center py-2">
                            <a href="#" class="text-white mx-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card team-card border-0">
                        <img src="../assets/images/Evans_Hankey.jpeg" class="team-img" alt="Team Member">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-1">Evans Hankey</h5>
                            <p class="text-muted mb-3">Product Design Director</p>
                            <p class="card-text small">User experience expert focused on creating intuitive, user-friendly educational product interfaces, pursuing excellent user experience.</p>
                        </div>
                        <div class="team-social bg-primary text-center py-2">
                            <a href="#" class="text-white mx-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-dribbble"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card team-card border-0">
                        <img src="../assets/images/jesenhuang.jpg" class="team-img" alt="Team Member">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-1">Jesen Huang</h5>
                            <p class="text-muted mb-3">Technical Lead</p>
                            <p class="card-text small">Full-stack development engineer proficient in multiple programming languages and frameworks, responsible for system architecture and core functionality development.</p>
                        </div>
                        <div class="team-social bg-primary text-center py-2">
                            <a href="#" class="text-white mx-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-github"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-envelope"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card team-card border-0">
                        <img src="../assets/images/google.jpg" class="team-img" alt="Team Member">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-1">Chen Xue</h5>
                            <p class="text-muted mb-3">Customer Success Manager</p>
                            <p class="card-text small">Educational consultant focusing on customer needs and feedback, ensuring the product meets various requirements in actual teaching scenarios.</p>
                        </div>
                        <div class="team-social bg-primary text-center py-2">
                            <a href="#" class="text-white mx-2"><i class="bi bi-linkedin"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-twitter"></i></a>
                            <a href="#" class="text-white mx-2"><i class="bi bi-envelope"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Us Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold">Contact Us</h2>
                    <p class="lead text-muted">For any questions or suggestions, feel free to contact us anytime</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-primary bg-gradient text-white d-inline-flex align-items-center justify-content-center mb-4" style="width: 60px; height: 60px;">
                                <i class="bi bi-envelope-fill fs-4"></i>
                            </div>
                            <h5>Email</h5>
                            <p class="text-muted">info@campusconnect.com<br>support@campusconnect.com</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-primary bg-gradient text-white d-inline-flex align-items-center justify-content-center mb-4" style="width: 60px; height: 60px;">
                                <i class="bi bi-telephone-fill fs-4"></i>
                            </div>
                            <h5>Phone</h5>
                            <p class="text-muted">+86 10 8888 7777<br>+86 400 123 4567</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="rounded-circle bg-primary bg-gradient text-white d-inline-flex align-items-center justify-content-center mb-4" style="width: 60px; height: 60px;">
                                <i class="bi bi-geo-alt-fill fs-4"></i>
                            </div>
                            <h5>Address</h5>
                            <p class="text-muted">Innovation Building B, 15th Floor<br>Zhongguancun Science Park, Haidian District, Beijing</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5 class="footer-heading">CampusConnect</h5>
                    <p class="text-muted mb-4">A comprehensive learning management system that connects campuses, shares knowledge, and enhances the educational experience.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-linkedin"></i></a>
                        <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h5 class="footer-heading">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="../index.php" class="footer-link">Home</a></li>
                        <li><a href="../dashboard.php" class="footer-link">Dashboard</a></li>
                        <li><a href="about.php" class="footer-link">About Us</a></li>
                    </ul>
                </div>


            </div>
            <hr class="mt-5 mb-4" style="background-color: rgba(255, 255, 255, 0.1);">
            <div class="row">
                <div class="col-md-6 mb-3 mb-md-0">
                    <p class="text-muted mb-0">&copy; <?= date('Y') ?> CampusConnect. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">Designed and developed by <a href="#" class="text-muted">CampusConnect Team</a></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>