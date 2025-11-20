/**
 * Theme Manager - Handles light/dark mode across the entire application
 */

class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'scholarseek-theme';
        this.LIGHT_THEME = 'light';
        this.DARK_THEME = 'dark';
        this.init();
    }

    init() {
        // Get saved theme or default to light
        const savedTheme = localStorage.getItem(this.STORAGE_KEY) || this.LIGHT_THEME;
        this.setTheme(savedTheme);
        this.setupToggleButtons();
        this.setupSystemPreference();
    }

    setTheme(theme) {
        const html = document.documentElement;
        const isDark = theme === this.DARK_THEME;

        if (isDark) {
            html.setAttribute('data-theme', this.DARK_THEME);
            document.body.classList.add('theme-dark');
            document.body.classList.remove('theme-light');
        } else {
            html.setAttribute('data-theme', this.LIGHT_THEME);
            document.body.classList.add('theme-light');
            document.body.classList.remove('theme-dark');
        }

        localStorage.setItem(this.STORAGE_KEY, theme);
        this.updateToggleButtons(isDark);
        this.dispatchThemeChangeEvent(theme);
    }

    toggleTheme() {
        const currentTheme = localStorage.getItem(this.STORAGE_KEY) || this.LIGHT_THEME;
        const newTheme = currentTheme === this.LIGHT_THEME ? this.DARK_THEME : this.LIGHT_THEME;
        this.setTheme(newTheme);
    }

    setupToggleButtons() {
        // Find all theme toggle buttons with data-toggle-theme attribute
        const toggleButtons = document.querySelectorAll('[data-toggle-theme]');
        toggleButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleTheme();
            });
        });

        // Support modern-toggle class
        const modernToggles = document.querySelectorAll('.modern-toggle');
        modernToggles.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleTheme();
            });
        });

        // Also support the old themeToggle ID
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle && !themeToggle.classList.contains('modern-toggle')) {
            themeToggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleTheme();
            });
        }
    }

    updateToggleButtons(isDark) {
        const toggleButtons = document.querySelectorAll('[data-toggle-theme]');
        toggleButtons.forEach(btn => {
            if (isDark) {
                btn.classList.add('dark-mode');
                btn.classList.remove('light-mode');
                btn.innerHTML = '<i class="fas fa-sun"></i><span>Light</span>';
            } else {
                btn.classList.add('light-mode');
                btn.classList.remove('dark-mode');
                btn.innerHTML = '<i class="fas fa-moon"></i><span>Dark</span>';
            }
        });

        // Update old themeToggle if exists
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            if (isDark) {
                themeToggle.classList.add('dark-mode');
                themeToggle.classList.remove('light-mode');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i><span>Light</span>';
            } else {
                themeToggle.classList.add('light-mode');
                themeToggle.classList.remove('dark-mode');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i><span>Dark</span>';
            }
        }
    }

    setupSystemPreference() {
        // Listen for system theme preference changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                // Only apply if user hasn't manually set a preference
                if (!localStorage.getItem(this.STORAGE_KEY)) {
                    this.setTheme(e.matches ? this.DARK_THEME : this.LIGHT_THEME);
                }
            });
        }
    }

    getCurrentTheme() {
        return localStorage.getItem(this.STORAGE_KEY) || this.LIGHT_THEME;
    }

    dispatchThemeChangeEvent(theme) {
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }
}

// Initialize theme manager when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.themeManager = new ThemeManager();
    });
} else {
    window.themeManager = new ThemeManager();
}
