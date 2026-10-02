<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_DB
{
    const DB_VERSION = '1';

    public static function init()
    {
        if (get_option('aiah5p_db_version') !== self::DB_VERSION) {
            self::install();
        }
    }

    public static function table($name)
    {
        global $wpdb;
        return $wpdb->prefix . 'aiah5p_' . $name;
    }

    public static function install()
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $libraries = self::table('libraries');
        $contents = self::table('contents');
        $contents_libraries = self::table('contents_libraries');

        dbDelta("CREATE TABLE {$libraries} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            machine_name VARCHAR(127) NOT NULL,
            title VARCHAR(255) NOT NULL DEFAULT '',
            major_version INT NOT NULL DEFAULT 0,
            minor_version INT NOT NULL DEFAULT 0,
            patch_version INT NOT NULL DEFAULT 0,
            runnable TINYINT NOT NULL DEFAULT 0,
            folder VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY uniq_library (machine_name, major_version, minor_version)
        ) {$charset};");

        dbDelta("CREATE TABLE {$contents} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL DEFAULT '',
            content_type VARCHAR(127) NOT NULL DEFAULT '',
            prompt TEXT NOT NULL DEFAULT '',
            parameters LONGTEXT NOT NULL DEFAULT '',
            main_library_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$contents_libraries} (
            content_id BIGINT UNSIGNED NOT NULL,
            library_id BIGINT UNSIGNED NOT NULL,
            dependency_type VARCHAR(31) NOT NULL DEFAULT 'preloaded',
            PRIMARY KEY (content_id, library_id)
        ) {$charset};");

        update_option('aiah5p_db_version', self::DB_VERSION);
    }
}
