<?php
/**
 * SEO Helper Functions
 * 
 * Provides consistent SEO metadata generation across all pages
 * Includes meta tags, structured data, and Open Graph tags
 * 
 * @author ScholarSeek Team
 * @version 1.0
 */

class SEOHelper {
    
    /**
     * Generate meta tags for a page
     * 
     * @param array $config {
     *     'title' => 'Page Title',
     *     'description' => 'Page description',
     *     'keywords' => 'keyword1, keyword2',
     *     'canonical' => 'https://example.com/page',
     *     'og_title' => 'OpenGraph Title',
     *     'og_description' => 'OpenGraph Description',
     *     'og_image' => 'https://example.com/image.jpg',
     *     'og_type' => 'website|article|profile',
     *     'twitter_card' => 'summary|summary_large_image',
     *     'author' => 'Author Name',
     *     'robots' => 'index, follow'
     * }
     * @return string HTML meta tags
     */
    public static function generateMetaTags($config = []) {
        $defaults = [
            'title' => 'ScholarSeek - Find Your Perfect Scholarship',
            'description' => 'ScholarSeek helps BiPSU students find and apply for scholarships easily.',
            'keywords' => 'scholarships, BiPSU, Biliran Province State University, student funding',
            'canonical' => self::getCurrentUrl(),
            'og_title' => 'ScholarSeek',
            'og_description' => 'Find Your Perfect Scholarship Match',
            'og_image' => 'https://scholarseek.infinityfreeapp.com/assets/img/og-image.jpg',
            'og_type' => 'website',
            'twitter_card' => 'summary_large_image',
            'author' => 'ScholarSeek Team',
            'robots' => 'index, follow'
        ];
        
        $config = array_merge($defaults, $config);
        
        $html = '';
        
        // Standard meta tags
        $html .= '<meta charset="UTF-8">' . "\n";
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= '<title>' . htmlspecialchars($config['title']) . '</title>' . "\n";
        $html .= '<meta name="description" content="' . htmlspecialchars($config['description']) . '">' . "\n";
        $html .= '<meta name="keywords" content="' . htmlspecialchars($config['keywords']) . '">' . "\n";
        $html .= '<meta name="robots" content="' . htmlspecialchars($config['robots']) . '">' . "\n";
        $html .= '<meta name="author" content="' . htmlspecialchars($config['author']) . '">' . "\n";
        $html .= '<link rel="canonical" href="' . htmlspecialchars($config['canonical']) . '">' . "\n";
        
        // Open Graph tags
        $html .= '<meta property="og:title" content="' . htmlspecialchars($config['og_title']) . '">' . "\n";
        $html .= '<meta property="og:description" content="' . htmlspecialchars($config['og_description']) . '">' . "\n";
        $html .= '<meta property="og:image" content="' . htmlspecialchars($config['og_image']) . '">' . "\n";
        $html .= '<meta property="og:type" content="' . htmlspecialchars($config['og_type']) . '">' . "\n";
        $html .= '<meta property="og:url" content="' . htmlspecialchars($config['canonical']) . '">' . "\n";
        
        // Twitter Card tags
        $html .= '<meta name="twitter:card" content="' . htmlspecialchars($config['twitter_card']) . '">' . "\n";
        $html .= '<meta name="twitter:title" content="' . htmlspecialchars($config['og_title']) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($config['og_description']) . '">' . "\n";
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($config['og_image']) . '">' . "\n";
        
        return $html;
    }
    
    /**
     * Generate structured data (Schema.org JSON-LD)
     * 
     * @param string $type Type of structured data (Organization, WebPage, Article, etc.)
     * @param array $data Data for the structured data
     * @return string JSON-LD script tag
     */
    public static function generateStructuredData($type = 'Organization', $data = []) {
        $baseUrl = 'https://scholarseek.infinityfreeapp.com';
        
        $schemas = [
            'Organization' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'ScholarSeek',
                'url' => $baseUrl,
                'logo' => $baseUrl . '/assets/img/logo.png',
                'description' => 'A scholarship web-app exclusively for Biliran Province State University students',
                'sameAs' => [
                    'https://www.facebook.com/scholarseek',
                    'https://twitter.com/scholarseek'
                ],
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressCountry' => 'PH',
                    'addressRegion' => 'Biliran',
                    'addressLocality' => 'Naval'
                ]
            ],
            'WebPage' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $data['title'] ?? 'ScholarSeek',
                'description' => $data['description'] ?? 'Find Your Perfect Scholarship Match',
                'url' => self::getCurrentUrl(),
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'ScholarSeek',
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $baseUrl . '/assets/img/logo.png'
                    ]
                ]
            ],
            'BreadcrumbList' => [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $data['items'] ?? []
            ],
            'FAQPage' => [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $data['faqs'] ?? []
            ],
            'Article' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $data['headline'] ?? 'ScholarSeek Article',
                'description' => $data['description'] ?? '',
                'image' => $data['image'] ?? $baseUrl . '/assets/img/og-image.jpg',
                'datePublished' => $data['datePublished'] ?? date('Y-m-d'),
                'dateModified' => $data['dateModified'] ?? date('Y-m-d'),
                'author' => [
                    '@type' => 'Organization',
                    'name' => 'ScholarSeek'
                ]
            ]
        ];
        
        $schema = $schemas[$type] ?? $schemas['Organization'];
        $schema = array_merge($schema, $data);
        
        return '<script type="application/ld+json">' . "\n" . 
               json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n" .
               '</script>' . "\n";
    }
    
    /**
     * Get current page URL
     * 
     * @return string Current URL
     */
    public static function getCurrentUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'scholarseek.infinityfreeapp.com';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query parameters for canonical URL
        $path = strtok($path, '?');
        
        return $protocol . '://' . $host . $path;
    }
    
    /**
     * Generate breadcrumb structured data
     * 
     * @param array $breadcrumbs Array of ['name' => 'Page Name', 'url' => 'https://...']
     * @return string JSON-LD script tag
     */
    public static function generateBreadcrumbs($breadcrumbs = []) {
        $items = [];
        
        foreach ($breadcrumbs as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url']
            ];
        }
        
        return self::generateStructuredData('BreadcrumbList', ['items' => $items]);
    }
    
    /**
     * Generate FAQ structured data
     * 
     * @param array $faqs Array of ['question' => 'Q?', 'answer' => 'A']
     * @return string JSON-LD script tag
     */
    public static function generateFAQ($faqs = []) {
        $faqItems = [];
        
        foreach ($faqs as $faq) {
            $faqItems[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer']
                ]
            ];
        }
        
        return self::generateStructuredData('FAQPage', ['faqs' => $faqItems]);
    }
    
    /**
     * Sanitize text for meta tags
     * 
     * @param string $text Text to sanitize
     * @param int $maxLength Maximum length
     * @return string Sanitized text
     */
    public static function sanitizeMetaText($text, $maxLength = 160) {
        $text = strip_tags($text);
        $text = html_entity_decode($text);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength) . '...';
        }
        
        return $text;
    }
    
    /**
     * Get page-specific SEO configuration
     * 
     * @param string $page Page identifier (login, register, apply, etc.)
     * @param array $customData Custom data to merge
     * @return array SEO configuration
     */
    public static function getPageConfig($page, $customData = []) {
        $baseUrl = 'https://scholarseek.infinityfreeapp.com';
        
        $configs = [
            'login' => [
                'title' => 'Login - ScholarSeek | BiPSU Scholarship Portal',
                'description' => 'Login to your ScholarSeek account to access scholarships and manage your applications at Biliran Province State University.',
                'keywords' => 'login, BiPSU, scholarship portal, student login, account access',
                'og_title' => 'Login to ScholarSeek',
                'og_description' => 'Access your scholarship applications and opportunities',
                'og_type' => 'website'
            ],
            'register' => [
                'title' => 'Register - ScholarSeek | Free Scholarship Platform for BiPSU',
                'description' => 'Create your free ScholarSeek account to discover and apply for scholarships at Biliran Province State University.',
                'keywords' => 'register, signup, scholarship, BiPSU, student account, free registration',
                'og_title' => 'Join ScholarSeek Today',
                'og_description' => 'Find scholarships that match your profile and apply with one click',
                'og_type' => 'website'
            ],
            'apply' => [
                'title' => 'Apply for Scholarship - ScholarSeek | BiPSU',
                'description' => 'Apply for your chosen scholarship at Biliran Province State University through ScholarSeek. Quick, easy, and secure application process.',
                'keywords' => 'scholarship application, apply for scholarship, BiPSU, student funding',
                'og_title' => 'Apply for Scholarship',
                'og_description' => 'Complete your scholarship application with pre-filled information',
                'og_type' => 'website'
            ],
            'legal' => [
                'title' => 'Legal - ScholarSeek | Privacy & Terms',
                'description' => 'Read ScholarSeek\'s privacy policy, terms of service, and other legal information.',
                'keywords' => 'privacy policy, terms of service, legal, ScholarSeek',
                'og_title' => 'Legal Information',
                'og_description' => 'Privacy Policy and Terms of Service',
                'og_type' => 'website'
            ]
        ];
        
        $config = $configs[$page] ?? $configs['login'];
        return array_merge($config, $customData);
    }
}

/**
 * Helper function to quickly output SEO meta tags
 * 
 * @param string $page Page identifier
 * @param array $customData Custom data
 */
function outputSEOMetaTags($page, $customData = []) {
    $config = SEOHelper::getPageConfig($page, $customData);
    echo SEOHelper::generateMetaTags($config);
}

/**
 * Helper function to output structured data
 * 
 * @param string $type Schema type
 * @param array $data Schema data
 */
function outputStructuredData($type = 'Organization', $data = []) {
    echo SEOHelper::generateStructuredData($type, $data);
}
