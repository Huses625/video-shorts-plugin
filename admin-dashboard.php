<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function video_shorts_menu() {
    add_menu_page('Video Shorts', 'Video Shorts', 'manage_options', 'video-shorts', 'video_shorts_page', 'dashicons-video-alt3');
}
add_action('admin_menu', 'video_shorts_menu');

function video_shorts_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'video_shorts';
    $categories = get_option('video_shorts_categories', []);

    // Handle Delete
    if (isset($_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        $wpdb->delete($table_name, ['id' => $delete_id]);
    }

    // Handle Edit
    if (isset($_POST['edit_video_index'])) {
        $edit_id = intval($_POST['edit_video_index']);
        $wpdb->update($table_name, [
            'title' => sanitize_text_field($_POST['edit_video_title']),
            'category' => sanitize_text_field($_POST['edit_video_category']),
            'url' => sanitize_text_field($_POST['edit_video_url']),
        ], ['id' => $edit_id]);
    }

    // Handle Add
    if (isset($_POST['new_video_url'])) {
        $vimeo_url = sanitize_text_field($_POST['new_video_url']);

        // Extract the last part of the Vimeo URL (assuming it's the video ID)
        $url_parts = explode('/', rtrim($vimeo_url, '/'));
        $new_video_id = end($url_parts); // Get the last part of the URL

        if ($new_video_id) {
            $wpdb->insert($table_name, [
                'video_id' => $new_video_id,
                'url' => $vimeo_url,
                'title' => sanitize_text_field($_POST['new_video_title']),
                'category' => sanitize_text_field($_POST['new_video_category']),
            ]);
        } else {
            // Handle error if video ID extraction fails
            error_log('Failed to extract Vimeo video ID from URL: ' . $vimeo_url);
        }
    }

    // Fetch the video shorts from the database
    $video_shorts = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    ?>

    <div class="wrap">
        <h1>Video Shorts</h1>

        <form method="post">
            <h2>Add New Video</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Title</th>
                    <td><input type="text" name="new_video_title" class="regular-text" required></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Category</th>
                    <td>
                        <select name="new_video_category" required>
                            <option value="">Select a Category</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Vimeo Video URL</th>
                    <td><input type="url" name="new_video_url" class="regular-text" required></td>
                </tr>
            </table>
            <?php submit_button('Add Video'); ?>
        </form>

        <h2>Video List</h2>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Video URL</th>
                    <th>Likes</th>
                    <th>Dislikes</th>
                    <th>Comments</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($video_shorts)) : ?>
                    <?php foreach ($video_shorts as $video) :
                        // Get likes, dislikes, and comments count for each video
                        $likes_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}video_shorts_likes_dislikes WHERE video_id = %s AND action = 'like'",
                            $video['video_id']
                        ));
                        $dislikes_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}video_shorts_likes_dislikes WHERE video_id = %s AND action = 'dislike'",
                            $video['video_id']
                        ));
                        $comments_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}video_shorts_comments WHERE video_id = %s",
                            $video['video_id']
                        ));
                    ?>
                        <tr>
                            <td><?php echo esc_html($video['id']); ?></td> <!-- Display Video ID -->
                            <td><?php echo esc_html($video['title']); ?></td>
                            <td><?php echo esc_html($video['category']); ?></td>
                            <td><?php echo esc_url($video['url']); ?></td>
                            <td><?php echo esc_html($likes_count); ?></td>
                            <td><?php echo esc_html($dislikes_count); ?></td>
                            <td><?php echo esc_html($comments_count); ?></td>
                            <td>
                                <!-- Edit Button -->
                                <button class="button edit-button" data-index="<?php echo $video['id']; ?>" data-title="<?php echo esc_attr($video['title']); ?>" data-category="<?php echo esc_attr($video['category']); ?>" data-url="<?php echo esc_attr($video['url']); ?>">Edit</button>
                                
                                <!-- Delete Button -->
                                <a href="?page=video-shorts&delete=<?php echo $video['id']; ?>" class="button delete-button" onclick="return confirm('Are you sure you want to delete this video?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8">No videos added yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Edit Form (hidden by default) -->
        <div id="edit-video-form" style="display:none;">
            <h2>Edit Video</h2>
            <form method="post">
                <input type="hidden" name="edit_video_index" id="edit_video_index">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Title</th>
                        <td><input type="text" name="edit_video_title" id="edit_video_title" class="regular-text" required></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Category</th>
                        <td>
                            <select name="edit_video_category" id="edit_video_category" required>
                                <option value="">Select a Category</option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Vimeo Video URL</th>
                        <td><input type="url" name="edit_video_url" id="edit_video_url" class="regular-text" required></td>
                    </tr>
                </table>
                <?php submit_button('Update Video'); ?>
            </form>
        </div>
    </div>

    <script>
        // Handle the Edit button click event to populate the edit form
        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                const title = this.getAttribute('data-title');
                const category = this.getAttribute('data-category');
                const url = this.getAttribute('data-url');

                document.getElementById('edit_video_index').value = index;
                document.getElementById('edit_video_title').value = title;
                document.getElementById('edit_video_category').value = category;
                document.getElementById('edit_video_url').value = url;

                document.getElementById('edit-video-form').style.display = 'block';
                window.scrollTo(0, document.getElementById('edit-video-form').offsetTop);
            });
        });
    </script>

    <?php
}
?>