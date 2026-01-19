/**
 * Opentape Upload Handler
 * Handles file upload form validation and status display
 * Modernized with vanilla JS
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('upload_form');
        const button = document.getElementById('upload_button');
        const input = document.getElementById('upload_input');

        if (!form || !button) return;

        let animationInterval = null;

        form.addEventListener('submit', function(event) {
            const value = input ? input.value : '';

            if (!value) {
                event.preventDefault();
                return false;
            }

            // Check for MP3 extension (case insensitive)
            if (!value.toLowerCase().endsWith('.mp3')) {
                alert('Opentape only accepts MP3s.');
                if (input) input.value = '';
                event.preventDefault();
                return false;
            }

            // Disable button and show uploading status
            button.blur();
            button.classList.add('deactivated');
            button.disabled = true;

            // Start animation
            startUploadAnimation();
        });

        function startUploadAnimation() {
            let dots = 0;
            const baseText = 'uploading';

            animationInterval = setInterval(function() {
                dots = (dots + 1) % 4;
                const dotStr = '.'.repeat(dots) + ' '.repeat(3 - dots);
                button.value = baseText + dotStr;
            }, 500);
        }
    });
})();
