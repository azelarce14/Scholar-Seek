<?php
/**
 * Dynamic Sitemap Generator for ScholarSeek
 * Generates XML sitemap with all pages and database-driven content
 * 
 * Usage: scholarseek.com/sitemap.php
 * Robots.txt entry: Sitemap: https://scholarseek.com/sitemap.php
 */

// Set XML header
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Get base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$domain = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $domain;

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Include database connection
require_once 'db_connect.php';

// Array to store all URLs
$urls = [];

// 1. STATIC PAGES
$staticPages = [
    ['url' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['url' => '/index.html', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['url' => '/login.php', 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['url' => '/register.php', 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['url' => '/legal.php', 'priority' => '0.5', 'changefreq' => 'yearly'],
];

foreach ($staticPages as $page) {
    $urls[] = [
        'loc' => $baseUrl . $page['url'],
        'lastmod' => date('Y-m-d'),
        'changefreq' => $page['changefreq'],
        'priority' => $page['priority']
    ];
}

// 2. SCHOLARSHIPS (Public-facing)
try {
    $query = "SELECT id, created_at FROM scholarships WHERE status = 'active' ORDER BY created_at DESC";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $urls[] = [
                'loc' => $baseUrl . '/apply_scholarship.php?id=' . urlencode($row['id']),
                'lastmod' => date('Y-m-d', strtotime($row['created_at'])),
                'changefreq' => 'weekly',
                'priority' => '0.8'
            ];
        }
    }
} catch (Exception $e) {
    error_log('Sitemap: Error fetching scholarships - ' . $e->getMessage());
}

// 3. ADMIN PAGES (if user is logged in as admin)
// Note: These are included for completeness but may require authentication
$adminPages = [
    ['url' => '/admin_dashboard.php', 'priority' => '0.7', 'changefreq' => 'daily'],
    ['url' => '/manage_applications.php', 'priority' => '0.7', 'changefreq' => 'daily'],
    ['url' => '/manage_scholarships.php', 'priority' => '0.7', 'changefreq' => 'daily'],
    ['url' => '/manage_students.php', 'priority' => '0.6', 'changefreq' => 'daily'],
    ['url' => '/manage_staff.php', 'priority' => '0.6', 'changefreq' => 'daily'],
];

foreach ($adminPages as $page) {
    $urls[] = [
        'loc' => $baseUrl . $page['url'],
        'lastmod' => date('Y-m-d'),
        'changefreq' => $page['changefreq'],
        'priority' => $page['priority']
    ];
}

// 4. STAFF PAGES
$staffPages = [
    ['url' => '/staff_dashboard.php', 'priority' => '0.7', 'changefreq' => 'daily'],
];

foreach ($staffPages as $page) {
    $urls[] = [
        'loc' => $baseUrl . $page['url'],
        'lastmod' => date('Y-m-d'),
        'changefreq' => $page['changefreq'],
        'priority' => $page['priority']
    ];
}

// 5. STUDENT PAGES
$studentPages = [
    ['url' => '/student_dashboard.php', 'priority' => '0.7', 'changefreq' => 'daily'],
];

foreach ($studentPages as $page) {
    $urls[] = [
        'loc' => $baseUrl . $page['url'],
        'lastmod' => date('Y-m-d'),
        'changefreq' => $page['changefreq'],
        'priority' => $page['priority']
    ];
}

// Remove duplicates based on 'loc'
$uniqueUrls = [];
$seenUrls = [];

foreach ($urls as $url) {
    if (!isset($seenUrls[$url['loc']])) {
        $seenUrls[$url['loc']] = true;
        $uniqueUrls[] = $url;
    }
}

// Output all URLs
foreach ($uniqueUrls as $url) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo "    <changefreq>" . htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
    echo "    <priority>" . htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
?>
