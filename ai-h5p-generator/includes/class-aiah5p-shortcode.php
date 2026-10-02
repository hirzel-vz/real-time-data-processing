<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Shortcode
{
    public static function init()
    {
        add_shortcode('aiah5p', [__CLASS__, 'render']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    }

    public static function register_assets()
    {
        wp_register_script(
            'aiah5p-standalone',
            AIAH5P_PLUGIN_URL . 'assets/h5p-standalone/main.bundle.js',
            [],
            AIAH5P_VERSION,
            true
        );
    }

    public static function render($atts)
    {
        $atts = shortcode_atts([
            'id' => '',
            'width' => '',
        ], $atts, 'aiah5p');

        $id = preg_replace('/^aiah5p_/', '', $atts['id']);
        if (!ctype_digit((string) $id)) {
            return '';
        }

        $dir = AIAH5P_Content_Store::content_dir($id);
        if (is_wp_error($dir)) {
            return current_user_can('manage_options')
                ? '<p><em>' . esc_html($dir->get_error_message()) . '</em></p>'
                : '';
        }

        wp_enqueue_script('aiah5p-standalone');

        $frame_id = 'aiah5p-frame-' . $id;
        $url = AIAH5P_Content_Store::content_url($id);

        ob_start();
        ?>
        <div class="aiah5p-container" id="<?php echo esc_attr($frame_id); ?>"></div>
        <script>
        (function () {
            function init() {
                new H5PStandalone.H5P('<?php echo esc_js($frame_id); ?>', '<?php echo esc_js($url); ?>', {
                    frame: true,
                    copyright: false,
                    embed: false,
                    download: false,
                    icon: false,
                    export: false
                });
            }
            if (window.H5PStandalone) {
                init();
            } else {
                window.addEventListener('load', init);
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
