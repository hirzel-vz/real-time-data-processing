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
            __('Content', 'ai-h5p-generator'),
            __('Content', 'ai-h5p-generator'),
            'manage_options',
            'aiah5p-content',
            [__CLASS__, 'render_content_page']
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

    public static function render_content_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }

        if (isset($_GET['aiah5p_action'], $_GET['aiah5p_content_id'])) {
            check_admin_referer('aiah5p_content_action');
            $content_id = absint($_GET['aiah5p_content_id']);
            $action = sanitize_key($_GET['aiah5p_action']);

            if ($action === 'delete') {
                $result = AIAH5P_Content_Store::delete($content_id);
                if (is_wp_error($result)) {
                    self::redirect_with_notice('aiah5p-content', 'error', $result->get_error_message());
                }
                self::redirect_with_notice('aiah5p-content', 'success', __('Content deleted.', 'ai-h5p-generator'));
            } elseif ($action === 'download') {
                $zip = AIAH5P_H5P_Builder::zip_content($content_id);
                if (is_wp_error($zip)) {
                    self::redirect_with_notice('aiah5p-content', 'error', $zip->get_error_message());
                }
                $title = get_the_title($content_id);
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . sanitize_title($title ? $title : 'aiah5p-content') . '.h5p"');
                header('Content-Length: ' . filesize($zip));
                header('Connection: close');
                readfile($zip);
                exit;
            }
        }

        $contents = get_posts([
            'post_type' => AIAH5P_Content_Store::POST_TYPE,
            'numberposts' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'any',
        ]);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI H5P Generator — Content', 'ai-h5p-generator'); ?></h1>
            <p><?php printf(esc_html__('Use %s in any post or page to embed an item.', 'ai-h5p-generator'), '<code>[aiah5p id="…"]</code>'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'ai-h5p-generator'); ?></th>
                        <th><?php esc_html_e('Content type', 'ai-h5p-generator'); ?></th>
                        <th><?php esc_html_e('Shortcode', 'ai-h5p-generator'); ?></th>
                        <th><?php esc_html_e('Created', 'ai-h5p-generator'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-h5p-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($contents)) : ?>
                    <tr><td colspan="5"><em><?php esc_html_e('No content generated yet. Go to Generate to create your first H5P.', 'ai-h5p-generator'); ?></em></td></tr>
                <?php else : foreach ($contents as $content) : ?>
                    <tr>
                        <td><?php echo esc_html($content->post_title); ?></td>
                        <td><?php echo esc_html((string) get_post_meta($content->ID, 'aiah5p_content_type', true)); ?></td>
                        <td><code>[aiah5p id="<?php echo esc_attr($content->ID); ?>"]</code></td>
                        <td><?php echo esc_html(get_the_date('', $content)); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['aiah5p_action' => 'download', 'aiah5p_content_id' => $content->ID], admin_url('admin.php?page=aiah5p-content')), 'aiah5p_content_action')); ?>"><?php esc_html_e('Download .h5p', 'ai-h5p-generator'); ?></a> |
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['aiah5p_action' => 'delete', 'aiah5p_content_id' => $content->ID], admin_url('admin.php?page=aiah5p-content')), 'aiah5p_content_action')); ?>"
                                onclick="return confirm('<?php echo esc_js(__('Delete this content permanently?', 'ai-h5p-generator')); ?>');"><?php esc_html_e('Delete', 'ai-h5p-generator'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function redirect_with_notice($page, $type, $message)
    {
        $url = add_query_arg([
            'aiah5p_notice' => $type,
            'aiah5p_message' => rawurlencode($message),
        ], admin_url('admin.php?page=' . $page));
        wp_safe_redirect($url);
        exit;
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
