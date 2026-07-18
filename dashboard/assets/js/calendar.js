/**
 * Postings Calendar JavaScript.
 */
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('post-modal');
    const modalBody = document.getElementById('modal-body-content');
    const closeBtn = document.getElementById('modal-close-btn');

    // 1. Click handler for post pins to load detail modal
    document.querySelectorAll('.post-pin').forEach(pin => {
        pin.addEventListener('click', function(e) {
            e.stopPropagation();
            const postId = this.getAttribute('data-id');
            openPostModal(postId);
        });
    });

    // 2. Click handler for overflow indicators
    document.querySelectorAll('.overflow-pin').forEach(pin => {
        pin.addEventListener('click', function(e) {
            e.stopPropagation();
            const date = this.getAttribute('data-date');
            window.location.href = `post_history.php?date=${date}`;
        });
    });

    // Open and load details via AJAX
    function openPostModal(postId) {
        modal.style.display = 'flex';
        modalBody.innerHTML = '<p style="color:var(--text-secondary); text-align:center; padding:2rem 0;">Loading post details...</p>';

        fetch(`post_detail.php?post_id=${postId}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.text())
        .then(html => {
            modalBody.innerHTML = html;
            // Setup internal action bindings inside the loaded detail modal
            attachModalActionListeners();
        })
        .catch(err => {
            console.error(err);
            modalBody.innerHTML = '<p style="color:var(--color-danger); text-align:center;">Failed to retrieve post details.</p>';
        });
    }

    // Bind edit/delete handlers in the dynamically loaded modal markup
    function attachModalActionListeners() {
        const deleteBtn = modalBody.querySelector('#btn-delete-post');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                const postId = this.getAttribute('data-id');
                const confirmed = confirm("Are you sure you want to delete this post from the Hub and all integrated channels? This action is irreversible.");
                
                if (confirmed) {
                    deleteBtn.disabled = true;
                    deleteBtn.textContent = 'Deleting...';
                    
                    fetch('post_detail.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type: application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            action: 'delete',
                            post_id: postId
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('Post deleted successfully.');
                            window.location.reload();
                        } else {
                            alert('Deletion Failed: ' + data.error);
                            deleteBtn.disabled = false;
                            deleteBtn.textContent = 'Delete Post';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Network communication failure.');
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = 'Delete Post';
                    });
                }
            });
        }
    }

    // Modal close controls
    function closeModal() {
        modal.style.display = 'none';
        modalBody.innerHTML = '';
    }

    closeBtn.addEventListener('click', closeModal);
    
    // Close modal if user clicks outside of modal card
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
});
