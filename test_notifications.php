 function createCommentsModal() { const modalHTML = ` <div id="commentsModal" class="comments-modal"> <div class="comments-modal-content"> <div class="comments-header"> <h3>Comments</h3> <button class="close-comments" type="button">×</button> </div>

            <div class="comments-body" id="commentsBody">
                <!-- Comments will be loaded here dynamically -->
            </div>

            <div class="comment-form">
                <div class="comment-input-container">
                    <img src="${document.querySelector('.profile-avatar img')?.src || 'images/default-profile.png'}" alt="Your avatar" class="comment-avatar">
                    <textarea
                        id="commentInput"
                        class="comment-input"
                        placeholder="Write a comment..."
                        rows="1"
                        maxlength="500"></textarea>
                    <button
                        id="commentSubmit"
                        class="comment-submit"
                        type="button"
                        disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
`;

document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Function to open comments modal
window.openCommentsModal = function(postId) {
console.log('📖 Opening comments modal for post:', postId);

currentPostId = postId;
currentOffset = 0;
hasMoreComments = false;
totalCommentsCount = 0;

const modal = document.getElementById('commentsModal');
const commentsBody = document.getElementById('commentsBody');
const commentInput = document.getElementById('commentInput');

if (!modal || !commentsBody) {
    console.error('❌ Comments modal elements not found');
    return;
}

// Show modal immediately
modal.style.display = 'block';
document.body.style.overflow = 'hidden';

// Clear previous content and show loading
commentsBody.innerHTML = `
    <div style="text-align: center; padding: 40px; color: #666;">
        <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
        <p>Loading comments...</p>
    </div>
`;

// Reset comment input
if (commentInput) {
    commentInput.value = '';
    commentInput.style.height = 'auto';
}

updateCommentSubmitButton();

// Load initial comments
loadComments(postId, true);

// Focus on comment input after a short delay
setTimeout(() => {
    if (commentInput) {
        commentInput.focus();
    }
}, 500);
};

// Function to close comments modal
function closeCommentsModal() {
console.log('🔒 Closing comments modal');

const modal = document.getElementById('commentsModal');
if (modal) {
    modal.style.display = 'none';
}

document.body.style.overflow = 'auto';
currentPostId = null;
currentOffset = 0;
hasMoreComments = false;

// Close any open reaction panels
closeAllCommentReactionPanels();
}

// Enhanced function to loaLooking at your dashboard.php and profile_settings.php files, I'll generate the complete JavaScript code for the profile posts with the same reactions, comments, edit/delete functionality. Here's the comprehensive JavaScript for profile_settings.php:

<artifacts> <invoke name="artifacts"> <parameter name="command">create</parameter> <parameter name="type">application/vnd.ant.code</parameter> <parameter name="language">javascript</parameter> <parameter name="title">Profile Settings Complete JavaScript</parameter> <parameter name="id">profile_js_complete</parameter> <parameter name="content">// ======================================== // COMPLETE PROFILE SETTINGS JAVASCRIPT // Enhanced Reactions, Comments, Edit/Delete Posts // ========================================
// Global variables let currentPostId = null; let commentsLoading = false; let currentOffset = 0; let hasMoreComments = false; let totalCommentsCount = 0; const commentsPerLoad = 10; const currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
console.log('🚀 Initializing Profile Settings JavaScript...');

initializeProfileSystems();
initializeBannerUpload();
initializeCommentsSystem();
initializePostInteractions();
initializeModals();

console.log('✅ Profile Settings JavaScript Loaded Successfully!');
});

// ========================================
// BANNER UPLOAD SYSTEM
// ========================================
function initializeBannerUpload() {
const bannerUploadInput = document.getElementById('banner-upload');

if (bannerUploadInput) {
    bannerUploadInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Please select a valid image file (JPG, PNG, GIF, or WEBP)', 'error');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            showToast('Image must be smaller than 5MB', 'error');
            return;
        }

        uploadBanner(file);
    });
}
}

function uploadBanner(file) {
const formData = new FormData();
formData.append('action', 'upload_banner');
formData.append('banner_image', file);

showToast('Uploading banner...', 'success');

fetch('profile_settings.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        showToast('Banner updated successfully!', 'success');
        // Update banner image
        const bannerImg = document.querySelector('.banner-image');
        if (bannerImg && data.banner_url) {
            bannerImg.src = data.banner_url;
        }
    } else {
        showToast(data.message || 'Failed to update banner', 'error');
    }
})
.catch(error => {
    console.error('Banner upload error:', error);
    showToast('Error uploading banner', 'error');
});
}

// ========================================
// FOLLOW/UNFOLLOW SYSTEM
// ========================================
function toggleFollow(userId) {
const followBtn = document.getElementById('follow-btn');
if (!followBtn) return;

const isFollowing = followBtn.textContent.trim() === 'Following';
const action = isFollowing ? 'unfollow' : 'follow';

const formData = new FormData();
formData.append('action', action);
formData.append('user_id', userId);

// Show loading state
followBtn.disabled = true;
followBtn.textContent = isFollowing ? 'Unfollowing...' : 'Following...';

fetch('profile_settings.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        followBtn.textContent = data.action === 'followed' ? 'Following' : 'Follow';
        followBtn.className = data.action === 'followed' ? 'btn btn-secondary' : 'btn btn-primary';
        
        // Update follower count
        const followerCount = document.querySelector('.stat-item .stat-number');
        if (followerCount && data.new_follower_count !== undefined) {
            followerCount.textContent = new Intl.NumberFormat().format(data.new_follower_count);
        }
        
        showToast(data.message, 'success');
    } else {
        showToast(data.message || 'Action failed', 'error');
    }
})
.catch(error => {
    console.error('Follow/unfollow error:', error);
    showToast('Network error occurred', 'error');
})
.finally(() => {
    followBtn.disabled = false;
});
}

// ========================================
// POST REACTIONS SYSTEM
// ========================================
function initializeProfileSystems() {
// Initialize reaction handling
initializeReactionHandlers();

// Initialize mobile touch support
initializeMobileReactionSupport();
}

function initializeReactionHandlers() {
const likesContainers = document.querySelectorAll('.post-likes-container');

likesContainers.forEach(container => {
    let touchStartTime = 0;

    // Handle touch start
    container.addEventListener('touchstart', function(e) {
        touchStartTime = Date.now();
    });

    // Handle touch end with duration check
    container.addEventListener('touchend', function(e) {
        const touchDuration = Date.now() - touchStartTime;

        // Short tap - toggle reactions panel
        if (touchDuration < 200) {
            e.preventDefault();
            toggleReactionPanel(this);
        }
    });
});

// Close reactions when tapping elsewhere
document.addEventListener('touchstart', function(e) {
    const activeContainers = document.querySelectorAll('.post-likes-container.active');
    activeContainers.forEach(container => {
        if (!container.contains(e.target)) {
            container.classList.remove('active');
        }
    });
});
}

function toggleReactionPanel(container) {
const allContainers = document.querySelectorAll('.post-likes-container');

// Close all other panels
allContainers.forEach(c => {
    if (c !== container) {
        c.classList.remove('active');
    }
});

// Toggle current panel
container.classList.toggle('active');
}

// Enhanced reaction handling function function handleReaction(postId, reactionType) { console.log(🎭 Handling reaction: ${reactionType} for post: ${postId});

// Add loading state to the clicked reaction
const clickedReactionIcon = document.querySelector(`[data-post-id="${postId}"] [data-reaction="${reactionType}"]`);
if (clickedReactionIcon) {
    clickedReactionIcon.style.opacity = '0.6';
    clickedReactionIcon.style.pointerEvents = 'none';
}

const formData = new FormData();
formData.append('action', 'add_reaction');
formData.append('post_id', postId);
formData.append('reaction_type', reactionType);

fetch('post_reaction.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Update the UI with new reaction data
        updateReactionUI(postId, data.reaction_data, reactionType, data.action_type);

        // Hide the reaction panel
        const container = document.querySelector(`[data-post-id="${postId}"].post-likes-container`);
        if (container) {
            container.classList.remove('active');
        }

        // Show appropriate message
        let message = '';
        switch (data.action_type) {
            case 'added':
                message = `You reacted with ${reactionType}! ✨`;
                break;
            case 'updated':
                message = `Changed reaction to ${reactionType}! 🔄`;
                break;
            case 'removed':
                message = `Reaction removed! 👋`;
                break;
            default:
                message = 'Reaction updated!';
        }
        showToast(message, 'success');
    } else {
        showToast(data.message || 'Error adding reaction', 'error');
    }
})
.catch(error => {
    console.error('Error handling reaction:', error);
    showToast('An error occurred while adding the reaction.', 'error');
})
.finally(() => {
    // Remove loading state
    if (clickedReactionIcon) {
        clickedReactionIcon.style.opacity = '1';
        clickedReactionIcon.style.pointerEvents = 'auto';
    }
});
}

// Enhanced UI update function function updateReactionUI(postId, reactionData, newReaction, actionType) { const container = document.querySelector([data-post-id="${postId}"].post-likes-container); if (!container) return;

const likeButton = container.querySelector('.post-likes');
const likeCount = container.querySelector('.like-count');
const likeIcon = container.querySelector('.like-icon');

// Update like button state based on user reaction
if (reactionData.user_reaction) {
    likeButton.classList.add('active');
    likeButton.setAttribute('data-user-reaction', reactionData.user_reaction);
    if (likeIcon) {
        likeIcon.setAttribute('fill', 'var(--primary-color)');
        likeIcon.setAttribute('stroke', 'var(--primary-color)');
    }
} else {
    likeButton.classList.remove('active');
    likeButton.setAttribute('data-user-reaction', '');
    if (likeIcon) {
        likeIcon.setAttribute('fill', 'none');
        likeIcon.setAttribute('stroke', 'currentColor');
    }
}

// Update reaction count with animation
const totalReactions = reactionData.total_reactions;
if (likeCount) {
    // Add a subtle animation to the count change
    likeCount.style.transform = 'scale(1.1)';
    likeCount.style.transition = 'transform 0.2s ease';

    setTimeout(() => {
        if (totalReactions > 0) {
            likeCount.textContent = totalReactions + (totalReactions === 1 ? ' reaction' : ' reactions');
        } else {
            likeCount.textContent = '0 reactions';
        }

        // Reset animation
        likeCount.style.transform = 'scale(1)';
    }, 100);
}

// Add animation to the clicked reaction icon
const clickedReactionIcon = container.querySelector(`[data-reaction="${newReaction}"]`);
if (clickedReactionIcon && actionType !== 'removed') {
    clickedReactionIcon.classList.add('reaction-animation');

    // Create a floating emoji effect
    createFloatingEmoji(clickedReactionIcon, newReaction);

    setTimeout(() => {
        clickedReactionIcon.classList.remove('reaction-animation');
    }, 400);
}

// Update visual state of reaction icons
const allReactionIcons = container.querySelectorAll('.reaction-icon');
allReactionIcons.forEach(icon => {
    const iconReaction = icon.getAttribute('data-reaction');
    if (iconReaction === reactionData.user_reaction) {
        icon.style.backgroundColor = 'rgba(237, 90, 44, 0.2)';
        icon.style.transform = 'scale(1.1)';
    } else {
        icon.style.backgroundColor = '';
        icon.style.transform = '';
    }
});
}

// Create floating emoji animation effect
function createFloatingEmoji(element, reactionType) {
const emojiMap = {
'yummy': '🍔',
'delicious': '🍕',
'tasty': '🍰',
'love': '🍲',
'amazing': '🍗'
};

const emoji = emojiMap[reactionType];
if (!emoji) return;

const floatingEmoji = document.createElement('div');
floatingEmoji.textContent = emoji;
floatingEmoji.style.position = 'absolute';
floatingEmoji.style.fontSize = '20px';
floatingEmoji.style.pointerEvents = 'none';
floatingEmoji.style.zIndex = '9999';
floatingEmoji.style.animation = 'floatUp 2s ease-out forwards';

// Position relative to the clicked element
const rect = element.getBoundingClientRect();
floatingEmoji.style.left = (rect.left + rect.width / 2) + 'px';
floatingEmoji.style.top = (rect.top + window.scrollY) + 'px';

document.body.appendChild(floatingEmoji);

// Remove after animation
setTimeout(() => {
    if (floatingEmoji.parentNode) {
        floatingEmoji.parentNode.removeChild(floatingEmoji);
    }
}, 2000);
}

// Function to show reaction users
function showReactionUsers(postId) {
console.log('🎯 showReactionUsers called with postId:', postId);

const formData = new FormData();
formData.append('action', 'get_reaction_users');
formData.append('post_id', postId);

// Create and show modal
showReactionUsersModal();

const usersList = document.getElementById('postReactionUsersList');
if (usersList) {
    usersList.innerHTML = `
        <div style="text-align: center; padding: 30px;">
            <div class="loading-spinner" style="margin: 0 auto 15px;"></div>
            <p style="color: #666;">Loading reactions...</p>
        </div>
    `;
}

fetch('post_reaction.php', {
    method: 'POST',
    body: formData
})
.then(response => {
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }
    return response.json();
})
.then(data => {
    if (data.success && data.users) {
        displayPostReactionUsers(data.users);
    } else {
        throw new Error(data.message || 'No reaction data found');
    }
})
.catch(error => {
    console.error('❌ Error fetching reactions:', error);
    if (usersList) {
        usersList.innerHTML = `
            <div style="text-align: center; padding: 30px; color: #f44336;">
                <p style="margin-bottom: 15px;">Failed to load reactions</p>
                <button onclick="showReactionUsers(${postId})" 
                        style="background: #ED5A2C; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer;">
                    Try Again
                </button>
            </div>
        `;
    }
});
}

function showReactionUsersModal() { // Create modal if it doesn't exist let modal = document.getElementById('postReactionUsersModal'); if (!modal) { const modalHTML =             <div id="postReactionUsersModal" class="modal" style="display: none;">                 <div class="modal-content" style="max-width: 500px; max-height: 70vh; overflow: hidden;">                     <span class="close-modal" style="cursor: pointer;">&times;</span>                     <h2 style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">People who reacted</h2>                     <div id="postReactionUsersList" class="reaction-users-list" style="max-height: 400px; overflow-y: auto;">                         <!-- Post reaction users will be loaded here -->                     </div>                 </div>             </div>        ; document.body.insertAdjacentHTML('beforeend', modalHTML);

    modal = document.getElementById('postReactionUsersModal');
    const closeBtn = modal.querySelector('.close-modal');
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
}

modal.style.display = 'block';
}

function displayPostReactionUsers(users) {
const usersList = document.getElementById('postReactionUsersList');
if (!usersList) return;

if (!users || users.length === 0) {
    usersList.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #666;">
            <div style="font-size: 48px; margin-bottom: 15px;">😔</div>
            <p style="font-size: 16px;">No reactions yet</p>
            <p style="font-size: 14px; opacity: 0.8;">Be the first to react!</p>
        </div>
    `;
    return;
}

let html = '';
users.forEach((user) => {
    const userImage = user.PROFILE_IMAGE || 'images/default-profile.png';
    const reactionTime = formatReactionTime(user.CREATED_AT);
    const reactionType = user.REACTION_TYPE?.charAt(0).toUpperCase() + user.REACTION_TYPE?.slice(1) || 'Like';
    const reactionEmoji = user.REACTION_EMOJI || '👍';

    html += `
        <div class="reaction-user-item" style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid #eee; transition: all 0.2s ease; cursor: pointer;">
            <img src="${userImage}" 
                 alt="${user.NAME || 'User'}" 
                 class="reaction-user-avatar" 
                 style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #f0f0f0; transition: all 0.2s ease;"
                 onerror="this.src='images/default-profile.png'">
            <div class="reaction-user-info" style="flex: 1;">
                <div class="reaction-user-name" style="font-weight: 600; margin-bottom: 5px; color: #333; font-size: 16px;">
                    ${user.NAME || 'Unknown User'}
                </div>
                <div class="reaction-user-time" style="font-size: 13px; color: #666;">
                    Reacted ${reactionTime}
                </div>
            </div>
            <div class="reaction-user-emoji" style="font-size: 28px; margin-left: 15px; display: flex; flex-direction: column; align-items: center;">
                <span style="margin-bottom: 2px;">${reactionEmoji}</span>
                <small style="font-size: 11px; color: #888; text-transform: capitalize; font-weight: 500;">
                    ${reactionType}
                </small>
            </div>
        </div>
    `;
});

usersList.innerHTML = html;

// Add hover effects
usersList.querySelectorAll('.reaction-user-item').forEach(item => {
    item.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f8f9fa';
    });
    item.addEventListener('mouseleave', function() {
        this.style.backgroundColor = 'transparent';
    });
});
}

function initializeMobileReactionSupport() {
// Enhanced mobile touch support for reactions
const likesContainers = document.querySelectorAll('.post-likes-container');

likesContainers.forEach(container => {
    let touchStartTime = 0;
    let longPressTimer;

    container.addEventListener('touchstart', function(e) {
        touchStartTime = Date.now();
        
        longPressTimer = setTimeout(() => {
            // Long press detected - directly like with default reaction
            const postId = this.getAttribute('data-post-id');
            if (postId) {
                handleReaction(postId, 'yummy');
                this.classList.remove('active');

                // Provide haptic feedback if available
                if ('vibrate' in navigator) {
                    navigator.vibrate(50);
                }
            }
        }, 500);
    });

    container.addEventListener('touchend', function(e) {
        const touchDuration = Date.now() - touchStartTime;
        clearTimeout(longPressTimer);

        // Short tap - toggle reactions panel
        if (touchDuration < 200) {
            e.preventDefault();
            toggleReactionPanel(this);
        }
    });

    container.addEventListener('touchmove', function() {
        clearTimeout(longPressTimer);
    });
});
}

// ========================================
// COMMENTS SYSTEM
// ========================================
function initializeCommentsSystem() {
console.log('🚀 Initializing Enhanced Comments System...');

// Initialize modal elements
const commentsModal = document.getElementById('commentsModal');
const closeButton = document.querySelector('.close-comments');
const commentInput = document.getElementById('commentInput');
const commentSubmit = document.getElementById('commentSubmit');

// Create comments modal if it doesn't exist
if (!commentsModal) {
    createCommentsModal();
}

// Close button event listener
if (closeButton) {
    closeButton.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeCommentsModal();
    });
}

// Modal backdrop click to close
if (commentsModal) {
    commentsModal.addEventListener('click', function(e) {
        if (e.target === commentsModal) {
            closeCommentsModal();
        }
    });

    // Prevent modal content clicks from closing modal
    const modalContent = commentsModal.querySelector('.comments-modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
}

// Comment input event listeners
if (commentInput) {
    // Auto-resize textarea
    commentInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
        updateCommentSubmitButton();
    });

    // Enter key to submit (Shift+Enter for new line)
    commentInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim() && !commentSubmit.disabled) {
                addComment();
            }
        }
    });

    // Focus and blur events
    commentInput.addEventListener('focus', function() {
        this.parentElement.style.borderColor = 'var(--primary-color)';
    });

    commentInput.addEventListener('blur', function() {
        this.parentElement.style.borderColor = '#e0e0e0';
    });
}

// Comment submit button event listener
if (commentSubmit) {
    commentSubmit.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!this.disabled && commentInput && commentInput.value.trim()) {
            addComment();
        }
    });
}

// Initialize keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key to close modal
    if (e.key === 'Escape' && commentsModal && commentsModal.style.display === 'block') {
        closeCommentsModal();
    }
});

console.log('✅ Comments System Initialized Successfully!');
}

function createCommentsModal() { const modalHTML = ` <div id="commentsModal" class="comments-modal"> <div class="comments-modal-content"> <div class="comments-header"> <h3>Comments</h3> <button class="close-comments" type="button">×</button> </div>

            <div class="comments-body" id="commentsBody">
                <!-- Comments will be loaded here dynamically -->
            </div>

            <div class="comment-form">
                <div class="comment-input-container">
                    <img src="${getProfileImage()}" alt="Your avatar" class="comment-avatar">
                    <textarea
                        id="commentInput"
                        class="comment-input"
                        placeholder="Write a comment..."
                        rows="1"
                        maxlength="500"></textarea>
                    <button
                        id="commentSubmit"
                        class="comment-submit"
                        type="button"
                        disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
`;

document.body.insertAdjacentHTML('beforeend', modalHTML);
}

function getProfileImage() { // Get current user's profile imageLooking at your profile_settings.php and comparing it with the dashboard.php functionality, I'll generate the complete JavaScript code that provides the same post interactions (reactions, comments, edit/delete) for the profile page.

