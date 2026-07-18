/**
 * Composer Interactive Logic.
 */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('composer-form');
    if (!form) return;

    const textarea = document.getElementById('content');
    const previewText = document.getElementById('preview-text-content');
    const mediaInput = document.getElementById('media');
    const previewMedia = document.getElementById('preview-media');
    const fileError = document.getElementById('file-error');
    
    const checkboxes = document.querySelectorAll('input[name="platforms[]"]');
    const igWarning = document.getElementById('ig-warning');
    const ytInfo = document.getElementById('yt-info');
    const ytTitleGroup = document.getElementById('youtube-title-group');
    const previewPlatformLabel = document.getElementById('preview-platform-label');
    
    const toggleSchedule = document.getElementById('toggle-schedule');
    const scheduleContainer = document.getElementById('schedule-container');
    const scheduleType = document.getElementById('schedule-type');
    const btnPublish = document.getElementById('btn-publish');
    const submitLoading = document.getElementById('submit-loading');

    // 1. Textarea content live sync with preview
    textarea.addEventListener('input', function() {
        if (textarea.value.trim() === '') {
            previewText.textContent = 'Post caption preview will render here...';
        } else {
            previewText.textContent = textarea.value;
        }
    });

    // 2. Selectable platform label classes and dynamic notices
    checkboxes.forEach(chk => {
        chk.addEventListener('change', function() {
            const label = chk.closest('.platform-checkbox-label');
            if (chk.checked) {
                label.classList.add('selected');
            } else {
                label.classList.remove('selected');
            }
            updatePlatformNotices();
        });
    });

    function updatePlatformNotices() {
        const checkedPlatforms = Array.from(checkboxes)
                                     .filter(c => c.checked)
                                     .map(c => c.value);

        // Show/hide IG specific warnings
        if (checkedPlatforms.includes('instagram')) {
            igWarning.style.display = 'block';
        } else {
            igWarning.style.display = 'none';
        }

        // Show/hide YouTube metadata inputs
        if (checkedPlatforms.includes('youtube')) {
            ytInfo.style.display = 'block';
            ytTitleGroup.style.display = 'block';
        } else {
            ytInfo.style.display = 'none';
            ytTitleGroup.style.display = 'none';
        }

        // Update preview labels
        if (checkedPlatforms.length > 0) {
            previewPlatformLabel.textContent = checkedPlatforms.map(p => p.toUpperCase().replace('_', ' ')).join(', ');
        } else {
            previewPlatformLabel.textContent = 'No Platform Selected';
        }
    }

    // 3. Client-side File size and mimetype validation
    mediaInput.addEventListener('change', function() {
        const placeholderIcon = document.getElementById('preview-placeholder-icon');
        fileError.style.display = 'none';
        previewMedia.style.display = 'none';
        previewMedia.innerHTML = '';
        
        if (!mediaInput.files || mediaInput.files.length === 0) {
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            return;
        }

        const file = mediaInput.files[0];
        const size = file.size;
        const type = file.type;
        
        const isImage = type.startsWith('image/');
        const isVideo = type.startsWith('video/');

        if (!isImage && !isVideo) {
            showFileError("Invalid format: Only image or video attachments are permitted.");
            mediaInput.value = '';
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            return;
        }

        // Validate: 8MB limit for images
        if (isImage && size > (8 * 1024 * 1024)) {
            showFileError(`Selected image is too large (${(size / (1024 * 1024)).toFixed(1)}MB). Max limit is 8MB.`);
            mediaInput.value = '';
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            return;
        }

        // Validate: 70MB limit for videos
        if (isVideo && size > (70 * 1024 * 1024)) {
            showFileError(`Selected video is too large (${(size / (1024 * 1024)).toFixed(1)}MB). Max limit is 70MB.`);
            mediaInput.value = '';
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            return;
        }

        // Render attachment preview in preview card
        if (placeholderIcon) placeholderIcon.style.display = 'none';
        previewMedia.style.display = 'flex';
        const fileUrl = URL.createObjectURL(file);
        
        if (isImage) {
            const img = document.createElement('img');
            img.src = fileUrl;
            previewMedia.appendChild(img);
        } else {
            const video = document.createElement('video');
            video.src = fileUrl;
            video.controls = true;
            previewMedia.appendChild(video);
        }
    });

    function showFileError(msg) {
        fileError.textContent = msg;
        fileError.style.display = 'block';
    }

    // 4. Toggle Scheduling Date selection box
    toggleSchedule.addEventListener('change', function() {
        if (toggleSchedule.checked) {
            scheduleContainer.style.display = 'block';
            scheduleType.value = 'later';
            btnPublish.textContent = '📅 Schedule Post';
        } else {
            scheduleContainer.style.display = 'none';
            scheduleType.value = 'now';
            btnPublish.textContent = '🚀 Publish Post';
        }
    });

    // 5. Submit post via AJAX fetch
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Ensure at least one platform checked
        const checked = Array.from(checkboxes).some(c => c.checked);
        if (!checked) {
            alert('Please choose at least one social media channel.');
            return;
        }

        const checkedPlatforms = Array.from(checkboxes).filter(c => c.checked).map(c => c.value);
        if (checkedPlatforms.includes('instagram') && mediaInput.files.length === 0) {
            alert('Instagram requires a photo or video attachment to publish.');
            return;
        }
        
        if (checkedPlatforms.includes('youtube') && mediaInput.files.length === 0) {
            alert('YouTube posting requires a video attachment.');
            return;
        }

        // Disable UI and trigger loader status
        btnPublish.disabled = true;
        submitLoading.style.display = 'inline-block';

        const formData = new FormData(form);

        fetch('composer_submit.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(errData => { throw new Error(errData.error || 'Server error occurred.'); });
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert('Post created successfully!');
                // Redirect user to calendar view to see their changes
                window.location.href = 'calendar.php';
            } else {
                alert('Error publishing post: ' + data.error);
                btnPublish.disabled = false;
                submitLoading.style.display = 'none';
            }
        })
        .catch(err => {
            console.error(err);
            alert('Request Failed: ' + err.message);
            btnPublish.disabled = false;
            submitLoading.style.display = 'none';
        });
    });
});
