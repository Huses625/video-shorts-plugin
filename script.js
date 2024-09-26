jQuery(document).ready(function($) {
    const players = [];
    let currentPlayingPlayer = null;

    $('.video-container iframe').each(function() {
        const player = new Vimeo.Player(this, { loop: true });
        players.push(player);
    });

    const options = {
        root: null,
        rootMargin: '0px',
        threshold: 0.5
    };

    function handleIntersect(entries, observer) {
        entries.forEach(entry => {
            const playerIndex = $('.video-container iframe').index(entry.target.querySelector('iframe'));
            const player = players[playerIndex];

            if (entry.isIntersecting) {
                if (currentPlayingPlayer && currentPlayingPlayer !== player) {
                    currentPlayingPlayer.pause().catch(function(error) {
                        console.error('Error pausing video:', error);
                    });
                }
                player.play().then(() => {
                    currentPlayingPlayer = player;
                }).catch(function(error) {
                    console.error('Error playing video:', error);
                });
            } else if (currentPlayingPlayer === player) {
                player.pause().catch(function(error) {
                    console.error('Error pausing video:', error);
                });
                currentPlayingPlayer = null;
            }
        });
    }

    const observer = new IntersectionObserver(handleIntersect, options);

    document.querySelectorAll('.video-container').forEach(container => {
        observer.observe(container);
    });

    // Like/Dislike functionality
    $('.like-button, .dislike-button').on('click', function() {
        const button = $(this);
        const videoId = button.data('video-id');
        const action = button.data('action');

        $.ajax({
            url: videoShorts.ajaxurl, 
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'handle_video_like_dislike',
                video_id: videoId,
                user_action: action,
                nonce: videoShorts.nonce 
            },
            success: function(response) {
                if (response.success) {
                    button.closest('.interaction-buttons').find('.like-count').text(response.data.likes_count);
                    button.closest('.interaction-buttons').find('.dislike-count').text(response.data.dislikes_count);
                } else {
                    console.error(response.data);
                }
            },
            error: function(xhr) {
                console.error('Request failed:', xhr.responseText);
            }
        });
    });

    // Comment Modal Toggle
    $('.comment-button').on('click', function() {
        const videoId = $(this).data('video-id');
        $('#commentModal-' + videoId).fadeIn();
    });

    $('.modal .close').on('click', function() {
        const videoId = $(this).data('video-id');
        $('#commentModal-' + videoId).fadeOut();
    });

    // Comment functionality
    $('.comment-form').off('submit').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const videoId = form.data('video-id');
        const comment = form.find('textarea[name="comment"]').val();

        if (!videoId || !comment) {
            console.error('Invalid video ID or comment.');
            return;
        }

        $.ajax({
            url: videoShorts.ajaxurl, 
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'handle_video_comment',
                video_id: videoId,
                comment: comment,
                nonce: videoShorts.nonce 
            },
            success: function(response) {
                if (response.success) {
                    // Clear the textarea
                    form.find('textarea').val('');

                    // Reload the comments list
                    reloadComments(videoId);
                } else {
                    console.error(response.data);
                }
            },
            error: function(xhr) {
                console.error('Request failed:', xhr.responseText);
            }
        });
    });

    $('.comment-form').off('submit').on('submit', function(e) {
        e.preventDefault();
    
        const form = $(this);
        const videoId = form.data('video-id');
        const comment = form.find('textarea[name="comment"]').val();
    
        if (!videoId || !comment) {
            console.error('Invalid video ID or comment.');
            return;
        }
    
        $.ajax({
            url: videoShorts.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'handle_video_comment',
                video_id: videoId,
                comment: comment,
                nonce: videoShorts.nonce
            },
            success: function(response) {
                if (response.success) {
                    form.find('textarea').val('');
                    loadComments(videoId); // Reload comments after submission
                } else {
                    console.error(response.data);
                }
            },
            error: function(xhr) {
                console.error('Request failed:', xhr.responseText);
            }
        });
    });
    
    function loadComments(videoId) {
        $.ajax({
            url: videoShorts.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'load_comments',
                video_id: videoId,
                nonce: videoShorts.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#comments-list-' + videoId).html(response.data.comments_html);
                } else {
                    console.error(response.data);
                }
            },
            error: function(xhr) {
                console.error('Request failed:', xhr.responseText);
            }
        });
    }

    
    
});
