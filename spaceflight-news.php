<?php
/**
 * Plugin Name: Spaceflight News
 * Description: Fetches space news from the Spaceflight News API.
 * Version: 1.0.0
 * Author: Christian Ada
 * Text Domain: spaceflight-news
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register Custom Post Type for News Articles
 */
function sfn_register_post_type() {
    $labels = array(
        'name'               => __('Space News', 'spaceflight-news'),
        'singular_name'      => __('News Article', 'spaceflight-news'),
        'add_new'            => __('Add New', 'spaceflight-news'),
        'add_new_item'       => __('Add New Article', 'spaceflight-news'),
        'edit_item'          => __('Edit Article', 'spaceflight-news'),
        'view_item'          => __('View Article', 'spaceflight-news'),
        'all_items'          => __('All Articles', 'spaceflight-news'),
    );
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'menu_icon'          => 'dashicons-rss',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'show_in_rest'       => true,
    );
    register_post_type('sfn_news', $args);
}
add_action('init', 'sfn_register_post_type');

/**
 * Add Admin Menu
 */
function sfn_add_admin_menu() {
    add_menu_page(
        __('Spaceflight News Settings', 'spaceflight-news'),
        __('Spaceflight News', 'spaceflight-news'),
        'manage_options',
        'spaceflight-news-settings',
        'sfn_settings_page',
        'dashicons-admin-generic',
        30
    );
}
add_action('admin_menu', 'sfn_add_admin_menu');

/**
 * Display Settings Page
 */
function sfn_settings_page() {
    // Check user permissions
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle manual fetch
    if (isset($_POST['sfn_fetch_now'])) {
        check_admin_referer('sfn_fetch_now');
        $result = sfn_fetch_news();

        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }

    // Save settings if form submitted
    if (isset($_POST['sfn_save_settings'])) {
        check_admin_referer('sfn_settings');

        $new_frequency = sanitize_text_field($_POST['update_frequency']); // Get the new frequency

        $settings = array(
            'search_phrase'     => sanitize_text_field($_POST['search_phrase']),
            'date_cutoff'       => sanitize_text_field($_POST['date_cutoff']),
            'update_frequency'  => $new_frequency,
        );
        update_option('sfn_settings', $settings);
        /**
         * FIX: Wire the setting to the Cron
         */
        // 1. Clear the existing schedule
        wp_clear_scheduled_hook('sfn_fetch_news_cron');
        // 2. Reschedule with the new frequency
        wp_schedule_event(time(), $new_frequency, 'sfn_fetch_news_cron');
        echo '<div class="notice notice-success"><p>' . __('Settings saved and Cron schedule updated!', 'spaceflight-news') . '</p></div>';
    }

    // Get current settings, set defaults if needed
    $defaults = array(
        'search_phrase' => '',
        'date_cutoff' => date('Y-m-d', strtotime('-30 days')),
        'update_frequency' => 'hourly',
    );
    $settings = wp_parse_args(get_option('sfn_settings', array()), $defaults);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Spaceflight News Settings', 'spaceflight-news'); ?></h1>
        <div style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
            <h2><?php esc_html_e('Fetch News Now', 'spaceflight-news'); ?></h2>
            <p><?php esc_html_e('Click the button below to manually fetch the latest articles.', 'spaceflight-news'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('sfn_fetch_now'); ?>
                <button type="submit" name="sfn_fetch_now" class="button button-primary button-large">
                    <?php esc_html_e('Fetch News Now', 'spaceflight-news'); ?>
                </button>
            </form>
        </div>
        <?php
        // Get statistics
        $total_articles = wp_count_posts('sfn_news')->publish;

        $latest_article = get_posts(array(
            'post_type' => 'sfn_news',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        $latest_date = !empty($latest_article) ? human_time_diff(strtotime($latest_article[0]->post_date), current_time('timestamp')) . ' ago' : 'Never';

        $next_cron = wp_next_scheduled('sfn_fetch_news_cron');
        $next_update = $next_cron ? human_time_diff($next_cron, current_time('timestamp')) . ' from now' : 'Not scheduled';
        ?>
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">
            <h2>Statistics</h2>
            <table class="widefat">
                <tr>
                    <td><strong>Total Articles:</strong></td>
                    <td><?php echo esc_html($total_articles); ?></td>
                </tr>
                <tr>
                    <td><strong>Latest Article:</strong></td>
                    <td><?php echo esc_html($latest_date); ?></td>
                </tr>
                <tr>
                    <td><strong>Next Auto-Update:</strong></td>
                    <td><?php echo esc_html($next_update); ?></td>
                </tr>
            </table>
        </div>
        <form method="post" action="">
            <?php wp_nonce_field('sfn_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="search_phrase"><?php esc_html_e('Search Phrase', 'spaceflight-news'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="search_phrase"
                               name="search_phrase"
                               value="<?php echo esc_attr($settings['search_phrase']); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e('Filter articles by keyword (e.g., SpaceX, NASA). Leave blank for all.', 'spaceflight-news'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="date_cutoff"><?php esc_html_e('Date Cutoff', 'spaceflight-news'); ?></label>
                    </th>
                    <td>
                        <input type="date"
                               id="date_cutoff"
                               name="date_cutoff"
                               value="<?php echo esc_attr($settings['date_cutoff']); ?>">
                        <p class="description"><?php esc_html_e('Only fetch articles published after this date.', 'spaceflight-news'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="update_frequency"><?php esc_html_e('Update Frequency', 'spaceflight-news'); ?></label>
                    </th>
                    <td>
                        <select id="update_frequency" name="update_frequency">
                            <option value="hourly"      <?php selected($settings['update_frequency'], 'hourly'); ?>><?php esc_html_e('Hourly', 'spaceflight-news'); ?></option>
                            <option value="twicedaily"  <?php selected($settings['update_frequency'], 'twicedaily'); ?>><?php esc_html_e('Twice Daily', 'spaceflight-news'); ?></option>
                            <option value="daily"       <?php selected($settings['update_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'spaceflight-news'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('How often to automatically fetch new articles.', 'spaceflight-news'); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="sfn_save_settings" class="button button-primary"><?php esc_html_e('Save Settings', 'spaceflight-news'); ?></button>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Fetch News from API
 * (Now includes caching to avoid hitting API rate limits and handles article images)
 */
function sfn_fetch_news() {
    $settings = get_option('sfn_settings', array());
    $api_url = 'https://api.spaceflightnewsapi.net/v4/articles/';
    $params = array(
        'limit'     => 20,
        'ordering'  => '-published_at',
    );
    if (!empty($settings['search_phrase'])) {
        $params['search'] = $settings['search_phrase'];
    }
    if (!empty($settings['date_cutoff'])) {
        $params['published_at_gte'] = $settings['date_cutoff'] . 'T00:00:00Z';
    }
    $url = add_query_arg($params, $api_url);
    $cache_key = 'sfn_api_' . md5($url);
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        $articles = $cached_data;
        if (is_admin() && !defined('DOING_CRON')) {
            echo '<div class="notice notice-info"><p>' . esc_html__('Using cached data (updates every hour)', 'spaceflight-news') . '</p></div>';
        }
    } else {
        $response = wp_remote_get($url, array('timeout' => 30));
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'API request failed: ' . $response->get_error_message(),
            );
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return array(
                'success' => false,
                'message' => 'API returned error code: ' . $code,
            );
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'message' => 'Failed to parse API response',
            );
        }
        $articles = isset($data['results']) && is_array($data['results']) ? $data['results'] : array();
        set_transient($cache_key, $articles, HOUR_IN_SECONDS);
    }

    if (empty($articles)) {
        return array(
            'success' => true,
            'count' => 0,
            'message' => __('No articles found', 'spaceflight-news'),
        );
    }

    $count = 0;
    foreach ($articles as $article) {
        $existing_id = sfn_article_exists($article['id']);
        $post_data = array(
            'post_title'   => sanitize_text_field($article['title']),
            'post_content' => wp_kses_post($article['summary']),
        );

        if ($existing_id) {
            $post_data['ID'] = $existing_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_data['post_status'] = 'publish';
            $post_data['post_type']   = 'sfn_news';
            $post_data['post_date']   = get_date_from_gmt(date('Y-m-d H:i:s', strtotime($article['published_at'])));
            $post_id = wp_insert_post($post_data);
        }

        if (!is_wp_error($post_id) && $post_id > 0) {
            if (isset($article['id'])) {
                update_post_meta($post_id, '_sfn_api_id', $article['id']);
            }
            if (isset($article['news_site'])) {
                update_post_meta($post_id, '_sfn_news_site', sanitize_text_field($article['news_site']));
            }
            if (isset($article['url'])) {
                update_post_meta($post_id, '_sfn_url', esc_url_raw($article['url']));
            }
            if (isset($article['image_url']) && !empty($article['image_url'])) {
                update_post_meta($post_id, '_sfn_image_url', esc_url_raw($article['image_url']));
                // Avoid re-downloading images: Only download/set if this image hasn't already been attached elsewhere
                sfn_attach_news_image($post_id, $article['image_url']);
            }
            $count++;
        }
    }

    return array(
        'success' => true,
        'count'   => $count,
        'message' => sprintf(
            /* translators: %d: Number of articles imported */
            __('Successfully fetched %d articles', 'spaceflight-news'),
            $count
        ),
    );
}

/**
 * Download the article image and attach it as the post thumbnail, if not already attached or already present in library for that URL.
 */
function sfn_attach_news_image($post_id, $image_url) {
    // Avoid re-downloading if attachment exists for this image_url
    $existing = get_posts(array(
        'post_type'      => 'attachment',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'   => '_sfn_source_image_url',
                'value' => $image_url,
            ),
        ),
        'fields' => 'ids',
    ));

    if (!empty($existing)) {
        // If the current post doesn't already have this set as featured image, set it
        $existing_thumbnail_id = get_post_thumbnail_id($post_id);
        if ($existing_thumbnail_id != $existing[0]) {
            set_post_thumbnail($post_id, $existing[0]);
        }
        return;
    }

    // Check if post already has a featured image with this URL
    $existing_thumbnail_id = get_post_thumbnail_id($post_id);
    if ($existing_thumbnail_id) {
        $attached_url = wp_get_attachment_url($existing_thumbnail_id);
        if ($attached_url === $image_url) {
            return;
        }
    }

    // Download image and attach it
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // Generate a unique filename
    $file_array = array();
    $file_array['name'] = basename(parse_url($image_url, PHP_URL_PATH));
    $file_array['tmp_name'] = download_url($image_url);

    if (is_wp_error($file_array['tmp_name'])) {
        return; // Download failed
    }

    // Upload to media library
    $attachment_id = media_handle_sideload($file_array, $post_id);

    // Remove the temporary file
    @unlink($file_array['tmp_name']);

    if (is_wp_error($attachment_id)) {
        return;
    }

    // Mark this attachment as being for this image_url
    update_post_meta($attachment_id, '_sfn_source_image_url', $image_url);

    // Set as featured image
    set_post_thumbnail($post_id, $attachment_id);
}

/**
 * Check if article already exists
 */
function sfn_article_exists($api_id) {
    $args = array(
        'post_type'      => 'sfn_news',
        'meta_query'     => array(
            array(
                'key'     => '_sfn_api_id',
                'value'   => $api_id,
                'compare' => '=',
            ),
        ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
    );
    $posts = get_posts($args);
    return !empty($posts) ? $posts[0] : false;
}

/**
 * Activate Plugin
 */
function sfn_activate() {
    sfn_register_post_type();
    flush_rewrite_rules();
    if (!wp_next_scheduled('sfn_fetch_news_cron')) {
        wp_schedule_event(time(), 'hourly', 'sfn_fetch_news_cron');
    }
}
register_activation_hook(__FILE__, 'sfn_activate');

/**
 * Deactivate Plugin
 */
function sfn_deactivate() {
    $timestamp = wp_next_scheduled('sfn_fetch_news_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'sfn_fetch_news_cron');
    }
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'sfn_deactivate');

/**
 * Cron callback - run the fetch
 */
function sfn_cron_fetch() {
    sfn_fetch_news();
}
add_action('sfn_fetch_news_cron', 'sfn_cron_fetch');
