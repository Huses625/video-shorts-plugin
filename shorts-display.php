<?php 

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function video_player_display() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'video_shorts';

    // Get the video_id from the URL
    $video_id_from_url = isset($_GET['video_id']) ? intval($_GET['video_id']) : null;

    // Fetch the specific video by video_id
    $first_video = null;
    if ($video_id_from_url) {
        $first_video = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE video_id = %d", $video_id_from_url), ARRAY_A);
    }

    // Fetch all other videos except the one matched by video_id
    if ($first_video) {
        $video_shorts = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE video_id != %d", $video_id_from_url), ARRAY_A);
        array_unshift($video_shorts, $first_video); // Add the first video to the beginning of the array
    } else {
        $video_shorts = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    }

    if (empty($video_shorts)) {
        return 'No video shorts available.';
    }

    ob_start();
    ?>

    <div class="video-player-container">
        <div class="video-shorts-container">
            <?php foreach ($video_shorts as $video): 
                $video_id = intval($video['video_id']); // Ensure correct video ID
                $user_id = get_current_user_id();

                // Get likes and dislikes count
                $likes_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}video_shorts_likes_dislikes WHERE video_id = %d AND action = 'like'",
                    $video_id
                ));

                $dislikes_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}video_shorts_likes_dislikes WHERE video_id = %d AND action = 'dislike'",
                    $video_id
                ));

                // Get all comments for the video
                $comments = $wpdb->get_results($wpdb->prepare(
                    "SELECT comment, created_at, user_id FROM {$wpdb->prefix}video_shorts_comments WHERE video_id = %d ORDER BY created_at DESC",
                    $video_id
                ));

                // Get comment count
                $comment_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}video_shorts_comments WHERE video_id = %d",
                    $video_id
                ));
            ?>
                <div class="video-container">
                    <div class="video-overlay">
                        <div class="video-details">
                            <p><?php echo esc_html($video['title']); ?></p>
                            <span><?php echo esc_html($video['category']); ?></span>
                        </div>
                    </div>
                    
                    <iframe src="<?php echo esc_url($video['url']); ?>?loop=1" frameborder="0" allow="autoplay; fullscreen" allowfullscreen></iframe>

                    <?php if (is_user_logged_in()) : ?>
                        <div class="interaction-buttons">
                            <button class="like-button" data-video-id="<?php echo $video_id; ?>" data-action="like">
                                <span class="like-icon">&#128077;</span>
                                <span class="like-count"><?php echo esc_html($likes_count); ?></span>
                            </button>
                            <button class="dislike-button" data-video-id="<?php echo $video_id; ?>" data-action="dislike">
                                <span class="dislike-icon">&#128078;</span>
                                <span class="dislike-count"><?php echo esc_html($dislikes_count); ?></span>
                            </button>
                            <button class="comment-button" id="open-comment" data-video-id="<?php echo $video_id; ?>">
                                <span class="comment-icon">&#128172;</span>
                                <span class="comment-count"><?php echo esc_html($comment_count); ?></span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Comment Modal -->
                    <div id="commentModal-<?php echo $video_id; ?>" class="modal">
                        <div class="modal-content">
                            <span class="close" data-video-id="<?php echo $video_id; ?>">&times;</span>
                            <h4 class="modal-title"><?php echo esc_html($video['title']); ?></h4>
                            <div class="comments-list" id="comments-list-<?php echo $video_id; ?>">
                                <?php if (empty($comments)) : ?>
                                    <p>No comments right now.</p>
                                <?php else : ?>
                                    <?php foreach ($comments as $comment) : 
                                        $commenter = get_userdata($comment->user_id);
                                        $avatar = get_avatar_url($comment->user_id); 
                                        ?>
                                        <div class="comment-item" style="margin-bottom: 15px;">
                                            <div class="comment-header" style="display: flex; align-items: center; justify-content: space-between;">
                                                <div class="comment-avatar-name" style="display: flex; align-items: center;">
                                                    <img style="border-radius: 45px; width: 50px; height: 50px; margin-right: 10px;" src="<?php echo esc_url($avatar); ?>" alt="<?php echo esc_attr($commenter->display_name); ?>">
                                                    <strong><?php echo esc_html($commenter->display_name); ?></strong>
                                                </div>
                                                <div class="comment-date" style="font-size: 0.9em; color: #777;">
                                                    <small><?php echo date('F j, Y g:i A', strtotime($comment->created_at)); ?></small>
                                                </div>
                                            </div>
                                            <div class="comment-body" style="margin-top: 10px;">
                                                <p><?php echo esc_html($comment->comment); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (is_user_logged_in()) : ?>
                                <form class="comment-form" data-video-id="<?php echo $video_id; ?>">
                                    <div class="comment-input-container">
                                        <textarea style="height:38px;" class="comment" name="comment" placeholder="Add a comment..." required></textarea>
                                        <button type="submit" class="btn btn-primary">Comment</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        const players = [];
        let currentPlayingPlayer = null;
        let currentVideoId = null;

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
                const videoId = $('.video-container').eq(playerIndex).find('.like-button').data('video-id');

                if (entry.isIntersecting) {
                    if (currentPlayingPlayer && currentPlayingPlayer !== player) {
                        currentPlayingPlayer.pause().catch(function(error) {
                            console.error('Error pausing video:', error);
                        });
                    }
                    player.play().then(() => {
                        currentPlayingPlayer = player;
                        currentVideoId = videoId;
                        updateUrlWithVideoId(videoId);
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

        function updateUrlWithVideoId(videoId) {
            if (history.pushState) {
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?video_id=' + videoId;
                window.history.pushState({path:newUrl}, '', newUrl);
            }
        }

        // Scroll to first video based on URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const videoIdFromUrl = urlParams.get('video_id');
        if (videoIdFromUrl) {
            const targetContainer = $('.like-button[data-video-id="' + videoIdFromUrl + '"]').closest('.video-container');
            if (targetContainer.length) {
                $('html, body').animate({
                    scrollTop: targetContainer.offset().top - ($(window).height() / 2) + (targetContainer.height() / 2)
                }, 500, function() {
                    const playerIndex = targetContainer.index();
                    const targetPlayer = players[playerIndex];
                    if (targetPlayer) {
                        targetPlayer.play().then(() => {
                            currentPlayingPlayer = targetPlayer;
                            updateUrlWithVideoId(videoIdFromUrl);
                        }).catch(function(error) {
                            console.error('Error playing video:', error);
                        });
                    }
                });
            }
        }

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

    // Close modal when clicking outside of it
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('modal')) {
            $(event.target).fadeOut();
        }
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
                    // Reload the comments list
                    $('#comments-list-' + videoId).load(window.location.href + ' #comments-list-' + videoId + ' > *');
                    form.find('textarea').val(''); 
                } else {
                    console.error(response.data);
                }
            },
            error:function(xhr) {
                console.error('Request failed:', xhr.responseText);
            }
        });
    });
});
</script>

<?php
return ob_get_clean();

}

add_shortcode('video_player', 'video_player_display');