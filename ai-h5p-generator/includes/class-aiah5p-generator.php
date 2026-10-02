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

        $file = AIAH5P_H5P_Builder::build_h5p_file($content_type, $content_json);
        if (is_wp_error($file)) {
            self::redirect_with_notice($redirect, 'error', $file->get_error_message());
        }

        $saved = self::save_to_media_library($file['path'], $content_json['title'] ?? 'ai-h5p-content');
        if (is_wp_error($saved)) {
            self::redirect_with_notice($redirect, 'error', $saved->get_error_message());
        }

        self::redirect_with_notice(
            add_query_arg('aiah5p_attachment_id', $saved, $redirect),
            'success',
            sprintf('H5P file generated and saved to the media library (attachment #%d). Import it into the H5P plugin or download it from the media library.', $saved)
        );
    }

    private static function save_to_media_library($path, $title)
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file_array = [
            'name' => sanitize_title($title) . '.h5p',
            'tmp_name' => $path,
        ];

        $attachment_id = media_handle_sideload($file_array, 0, $title);

        if (is_wp_error($attachment_id)) {
            @unlink($path);
            return $attachment_id;
        }

        wp_update_post([
            'ID' => $attachment_id,
            'post_content' => '',
        ]);

        return $attachment_id;
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
