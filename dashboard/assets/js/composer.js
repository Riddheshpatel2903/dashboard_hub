/**
 * Create Post Composer Interactive Logic.
 */
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('composer-form');
    if (!form) return;

    const textarea = document.getElementById('content');
    const mediaInput = document.getElementById('media');
    const fileError = document.getElementById('file-error');
    const checkboxes = document.querySelectorAll('input[name="platforms[]"]');
    const postTypeRadios = document.querySelectorAll('input[name="post_type"]');
    const igWarning = document.getElementById('ig-warning');
    const ytInfo = document.getElementById('yt-info');
    const ytTitleGroup = document.getElementById('youtube-title-group');
    const ytTitleInput = document.getElementById('title');
    
    const toggleSchedule = document.getElementById('toggle-schedule');
    const scheduleContainer = document.getElementById('schedule-container');
    const scheduleType = document.getElementById('schedule-type');
    const btnPublish = document.getElementById('btn-publish');
    const titleCharCount = document.getElementById('title-char-count');
    const contentCharCount = document.getElementById('content-char-count');
    const publishingOverlay = document.getElementById('publishing-overlay');

    // Scheduler inputs
    const schedDate = document.getElementById('sched-date');
    const schedHour = document.getElementById('sched-hour');
    const schedMinute = document.getElementById('sched-minute');
    const scheduledAtHidden = document.getElementById('scheduled-at');

    // Preview Elements
    const previewTabBar = document.getElementById('preview-tab-bar');
    const phoneScreenContent = document.getElementById('phone-screen-content');

    // Cropper globals
    let cropperInstance = null;
    let croppedBlob = null;
    let originalFile = null;
    let activePreviewPlatform = null;

    // Helper: format YYYY-MM-DD
    const todayStr = new Date().toISOString().split('T')[0];
    if (schedDate) {
        schedDate.value = todayStr;
        schedDate.min = todayStr;
    }

    // Combine date/time selects into single ISO-like string
    function updateScheduledAt() {
        if (!schedDate || !schedHour || !schedMinute || !scheduledAtHidden) return;
        if (schedDate.value) {
            scheduledAtHidden.value = `${schedDate.value}T${schedHour.value}:${schedMinute.value}`;
        }
    }
    if (schedDate) {
        schedDate.addEventListener('change', updateScheduledAt);
        schedHour.addEventListener('change', updateScheduledAt);
        schedMinute.addEventListener('change', updateScheduledAt);
        updateScheduledAt();
    }

    function getSelectedPostType() {
        const checkedRadio = document.querySelector('input[name="post_type"]:checked');
        return checkedRadio && checkedRadio.value === 'video' ? 'video' : 'image';
    }

    function clearSelectedMedia() {
        if (mediaInput) {
            mediaInput.value = '';
        }
        originalFile = null;
        croppedBlob = null;
        if (fileError) {
            fileError.classList.add('hidden');
            fileError.textContent = '';
        }
    }

    function matchesSelectedPostType(file) {
        if (!file) return true;

        const selectedType = getSelectedPostType();
        const type = file.type || '';
        const name = file.name || '';

        if (selectedType === 'video') {
            return type.startsWith('video/') || /\.(mp4|mov|avi|mkv|webm)$/i.test(name);
        }

        return type.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif|svg)$/i.test(name);
    }

    function updateSubmitButtonText() {
        const isVideo = getSelectedPostType() === 'video';
        const isScheduled = toggleSchedule.checked;
        if (isScheduled) {
            btnPublish.textContent = isVideo ? '📅 Schedule Reel' : '📅 Schedule Post';
        } else {
            btnPublish.textContent = isVideo ? '🚀 Publish Reel' : '🚀 Publish Post';
        }
    }

    function updatePostTypeRestrictions() {
        const selectedType = getSelectedPostType();
        if (mediaInput) {
            mediaInput.accept = selectedType === 'video' ? 'video/*' : 'image/*';
        }

        const currentFile = mediaInput.files[0] || originalFile;
        if (currentFile && !matchesSelectedPostType(currentFile)) {
            clearSelectedMedia();
        }

        checkboxes.forEach(chk => {
            const label = chk.closest('.platform-checkbox-label');
            const allowedTypes = (label?.dataset.allowedTypes || '')
                .split(',')
                .map(value => value.trim())
                .filter(Boolean);
            const isAllowed = allowedTypes.length === 0 || allowedTypes.includes(selectedType);

            if (!isAllowed) {
                if (chk.checked) {
                    chk.checked = false;
                }
                if (label) {
                    label.classList.add('hidden');
                    label.classList.remove('selected');
                }
            } else if (label) {
                label.classList.remove('hidden');
            }
        });

        updatePlatformStates();
        updateSubmitButtonText();
    }

    function updateCharacterCounts() {
        const checked = getCheckedPlatforms();
        const contentLen = textarea.value.length;
        
        let maxContentLen = 63206; // Default/Facebook
        let limitingPlatform = 'Facebook';
        
        if (checked.includes('instagram')) {
            maxContentLen = 2200;
            limitingPlatform = 'Instagram';
        }
        if (checked.includes('linkedin') && maxContentLen > 3000) {
            maxContentLen = 3000;
            limitingPlatform = 'LinkedIn';
        }
        if (checked.includes('google_business') && maxContentLen > 1500) {
            maxContentLen = 1500;
            limitingPlatform = 'Google Business';
        }
        if (checked.includes('youtube') && maxContentLen > 5000) {
            maxContentLen = 5000;
            limitingPlatform = 'YouTube';
        }
        
        if (contentCharCount) {
            contentCharCount.textContent = `${contentLen} / ${maxContentLen} characters`;
            if (contentLen > maxContentLen) {
                contentCharCount.classList.add('text-error', 'font-bold');
            } else {
                contentCharCount.classList.remove('text-error', 'font-bold');
            }
        }
        
        if (ytTitleInput && titleCharCount) {
            const titleLen = ytTitleInput.value.length;
            titleCharCount.textContent = `${titleLen} / 100 characters`;
            if (titleLen > 100) {
                titleCharCount.classList.add('text-error', 'font-bold');
            } else {
                titleCharCount.classList.remove('text-error', 'font-bold');
            }
        }
    }

    // 1. Textarea live sync
    textarea.addEventListener('input', function() {
        updateCharacterCounts();
        validatePublishButton();
        if (activePreviewPlatform) {
            renderPreview(activePreviewPlatform);
        }
    });

    if (ytTitleInput) {
        ytTitleInput.addEventListener('input', function() {
            updateCharacterCounts();
            validatePublishButton();
            if (activePreviewPlatform === 'youtube') {
                renderPreview('youtube');
            }
        });
    }

    // Platform checkbox state and tabs builder
    function updatePlatformStates() {
        const file = mediaInput.files[0] || originalFile;
        let isVideo = false;
        let isImage = false;

        if (file) {
            const type = file.type || '';
            const name = file.name || '';
            isVideo = type.startsWith('video/') || /\.(mp4|mov|avi|mkv|webm)$/i.test(name);
            isImage = type.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif|svg)$/i.test(name);
        }

        const ytCheckbox = document.getElementById('platform-youtube');
        if (ytCheckbox) {
            const ytLabel = ytCheckbox.closest('.platform-checkbox-label');
            const selectedType = getSelectedPostType();
            const isAllowedByPostType = (ytLabel?.dataset.allowedTypes || '')
                .split(',')
                .map(value => value.trim())
                .filter(Boolean)
                .includes(selectedType);

            if (!isAllowedByPostType) {
                const wasChecked = ytCheckbox.checked;
                if (ytCheckbox.checked) ytCheckbox.checked = false;
                ytCheckbox.disabled = true;
                if (ytLabel) {
                    ytLabel.classList.add('opacity-40', 'cursor-not-allowed');
                    ytLabel.title = 'YouTube is not available for this post type';
                }
                if (wasChecked) {
                    rebuildPreviewTabs();
                }
            } else {
                ytCheckbox.disabled = false;
                if (ytLabel) {
                    ytLabel.classList.remove('opacity-40', 'cursor-not-allowed');
                    ytLabel.title = 'YouTube Video Upload';
                }
            }
        }

        // Highlight selected labels
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
        rebuildPreviewTabs();
        updateCharacterCounts();
        validatePublishButton();
    }

    checkboxes.forEach(chk => {
        chk.addEventListener('change', updatePlatformStates);
    });

    postTypeRadios.forEach(radio => {
        radio.addEventListener('change', updatePostTypeRestrictions);
    });

    function updatePlatformNotices() {
        const checked = getCheckedPlatforms();

        if (checked.includes('youtube')) {
            ytTitleGroup.classList.remove('hidden');
        } else {
            ytTitleGroup.classList.add('hidden');
        }
    }

    function getCheckedPlatforms() {
        return Array.from(checkboxes).filter(c => c.checked).map(c => c.value);
    }

    // Rebuild the preview tabs row
    function rebuildPreviewTabs() {
        const checked = getCheckedPlatforms();
        previewTabBar.innerHTML = '';

        if (checked.length === 0) {
            previewTabBar.classList.add('hidden');
            activePreviewPlatform = null;
            renderPreview(null);
            return;
        }

        previewTabBar.classList.remove('hidden');
        
        // Find if active platform is still selected
        if (!activePreviewPlatform || !checked.includes(activePreviewPlatform)) {
            activePreviewPlatform = checked[0];
        }

        checked.forEach(p => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `px-sm py-1 rounded text-xs font-bold capitalize transition-all border ${
                activePreviewPlatform === p 
                ? 'bg-primary text-on-primary border-primary shadow-xs' 
                : 'bg-surface-container border-surface-variant text-on-surface hover:bg-surface-container-high'
            }`;
            btn.textContent = p === 'google_business' ? 'Google Business Profile' : p;
            btn.addEventListener('click', () => {
                activePreviewPlatform = p;
                rebuildPreviewTabs(); // update active class
            });
            previewTabBar.appendChild(btn);
        });

        renderPreview(activePreviewPlatform);
    }

    /**
     * C: Platform capability matrix — disable Publish button with inline messages
     * when platform-specific media requirements are not satisfied.
     */
    function validatePublishButton() {
        const checked = getCheckedPlatforms();
        const file = mediaInput.files[0] || originalFile;
        const hasFile = !!(file || croppedBlob);
        const isVideo = hasFile && file && (file.type.startsWith('video/') || /\.(mp4|mov|avi|mkv|webm)$/i.test(file.name || ''));

        let blockReason = null;

        if (checked.includes('youtube') && !isVideo) {
            blockReason = '📹 YouTube requires a video attachment.';
        }

        // Check description character limitations
        const contentLen = textarea.value.length;
        let maxContentLen = 63206;
        let limitingPlatform = '';
        if (checked.includes('instagram')) { maxContentLen = 2200; limitingPlatform = 'Instagram'; }
        if (checked.includes('linkedin') && maxContentLen > 3000) { maxContentLen = 3000; limitingPlatform = 'LinkedIn'; }
        if (checked.includes('google_business') && maxContentLen > 1500) { maxContentLen = 1500; limitingPlatform = 'Google Business'; }
        if (checked.includes('youtube') && maxContentLen > 5000) { maxContentLen = 5000; limitingPlatform = 'YouTube'; }
        
        if (contentLen > maxContentLen) {
            blockReason = `📝 Post content exceeds the ${maxContentLen} character limit for ${limitingPlatform}.`;
        }
        
        if (checked.includes('youtube') && ytTitleInput) {
            if (ytTitleInput.value.length > 100) {
                blockReason = '📹 YouTube Title exceeds 100 character limit.';
            } else if (ytTitleInput.value.trim().length === 0) {
                blockReason = '📹 YouTube Video Title is required.';
            }
        }

        // Show/hide inline validation message
        let validationMsg = document.getElementById('platform-validation-msg');
        if (!validationMsg && btnPublish) {
            validationMsg = document.createElement('p');
            validationMsg.id = 'platform-validation-msg';
            validationMsg.className = 'text-xs text-error font-semibold mt-xs hidden';
            btnPublish.parentNode.insertBefore(validationMsg, btnPublish.nextSibling);
        }

        if (blockReason) {
            btnPublish.disabled = true;
            btnPublish.classList.add('opacity-50', 'cursor-not-allowed');
            if (validationMsg) {
                validationMsg.textContent = blockReason;
                validationMsg.classList.remove('hidden');
            }
        } else {
            btnPublish.disabled = false;
            btnPublish.classList.remove('opacity-50', 'cursor-not-allowed');
            if (validationMsg) {
                validationMsg.classList.add('hidden');
            }
        }
    }

    // 3. Media file upload triggers cropping modal if image
    mediaInput.addEventListener('change', function() {
        fileError.classList.add('hidden');
        
        if (!mediaInput.files || mediaInput.files.length === 0) {
            originalFile = null;
            croppedBlob = null;
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
            originalFile = null;
            croppedBlob = null;
            updatePlatformStates();
            return;
        }

        // Image size check (8MB)
        if (isImage && size > (8 * 1024 * 1024)) {
            showFileError(`Selected image is too large (${(size / (1024 * 1024)).toFixed(1)}MB). Max limit is 8MB.`);
            mediaInput.value = '';
            originalFile = null;
            croppedBlob = null;
            updatePlatformStates();
            return;
        }

        // Video size check (70MB)
        if (isVideo && size > (70 * 1024 * 1024)) {
            showFileError(`Selected video is too large (${(size / (1024 * 1024)).toFixed(1)}MB). Max limit is 70MB.`);
            mediaInput.value = '';
            originalFile = null;
            croppedBlob = null;
            updatePlatformStates();
            return;
        }

        originalFile = file;

        croppedBlob = null;
        updatePlatformStates();
    });

    function showFileError(msg) {
        fileError.textContent = msg;
        fileError.classList.remove('hidden');
    }

    // 4. Toggle Scheduling Date selection box
    toggleSchedule.addEventListener('change', function() {
        if (toggleSchedule.checked) {
            scheduleContainer.classList.remove('hidden');
            scheduleType.value = 'later';
        } else {
            scheduleContainer.classList.add('hidden');
            scheduleType.value = 'now';
        }
        updateSubmitButtonText();
    });

    // 5. Submit form with cropped blob override
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const checked = getCheckedPlatforms();
        if (checked.length === 0) {
            alert('Please choose at least one social media channel.');
            return;
        }

        // Validate scheduled date/time is in the future
        if (toggleSchedule.checked) {
            updateScheduledAt();
            const schedValue = scheduledAtHidden.value; // Format: YYYY-MM-DDTHH:MM
            if (!schedValue) {
                alert('Please select a valid scheduled release date and time.');
                return;
            }
            const schedDateObj = new Date(schedValue);
            const now = new Date();
            if (schedDateObj <= now) {
                alert('The scheduled release date and time must be in the future. Please select a future time.');
                return;
            }
        }

        const file = mediaInput.files[0] || originalFile;
        if (checked.includes('youtube')) {
            if (!file) {
                alert('YouTube posting requires a video attachment.');
                return;
            }
            if (!file.type.startsWith('video/') && !/\.(mp4|mov|avi|mkv|webm)$/i.test(file.name)) {
                alert('YouTube only supports video uploads. Please attach a video file.');
                return;
            }
        }

        // Disable UI and show loading overlay
        btnPublish.disabled = true;
        if (publishingOverlay) {
            publishingOverlay.classList.remove('hidden');
        }

        const formData = new FormData(form);
        if (croppedBlob) {
            formData.delete('media');
            formData.append('media', croppedBlob, 'cropped_image.jpg');
        }

        fetch('composer_submit.php', {
            method: 'POST',
            body: formData
        })
        .then(res => {
            if (!res.ok) {
                return res.json().then(errData => { throw new Error(errData.error || 'Server error.'); });
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert('Post created successfully!');
                window.location.href = 'calendar.php';
            } else {
                alert('Error: ' + data.error);
                btnPublish.disabled = false;
                if (publishingOverlay) {
                    publishingOverlay.classList.add('hidden');
                }
            }
        })
        .catch(err => {
            console.error(err);
            alert('Request Failed: ' + err.message);
            btnPublish.disabled = false;
            if (publishingOverlay) {
                publishingOverlay.classList.add('hidden');
            }
        });
    });

    // Platform-specific mock screens generator
    function renderPreview(platform) {
        const contentVal = textarea.value.trim() || 'Post caption preview will render here...';
        const file = mediaInput.files[0] || originalFile;
        let fileUrl = '';
        let isImage = false;
        let isVideo = false;

        if (croppedBlob) {
            fileUrl = URL.createObjectURL(croppedBlob);
            isImage = true;
        } else if (file) {
            fileUrl = URL.createObjectURL(file);
            const type = file.type || '';
            const name = file.name || '';
            isVideo = type.startsWith('video/') || /\.(mp4|mov|avi|mkv|webm)$/i.test(name);
            isImage = type.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif|svg)$/i.test(name);
        }

        let mediaHtml = '';
        if (fileUrl) {
            if (isImage) {
                mediaHtml = `<img src="${fileUrl}" class="w-full h-auto object-contain max-h-[300px] rounded-lg bg-black/5" alt="Preview" />`;
            } else if (isVideo) {
                mediaHtml = `<video src="${fileUrl}" controls class="w-full h-auto object-contain max-h-[300px] rounded-lg bg-black/5"></video>`;
            }
        } else {
            mediaHtml = `<div class="w-full h-40 bg-gray-100 flex flex-col items-center justify-center text-gray-400 rounded-lg border border-dashed border-gray-200">
                <span class="material-symbols-outlined text-3xl">add_a_photo</span>
                <span class="text-[10px] mt-1">No media attached</span>
            </div>`;
        }

        if (platform === 'facebook') {
            phoneScreenContent.className = "h-full bg-[#f0f2f5] p-3 overflow-y-auto no-scrollbar pt-8";
            phoneScreenContent.innerHTML = `
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 space-y-3 text-xs">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm">C</div>
                        <div class="text-left">
                            <p class="font-bold text-[13px] text-gray-900 flex items-center gap-0.5">Acme Corporate <span class="material-symbols-outlined text-blue-500 text-[14px]">verified</span></p>
                            <p class="text-[10px] text-gray-500 flex items-center gap-0.5">Just now · <span class="material-symbols-outlined text-[10px]">public</span></p>
                        </div>
                    </div>
                    <p class="text-gray-800 leading-normal whitespace-pre-line text-left">${contentVal}</p>
                    <div class="mt-2">${mediaHtml}</div>
                    <div class="flex items-center justify-between text-[11px] text-gray-500 border-t border-b border-gray-100 py-1.5 px-1 mt-2">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[12px] text-blue-500">thumb_up</span> 0</span>
                        <span>0 Comments · 0 Shares</span>
                    </div>
                    <div class="grid grid-cols-3 text-center text-gray-600 text-[11px] font-semibold pt-1">
                        <div class="flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded"><span class="material-symbols-outlined text-[14px]">thumb_up</span> Like</div>
                        <div class="flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded"><span class="material-symbols-outlined text-[14px]">chat_bubble</span> Comment</div>
                        <div class="flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded"><span class="material-symbols-outlined text-[14px]">share</span> Share</div>
                    </div>
                </div>
            `;
        } else if (platform === 'instagram') {
            phoneScreenContent.className = "h-full bg-white p-0 overflow-y-auto no-scrollbar pt-8";
            phoneScreenContent.innerHTML = `
                <div class="flex items-center justify-between p-3 border-b border-gray-100 text-xs">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 p-[1.5px]">
                            <div class="w-full h-full rounded-full bg-white p-[1.5px]">
                                <div class="w-full h-full rounded-full bg-gray-200"></div>
                            </div>
                        </div>
                        <div class="text-left">
                            <p class="font-bold text-gray-900">acmecorporate</p>
                            <p class="text-[9px] text-gray-500">Sponsored</p>
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-gray-500">more_horiz</span>
                </div>
                <div class="w-full bg-gray-50 flex items-center justify-center overflow-hidden">
                    ${fileUrl ? (isImage ? `<img src="${fileUrl}" class="w-full h-auto max-h-[340px] object-contain" />` : `<video src="${fileUrl}" controls class="w-full h-auto max-h-[340px] object-contain"></video>`) : `<div class="text-gray-400 flex flex-col items-center"><span class="material-symbols-outlined text-3xl">add_a_photo</span><span class="text-[10px] mt-1">No media attached</span></div>`}
                </div>
                <div class="p-3 space-y-2 text-xs">
                    <div class="flex justify-between items-center text-gray-800">
                        <div class="flex gap-3">
                            <span class="material-symbols-outlined text-[20px]">favorite</span>
                            <span class="material-symbols-outlined text-[20px]">chat_bubble</span>
                            <span class="material-symbols-outlined text-[20px]">send</span>
                        </div>
                        <span class="material-symbols-outlined text-[20px]">bookmark</span>
                    </div>
                    <p class="font-bold text-gray-900 text-[11px] text-left">1,248 likes</p>
                    <div class="leading-relaxed text-[11px] text-left">
                        <span class="font-bold mr-1">acmecorporate</span>
                        <span class="text-gray-800 whitespace-pre-line">${contentVal}</span>
                    </div>
                    <p class="text-[9px] text-gray-400 uppercase text-left">Just now</p>
                </div>
            `;
        } else if (platform === 'youtube') {
            phoneScreenContent.className = "h-full bg-white p-3 overflow-y-auto no-scrollbar pt-8";
            const ytTitle = ytTitleInput.value || 'YouTube Video Title';
            phoneScreenContent.innerHTML = `
                <div class="space-y-3 text-xs text-left">
                    <div class="w-full aspect-video bg-black rounded-lg overflow-hidden flex items-center justify-center text-white relative">
                        ${fileUrl && isVideo ? `<video src="${fileUrl}" class="w-full h-full object-contain" controls></video>` : `<div class="text-gray-400 flex flex-col items-center"><span class="material-symbols-outlined text-4xl text-red-600">play_circle</span><span class="text-[10px] mt-1 text-white">Attach video to preview player</span></div>`}
                    </div>
                    <div class="space-y-1">
                        <h3 class="font-bold text-[14px] leading-snug text-gray-900">${ytTitle}</h3>
                        <p class="text-[10px] text-gray-500">0 views · Just now</p>
                    </div>
                    <div class="flex items-center justify-between border-t border-b border-gray-100 py-2">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-red-600 flex items-center justify-center text-white font-bold text-xs">Y</div>
                            <div>
                                <p class="font-bold text-[11px] text-gray-950">Acme Corporate</p>
                                <p class="text-[9px] text-gray-500">0 subscribers</p>
                            </div>
                        </div>
                        <button class="bg-red-600 text-white font-bold text-[10px] px-3 py-1 rounded-full uppercase">Subscribe</button>
                    </div>
                    <p class="text-gray-800 text-[11px] whitespace-pre-line leading-relaxed bg-gray-50 p-2 rounded">${contentVal}</p>
                </div>
            `;
        } else if (platform === 'linkedin') {
            phoneScreenContent.className = "h-full bg-[#f3f4f6] p-3 overflow-y-auto no-scrollbar pt-8";
            phoneScreenContent.innerHTML = `
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 space-y-3 text-xs text-left">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-xs">A</div>
                            <div>
                                <p class="font-bold text-[12px] text-gray-950">Acme Corporate</p>
                                <p class="text-[9px] text-gray-500">Social Media Marketing Specialist</p>
                                <p class="text-[9px] text-gray-500 flex items-center gap-1">Just now · <span class="material-symbols-outlined text-[9px]">public</span></p>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-gray-400">more_horiz</span>
                    </div>
                    <p class="text-gray-800 whitespace-pre-line leading-relaxed">${contentVal}</p>
                    <div class="mt-2">${mediaHtml}</div>
                    <div class="flex items-center justify-between text-[10px] text-gray-500 border-b border-gray-100 pb-2 mt-2">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[11px] text-blue-600">thumb_up</span> 0</span>
                        <span>0 comments · 0 reposts</span>
                    </div>
                    <div class="grid grid-cols-4 text-center text-gray-600 text-[10px] font-semibold pt-1">
                        <div class="flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded"><span class="material-symbols-outlined text-[13px]">thumb_up</span> Like</div>
                        <div class="flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded"><span class="material-symbols-outlined text-[13px]">chat</span> Comment</div>
                        <div class="flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded"><span class="material-symbols-outlined text-[13px]">share</span> Share</div>
                        <div class="flex items-center justify-center gap-1 py-1 hover:bg-gray-50 rounded"><span class="material-symbols-outlined text-[13px]">send</span> Send</div>
                    </div>
                </div>
            `;
        } else if (platform === 'google_business') {
            phoneScreenContent.className = "h-full bg-white p-3 overflow-y-auto no-scrollbar pt-8";
            phoneScreenContent.innerHTML = `
                <div class="border border-gray-200 rounded-lg p-3 space-y-3 text-xs text-left shadow-sm">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-xs"><span class="material-symbols-outlined text-sm">store</span></div>
                        <div>
                            <p class="font-bold text-[12px] text-gray-900">Acme Corporate</p>
                            <p class="text-[9px] text-green-700">Google Business Profile</p>
                        </div>
                    </div>
                    <div class="mt-2">${mediaHtml}</div>
                    <div class="space-y-2">
                        <span class="inline-block bg-blue-50 text-blue-700 font-bold text-[9px] px-2 py-0.5 rounded">UPDATE</span>
                        <p class="text-gray-800 whitespace-pre-line leading-relaxed text-[11px]">${contentVal}</p>
                    </div>
                    <div class="pt-2 border-t border-gray-100 flex justify-between items-center">
                        <button class="bg-[#1a73e8] text-white font-bold text-[10px] px-4 py-1.5 rounded uppercase">Learn More</button>
                        <span class="material-symbols-outlined text-gray-400 text-sm">share</span>
                    </div>
                </div>
            `;
        } else if (platform === 'whatsapp') {
            phoneScreenContent.className = "h-full bg-[#efeae2] p-3 overflow-y-auto no-scrollbar relative flex flex-col justify-end pt-8";
            phoneScreenContent.innerHTML = `
                <div class="absolute inset-0 bg-opacity-5 pointer-events-none" style="background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 16px 16px;"></div>
                <div class="bg-white rounded-lg shadow-xs p-2 max-w-[85%] self-start text-xs border border-gray-200/50 space-y-2 relative z-10 text-left">
                    <div>${mediaHtml}</div>
                    <p class="text-gray-900 whitespace-pre-line leading-relaxed">${contentVal}</p>
                    <div class="text-[9px] text-gray-500 text-right">10:42 AM ✔✔</div>
                </div>
            `;
        } else {
            phoneScreenContent.className = "h-full bg-white flex flex-col items-center justify-center text-gray-400 text-xs pt-8";
            phoneScreenContent.innerHTML = `
                <span class="material-symbols-outlined text-4xl">mobile_screen_share</span>
                <span class="mt-2">Select a platform to preview</span>
            `;
        }
    }

    // Select All Platforms
    const btnSelectAll = document.getElementById('btn-select-all');
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function() {
            const visibleLabels = document.querySelectorAll('.platform-checkbox-label:not(.hidden)');
            const allChecked = Array.from(visibleLabels).every(lbl => {
                const chk = lbl.querySelector('input[type="checkbox"]');
                return chk && chk.checked;
            });
            
            visibleLabels.forEach(lbl => {
                const chk = lbl.querySelector('input[type="checkbox"]');
                if (chk) {
                    chk.checked = !allChecked;
                }
            });
            updatePlatformStates();
            if (activePreviewPlatform) {
                renderPreview(activePreviewPlatform);
            }
        });
    }

    // Run initial state setup
    updatePostTypeRestrictions();
    validatePublishButton();
});
