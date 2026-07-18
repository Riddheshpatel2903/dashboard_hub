/**
 * Unified Inbox JavaScript.
 */

// Scroll active chat timelines to the bottom on page load
document.addEventListener('DOMContentLoaded', function() {
    const activeTimeline = document.querySelector('.thread-chat-view[style*="display: flex"] .chat-timeline');
    if (activeTimeline) {
        activeTimeline.scrollTop = activeTimeline.scrollHeight;
    }
});

/**
 * Toggles active chat thread panels.
 */
function switchActiveThread(number) {
    // Hide all chat views
    document.querySelectorAll('.thread-chat-view').forEach(view => {
        view.style.display = 'none';
    });
    
    // De-activate all list items
    document.querySelectorAll('.thread-item').forEach(item => {
        item.classList.remove('active');
    });

    // Show selected chat view
    const selectedView = document.getElementById('chat-' + number);
    if (selectedView) {
        selectedView.style.display = 'flex';
        
        // Scroll timeline to bottom
        const timeline = selectedView.querySelector('.chat-timeline');
        if (timeline) {
            setTimeout(() => {
                timeline.scrollTop = timeline.scrollHeight;
            }, 50);
        }
    }

    // Set active item class
    const selectedItem = document.querySelector(`.thread-item[data-number="${number}"]`);
    if (selectedItem) {
        selectedItem.classList.add('active');
    }
}

/**
 * Dispatch chat reply to backend.
 */
function submitChatReply(number) {
    const textInput = document.getElementById('input-' + number);
    const templateSelect = document.getElementById('template-' + number);
    const timeline = document.getElementById('timeline-' + number);
    
    const message = textInput.value.trim();
    const templateName = templateSelect ? templateSelect.value : '';

    if (message === '' && templateName === '') {
        alert('Please write a message or select a template to reply.');
        return;
    }

    const payload = {
        platform: 'whatsapp',
        recipient: number
    };

    if (templateName !== '') {
        payload.template_name = templateName;
        payload.message = ''; // Templates send standard layouts
    } else {
        payload.message = message;
    }

    // Disable inputs during network request
    textInput.disabled = true;
    if (templateSelect) templateSelect.disabled = true;

    fetch('inbox_submit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Append message bubble inline
            const bubble = document.createElement('div');
            bubble.className = 'chat-msg outbound';
            
            const displayText = (templateName !== '') ? `[Template Sent: ${templateName}]` : message;
            
            const now = new Date();
            const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            
            bubble.innerHTML = `${escapeHtml(displayText)} <span class="msg-time">${timeStr}</span>`;
            timeline.appendChild(bubble);
            
            // Scroll to bottom
            timeline.scrollTop = timeline.scrollHeight;

            // Clear inputs
            textInput.value = '';
            if (templateSelect) templateSelect.value = '';
        } else {
            alert('Failed to send reply: ' + data.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Communication failed: Network error.');
    })
    .finally(() => {
        textInput.disabled = false;
        if (templateSelect) templateSelect.disabled = false;
    });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
