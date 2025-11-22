<?php
/**
 * Sitemap Viewer & Manager
 * Admin tool to view and test the sitemap
 */

// Check if user is logged in as admin
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'db_connect.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$sitemapUrl = $baseUrl . '/sitemap.php';

// Get statistics
$stats = [
    'total_scholarships' => 0,
    'active_scholarships' => 0,
    'total_applications' => 0,
    'total_students' => 0,
    'total_staff' => 0,
];

try {
    $result = $conn->query("SELECT COUNT(*) as count FROM scholarships");
    if ($result) {
        $stats['total_scholarships'] = $result->fetch_assoc()['count'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM scholarships WHERE status = 'active'");
    if ($result) {
        $stats['active_scholarships'] = $result->fetch_assoc()['count'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM applications");
    if ($result) {
        $stats['total_applications'] = $result->fetch_assoc()['count'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    if ($result) {
        $stats['total_students'] = $result->fetch_assoc()['count'];
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM staff");
    if ($result) {
        $stats['total_staff'] = $result->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    error_log('Sitemap Viewer: ' . $e->getMessage());
}

// Calculate total URLs in sitemap
$totalUrls = 5 + // Static pages
             $stats['active_scholarships'] + // Scholarship pages
             6 + // Admin pages
             1 + // Staff pages
             1;  // Student pages
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitemap Viewer - ScholarSeek</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .header p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .info-box strong {
            color: #667eea;
        }

        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            margin-bottom: 15px;
        }

        .url-list {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
        }

        .url-item {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s;
        }

        .url-item:hover {
            background: #f0f0f0;
        }

        .url-item:last-child {
            border-bottom: none;
        }

        .url-item i {
            color: #667eea;
            min-width: 20px;
        }

        .url-item a {
            color: #667eea;
            text-decoration: none;
            word-break: break-all;
        }

        .url-item a:hover {
            text-decoration: underline;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            border-top: 1px solid #e0e0e0;
            font-size: 0.85rem;
            color: #666;
            text-align: center;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .content {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-sitemap"></i>
                Sitemap Viewer
            </h1>
            <p>Manage and monitor your ScholarSeek sitemap</p>
        </div>

        <div class="content">
            <!-- Statistics Section -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Sitemap Statistics
                </h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="value"><?php echo $totalUrls; ?></div>
                        <div class="label">Total URLs</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['active_scholarships']; ?></div>
                        <div class="label">Active Scholarships</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['total_applications']; ?></div>
                        <div class="label">Applications</div>
                    </div>
                    <div class="stat-card">
                        <div class="value"><?php echo $stats['total_students']; ?></div>
                        <div class="label">Students</div>
                    </div>
                </div>
            </div>

            <!-- Actions Section -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-cog"></i>
                    Actions
                </h2>
                
                <div class="action-buttons">
                    <a href="<?php echo $sitemapUrl; ?>" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i>
                        View Sitemap XML
                    </a>
                    <button onclick="testSitemap()" class="btn btn-secondary">
                        <i class="fas fa-check-circle"></i>
                        Test Sitemap
                    </button>
                    <button onclick="copySitemapUrl()" class="btn btn-secondary">
                        <i class="fas fa-copy"></i>
                        Copy URL
                    </button>
                </div>
            </div>

            <!-- Configuration Section -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-cogs"></i>
                    Configuration
                </h2>
                
                <div class="info-box">
                    <strong>Sitemap URL:</strong><br>
                    <code><?php echo $sitemapUrl; ?></code>
                </div>

                <div class="info-box">
                    <strong>Add to robots.txt:</strong>
                </div>
                <div class="code-block">
Sitemap: <?php echo $sitemapUrl; ?>
                </div>

                <div class="info-box">
                    <strong>Submit to Search Engines:</strong>
                </div>
                <div class="action-buttons">
                    <a href="https://www.google.com/webmasters/tools/submit-url" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-google"></i>
                        Google Search Console
                    </a>
                    <a href="https://www.bing.com/webmaster/home/mysites" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-search"></i>
                        Bing Webmaster
                    </a>
                </div>
            </div>

            <!-- URL Preview Section -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    URL Preview
                </h2>
                
                <div class="info-box">
                    <strong>Static Pages:</strong> <?php echo count([
                        '/', '/index.html', '/login.php', '/register.php', '/legal.php'
                    ]); ?> URLs
                </div>

                <div class="url-list">
                    <div class="url-item">
                        <i class="fas fa-home"></i>
                        <a href="<?php echo $baseUrl; ?>/" target="_blank"><?php echo $baseUrl; ?>/</a>
                        <span class="badge badge-success">Homepage</span>
                    </div>
                    <div class="url-item">
                        <i class="fas fa-sign-in-alt"></i>
                        <a href="<?php echo $baseUrl; ?>/login.php" target="_blank"><?php echo $baseUrl; ?>/login.php</a>
                        <span class="badge badge-info">Public</span>
                    </div>
                    <div class="url-item">
                        <i class="fas fa-user-plus"></i>
                        <a href="<?php echo $baseUrl; ?>/register.php" target="_blank"><?php echo $baseUrl; ?>/register.php</a>
                        <span class="badge badge-info">Public</span>
                    </div>
                    <div class="url-item">
                        <i class="fas fa-file-alt"></i>
                        <a href="<?php echo $baseUrl; ?>/legal.php" target="_blank"><?php echo $baseUrl; ?>/legal.php</a>
                        <span class="badge badge-info">Public</span>
                    </div>
                    <div class="url-item">
                        <i class="fas fa-graduation-cap"></i>
                        <strong><?php echo $stats['active_scholarships']; ?> Active Scholarship URLs</strong>
                        <span class="badge badge-success">Dynamic</span>
                    </div>
                </div>
            </div>

            <!-- Information Section -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Information
                </h2>
                
                <div class="info-box">
                    <strong>What is a Sitemap?</strong><br>
                    A sitemap is an XML file that lists all the important pages on your website. Search engines use it to discover and index your content more efficiently.
                </div>

                <div class="info-box">
                    <strong>How Often is it Updated?</strong><br>
                    The sitemap is dynamically generated on each request and cached for 1 hour. New scholarships and applications are automatically included.
                </div>

                <div class="info-box">
                    <strong>SEO Benefits:</strong><br>
                    ✓ Helps search engines discover all pages<br>
                    ✓ Improves indexing speed<br>
                    ✓ Provides page priority information<br>
                    ✓ Indicates update frequency
                </div>
            </div>
        </div>

        <div class="footer">
            <p>Sitemap Viewer • ScholarSeek Admin Tool</p>
        </div>
    </div>

    <script>
        function testSitemap() {
            const sitemapUrl = '<?php echo $sitemapUrl; ?>';
            
            fetch(sitemapUrl)
                .then(response => {
                    if (response.ok) {
                        alert('✓ Sitemap is working correctly!\n\nStatus: ' + response.status);
                    } else {
                        alert('✗ Sitemap returned status: ' + response.status);
                    }
                })
                .catch(error => {
                    alert('✗ Error testing sitemap:\n' + error.message);
                });
        }

        function copySitemapUrl() {
            const sitemapUrl = '<?php echo $sitemapUrl; ?>';
            navigator.clipboard.writeText(sitemapUrl).then(() => {
                alert('Sitemap URL copied to clipboard!');
            }).catch(err => {
                alert('Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>
