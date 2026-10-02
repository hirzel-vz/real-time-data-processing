<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Admin
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_notices', [__CLASS__, 'render_notices']);
    }

    public static function add_menu()
    {
        add_menu_page(
            __('AI H5P Generator', 'ai-h5p-generator'),
            __('AI H5P', 'ai-h5p-generator'),
            'manage_options',
            'aiah5p-generator',
            [__CLASS__, 'render_generator_page'],
            'dashicons-welcome-learn-more',
            58
        );

        add_submenu_page(
            'aiah5p-generator',
            __('Generate', 'ai-h5p-generator'),
            __('Generate', 'ai-h5p-generator'),
            'manage_options',
            'aiah5p-generator',
            [__CLASS__, 'render_generator_page']
        );

        add_submenu_page(
            'aiah5p-generator',
            __('Settings', 'ai-h5p-generator'),
            __('Settings', 'ai-h5p-generator'),
            'manage_options',
            'aiah5p-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_notices()
    {
        if (empty($_GET['aiah5p_notice'])) {
            return;
        }
        $type = sanitize_text_field(wp_unslash($_GET['aiah5p_notice']));
        $message = isset($_GET['aiah5p_message']) ? sanitize_text_field(wp_unslash(rawurldecode(wp_unslash($_GET['aiah5p_message'])))) : '';
        if ($message === '') {
            return;
        }
        $class = $type === 'success' ? 'notice-success' : 'notice-error';
        printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
    }

    public static function render_generator_page()
    {
        $settings = AIAH5P_Settings::all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI H5P Generator', 'ai-h5p-generator'); ?></h1>

            <?php if (empty($settings['api_key'])) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: URL to settings page */
                            esc_html__('No Mistral API key configured yet. Set it on the %s page.', 'ai-h5p-generator'),
                            '<a href="' . esc_url(admin_url('admin.php?page=aiah5p-settings')) . '">' . esc_html__('Settings', 'ai-h5p-generator') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="aiah5p_generate" />
                <?php wp_nonce_field('aiah5p_generate'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aiah5p_content_type"><?php esc_html_e('Content type', 'ai-h5p-generator'); ?></label></th>
                        <td>
                            <select name="aiah5p_content_type" id="aiah5p_content_type" required>
                                <?php foreach (AIAH5P_H5P_Builder::supported_types() as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aiah5p_prompt"><?php esc_html_e('Topic / instructions', 'ai-h5p-generator'); ?></label></th>
                        <td>
                            <textarea name="aiah5p_prompt" id="aiah5p_prompt" rows="6" class="large-text" required
                                placeholder="<?php esc_attr_e('e.g. A 5-question quiz about photosynthesis for high school students', 'ai-h5p-generator'); ?>"></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Generate H5P', 'ai-h5p-generator'), 'primary', 'submit', true, empty($settings['api_key'])); ?>
            </form>
        </div>
        <?php
    }

    public static function render_settings_page()
    {
        $settings = AIAH5P_Settings::all();
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI H5P Generator — Settings', 'ai-h5p-generator'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('aiah5p_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aiah5p_api_key"><?php esc_html_e('Mistral API key', 'ai-h5p-generator'); ?></label></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(AIAH5P_Settings::OPTION_KEY); ?>[api_key]" id="aiah5p_api_key"
                                value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" autocomplete="new-password" />
                            <p class="description"><?php esc_html_e('Stored in the WordPress options table. Create a key at console.mistral.ai.', 'ai-h5p-generator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aiah5p_model"><?php esc_html_e('Model', 'ai-h5p-generator'); ?></label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(AIAH5P_Settings::OPTION_KEY); ?>[model]" id="aiah5p_model"
                                value="<?php echo esc_attr($settings['model']); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('e.g. mistral-large-latest, mistral-small-latest', 'ai-h5p-generator'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
