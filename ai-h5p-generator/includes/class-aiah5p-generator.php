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

        $title = isset($content_json['title']) && $content_json['title'] !== '' ? $content_json['title'] : __('AI-generated H5P', 'ai-h5p-generator');

        $resolved = AIAH5P_H5P_Builder::resolve_libraries($content_type);
        if (is_wp_error($resolved)) {
            self::redirect_with_notice($redirect, 'error', $resolved->get_error_message());
        }

        $content_id = AIAH5P_Content_Store::save(
            $title,
            $content_type,
            $prompt,
            $content_json,
            $resolved['main']['id'],
            wp_list_pluck($resolved['libraries'], 'id')
        );
        if (is_wp_error($content_id)) {
            self::redirect_with_notice($redirect, 'error', $content_id->get_error_message());
        }

        $dir = AIAH5P_H5P_Builder::write_content_dir($content_id, $title, $resolved['main'], $content_json);
        if (is_wp_error($dir)) {
            AIAH5P_Content_Store::delete($content_id);
            self::redirect_with_notice($redirect, 'error', $dir->get_error_message());
        }

        self::redirect_with_notice(
            add_query_arg('aiah5p_content_id', $content_id, $redirect),
            'success',
            sprintf(
                /* translators: 1: content id, 2: shortcode */
                __('H5P content created (id %1$d). Use the shortcode %2$s in any post or page.', 'ai-h5p-generator'),
                $content_id,
                '[aiah5p id="' . $content_id . '"]'
            )
        );
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
