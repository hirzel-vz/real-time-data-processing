<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Library_Manager
{
    public static function init()
    {
        add_action('admin_post_aiah5p_upload_library', [__CLASS__, 'handle_upload']);
        add_action('admin_post_aiah5p_delete_library', [__CLASS__, 'handle_delete']);
    }

    public static function handle_upload()
    {
        self::guard();

        if (empty($_FILES['aiah5p_library'])) {
            self::redirect('error', __('No file uploaded.', 'ai-h5p-generator'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file = wp_unslash($_FILES['aiah5p_library']);
        $tmp_name = $file['tmp_name'];

        $zip = new ZipArchive();
        if ($zip->open($tmp_name) !== true) {
            self::redirect('error', __('The uploaded file could not be opened as a zip archive.', 'ai-h5p-generator'));
        }

        $libraries = AIAH5P_H5P_Builder::libraries_dir();
        wp_mkdir_p($libraries);

        $added = [];
        $skipped = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $parts = explode('/', $entry['name']);
            $top = $parts[0];
            if ($top === '' || $top === 'content' || strpos($top, '.') === 0 || $top === 'h5p.json' || count($parts) < 2) {
                continue;
            }
            if (!preg_match('/^H5P\.[A-Za-z0-9_]+$/', $top)) {
                continue;
            }

            $dest_dir = trailingslashit($libraries) . $top;
            if (file_exists($dest_dir . '/library.json')) {
                $skipped[] = $top;
                continue;
            }

            $zip->extractTo($libraries, [$entry['name']]);
            $added[] = $top;
        }
        $zip->close();

        if (empty($added) && empty($skipped)) {
            self::redirect('error', __('No H5P libraries found in the uploaded file.', 'ai-h5p-generator'));
        }

        $message = sprintf(
            /* translators: 1: list of added libraries, 2: list of skipped libraries */
            __('Added: %1$s. %2$s', 'ai-h5p-generator'),
            implode(', ', array_unique($added)) ?: __('none', 'ai-h5p-generator'),
            $skipped ? sprintf(__('Skipped (already installed): %s', 'ai-h5p-generator'), implode(', ', array_unique($skipped))) : ''
        );
        self::redirect('success', $message);
    }

    public static function handle_delete()
    {
        self::guard();

        $name = isset($_POST['aiah5p_library_name']) ? sanitize_text_field(wp_unslash($_POST['aiah5p_library_name'])) : '';
        if (!preg_match('/^H5P\.[A-Za-z0-9_]+$/', $name)) {
            self::redirect('error', __('Invalid library name.', 'ai-h5p-generator'));
        }

        $dir = trailingslashit(AIAH5P_H5P_Builder::libraries_dir()) . $name;
        if (!is_dir($dir)) {
            self::redirect('error', __('Library not found.', 'ai-h5p-generator'));
        }

        self::rrmdir($dir);
        self::redirect('success', sprintf(__('Deleted library %s.', 'ai-h5p-generator'), $name));
    }

    private static function guard()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }
        check_admin_referer('aiah5p_library');
    }

    private static function redirect($type, $message)
    {
        $url = add_query_arg([
            'aiah5p_notice' => $type,
            'aiah5p_message' => rawurlencode($message),
        ], admin_url('admin.php?page=aiah5p-libraries'));
        wp_safe_redirect($url);
        exit;
    }

    private static function rrmdir($dir)
    {
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
