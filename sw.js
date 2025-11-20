const CACHE_NAME = 'scholarseek-cache-v6';
const urlsToCache = [
  './index.html',
  './manifest.json',
  './assets/css/admin_dashboard.css',
  './assets/css/staff_dashboard.css',
  './assets/css/student_dashboard.css',
  './assets/css/manage_students.css',
  './assets/css/manage_staff.css',
  './assets/css/manage_applications.css',
  './assets/css/manage_scholarships.css',
  './assets/css/apply_scholarship.css',
  './assets/css/login.css',
  './assets/css/register.css',
  './assets/css/profile_enhancements.css',
  './assets/css/custom-modal.css',
  './assets/css/rejection_modal.css',
  './assets/img/logo.png'
];

// Install service worker and cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Activate service worker and clean old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.filter(name => name !== CACHE_NAME)
          .map(name => caches.delete(name))
      );
    })
  );
});

// Fetch resources from cache first, then network
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request);
      })
  );
});
