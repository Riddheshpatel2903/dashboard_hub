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

    // Dynamic media attachment validation for YouTube (Only selectable when video is attached)
    function updatePlatformStates() {
        const files = mediaInput.files;
        let isVideoAttached = false;

        if (files && files.length > 0) {
            const file = files[0];
            const type = file.type || '';
            const name = file.name || '';
            if (type.startsWith('video/') || /\.(mp4|mov|avi|mkv|webm)$/i.test(name)) {
                isVideoAttached = true;
            }
        }

        const ytCheckbox = document.getElementById('platform-youtube');
        if (ytCheckbox) {
            const ytLabel = ytCheckbox.closest('.platform-checkbox-label');
            if (isVideoAttached) {
                ytCheckbox.disabled = false;
                if (ytLabel) {
                    ytLabel.classList.remove('opacity-40', 'cursor-not-allowed');
                    ytLabel.title = "YouTube Video Upload Supported";
                }
            } else {
                if (ytCheckbox.checked) {
                    ytCheckbox.checked = false;
                }
                ytCheckbox.disabled = true;
                if (ytLabel) {
                    ytLabel.classList.add('opacity-40', 'cursor-not-allowed');
                    ytLabel.title = "YouTube requires a video attachment";
                }
            }
        }

        // Highlight selected platform labels
        checkboxes.forEach(chk => {
            const label = chk.closest('.platform-checkbox-label');
            if (label) {
                if (chk.checked) {
                    label.classList.add('selected');
                } else {
                    label.classList.remove('selected');
                }
            }
        });

        updatePlatformNotices();
    }

    checkboxes.forEach(chk => {
        chk.addEventListener('change', function() {
            updatePlatformStates();
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

    // Run initial state check on page load
    updatePlatformStates();

    // 3. Client-side File size and mimetype validation
    mediaInput.addEventListener('change', function() {
        const placeholderIcon = document.getElementById('preview-placeholder-icon');
        fileError.style.display = 'none';
        previewMedia.style.display = 'none';
        previewMedia.innerHTML = '';
        
        if (!mediaInput.files || mediaInput.files.length === 0) {
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            updatePlatformStates();
            return;
        }

        const file = mediaInput.files[0];
        const size = file.size;
        const type = file.type || '';
        const name = file.name || '';
        
        const isImage = type.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif)$/i.test(name);
        const isVideo = type.startsWith('video/') || /\.(mp4|mov|avi|mkv|webm)$/i.test(name);

        if (!isImage && !isVideo) {
            showFileError("Invalid format: Only image or video attachments are permitted.");
            mediaInput.value = '';
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            updatePlatformStates();
            return;
        }

        // Validate: 8MB limit for images
        if (isImage && size > (8 * 1024 * 1024)) {
            showFileError(`Selected image is too large (${(size / (1024 * 1024)).toFixed(1)}MB). Max limit is 8MB.`);
            mediaInput.value = '';
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            updatePlatformStates();
            return;
        }

        // Validate: 70MB limit for videos
        if (isVideo && size > (70 * 1024 * 1024)) {
            showFileError(`Selected video is too large (${(size / (1024 * 1024)).toFixed(1)}MB). Max limit is 70MB.`);
            mediaInput.value = '';
            if (placeholderIcon) placeholderIcon.style.display = 'flex';
            updatePlatformStates();
            return;
        }

        // Update platform selection state based on attached file type
        updatePlatformStates();

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
        
        if (checkedPlatforms.includes('youtube')) {
            if (mediaInput.files.length === 0) {
                alert('YouTube posting requires a video attachment.');
                return;
            }
            const file = mediaInput.files[0];
            if (!file.type.startsWith('video/')) {
                alert('YouTube only supports video uploads. Please attach a video file.');
                return;
            }
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
    // Enforce 5-minute interval rounding on schedule datetime selection
    function roundToNearest5Minutes(date) {
        const coefficients = 1000 * 60 * 5; // 5 minutes in ms
        return new Date(Math.round(date.getTime() / coefficients) * coefficients);
    }

    const scheduleInput = document.getElementById('scheduled-at');
    if (scheduleInput) {
        // Default to current time rounded up to nearest 5 minutes
        const now = new Date();
        const roundedNow = roundToNearest5Minutes(now);
        
        // Format to YYYY-MM-DDTHH:MM local time compatible with datetime-local value
        const year = roundedNow.getFullYear();
        const month = String(roundedNow.getMonth() + 1).padStart(2, '0');
        const day = String(roundedNow.getDate()).padStart(2, '0');
        const hours = String(roundedNow.getHours()).padStart(2, '0');
        const minutes = String(roundedNow.getMinutes()).padStart(2, '0');
        
        const formattedDate = `${year}-${month}-${day}T${hours}:${minutes}`;
        scheduleInput.value = formattedDate;
        scheduleInput.min = formattedDate;

        // Automatically correct manual inputs to nearest 5-minute multiples
        scheduleInput.addEventListener('change', function() {
            if (!this.value) return;
            const selectedDate = new Date(this.value);
            const roundedSelected = roundToNearest5Minutes(selectedDate);
            
            const sYear = roundedSelected.getFullYear();
            const sMonth = String(roundedSelected.getMonth() + 1).padStart(2, '0');
            const sDay = String(roundedSelected.getDate()).padStart(2, '0');
            const sHours = String(roundedSelected.getHours()).padStart(2, '0');
            const sMinutes = String(roundedSelected.getMinutes()).padStart(2, '0');
            
            this.value = `${sYear}-${sMonth}-${sDay}T${sHours}:${sMinutes}`;
        });
    }
});
