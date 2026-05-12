// SkillSwap - Main JavaScript

// Initialize on document ready
$(document).ready(function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize datepickers
    initDatepickers();
    
    // Handle search form
    initSearch();
    
    // Handle form validations
    initFormValidation();
    
    // Handle AJAX requests
    initAjax();
    
    // Handle notifications
    initNotifications();
});

// Initialize tooltips
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Initialize datepickers
function initDatepickers() {
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            startDate: '0d',
            autoclose: true
        });
    }
    
    if ($.fn.timepicker) {
        $('.timepicker').timepicker({
            showMeridian: false,
            minuteStep: 15,
            defaultTime: '10:00'
        });
    }
}

// Initialize search
function initSearch() {
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        var query = $('#searchInput').val();
        if (query.length > 0) {
            window.location.href = window.APP_URL + '/pages/skills.php?search=' + encodeURIComponent(query);
        }
    });
}

// Initialize form validation
function initFormValidation() {
    $('.needs-validation').on('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        $(this).addClass('was-validated');
    });
    
    // Password strength checker
    $('#password, #registerPassword').on('input', function() {
        var password = $(this).val();
        var strength = checkPasswordStrength(password);
        updatePasswordStrength(strength);
    });
}

// Check password strength
function checkPasswordStrength(password) {
    var strength = 0;
    var feedback = [];
    
    if (password.length >= 8) {
        strength += 1;
    } else {
        feedback.push('At least 8 characters');
    }
    
    if (password.match(/[a-z]/)) {
        strength += 1;
    } else {
        feedback.push('Lowercase letter');
    }
    
    if (password.match(/[A-Z]/)) {
        strength += 1;
    } else {
        feedback.push('Uppercase letter');
    }
    
    if (password.match(/[0-9]/)) {
        strength += 1;
    } else {
        feedback.push('Number');
    }
    
    if (password.match(/[^a-zA-Z0-9]/)) {
        strength += 1;
    } else {
        feedback.push('Special character');
    }
    
    return { strength: strength, feedback: feedback };
}

// Update password strength indicator
function updatePasswordStrength(strength) {
    var $indicator = $('#passwordStrength');
    var $bar = $('#passwordStrengthBar');
    var colors = ['#dc3545', '#ffc107', '#28a745', '#17a2b8', '#667eea'];
    var labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    
    if (strength.strength > 0) {
        $indicator.text(labels[strength.strength - 1]).css('color', colors[strength.strength - 1]);
        $bar.css('width', (strength.strength * 20) + '%').css('background-color', colors[strength.strength - 1]);
    } else {
        $indicator.text('');
        $bar.css('width', '0%');
    }
}

// Initialize AJAX
function initAjax() {
    // Setup AJAX defaults
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        beforeSend: function() {
            $('#loading').show();
        },
        complete: function() {
            $('#loading').hide();
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                window.location.href = window.APP_URL + '/pages/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            } else if (xhr.status === 403) {
                showToast('Access denied', 'error');
            } else if (xhr.status === 500) {
                showToast('Server error. Please try again later.', 'error');
            }
        }
    });
}

// Initialize notifications
function initNotifications() {
    // Mark notification as read on click
    $('.notification-item').on('click', function() {
        var id = $(this).data('id');
        if (id) {
            $.post(window.APP_URL + '/api/notifications.php', { action: 'mark_read', id: id });
        }
    });
}

// Show toast notification
function showToast(message, type = 'info') {
    var bgColor = '#17a2b8';
    var icon = 'fa-info-circle';
    
    switch(type) {
        case 'success':
            bgColor = '#28a745';
            icon = 'fa-check-circle';
            break;
        case 'error':
            bgColor = '#dc3545';
            icon = 'fa-exclamation-circle';
            break;
        case 'warning':
            bgColor = '#ffc107';
            icon = 'fa-exclamation-triangle';
            break;
    }
    
    var toast = `
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header" style="background-color: ${bgColor}; color: white;">
                <i class="fas ${icon} me-2"></i>
                <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    $('.toast-container').append(toast);
    setTimeout(function() {
        $('.toast').last().remove();
    }, 5000);
}

// Booking functions
function acceptBooking(bookingId) {
    if (confirm('Are you sure you want to accept this booking?')) {
        $.ajax({
            url: window.APP_URL + '/api/bookings.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'accept', id: bookingId },
            success: function(response) {
                if (response.success) {
                    showToast('Booking accepted!', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(response.message || 'Failed to accept booking', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Error: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    }
}

function rejectBooking(bookingId) {
    if (confirm('Are you sure you want to reject this booking?')) {
        $.ajax({
            url: window.APP_URL + '/api/bookings.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'reject', id: bookingId },
            success: function(response) {
                if (response.success) {
                    showToast('Booking rejected!', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(response.message || 'Failed to reject booking', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Error: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    }
}

function completeBooking(bookingId) {
    // Kept for backward compatibility — triggers teacher confirm flow
    teacherConfirmBooking(bookingId);
}

// Teacher confirms session is complete (step 1 of mutual agreement)
function teacherConfirmBooking(bookingId) {
    $.ajax({
        url: window.APP_URL + '/api/bookings.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'teacher_confirm', id: bookingId },
        success: function(response) {
            if (response.success) {
                if (response.completed) {
                    showToast('Session completed! Points have been transferred.', 'success');
                } else {
                    showToast(response.message || 'Confirmation sent. Waiting for learner.', 'info');
                }
                setTimeout(function() { window.location.reload(); }, 2000);
            } else {
                showToast(response.message || 'Failed to confirm session', 'error');
            }
        },
        error: function(xhr, status, error) {
            showToast('Error: ' + error, 'error');
            console.error('AJAX Error:', xhr.responseText);
        }
    });
}

// Learner confirms session is complete (step 2 of mutual agreement)
function learnerConfirmBooking(bookingId) {
    if (confirm('Confirm that this session is complete? Points will be deducted from your balance once both parties confirm.')) {
        $.ajax({
            url: window.APP_URL + '/api/bookings.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'learner_confirm', id: bookingId },
            success: function(response) {
                if (response.success) {
                    if (response.completed) {
                        showToast('Session completed! Points have been transferred.', 'success');
                    } else {
                        showToast(response.message || 'Confirmation sent. Waiting for teacher.', 'info');
                    }
                    setTimeout(function() { window.location.reload(); }, 2000);
                } else {
                    showToast(response.message || 'Failed to confirm session', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Error: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    }
}

function cancelBooking(bookingId) {
    if (confirm('Are you sure you want to cancel this booking?')) {
        $.ajax({
            url: window.APP_URL + '/api/bookings.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'cancel', id: bookingId },
            success: function(response) {
                if (response.success) {
                    showToast('Booking cancelled!', 'success');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(response.message || 'Failed to cancel booking', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Error: ' + error, 'error');
                console.error('AJAX Error:', xhr.responseText);
            }
        });
    }
}

// Review functions
function submitReview(bookingId) {
    var rating = $('input[name="rating"]:checked').val();
    var comment = $('#reviewComment').val();
    
    if (!rating) {
        showToast('Please select a rating', 'warning');
        return;
    }
    
    $.post(window.APP_URL + '/api/reviews.php', { 
        action: 'create', 
        booking_id: bookingId, 
        rating: rating, 
        comment: comment 
    }, function(response) {
        if (response.success) {
            showToast('Review submitted!', 'success');
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showToast(response.message, 'error');
        }
    }, 'json');
}

// Skill functions
function deleteSkill(skillId) {
    if (confirm('Are you sure you want to delete this skill?')) {
        $.post(window.APP_URL + '/api/skills.php', { action: 'delete', id: skillId }, function(response) {
            if (response.success) {
                showToast('Skill deleted!', 'success');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                showToast(response.message, 'error');
            }
        }, 'json');
    }
}

function toggleSkillStatus(skillId) {
    $.post(window.APP_URL + '/api/skills.php', { action: 'toggle_status', id: skillId }, function(response) {
        if (response.success) {
            showToast('Skill status updated!', 'success');
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showToast(response.message, 'error');
        }
    }, 'json');
}

// User functions
function followUser(userId) {
    $.post(window.APP_URL + '/api/users.php', { action: 'follow', id: userId }, function(response) {
        if (response.success) {
            showToast('Following user!', 'success');
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        } else {
            showToast(response.message, 'error');
        }
    }, 'json');
}

// Image preview
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            $('#' + previewId).attr('src', e.target.result).show();
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Format points
function formatPoints(points) {
    if (points >= 1000) {
        return (points / 1000).toFixed(1) + 'k';
    }
    return points;
}

// Time ago helper
function timeAgo(date) {
    var seconds = Math.floor((new Date() - new Date(date)) / 1000);
    
    var interval = seconds / 31536000;
    if (interval > 1) return Math.floor(interval) + ' years ago';
    
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + ' months ago';
    
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + ' days ago';
    
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + ' hours ago';
    
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + ' minutes ago';
    
    return Math.floor(seconds) + ' seconds ago';
}

// Loading spinner
function showLoading() {
    $('#loading').show();
}

function hideLoading() {
    $('#loading').hide();
}

// Confirm dialog
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Debounce function
function debounce(func, wait) {
    var timeout;
    return function executedFunction() {
        var context = this;
        var args = arguments;
        var later = function() {
            timeout = null;
            func.apply(context, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search autocomplete
$('#searchInput').on('input', debounce(function() {
    var query = $(this).val();
    if (query.length >= 2) {
        $.get(window.APP_URL + '/api/search.php', { q: query }, function(response) {
            var suggestions = response.map(function(item) {
                return '<div class="suggestion-item" data-id="' + item.id + '">' + item.title + '</div>';
            });
            $('#searchSuggestions').html(suggestions.join('')).show();
        }, 'json');
    } else {
        $('#searchSuggestions').hide();
    }
}, 300));

// Click outside to close
$(document).on('click', function(e) {
    if (!$(e.target).closest('.search-box').length) {
        $('#searchSuggestions').hide();
    }
});

// Star rating click handler
$('.rating-input').on('click', function() {
    var rating = $(this).data('value');
    $('.rating-input').each(function() {
        if ($(this).data('value') <= rating) {
            $(this).removeClass('far').addClass('fas');
        } else {
            $(this).removeClass('fas').addClass('far');
        }
    });
});

// Initialize star ratings
$('.rating-display').each(function() {
    var rating = $(this).data('rating');
    var stars = '';
    for (var i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star"></i>';
        } else if (i - 0.5 <= rating) {
            stars += '<i class="fas fa-star-half-alt"></i>';
        } else {
            stars += '<i class="far fa-star"></i>';
        }
    }
    $(this).html(stars);
});