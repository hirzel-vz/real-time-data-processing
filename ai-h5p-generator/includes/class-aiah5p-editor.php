<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Editor
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_pages'], 11);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_aiah5p_save_content', [__CLASS__, 'handle_save']);
    }

    public static function register_pages()
    {
        add_submenu_page(
            'aiah5p-generator',
            __('Create', 'ai-h5p-generator'),
            __('Create', 'ai-h5p-generator'),
            'manage_options',
            'aiah5p-create',
            [__CLASS__, 'render_create_page']
        );

        add_submenu_page(
            null,
            __('Edit H5P', 'ai-h5p-generator'),
            __('Edit H5P', 'ai-h5p-generator'),
            'manage_options',
            'aiah5p-edit',
            [__CLASS__, 'render_edit_page']
        );
    }

    public static function enqueue_assets($hook)
    {
        if (!in_array($hook, ['ai-h5p-generator_page_aiah5p-edit'], true)) {
            return;
        }
        wp_enqueue_code_editor(['type' => 'application/json']);
        wp_add_inline_script(
            'code-editor',
            'jQuery(function($){ if (window.wp && wp.codeEditor) { wp.codeEditor.initialize("aiah5p_parameters", {}); } });'
        );
    }

    public static function runnable_libraries()
    {
        global $wpdb;
        $table = AIAH5P_DB::table('libraries');
        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE runnable = 1 ORDER BY machine_name ASC",
            ARRAY_A
        );
    }

    public static function render_create_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }

        $libraries = self::runnable_libraries();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI H5P Generator — Create', 'ai-h5p-generator'); ?></h1>
            <p><?php esc_html_e('Create H5P content manually from scratch, or use the Generate page to create it with AI. Both are editable afterwards.', 'ai-h5p-generator'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Content type', 'ai-h5p-generator'); ?></th>
                        <th><?php esc_html_e('Title', 'ai-h5p-generator'); ?></th>
                        <th><?php esc_html_e('Version', 'ai-h5p-generator'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-h5p-generator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($libraries)) : ?>
                    <tr><td colspan="4"><em><?php esc_html_e('No runnable libraries installed yet. Upload .h5p example files under Libraries first.', 'ai-h5p-generator'); ?></em></td></tr>
                <?php else : foreach ($libraries as $library) : ?>
                    <tr>
                        <td><code><?php echo esc_html($library['machine_name']); ?></code></td>
                        <td><?php echo esc_html($library['title']); ?></td>
                        <td><?php echo esc_html($library['major_version'] . '.' . $library['minor_version']); ?></td>
                        <td>
                            <a class="button button-primary"
                               href="<?php echo esc_url(admin_url('admin.php?page=aiah5p-edit&library=' . rawurlencode($library['machine_name']))); ?>">
                                <?php esc_html_e('Create new', 'ai-h5p-generator'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_edit_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }

        $content_id = isset($_GET['content_id']) ? absint($_GET['content_id']) : 0;
        $library_name = isset($_GET['library']) ? sanitize_text_field(wp_unslash($_GET['library'])) : '';

        $is_new = $content_id === 0 && $library_name !== '';

        if ($is_new) {
            $resolved = AIAH5P_H5P_Builder::resolve_libraries($library_name);
            if (is_wp_error($resolved)) {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($resolved->get_error_message()));
                return;
            }
            $title = '';
            $parameters = get_transient('aiah5p_params_' . get_current_user_id());
            if (!is_string($parameters)) {
                $parameters = '';
            }
            $main_library = $resolved['main'];
        } elseif ($content_id > 0) {
            $content = AIAH5P_Content_Store::get($content_id);
            if (is_wp_error($content)) {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($content->get_error_message()));
                return;
            }
            $title = $content['title'];
            $parameters = $content['parameters'];

            $resolved = AIAH5P_H5P_Builder::resolve_libraries($content['content_type']);
            if (is_wp_error($resolved)) {
                printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($resolved->get_error_message()));
                return;
            }
            $main_library = $resolved['main'];
        } else {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html(__('Nothing to edit.', 'ai-h5p-generator')));
            return;
        }

        $semantics = AIAH5P_Library_Manager::library_semantics($main_library['id']);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($is_new ? __('Create H5P content', 'ai-h5p-generator') : __('Edit H5P content', 'ai-h5p-generator')); ?></h1>
            <p>
                <code><?php echo esc_html($main_library['machine_name'] . ' ' . $main_library['major_version'] . '.' . $main_library['minor_version']); ?></code>
                <?php if (!$is_new) : ?>
                    — <code>[aiah5p id="<?php echo esc_attr($content_id); ?>"]</code>
                <?php endif; ?>
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="aiah5p_save_content" />
                <input type="hidden" name="aiah5p_content_id" value="<?php echo esc_attr($content_id); ?>" />
                <input type="hidden" name="aiah5p_library" value="<?php echo esc_attr($main_library['machine_name']); ?>" />
                <?php wp_nonce_field('aiah5p_save_content'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aiah5p_title"><?php esc_html_e('Title', 'ai-h5p-generator'); ?></label></th>
                        <td><input type="text" name="aiah5p_title" id="aiah5p_title" class="regular-text" value="<?php echo esc_attr($title); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aiah5p_parameters"><?php esc_html_e('Parameters (content JSON)', 'ai-h5p-generator'); ?></label></th>
                        <td>
                            <textarea name="aiah5p_parameters" id="aiah5p_parameters" rows="18" class="large-text code" style="font-family: monospace;"><?php echo esc_textarea($parameters); ?></textarea>
                            <p class="description">
                                <?php
                                if ($semantics !== null) {
                                    esc_html_e('This library has semantics.json installed; the form-based editor UI (rendered from the library semantics) is planned as the next milestone.', 'ai-h5p-generator');
                                } else {
                                    esc_html_e('This library has no semantics.json; edit the raw content JSON.', 'ai-h5p-generator');
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button($is_new ? __('Create', 'ai-h5p-generator') : __('Save changes', 'ai-h5p-generator'), 'primary'); ?>
            </form>
        </div>
        <?php
    }

    public static function handle_save()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to do that.', 'ai-h5p-generator'));
        }
        check_admin_referer('aiah5p_save_content');

        $content_id = isset($_POST['aiah5p_content_id']) ? absint($_POST['aiah5p_content_id']) : 0;
        $library_name = isset($_POST['aiah5p_library']) ? sanitize_text_field(wp_unslash($_POST['aiah5p_library'])) : '';
        $title = isset($_POST['aiah5p_title']) ? sanitize_text_field(wp_unslash($_POST['aiah5p_title'])) : '';
        $parameters_raw = isset($_POST['aiah5p_parameters']) ? wp_unslash($_POST['aiah5p_parameters']) : '';

        if ($title === '') {
            self::redirect_back($content_id, $library_name, 'error', __('Please provide a title.', 'ai-h5p-generator'), $parameters_raw);
        }

        $parameters = json_decode($parameters_raw, true);
        if (!is_array($parameters)) {
            self::redirect_back($content_id, $library_name, 'error', __('The parameters are not valid JSON.', 'ai-h5p-generator'), $parameters_raw);
        }

        $resolved = AIAH5P_H5P_Builder::resolve_libraries($library_name);
        if (is_wp_error($resolved)) {
            self::redirect_back($content_id, $library_name, 'error', $resolved->get_error_message(), $parameters_raw);
        }

        global $wpdb;
        $contents = AIAH5P_DB::table('contents');

        if ($content_id > 0) {
            $existing = AIAH5P_Content_Store::get($content_id);
            if (is_wp_error($existing)) {
                self::redirect_back(0, $library_name, 'error', $existing->get_error_message(), $parameters_raw);
            }

            $wpdb->update($contents, [
                'title' => $title,
                'parameters' => wp_json_encode($parameters, JSON_UNESCAPED_UNICODE),
            ], ['id' => $content_id]);

            AIAH5P_H5P_Builder::write_content_dir($content_id, $title, $resolved['main'], $parameters);

            self::redirect_notice(
                admin_url('admin.php?page=aiah5p-edit&content_id=' . $content_id),
                'success',
                __('Content updated.', 'ai-h5p-generator')
            );
        }

        $content_id = AIAH5P_Content_Store::save(
            $title,
            $library_name,
            '',
            $parameters,
            $resolved['main']['id'],
            wp_list_pluck($resolved['libraries'], 'id')
        );
        if (is_wp_error($content_id)) {
            self::redirect_back(0, $library_name, 'error', $content_id->get_error_message(), $parameters_raw);
        }

        $dir = AIAH5P_H5P_Builder::write_content_dir($content_id, $title, $resolved['main'], $parameters);
        if (is_wp_error($dir)) {
            AIAH5P_Content_Store::delete($content_id);
            self::redirect_back(0, $library_name, 'error', $dir->get_error_message(), $parameters_raw);
        }

        self::redirect_notice(
            admin_url('admin.php?page=aiah5p-edit&content_id=' . $content_id),
            'success',
            sprintf(
                /* translators: 1: content id, 2: shortcode */
                __('Content created (id %1$d). Use the shortcode %2$s in any post or page.', 'ai-h5p-generator'),
                $content_id,
                '[aiah5p id="' . $content_id . '"]'
            )
        );
    }

    private static function redirect_back($content_id, $library_name, $type, $message, $parameters)
    {
        $url = $content_id > 0
            ? admin_url('admin.php?page=aiah5p-edit&content_id=' . $content_id)
            : admin_url('admin.php?page=aiah5p-edit&library=' . rawurlencode($library_name));
        set_transient(
            'aiah5p_params_' . get_current_user_id(),
            $parameters,
            5 * MINUTE_IN_SECONDS
        );
        self::redirect_notice($url, $type, $message);
    }

    private static function redirect_notice($url, $type, $message)
    {
        $url = add_query_arg([
            'aiah5p_notice' => $type,
            'aiah5p_message' => rawurlencode($message),
        ], $url);
        wp_safe_redirect($url);
        exit;
    }
}
