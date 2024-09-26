<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function video_shorts_categories_menu() {
    add_submenu_page('video-shorts', 'Categories', 'Categories', 'manage_options', 'video-shorts-categories', 'video_shorts_categories_page');
}
add_action('admin_menu', 'video_shorts_categories_menu');

function video_shorts_categories_page() {
    $categories = get_option('video_shorts_categories', []);

    // Handle Delete
    if (isset($_GET['delete'])) {
        $delete_index = intval($_GET['delete']);
        if (isset($categories[$delete_index])) {
            unset($categories[$delete_index]);
            $categories = array_values($categories); // Reindex array
            update_option('video_shorts_categories', $categories);
        }
    }

    // Handle Add
    if (isset($_POST['new_category_name'])) {
        $new_category = sanitize_text_field($_POST['new_category_name']);
        if (!in_array($new_category, $categories)) {
            $categories[] = $new_category;
            update_option('video_shorts_categories', $categories);
        }
    }

    ?>

    <div class="wrap">
        <h1>Manage Categories</h1>

        <form method="post">
            <h2>Add New Category</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Category Name</th>
                    <td><input type="text" name="new_category_name" class="regular-text" required></td>
                </tr>
            </table>
            <?php submit_button('Add Category'); ?>
        </form>

        <h2>Category List</h2>
        <table class="widefat fixed">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)) : ?>
                    <?php foreach ($categories as $index => $category) : ?>
                        <tr>
                            <td><?php echo esc_html($category); ?></td>
                            <td>
                                <!-- Delete Button -->
                                <a href="?page=video-shorts-categories&delete=<?php echo $index; ?>" class="button delete-button" onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="2">No categories added yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
}
?>