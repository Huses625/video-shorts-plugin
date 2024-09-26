<?php
/*
Plugin Name: Video Shorts
Description: A plugin to display Vimeo video shorts with play/pause functionality, likes, dislikes, comments, and a custom dashboard to add video URLs, titles, and categories.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


// Enqueue styles and scripts
function video_shorts_enqueue_scripts() {
    wp_enqueue_style('video-shorts-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('video-shorts-script', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), null, true);

    // Load Vimeo SDK
    wp_enqueue_script('vimeo-player', 'https://player.vimeo.com/api/player.js', null, null, true);

    // Localize the script with new data
    wp_localize_script('video-shorts-script', 'videoShorts', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('video_shorts_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'video_shorts_enqueue_scripts');


// Include admin dashboard
require_once plugin_dir_path(__FILE__) . 'admin-dashboard.php';

// Include categories dashboard
require_once plugin_dir_path(__FILE__) . 'categories-dashboard.php';

// Include shorts display functionality
require_once plugin_dir_path(__FILE__) . 'shorts-display.php';

register_activation_hook(__FILE__, 'video_shorts_create_tables');


function video_shorts_create_tables() {
    global $wpdb;

    // Table names
    $table_name_video_shorts = $wpdb->prefix . 'video_shorts';
    $table_name_likes_dislikes = $wpdb->prefix . 'video_shorts_likes_dislikes';
    $table_name_comments = $wpdb->prefix . 'video_shorts_comments';

    // Charset collate
    $charset_collate = $wpdb->get_charset_collate();

    // SQL for creating video_shorts table
    $sql_video_shorts = "CREATE TABLE $table_name_video_shorts (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        video_id varchar(255) NOT NULL,
        url text NOT NULL,
        title text NOT NULL,
        category text NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY video_id (video_id)
    ) $charset_collate;";

    // SQL for creating likes/dislikes table
    $sql_likes_dislikes = "CREATE TABLE $table_name_likes_dislikes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        video_id varchar(255) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        action varchar(10) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_video_action (video_id, user_id, action)
    ) $charset_collate;";

    // SQL for creating comments table
    $sql_comments = "CREATE TABLE $table_name_comments (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        video_id varchar(255) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        comment text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Include the upgrade script and create the tables
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_video_shorts);
    dbDelta($sql_likes_dislikes);
    dbDelta($sql_comments);
}

add_action('wp_ajax_handle_video_like_dislike', 'handle_video_like_dislike');
add_action('wp_ajax_nopriv_handle_video_like_dislike', 'handle_video_like_dislike');

function handle_video_like_dislike() {
    global $wpdb;

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'video_shorts_nonce')) {
        wp_send_json_error('Invalid nonce.');
        return;
    }

    $video_id = sanitize_text_field($_POST['video_id']);
    $user_action = sanitize_text_field($_POST['user_action']);
    $user_id = get_current_user_id();

    if (!$video_id || !$user_action || !$user_id) {
        error_log('Invalid video ID, action, or user.');
        wp_send_json_error('Invalid video ID, action, or user.');
        return;
    }

    $table_name = $wpdb->prefix . 'video_shorts_likes_dislikes';

    // Check if the user has already liked or disliked the video
    $existing_action = $wpdb->get_var($wpdb->prepare(
        "SELECT action FROM $table_name WHERE video_id = %s AND user_id = %d",
        $video_id, $user_id
    ));

    if ($existing_action === $user_action) {
        // If the user is trying to perform the same action again, remove it (unlike/undislike)
        $wpdb->delete($table_name, [
            'video_id' => $video_id,
            'user_id' => $user_id
        ]);
    } else {
        if ($existing_action) {
            // If the action is different, update the action
            $wpdb->update($table_name, [
                'action' => $user_action
            ], [
                'video_id' => $video_id,
                'user_id' => $user_id
            ]);
        } else {
            // Insert a new like/dislike action
            $wpdb->insert($table_name, [
                'video_id' => $video_id,
                'user_id' => $user_id,
                'action' => $user_action
            ]);
        }
    }

    // Get updated like and dislike counts
    $likes_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE video_id = %s AND action = 'like'",
        $video_id
    ));

    $dislikes_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE video_id = %s AND action = 'dislike'",
        $video_id
    ));

    wp_send_json_success([
        'likes_count' => $likes_count,
        'dislikes_count' => $dislikes_count
    ]);
}

// Handle comment submission
function handle_video_comment() {
    global $wpdb;

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'video_shorts_nonce')) {
        wp_send_json_error('Invalid nonce.');
        return;
    }

    $user_id = get_current_user_id();
    $video_id = sanitize_text_field($_POST['video_id']);
    $comment_text = sanitize_textarea_field($_POST['comment']);

    if ($user_id && $video_id && !empty($comment_text)) {
        $comments_table = $wpdb->prefix . 'video_shorts_comments';

        // Check if the comment is already inserted (avoid duplication)
        $existing_comment = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $comments_table WHERE video_id = %s AND user_id = %d AND comment = %s AND created_at = %s",
            $video_id, $user_id, $comment_text, current_time('mysql')
        ));

        if ($existing_comment == 0) {
            // Insert the new comment
            $insert_result = $wpdb->insert($comments_table, [
                'video_id' => $video_id,
                'user_id' => $user_id,
                'comment' => $comment_text,
                'created_at' => current_time('mysql')
            ]);

            if ($insert_result === false) {
                error_log('Insert failed: ' . $wpdb->last_error);
                wp_send_json_error('Failed to add comment.');
            }

            wp_send_json_success([
                'comment' => $comment_text,
                'created_at' => current_time('mysql')
            ]);
        } else {
            wp_send_json_error('Duplicate comment detected.');
        }
    } else {
        error_log('Invalid parameters for comment.');
        wp_send_json_error('Invalid parameters');
    }

    wp_die();
}
add_action('wp_ajax_handle_video_comment', 'handle_video_comment'); 


function load_comments() {
    global $wpdb;

    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'video_shorts_nonce')) {
        wp_send_json_error('Invalid nonce.');
        return;
    }

    $video_id = sanitize_text_field($_POST['video_id']);
    $comments_table = $wpdb->prefix . 'video_shorts_comments';

    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, comment, created_at FROM $comments_table WHERE video_id = %d ORDER BY created_at DESC",
        $video_id
    ));

    if ($comments) {
        ob_start();
        foreach ($comments as $comment) {
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
            <?php
        }
        $comments_html = ob_get_clean();
        wp_send_json_success(['comments_html' => $comments_html]);
    } else {
        wp_send_json_error('No comments found.');
    }

    wp_die();
}
add_action('wp_ajax_load_comments', 'load_comments');
add_action('wp_ajax_nopriv_load_comments', 'load_comments');

// Register the gallery shortcode
function video_shorts_gallery($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'video_shorts';

    // Extract shortcode attributes
    $atts = shortcode_atts(
        array(
            'category' => '', // Default to an empty string if no category is provided
        ),
        $atts,
        'video_shorts_gallery'
    );

    // Fetch videos from the database based on the category
    if (!empty($atts['category'])) {
        $videos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE category = %s", $atts['category']));
    } else {
        $videos = $wpdb->get_results("SELECT * FROM $table_name");
    }

    if (empty($videos)) {
        return 'No videos available.';
    }

    ob_start();
    ?>
    <div class="video-gallery-container">
        <?php foreach ($videos as $video): ?>
            <div class="video-gallery-item">
                <a href="<?php echo esc_url(site_url('/short-videos?video_id=' . $video->video_id)); ?>">
                    <img src="https://vumbnail.com/<?php echo $video->video_id; ?>.jpg" alt="<?php echo esc_html($video->title); ?>" />
                    <p><?php echo esc_html($video->title); ?></p>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    
    <style>
        .video-gallery-container {
            display: flex;
            overflow-x: auto;
            padding: 10px;
            white-space: nowrap; /* Ensures items stay in one line */
        }

        .video-gallery-item {
            flex: 0 0 auto; /* Prevents items from shrinking or growing */
            margin-right: 15px;
            text-align: center;
            width: 200px; /* Set the width of each gallery item */
        }

        .video-gallery-item img {
            width: 100%;
            height: auto;
            display: block;
            margin-bottom: 5px;
        }

        .video-gallery-item p {
            margin: 20px;
            text-align: center;
            font-size: 14px;
        }

        /* Optional: Hide scrollbar in webkit browsers */
        .video-gallery-container::-webkit-scrollbar {
            display: none;
        }
    </style>

    <?php
    return ob_get_clean();
}
add_shortcode('video_shorts_gallery', 'video_shorts_gallery');