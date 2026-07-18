/**
 * Platform Connections JavaScript logic.
 */
document.addEventListener('DOMContentLoaded', function() {
    const reconnectButtons = document.querySelectorAll('.btn-primary');
    
    reconnectButtons.forEach(btn => {
        if (btn.textContent.includes('Reconnect')) {
            btn.addEventListener('click', function(e) {
                const confirmed = confirm("Are you sure you want to re-authenticate this platform connection? This will update the existing login tokens on the Hub.");
                if (!confirmed) {
                    e.preventDefault();
                }
            });
        }
    });
});
