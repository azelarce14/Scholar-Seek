<?php
$page_title = "Legal - ScholarSeek";
$current_page = "legal";

// Determine which section to show
$section = $_GET['section'] ?? 'terms';
$valid_sections = ['terms', 'privacy'];
if (!in_array($section, $valid_sections)) {
    $section = 'terms';
}

$page_titles = [
    'terms' => 'Terms of Use',
    'privacy' => 'Privacy Policy'
];

$current_title = $page_titles[$section];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_title . ' - ScholarSeek'; ?></title>
    <link rel="stylesheet" href="assets/css/legal.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" href="assets/img/icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="legal-wrapper">
        <div class="legal-container">
            <!-- Header -->
            <div class="legal-header">
                <a href="login.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Login
                </a>
                <div class="logo-section">
                    <img src="assets/img/logo.png" alt="ScholarSeek Logo" class="logo-img">
                    <h1><?php echo $current_title; ?></h1>
                </div>
                <p class="last-updated">Last updated: <?php echo date('F j, Y'); ?></p>
                
                <!-- Navigation Tabs -->
                <div class="legal-tabs">
                    <a href="?section=terms" class="tab-button <?php echo $section === 'terms' ? 'active' : ''; ?>">
                        <i class="fas fa-file-contract"></i>
                        Terms of Use
                    </a>
                    <a href="?section=privacy" class="tab-button <?php echo $section === 'privacy' ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i>
                        Privacy Policy
                    </a>
                </div>
            </div>

            <!-- Content -->
            <div class="legal-content">
                <?php if ($section === 'terms'): ?>
                    <!-- Terms of Use Content -->
                    <section class="content-section">
                        <h2>1. Acceptance of Terms</h2>
                        <p>By accessing and using ScholarSeek, you accept and agree to be bound by the terms and provision of this agreement.</p>
                    </section>

                    <section class="content-section">
                        <h2>2. Use License</h2>
                        <p>Permission is granted to temporarily use ScholarSeek for personal, non-commercial transitory viewing only. This is the grant of a license, not a transfer of title.</p>
                    </section>

                    <section class="content-section">
                        <h2>3. User Account</h2>
                        <p>When you create an account with us, you must provide accurate and complete information. You are responsible for safeguarding the password and for all activities that occur under your account.</p>
                    </section>

                    <section class="content-section">
                        <h2>4. Scholarship Applications</h2>
                        <p>All scholarship applications submitted through ScholarSeek must contain accurate and truthful information. Providing false information may result in immediate termination of your account and disqualification from scholarship opportunities.</p>
                    </section>

                    <section class="content-section">
                        <h2>5. Privacy</h2>
                        <p>Your privacy is important to us. Please read our Privacy Policy to understand how we collect, use, and protect your personal information.</p>
                    </section>

                    <section class="content-section">
                        <h2>6. Intellectual Property</h2>
                        <p>The ScholarSeek platform and its original content, features, and functionality are owned by ScholarSeek and are protected by international copyright, trademark, and other intellectual property laws.</p>
                    </section>

                    <section class="content-section">
                        <h2>7. Termination</h2>
                        <p>We may terminate or suspend your account immediately, without prior notice or liability, for any reason whatsoever, including without limitation if you breach the Terms.</p>
                    </section>

                    <section class="content-section">
                        <h2>8. Changes to Terms</h2>
                        <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. We will provide notice of any changes by posting the new Terms on this page.</p>
                    </section>

                    <section class="content-section">
                        <h2>9. Contact Information</h2>
                        <p>If you have any questions about these Terms, please contact us at:</p>
                        <div class="contact-info">
                            <p><i class="fas fa-envelope"></i> legal@scholarseek.edu.ph</p>
                            <p><i class="fas fa-phone"></i> +63 2 1234 5678</p>
                            <p><i class="fas fa-map-marker-alt"></i> Biliran, Eastern Visayas, Philippines</p>
                        </div>
                    </section>

                <?php else: ?>
                    <!-- Privacy Policy Content -->
                    <section class="content-section">
                        <h2>1. Information We Collect</h2>
                        <p>We collect information you provide directly to us, including:</p>
                        <ul>
                            <li>Personal identification information (Name, email address, phone number)</li>
                            <li>Academic information (Grades, course details, institution)</li>
                            <li>Financial information for scholarship eligibility assessment</li>
                            <li>Application documents and supporting materials</li>
                        </ul>
                    </section>

                    <section class="content-section">
                        <h2>2. How We Use Your Information</h2>
                        <p>We use the information we collect to:</p>
                        <ul>
                            <li>Process and evaluate scholarship applications</li>
                            <li>Communicate with you about your application status</li>
                            <li>Improve our services and platform</li>
                            <li>Send important updates and notifications</li>
                            <li>Comply with legal obligations</li>
                        </ul>
                    </section>

                    <section class="content-section">
                        <h2>3. Information Sharing</h2>
                        <p>We do not sell, trade, or rent your personal identification information to others. We may share generic aggregated demographic information not linked to any personal identification information regarding visitors and users with our business partners and trusted affiliates.</p>
                    </section>

                    <section class="content-section">
                        <h2>4. Data Security</h2>
                        <p>We implement appropriate data collection, storage, and processing practices and security measures to protect against unauthorized access, alteration, disclosure, or destruction of your personal information.</p>
                    </section>

                    <section class="content-section">
                        <h2>5. Data Retention</h2>
                        <p>We retain your personal information only for as long as necessary to fulfill the purposes for which we collected it, including for the purposes of satisfying any legal, accounting, or reporting requirements.</p>
                    </section>

                    <section class="content-section">
                        <h2>6. Your Rights</h2>
                        <p>You have the right to:</p>
                        <ul>
                            <li>Access and receive a copy of your personal data</li>
                            <li>Rectify or update your personal data</li>
                            <li>Request deletion of your personal data</li>
                            <li>Object to processing of your personal data</li>
                            <li>Request data portability</li>
                        </ul>
                    </section>

                    <section class="content-section">
                        <h2>7. Cookies</h2>
                        <p>We use cookies and similar tracking technologies to track activity on our platform and hold certain information. Cookies are files with a small amount of data which may include an anonymous unique identifier.</p>
                    </section>

                    <section class="content-section">
                        <h2>8. Changes to This Policy</h2>
                        <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date.</p>
                    </section>

                    <section class="content-section">
                        <h2>9. Contact Us</h2>
                        <p>If you have any questions about this Privacy Policy, please contact us:</p>
                        <div class="contact-info">
                            <p><i class="fas fa-envelope"></i> privacy@scholarseek.edu.ph</p>
                            <p><i class="fas fa-phone"></i> +63 2 1234 5678</p>
                            <p><i class="fas fa-map-marker-alt"></i> Data Protection Office, Biliran, Eastern Visayas, Philippines</p>
                        </div>
                    </section>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="legal-footer">
                <p>&copy; <?php echo date('Y'); ?> ScholarSeek. All rights reserved.</p>
                <div class="footer-links">
                    <?php if ($section === 'terms'): ?>
                        <a href="?section=privacy">Privacy Policy</a>
                    <?php else: ?>
                        <a href="?section=terms">Terms of Use</a>
                    <?php endif; ?>
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add active state to sections when scrolling
        const sections = document.querySelectorAll('.content-section');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        }, { threshold: 0.1 });

        sections.forEach(section => {
            observer.observe(section);
        });

        // Tab switching with smooth transition
        document.querySelectorAll('.tab-button').forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    document.querySelector('.legal-content').style.opacity = '0.7';
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 150);
                }
            });
        });
    </script>
</body>
</html>