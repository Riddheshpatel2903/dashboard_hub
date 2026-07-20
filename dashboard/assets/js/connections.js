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

/**
 * Global function to trigger AJx disconnect/unlink flow.
 */
window.unlinkPlatform = function(platform) {
    const displayName = platform.replace('_', ' ');
    const confirmed = confirm(`Are you sure you want to unlink your ${displayName} channel? This will delete all saved API tokens, credentials, and settings on the Hub. This action is irreversible.`);
    
    if (confirmed) {
        fetch('unlink_connection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ platform: platform })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.reload();
            } else {
                alert('Unlinking failed: ' + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Communication failure while unlinking.');
        });
    }
};
