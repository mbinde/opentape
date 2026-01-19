/**
 * Opentape Player
 * Modernized with vanilla JS and native HTML5 Audio
 */

(function() {
    'use strict';

    // Player state
    let currentTrack = -1;
    let playerStatus = 'STOPPED';
    let currentPos = -1;
    let audioPlayer = null;
    let nextAudioPlayer = null;
    let fadeInterval = null;
    let initialized = false;

    // Debug helper
    function debug(...args) {
        if (typeof console !== 'undefined') {
            console.log(...args);
        }
    }

    // Initialize event listeners on all tracks
    function eventInit() {
        if (typeof openPlaylist === 'undefined') return;
        if (initialized) return;
        initialized = true;

        for (let i = 0; i < openPlaylist.length; i++) {
            const trackEntry = document.getElementById('song' + i);
            if (trackEntry) {
                trackEntry.addEventListener('mouseover', function() {
                    this.classList.add('hover');
                });
                trackEntry.addEventListener('mouseout', function() {
                    this.classList.remove('hover');
                });
                trackEntry.addEventListener('click', function(e) {
                    const target = e.target;
                    // Find the song element by traversing up
                    let songEl = target;
                    while (songEl && !songEl.id.startsWith('song')) {
                        songEl = songEl.parentNode;
                    }
                    if (songEl && songEl.id) {
                        togglePlayback(songEl.id);
                    }
                });
            }
        }
    }

    // Toggle play/pause for a track
    function togglePlayback(id) {
        const trackIndex = parseInt(id.replace(/song/, ''), 10);

        if (trackIndex === currentTrack && currentTrack >= 0) {
            // Same track - toggle play/pause
            if (playerStatus === 'PAUSED') {
                resumeTrack();
            } else {
                pauseTrack();
            }
        } else {
            // Different track - stop current and play new
            cleanTrackDisplay(currentTrack);
            currentTrack = trackIndex;
            playTrack();
        }
    }

    // Play the current track
    function playTrack() {
        if (typeof openPlaylist === 'undefined' || !openPlaylist[currentTrack]) {
            return;
        }

        // Stop and clean up previous player
        if (audioPlayer) {
            audioPlayer.pause();
            audioPlayer.src = '';
            audioPlayer = null;
        }

        // If we have a preloaded next track, use it
        if (nextAudioPlayer) {
            audioPlayer = nextAudioPlayer;
            nextAudioPlayer = null;
            setupTrackDisplay(currentTrack);
            audioPlayer.volume = 0.8;
            audioPlayer.play().catch(err => debug('Play failed:', err));
            return;
        }

        // Create new audio player
        const filename = decodeURIComponent(atob(openPlaylist[currentTrack]));
        audioPlayer = new Audio('songs/' + filename);
        audioPlayer.volume = 0.8;

        // Set up event listeners
        audioPlayer.addEventListener('play', onPlay);
        audioPlayer.addEventListener('pause', onPause);
        audioPlayer.addEventListener('ended', onEnded);
        audioPlayer.addEventListener('timeupdate', onTimeUpdate);
        audioPlayer.addEventListener('error', onError);

        setupTrackDisplay(currentTrack);

        audioPlayer.play().catch(err => debug('Play failed:', err));
    }

    // Pause current track
    function pauseTrack() {
        if (audioPlayer) {
            audioPlayer.pause();
        }
        const songClock = document.querySelector('#song' + currentTrack + ' .clock');
        if (songClock) {
            songClock.classList.remove('green');
            songClock.classList.add('grey');
        }
    }

    // Resume current track
    function resumeTrack() {
        if (audioPlayer) {
            audioPlayer.play().catch(err => debug('Resume failed:', err));
        }
        const songClock = document.querySelector('#song' + currentTrack + ' .clock');
        if (songClock) {
            songClock.classList.remove('grey');
            songClock.classList.add('green');
        }
    }

    // Advance to next track
    function nextTrack() {
        if (typeof openPlaylist !== 'undefined' && openPlaylist[currentTrack + 1]) {
            cleanTrackDisplay(currentTrack);
            currentTrack++;
            playTrack();
            return true;
        }
        return false;
    }

    // Preload the next track for gapless playback
    function loadNextTrack() {
        if (typeof openPlaylist === 'undefined' || !openPlaylist[currentTrack + 1]) {
            debug('No next track to preload');
            return;
        }

        if (nextAudioPlayer) {
            nextAudioPlayer.src = '';
            nextAudioPlayer = null;
        }

        const filename = decodeURIComponent(atob(openPlaylist[currentTrack + 1]));
        nextAudioPlayer = new Audio('songs/' + filename);
        nextAudioPlayer.volume = 0;
        nextAudioPlayer.preload = 'auto';

        // Set up event listeners for the next track
        nextAudioPlayer.addEventListener('play', onPlay);
        nextAudioPlayer.addEventListener('pause', onPause);
        nextAudioPlayer.addEventListener('ended', onEnded);
        nextAudioPlayer.addEventListener('timeupdate', onTimeUpdate);
        nextAudioPlayer.addEventListener('error', onError);

        debug('Preloading next track');
    }

    // Begin crossfade transition between tracks
    function beginFadeTransition() {
        if (!nextAudioPlayer || !audioPlayer) return;

        debug('Beginning crossfade transition');

        nextAudioPlayer.play().catch(err => debug('Next track play failed:', err));

        // Clear any existing fade interval
        if (fadeInterval) {
            clearInterval(fadeInterval);
        }

        const fadeStep = 0.05;
        const fadeIntervalMs = 150;

        fadeInterval = setInterval(function() {
            // Fade out current
            if (audioPlayer && audioPlayer.volume > fadeStep) {
                audioPlayer.volume = Math.max(0, audioPlayer.volume - fadeStep);
            }

            // Fade in next
            if (nextAudioPlayer && nextAudioPlayer.volume < 0.8) {
                nextAudioPlayer.volume = Math.min(0.8, nextAudioPlayer.volume + fadeStep);
            }

            // Check if fade is complete
            if ((!audioPlayer || audioPlayer.volume <= fadeStep) &&
                (!nextAudioPlayer || nextAudioPlayer.volume >= 0.75)) {
                clearInterval(fadeInterval);
                fadeInterval = null;

                // Clean up old player
                if (audioPlayer) {
                    audioPlayer.pause();
                    audioPlayer.src = '';
                }

                // Promote next to current
                cleanTrackDisplay(currentTrack);
                currentTrack++;
                audioPlayer = nextAudioPlayer;
                nextAudioPlayer = null;
                setupTrackDisplay(currentTrack);
            }
        }, fadeIntervalMs);
    }

    // Check if crossfade is enabled (disabled on iOS due to audio restrictions)
    function isFadeEnabled() {
        const ua = navigator.userAgent;
        const isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        return !isIOS;
    }

    // Clean track display (remove highlight and clock)
    function cleanTrackDisplay(trackIndex) {
        if (trackIndex < 0 || trackIndex === undefined) return;

        const songClock = document.querySelector('#song' + trackIndex + ' .clock');
        const songItem = document.getElementById('song' + trackIndex);

        if (songItem) {
            songItem.classList.remove('hilite');
        }
        if (songClock) {
            songClock.innerHTML = '';
            songClock.classList.remove('green', 'grey');
        }
    }

    // Set up track display (highlight and initial clock)
    function setupTrackDisplay(trackIndex) {
        const songClock = document.querySelector('#song' + trackIndex + ' .clock');
        const songItem = document.getElementById('song' + trackIndex);
        const nameEl = document.querySelector('#song' + trackIndex + ' .name');

        if (songClock) {
            songClock.classList.remove('grey');
            songClock.classList.add('green');
            songClock.innerHTML = '&mdash;';
        }

        if (songItem) {
            songItem.classList.add('hilite');
        }

        // Update document title with now playing
        if (nameEl && typeof pageTitle !== 'undefined') {
            let name = nameEl.textContent || nameEl.innerText;
            name = name.trim().replace('&amp;', '&');
            document.title = '\u25BA ' + name + ' / ' + pageTitle;
        }
    }

    // Event handlers
    function onPlay() {
        playerStatus = 'PLAYING';
        document.title = document.title.replace(/\u25FC/, '\u25BA');
    }

    function onPause() {
        playerStatus = 'PAUSED';
        document.title = document.title.replace(/\u25BA/, '\u25FC');
    }

    function onEnded() {
        debug('Track ended');
        nextTrack();
    }

    function onError(e) {
        debug('Audio error:', e);
        nextTrack();
    }

    function onTimeUpdate() {
        if (!audioPlayer) return;

        const position = Math.floor(audioPlayer.currentTime);
        const duration = Math.floor(audioPlayer.duration) || 0;

        // Skip if position hasn't changed
        if (position === currentPos) return;
        currentPos = position;

        // Update clock display
        const songClock = document.querySelector('#song' + currentTrack + ' .clock');
        if (songClock) {
            const sec = position % 60;
            const min = Math.floor(position / 60);
            const minFormatted = min ? min + ':' : '';
            const secFormatted = min ? (sec < 10 ? '0' + sec : sec) : sec;
            songClock.innerHTML = minFormatted + secFormatted;
        }

        // Handle crossfade preloading
        if (isFadeEnabled() && duration > 0) {
            const remaining = duration - position;
            if (remaining === 10) {
                loadNextTrack();
            } else if (remaining === 5) {
                beginFadeTransition();
            }
        }
    }

    // Expose functions globally for inline event handlers
    window.togglePlayback = togglePlayback;
    window.event_init = eventInit;

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', eventInit);
    } else {
        eventInit();
    }
})();
