/**
 * First Time User Experience (FTUE) Onboarding System
 * Guides new users through ScholarSeek platform features
 */

class FTUEOnboarding {
    constructor() {
        this.currentStep = 0;
        this.isFirstTime = localStorage.getItem('ftue_completed') === null;
        this.steps = this.initializeSteps();
        this.overlay = null;
        this.tooltip = null;
        this.init();
    }

    init() {
        // Only show FTUE for first-time users
        if (this.isFirstTime) {
            // Delay initialization to ensure DOM is fully loaded
            setTimeout(() => {
                this.showOnboarding();
            }, 500);
        }
    }

    initializeSteps() {
        return [
            {
                id: 'welcome',
                title: 'Welcome to ScholarSeek! 🎓',
                description: 'Your gateway to scholarship opportunities. Let\'s take a quick tour to get you started.',
                target: null,
                position: 'center',
                action: 'next'
            },
            {
                id: 'dashboard-overview',
                title: 'Your Dashboard',
                description: 'Here\'s your personalized dashboard showing your scholarship journey at a glance.',
                target: '.welcome-banner',
                position: 'bottom',
                action: 'next'
            },
            {
                id: 'stats-cards',
                title: 'Quick Stats',
                description: 'Track your progress with these key metrics: available scholarships, your applications, pending reviews, and approved awards.',
                target: '.compact-stats-grid',
                position: 'bottom',
                action: 'next'
            },
            {
                id: 'upcoming-deadlines',
                title: 'Upcoming Deadlines',
                description: 'Never miss an opportunity! This section shows scholarships with the soonest deadlines. Click "Apply" to submit your application.',
                target: '.upcoming-deadlines-section',
                position: 'bottom',
                action: 'next'
            },
            {
                id: 'sidebar-navigation',
                title: 'Navigation Menu',
                description: 'Use this menu to navigate between different sections: Dashboard, Scholarships, Applications, Notifications, and your Profile.',
                target: '.sidebar',
                position: 'right',
                action: 'next'
            },
            {
                id: 'scholarships-section',
                title: 'Browse Scholarships',
                description: 'Visit the Scholarships section to explore all available opportunities. You can filter, search, and apply to scholarships that match your profile.',
                target: '.sidebar-item:nth-child(2)',
                position: 'right',
                action: 'next'
            },
            {
                id: 'applications-section',
                title: 'Track Applications',
                description: 'The Applications section shows all your submitted applications and their current status. You can track the progress of each application here.',
                target: '.sidebar-item:nth-child(3)',
                position: 'right',
                action: 'next'
            },
            {
                id: 'profile-section',
                title: 'Your Profile',
                description: 'Keep your profile updated with accurate information. This helps scholarship providers evaluate your applications better.',
                target: '.sidebar-item:nth-child(5)',
                position: 'right',
                action: 'next'
            },
            {
                id: 'tips-and-tricks',
                title: 'Pro Tips 💡',
                description: 'Complete your profile with all details • Apply early to increase your chances • Check deadlines regularly • Keep your contact information updated',
                target: null,
                position: 'center',
                action: 'finish'
            }
        ];
    }

    showOnboarding() {
        // Remove existing overlay/tooltip if any
        const existingOverlay = document.getElementById('ftue-overlay');
        const existingTooltip = document.getElementById('ftue-tooltip');
        if (existingOverlay) existingOverlay.remove();
        if (existingTooltip) existingTooltip.remove();
        
        this.createOverlay();
        this.showStep(0);
    }

    createOverlay() {
        // Create overlay container
        this.overlay = document.createElement('div');
        this.overlay.className = 'ftue-overlay';
        this.overlay.id = 'ftue-overlay';
        this.overlay.style.display = 'block';
        document.body.appendChild(this.overlay);

        // Create tooltip
        this.tooltip = document.createElement('div');
        this.tooltip.className = 'ftue-tooltip';
        this.tooltip.id = 'ftue-tooltip';
        this.tooltip.style.display = 'block';
        document.body.appendChild(this.tooltip);

        console.log('FTUE: Overlay and tooltip created');
    }

    showStep(stepIndex) {
        if (stepIndex >= this.steps.length) {
            this.completeOnboarding();
            return;
        }

        // Remove animation from previously highlighted element
        if (this.highlightedElement) {
            this.highlightedElement.style.animation = 'none';
            this.highlightedElement = null;
        }

        this.currentStep = stepIndex;
        const step = this.steps[stepIndex];

        console.log(`FTUE: Showing step ${stepIndex}: ${step.id}`);

        // Update tooltip first (before positioning)
        this.updateTooltip(step);
        
        // Ensure tooltip is visible
        this.tooltip.style.display = 'block';
        this.tooltip.style.visibility = 'visible';
        this.tooltip.style.opacity = '1';

        // Update overlay
        if (step.target) {
            const targetElement = document.querySelector(step.target);
            if (targetElement) {
                console.log(`FTUE: Found target element: ${step.target}`);
                this.highlightElement(targetElement);
            } else {
                console.log(`FTUE: Target element not found: ${step.target}, using center position`);
                this.overlay.style.display = 'block';
                this.overlay.style.pointerEvents = 'none';
                this.overlay.style.clipPath = 'none';
                this.positionTooltip(null, 'center');
            }
        } else {
            console.log(`FTUE: No target element, using center position`);
            this.overlay.style.display = 'block';
            this.overlay.style.pointerEvents = 'none';
            this.overlay.style.clipPath = 'none';
            this.positionTooltip(null, 'center');
        }
    }

    highlightElement(element) {
        const rect = element.getBoundingClientRect();
        const padding = 15;

        console.log(`FTUE: Element rect - top: ${rect.top}, left: ${rect.left}, width: ${rect.width}, height: ${rect.height}`);

        this.overlay.style.display = 'block';
        this.overlay.style.pointerEvents = 'auto';
        
        // Create a proper clip-path that cuts out the element
        // Format: polygon(x1 y1, x2 y2, ...)
        const clipPath = `polygon(
            0% 0%,
            0% 100%,
            100% 100%,
            100% 0%,
            0% 0%,
            ${rect.left - padding}px ${rect.top - padding}px,
            ${rect.right + padding}px ${rect.top - padding}px,
            ${rect.right + padding}px ${rect.bottom + padding}px,
            ${rect.left - padding}px ${rect.bottom + padding}px,
            ${rect.left - padding}px ${rect.top - padding}px
        )`;
        
        this.overlay.style.clipPath = clipPath;
        console.log(`FTUE: Clip-path applied: ${clipPath.substring(0, 100)}...`);

        // Add pulse effect to highlighted element
        element.style.animation = 'ftuePulse 2s infinite';
        element.style.borderRadius = '8px';
        
        // Store reference to remove animation later
        this.highlightedElement = element;

        // Position tooltip
        this.positionTooltip(element, this.steps[this.currentStep].position);
    }

    positionTooltip(element, position) {
        const tooltipWidth = 320;
        const tooltipHeight = 250;
        const gap = 20;
        const viewportPadding = 15;

        let top, left;

        // Handle center positioning (no element)
        if (!element || position === 'center') {
            top = window.innerHeight / 2 - tooltipHeight / 2;
            left = window.innerWidth / 2 - tooltipWidth / 2;
        } else {
            const rect = element.getBoundingClientRect();
            
            switch (position) {
                case 'bottom':
                    top = rect.bottom + gap;
                    left = rect.left + (rect.width - tooltipWidth) / 2;
                    break;
                case 'top':
                    top = rect.top - tooltipHeight - gap;
                    left = rect.left + (rect.width - tooltipWidth) / 2;
                    break;
                case 'right':
                    top = rect.top + (rect.height - tooltipHeight) / 2;
                    left = rect.right + gap;
                    break;
                case 'left':
                    top = rect.top + (rect.height - tooltipHeight) / 2;
                    left = rect.left - tooltipWidth - gap;
                    break;
                default:
                    top = window.innerHeight / 2 - tooltipHeight / 2;
                    left = window.innerWidth / 2 - tooltipWidth / 2;
            }
        }

        // Ensure tooltip stays within viewport with padding
        if (left < viewportPadding) {
            left = viewportPadding;
        }
        if (left + tooltipWidth > window.innerWidth - viewportPadding) {
            left = window.innerWidth - tooltipWidth - viewportPadding;
        }
        if (top < viewportPadding) {
            top = viewportPadding;
        }
        if (top + tooltipHeight > window.innerHeight - viewportPadding) {
            top = window.innerHeight - tooltipHeight - viewportPadding;
        }

        this.tooltip.style.top = Math.max(0, top) + 'px';
        this.tooltip.style.left = Math.max(0, left) + 'px';
        
        console.log(`FTUE: Positioned tooltip at top: ${top}px, left: ${left}px`);
    }

    updateTooltip(step) {
        const progress = ((this.currentStep + 1) / this.steps.length * 100).toFixed(0);
        
        console.log(`FTUE: Updating tooltip with step: ${step.title}`);

        this.tooltip.innerHTML = `
            <div class="ftue-tooltip-content">
                <div class="ftue-tooltip-header">
                    <h3>${step.title}</h3>
                    <button class="ftue-close-btn" onclick="ftueOnboarding.skipOnboarding()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="ftue-tooltip-description">${step.description}</p>
                <div class="ftue-tooltip-footer">
                    <div class="ftue-progress">
                        <div class="ftue-progress-bar" style="width: ${progress}%"></div>
                    </div>
                    <span class="ftue-step-counter">${this.currentStep + 1} of ${this.steps.length}</span>
                </div>
                <div class="ftue-tooltip-actions">
                    ${this.currentStep > 0 ? '<button class="ftue-btn ftue-btn-secondary" onclick="ftueOnboarding.previousStep()">← Back</button>' : ''}
                    <button class="ftue-btn ftue-btn-primary" onclick="ftueOnboarding.nextStep()">
                        ${step.action === 'finish' ? 'Get Started! →' : 'Next →'}
                    </button>
                </div>
            </div>
        `;
    }

    nextStep() {
        this.showStep(this.currentStep + 1);
    }

    previousStep() {
        if (this.currentStep > 0) {
            this.showStep(this.currentStep - 1);
        }
    }

    skipOnboarding() {
        if (confirm('Skip the tour? You can restart it anytime from your profile settings.')) {
            this.completeOnboarding();
        }
    }

    completeOnboarding() {
        // Mark FTUE as completed
        localStorage.setItem('ftue_completed', 'true');

        // Remove overlay and tooltip
        if (this.overlay) {
            this.overlay.remove();
        }
        if (this.tooltip) {
            this.tooltip.remove();
        }

        // Show completion message
        this.showCompletionMessage();
    }

    showCompletionMessage() {
        const message = document.createElement('div');
        message.className = 'ftue-completion-message';
        message.innerHTML = `
            <div class="ftue-completion-content">
                <div class="ftue-completion-icon">✨</div>
                <h2>You're all set!</h2>
                <p>You're ready to explore ScholarSeek and find your perfect scholarship.</p>
                <button class="ftue-btn ftue-btn-primary" onclick="this.parentElement.parentElement.remove()">
                    Start Exploring
                </button>
            </div>
        `;
        document.body.appendChild(message);

        setTimeout(() => {
            message.classList.add('show');
        }, 100);

        setTimeout(() => {
            message.remove();
        }, 4000);
    }

    // Allow users to restart the tour
    static restartTour() {
        localStorage.removeItem('ftue_completed');
        location.reload();
    }
}

// Initialize FTUE when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.ftueOnboarding = new FTUEOnboarding();
});
