<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Content_Store
{
    const POST_TYPE = 'aiah5p_content';

    public static function init()
    {
        add_action('init', [__CLASS__, 'register_post_type']);
    }

    public static function register_post_type()
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('H5P Content', 'ai-h5p-generator'),
                'singular_name' => __('H5P Content', 'ai-h5p-generator'),
            ],
            'public' => false,
            'show_ui' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function base_dir()
    {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'aiah5p';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
        }
        return $dir;
    }

    public static function base_url()
    {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['baseurl']) . 'aiah5p';
    }

    public static function content_dir($id)
    {
        $dir = trailingslashit(self::base_dir()) . $id;
        if (!is_dir($dir)) {
            return new WP_Error('aiah5p_not_found', __('The H5P content directory does not exist.', 'ai-h5p-generator'));
        }
        return $dir;
    }

    public static function content_url($id)
    {
        return trailingslashit(self::base_url()) . $id;
    }

    public static function save($title, $content_type, $prompt, $dir)
    {
        $post_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $prompt,
        ]);

        if (is_wp_error($post_id) || $post_id === 0) {
            return new WP_Error('aiah5p_save_failed', __('Could not save the content record.', 'ai-h5p-generator'));
        }

        update_post_meta($post_id, 'aiah5p_content_type', $content_type);
        update_post_meta($post_id, 'aiah5p_shortcode_id', 'aiah5p_' . $post_id);

        return $post_id;
    }

    public static function get($id)
    {
        $post = get_post((int) $id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return new WP_Error('aiah5p_not_found', __('H5P content not found.', 'ai-h5p-generator'));
        }
        return $post;
    }

    public static function delete($id)
    {
        $post = self::get($id);
        if (is_wp_error($post)) {
            return $post;
        }

        $dir = trailingslashit(self::base_dir()) . $id;
        if (is_dir($dir)) {
            self::rrmdir($dir);
        }
        $zip = $dir . '.h5p';
        if (file_exists($zip)) {
            @unlink($zip);
        }

        wp_delete_post($post->ID, true);
        return true;
    }

    private static function rrmdir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = new FilesystemIterator($dir);
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                self::rrmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }
        @rmdir($dir);
    }
}
