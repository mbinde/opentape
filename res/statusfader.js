/**
 * Opentape Status Fader
 * Displays status messages with fade animations
 * Modernized with vanilla JS and CSS transitions
 */

class StatusFader {
    constructor(el) {
        this.el = el || document.querySelector('div.ajax_status');
        this.colors = { good: '#008000', bad: '#ff0000', neutral: '#444' };
        this.messages = { progress: 'Wait\u2026', success: 'Saved', failure: 'ERROR' };
        this.fadeTimeout = null;
    }

    set(msg) {
        if (!this.el) return;

        this.el.textContent = this.messages[msg] || msg;

        // Clear any pending fade
        if (this.fadeTimeout) {
            clearTimeout(this.fadeTimeout);
            this.fadeTimeout = null;
        }

        switch (msg) {
            case 'progress':
                this.el.style.color = this.colors.neutral;
                this.el.style.padding = '6px';
                this.el.style.opacity = '1';
                break;

            case 'success':
                this.el.style.color = this.colors.good;
                this.el.style.padding = '6px';
                this.el.style.opacity = '1';
                this.startFade();
                break;

            case 'failure':
                this.el.style.color = this.colors.bad;
                this.el.style.padding = '6px';
                this.el.style.opacity = '1';
                this.startFade();
                break;
        }
    }

    flash(msg, color) {
        if (!this.el) return;

        this.el.textContent = msg;
        this.el.style.color = color;
        this.el.style.padding = '6px';
        this.el.style.opacity = '1';
        this.startFade();
    }

    stay(msg, color) {
        if (!this.el) return;

        this.el.textContent = msg;
        this.el.style.color = color;
        this.el.style.padding = '6px';
        this.el.style.opacity = '1';
    }

    startFade() {
        // Small delay before fading
        this.fadeTimeout = setTimeout(() => {
            if (this.el) {
                this.el.style.opacity = '0';
            }
        }, 2000);
    }
}

// Export for global use
window.StatusFader = StatusFader;
