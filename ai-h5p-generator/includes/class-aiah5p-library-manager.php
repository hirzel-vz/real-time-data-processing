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

    public static function libraries_dir()
    {
        $uploads = wp_upload_dir();
        $dir = trailingslashit($uploads['basedir']) . 'aiah5p/libraries';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    public static function libraries_url()
    {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['baseurl']) . 'aiah5p/libraries';
    }

    public static function installed_libraries()
    {
        global $wpdb;
        $table = AIAH5P_DB::table('libraries');
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY machine_name ASC", ARRAY_A);
    }

    public static function library_id($machine_name, $major, $minor)
    {
        global $wpdb;
        $table = AIAH5P_DB::table('libraries');
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE machine_name = %s AND major_version = %d AND minor_version = %d",
            $machine_name,
            $major,
            $minor
        ));
    }

    public static function folder_name($info)
    {
        return $info['machine_name'] . '-' . $info['major_version'] . '.' . $info['minor_version'];
    }

    public static function handle_upload()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }
        check_admin_referer('aiah5p_library');

        if (empty($_FILES['aiah5p_library'])) {
            self::redirect('error', __('No file uploaded.', 'ai-h5p-generator'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $file = $_FILES['aiah5p_library'];
        $tmp_name = $file['tmp_name'];

        $zip = new ZipArchive();
        if ($zip->open($tmp_name) !== true) {
            self::redirect('error', __('The uploaded file could not be opened as a zip archive.', 'ai-h5p-generator'));
        }

        $added = [];
        $skipped = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $parts = explode('/', $entry['name']);
            $top = $parts[0];
            if (!preg_match('/^H5P\.[A-Za-z0-9_]+$/', $top) || count($parts) < 2) {
                continue;
            }

            $json = $zip->getFromName($top . '/library.json');
            if ($json === false) {
                continue;
            }
            $info = json_decode($json, true);
            if (!is_array($info) || empty($info['title']) || !isset($info['majorVersion'], $info['minorVersion'])) {
                continue;
            }

            $folder = $top . '-' . $info['majorVersion'] . '.' . $info['minorVersion'];
            if (self::library_id($top, (int) $info['majorVersion'], (int) $info['minorVersion'])) {
                $skipped[] = $folder;
                continue;
            }

            $target = trailingslashit(self::libraries_dir()) . $folder;
            if (!wp_mkdir_p($target)) {
                continue;
            }

            foreach (range(0, $zip->numFiles - 1) as $j) {
                $sub = $zip->statIndex($j);
                if (strpos($sub['name'], $top . '/') !== 0) {
                    continue;
                }
                $local = substr($sub['name'], strlen($top . '/'));
                if ($local === false || $local === '') {
                    continue;
                }
                if (substr($local, -1) === '/') {
                    wp_mkdir_p($target . '/' . $local);
                    continue;
                }
                $dir = dirname($target . '/' . $local);
                if (!is_dir($dir)) {
                    wp_mkdir_p($dir);
                }
                $stream = $zip->getStream($sub['name']);
                if ($stream) {
                    file_put_contents($target . '/' . $local, $stream);
                    fclose($stream);
                }
            }

            self::register_library($top, $info, $folder);
            $added[] = $folder;
        }
        $zip->close();

        if (empty($added) && empty($skipped)) {
            self::redirect('error', __('No H5P libraries found in the uploaded file.', 'ai-h5p-generator'));
        }

        $message = sprintf(
            __('Added: %1$s. %2$s', 'ai-h5p-generator'),
            implode(', ', $added) ?: __('none', 'ai-h5p-generator'),
            $skipped ? sprintf(__('Skipped (already installed): %s', 'ai-h5p-generator'), implode(', ', $skipped)) : ''
        );
        self::redirect('success', $message);
    }

    public static function register_library($machine_name, $info, $folder)
    {
        global $wpdb;
        $table = AIAH5P_DB::table('libraries');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (machine_name, title, major_version, minor_version, patch_version, runnable, folder)
             VALUES (%s, %s, %d, %d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE title = VALUES(title), folder = VALUES(folder)",
            $machine_name,
            isset($info['title']) ? $info['title'] : '',
            isset($info['majorVersion']) ? (int) $info['majorVersion'] : 0,
            isset($info['minorVersion']) ? (int) $info['minorVersion'] : 0,
            isset($info['patchVersion']) ? (int) $info['patchVersion'] : 0,
            isset($info['runnable']) ? (int) $info['runnable'] : 0,
            $folder
        ));
    }

    public static function handle_delete()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }
        check_admin_referer('aiah5p_library');

        $library_id = isset($_POST['aiah5p_library_id']) ? absint($_POST['aiah5p_library_id']) : 0;
        if ($library_id === 0) {
            self::redirect('error', __('Invalid library.', 'ai-h5p-generator'));
        }

        $usage = self::library_usage_count($library_id);
        if ($usage > 0) {
            self::redirect('error', sprintf(__('This library is used by %d content item(s) and cannot be deleted.', 'ai-h5p-generator'), $usage));
        }

        global $wpdb;
        $table = AIAH5P_DB::table('libraries');
        $library = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $library_id), ARRAY_A);
        if (!$library) {
            self::redirect('error', __('Library not found.', 'ai-h5p-generator'));
        }

        $dir = trailingslashit(self::libraries_dir()) . $library['folder'];
        if (is_dir($dir)) {
            self::rrmdir($dir);
        }
        $wpdb->delete($table, ['id' => $library_id]);
        self::redirect('success', sprintf(__('Deleted library %s.', 'ai-h5p-generator'), $library['folder']));
    }

    public static function library_row($library_id)
    {
        global $wpdb;
        $table = AIAH5P_DB::table('libraries');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $library_id), ARRAY_A);
    }

    public static function library_semantics($library_id)
    {
        $library = self::library_row($library_id);
        if (!$library) {
            return null;
        }
        $path = trailingslashit(self::libraries_dir()) . $library['folder'] . '/semantics.json';
        if (!file_exists($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public static function library_usage_count($library_id)
    {
        global $wpdb;
        $table = AIAH5P_DB::table('contents_libraries');
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE library_id = %d", $library_id));
    }

    public static function dependencies_of($library_id)
    {
        global $wpdb;
        $libraries = AIAH5P_DB::table('libraries');
        $library = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$libraries} WHERE id = %d", $library_id), ARRAY_A);
        if (!$library) {
            return [];
        }

        $dir = trailingslashit(self::libraries_dir()) . $library['folder'];
        $json_path = $dir . '/library.json';
        if (!file_exists($json_path)) {
            return [$library];
        }
        $info = json_decode((string) file_get_contents($json_path), true);
        if (!is_array($info) || empty($info['preloadedDependencies'])) {
            return [$library];
        }

        $result = [$library];
        foreach ($info['preloadedDependencies'] as $dep) {
            $dep_id = self::library_id($dep['machineName'], (int) $dep['majorVersion'], (int) $dep['minorVersion']);
            if ($dep_id) {
                foreach (self::dependencies_of($dep_id) as $sub) {
                    $result[$sub['id']] = $sub;
                }
            }
        }
        return $result;
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
