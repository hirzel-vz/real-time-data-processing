<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Generator
{
    public static function init()
    {
        add_action('admin_post_aiah5p_generate', [__CLASS__, 'handle_generate']);
    }

    public static function handle_generate()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }
        check_admin_referer('aiah5p_generate');

        $redirect = admin_url('admin.php?page=aiah5p-generator');

        $prompt = isset($_POST['aiah5p_prompt']) ? sanitize_textarea_field(wp_unslash($_POST['aiah5p_prompt'])) : '';
        $content_type = isset($_POST['aiah5p_content_type']) ? sanitize_text_field(wp_unslash($_POST['aiah5p_content_type'])) : '';

        if ($prompt === '' || $content_type === '') {
            self::redirect_with_notice($redirect, 'error', 'Please provide a topic and select a content type.');
        }

        $data = AIAH5P_Mistral_Client::generate_content($prompt, $content_type);
        if (is_wp_error($data)) {
            self::redirect_with_notice($redirect, 'error', $data->get_error_message());
        }

        $content_json = AIAH5P_H5P_Builder::build_content_json($content_type, $data);
        if (is_wp_error($content_json)) {
            self::redirect_with_notice($redirect, 'error', $content_json->get_error_message());
        }

        $id = uniqid('aiah5p_');
        $title = isset($content_json['title']) && $content_json['title'] !== '' ? $content_json['title'] : __('AI-generated H5P', 'ai-h5p-generator');

        $dir = AIAH5P_H5P_Builder::build_content_dir($id, $content_type, $content_json);
        if (is_wp_error($dir)) {
            self::redirect_with_notice($redirect, 'error', $dir->get_error_message());
        }

        $post_id = AIAH5P_Content_Store::save($title, $content_type, $prompt, $dir);
        if (is_wp_error($post_id)) {
            self::redirect_with_notice($redirect, 'error', $post_id->get_error_message());
        }

        $rename_from = trailingslashit(AIAH5P_Content_Store::base_dir()) . $id;
        $rename_to = trailingslashit(AIAH5P_Content_Store::base_dir()) . $post_id;
        if (!@rename($rename_from, $rename_to)) {
            self::copy_dir_simple($rename_from, $rename_to);
        }

        self::redirect_with_notice(
            add_query_arg('aiah5p_content_id', $post_id, $redirect),
            'success',
            sprintf(
                /* translators: 1: content id, 2: shortcode */
                __('H5P content created (id %1$d). Use the shortcode %2$s in any post or page.', 'ai-h5p-generator'),
                $post_id,
                '[aiah5p id="' . $post_id . '"]'
            )
        );
    }

    private static function copy_dir_simple($src, $dst)
    {
        if (!is_dir($src)) {
            return;
        }
        @mkdir($dst, 0755, true);
        $entries = new FilesystemIterator($src);
        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                self::copy_dir_simple($entry->getPathname(), $dst . '/' . $entry->getFilename());
            } else {
                @copy($entry->getPathname(), $dst . '/' . $entry->getFilename());
            }
        }
    }

    private static function redirect_with_notice($url, $type, $message)
    {
        $url = add_query_arg([
            'aiah5p_notice' => $type,
            'aiah5p_message' => rawurlencode($message),
        ], $url);
        wp_safe_redirect($url);
        exit;
    }
}
