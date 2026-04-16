/**
 * BML IOT VFD Dashboard — Client-Side Logic
 * Handles: sidebar toggle, clock, live indicator
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ============ SIDEBAR TOGGLE ============
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mainContent = document.getElementById('main-content');
    
    // Create overlay element
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }
    
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
    
    // ============ TOPBAR CLOCK ============
    const clockEl = document.getElementById('topbar-time');
    
    function updateClock() {
        if (!clockEl) return;
        const now = new Date();
        const options = { 
            year: 'numeric', month: 'short', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false 
        };
        clockEl.textContent = now.toLocaleDateString('en-IN', options);
    }
    
    updateClock();
    setInterval(updateClock, 1000);
    
    // ============ LIVE INDICATOR PULSE ============
    const liveIndicator = document.getElementById('live-indicator');
    let lastDataTime = Date.now();
    
    // Check if data is stale (>15s without update)
    setInterval(function() {
        if (!liveIndicator) return;
        const elapsed = Date.now() - lastDataTime;
        if (elapsed > 15000) {
            liveIndicator.style.opacity = '0.4';
            const dot = liveIndicator.querySelector('.live-dot');
            if (dot) dot.style.background = '#ba1a1a';
            const text = liveIndicator.querySelector('.live-text');
            if (text) text.textContent = 'OFFLINE';
        } else {
            liveIndicator.style.opacity = '1';
            const dot = liveIndicator.querySelector('.live-dot');
            if (dot) dot.style.background = '#006e25';
            const text = liveIndicator.querySelector('.live-text');
            if (text) text.textContent = 'LIVE';
        }
    }, 5000);
    
    // Expose function for pages to call when data arrives
    window.markDataReceived = function() {
        lastDataTime = Date.now();
    };
    
    // ============ VALUE UPDATE ANIMATION ============
    window.animateValue = function(elementId, newValue) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const oldValue = el.textContent;
        if (oldValue !== String(newValue) && oldValue !== '—') {
            el.classList.add('value-updated');
            setTimeout(() => el.classList.remove('value-updated'), 600);
        }
    };
    
    // ============ RESPONSIVE SIDEBAR ============
    function handleResize() {
        if (window.innerWidth > 991) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    }
    
    window.addEventListener('resize', handleResize);
    
    console.log('BML IOT Dashboard initialized.');
});
