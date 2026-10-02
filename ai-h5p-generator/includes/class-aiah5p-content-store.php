<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Content_Store
{
    public static function init()
    {
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

    public static function content_dir($id)
    {
        $dir = trailingslashit(self::base_dir()) . 'content/' . $id;
        return $dir;
    }

    public static function content_url($id)
    {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['baseurl']) . 'aiah5p/content/' . $id;
    }

    public static function save($title, $content_type, $prompt, $content_json, $main_library_id, $library_ids, $author_id = null)
    {
        global $wpdb;
        $contents = AIAH5P_DB::table('contents');
        $contents_libraries = AIAH5P_DB::table('contents_libraries');

        $wpdb->insert($contents, [
            'title' => $title,
            'content_type' => $content_type,
            'prompt' => $prompt,
            'parameters' => wp_json_encode($content_json, JSON_UNESCAPED_UNICODE),
            'main_library_id' => (int) $main_library_id,
            'author_id' => $author_id === null ? get_current_user_id() : (int) $author_id,
            'created_at' => current_time('mysql'),
        ]);
        $content_id = $wpdb->insert_id;
        if (!$content_id) {
            return new WP_Error('aiah5p_save_failed', __('Could not save the content record.', 'ai-h5p-generator'));
        }

        foreach (array_unique($library_ids) as $library_id) {
            $wpdb->insert($contents_libraries, [
                'content_id' => $content_id,
                'library_id' => (int) $library_id,
                'dependency_type' => 'preloaded',
            ]);
        }

        return $content_id;
    }

    public static function get($id)
    {
        global $wpdb;
        $table = AIAH5P_DB::table('contents');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id), ARRAY_A);
        if (!$row) {
            return new WP_Error('aiah5p_not_found', __('H5P content not found.', 'ai-h5p-generator'));
        }
        return $row;
    }

    public static function all()
    {
        global $wpdb;
        $table = AIAH5P_DB::table('contents');
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);
    }

    public static function content_libraries($content_id)
    {
        global $wpdb;
        $contents_libraries = AIAH5P_DB::table('contents_libraries');
        $libraries = AIAH5P_DB::table('libraries');
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.* FROM {$contents_libraries} cl JOIN {$libraries} l ON l.id = cl.library_id WHERE cl.content_id = %d",
            (int) $content_id
        ), ARRAY_A);
    }

    public static function delete($id)
    {
        global $wpdb;
        $contents = AIAH5P_DB::table('contents');
        $contents_libraries = AIAH5P_DB::table('contents_libraries');

        $row = self::get($id);
        if (is_wp_error($row)) {
            return $row;
        }

        $dir = self::content_dir($id);
        if (is_dir($dir)) {
            self::rrmdir($dir);
        }

        $wpdb->delete($contents_libraries, ['content_id' => (int) $id]);
        $wpdb->delete($contents, ['id' => (int) $id]);
        return true;
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
