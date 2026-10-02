<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Settings
{
    const OPTION_KEY = 'aiah5p_settings';

    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function activate()
    {
        if (get_option(self::OPTION_KEY) === false) {
            add_option(self::OPTION_KEY, [
                'api_key' => '',
                'model' => 'mistral-large-latest',
            ]);
        }
    }

    public static function register_settings()
    {
        register_setting('aiah5p_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [__CLASS__, 'sanitize'],
        ]);
    }

    public static function sanitize($input)
    {
        $defaults = self::defaults();
        $input = is_array($input) ? $input : [];
        return [
            'api_key' => isset($input['api_key']) ? sanitize_text_field($input['api_key']) : $defaults['api_key'],
            'model' => isset($input['model']) && $input['model'] !== '' ? sanitize_text_field($input['model']) : $defaults['model'],
        ];
    }

    public static function defaults()
    {
        return [
            'api_key' => '',
            'model' => 'mistral-large-latest',
        ];
    }

    public static function all()
    {
        $settings = get_option(self::OPTION_KEY, []);
        return wp_parse_args(is_array($settings) ? $settings : [], self::defaults());
    }

    public static function get($key)
    {
        $settings = self::all();
        return isset($settings[$key]) ? $settings[$key] : null;
    }

    public static function has_api_key()
    {
        return self::get('api_key') !== '';
    }
}
