<?php
/**
 * Plugin Name:       H5P Content Generator
 * Description:       Generate H5P quizzes and branching scenarios from uploaded documents (PDF, DOCX, TXT, MD) via a local generation service. Results appear for editor review before publishing.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:       7.4
 * Author:             Heritage and Education
 * License:            MIT
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('H5PGEN_SERVICE_URL')) {
    // Override in wp-config.php, e.g. for Docker:
    // define('H5PGEN_SERVICE_URL', 'http://h5p-service:8000');
    define('H5PGEN_SERVICE_URL', 'http://127.0.0.1:8000');
}
define('H5PGEN_TIMEOUT', 360);

// Register the admin page under Tools
add_action('admin_menu', function () {
    add_management_page(
        'H5P Content Generator',
        'H5P Generator',
        'edit_pages',
        'h5p-generator',
        'h5pgen_render_page'
    );
});

function h5pgen_render_page()
{
    if (!current_user_can('edit_pages')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    $result = null;
    $error = null;

    if (isset($_POST['h5pgen_submit']) && check_admin_referer('h5pgen_generate')) {
        if (!empty($_FILES['h5pgen_file']['tmp_name'])) {
            $result = h5pgen_call_service($_FILES['h5pgen_file']);
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
                $result = null;
            }
        } else {
            $error = 'Please choose a document file.';
        }
    }

    ?>
    <div class="wrap">
        <h1>H5P Content Generator</h1>

        <?php if ($error): ?>
            <div class="notice notice-error"><p><?= esc_html($error) ?></p></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="notice notice-success">
                <p><strong>Generation complete.</strong></p>
            </div>
            <h2>Review before publishing</h2>
            <p>Download the package, check the intermediate JSON, then upload the .h5p
               through <em>H5P Content → Add New → Upload</em> when you are satisfied.</p>
            <table class="widefat striped" style="max-width: 700px;">
                <tbody>
                    <tr>
                        <td><strong>H5P package</strong></td>
                        <td><a class="button button-primary"
                              href="<?= esc_url($result['h5p_url']) ?>">Download .h5p</a></td>
                    </tr>
                    <tr>
                        <td><strong>Review JSON</strong></td>
                        <td><a class="button"
                              href="<?= esc_url($result['json_url']) ?>"
                              target="_blank">Open JSON</a></td>
                    </tr>
                </tbody>
            </table>
            <p style="margin-top: 12px;"><em>Result ID: <?= esc_html($result['result_id']) ?></em></p>
            <hr>
        <?php endif; ?>

        <h2>Generate new content</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('h5pgen_generate'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Source document</th>
                    <td>
                        <input type="file" name="h5pgen_file"
                               accept=".pdf,.docx,.txt,.md">
                        <p class="description">
                            PDF, DOCX, TXT or MD. Text-based documents only;
                            scanned PDFs require OCR first.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Content type</th>
                    <td>
                        <label><input type="radio" name="h5pgen_type" value="quiz" checked>
                            Question Set (quiz)</label><br>
                        <label><input type="radio" name="h5pgen_type" value="scenario">
                            Branching Scenario</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Count</th>
                    <td>
                        <input type="number" name="h5pgen_count" value="10" min="2" max="90">
                        <p class="description">Number of questions (quiz) or nodes (scenario).</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Generate H5P', 'primary', 'h5pgen_submit'); ?>
        </form>

        <hr>
        <p class="description">
            Requires the generation service running locally:
            <code>uvicorn h5p_generation_service:app --host 127.0.0.1 --port 8000</code>
        </p>
    </div>
    <?php
}

function h5pgen_call_service(array $file)
{
    $type = isset($_POST['h5pgen_type']) && $_POST['h5pgen_type'] === 'scenario'
        ? 'scenario' : 'quiz';
    $count = isset($_POST['h5pgen_count']) ? max(2, min(90, (int) $_POST['h5pgen_count'])) : 10;

    $file_path = $file['tmp_name'];
    $file_name = sanitize_file_name($file['name']);

    if (!function_exists('wp_remote_post')) {
        return new WP_Error('h5pgen', 'WordPress HTTP API is unavailable.');
    }

    $boundary = wp_generate_password(24, false);
    $body = h5pgen_multipart_body($file_path, $file_name, $type, $count, $boundary);

    $response = wp_remote_post(H5PGEN_SERVICE_URL . '/generate', [
        'timeout' => H5PGEN_TIMEOUT,
        'headers' => [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
        ],
        'body' => $body,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        $detail = json_decode($raw, true);
        $msg = isset($detail['detail']) ? $detail['detail'] : substr($raw, 0, 300);
        return new WP_Error('h5pgen', "Generation service error (HTTP {$code}): {$msg}");
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['result_id'])) {
        return new WP_Error('h5pgen', 'Unexpected response from generation service.');
    }

    // prefix relative URLs with the service base URL
    foreach (['h5p_url', 'json_url'] as $key) {
        if (isset($data[$key]) && strpos($data[$key], '/') === 0) {
            $data[$key] = rtrim(H5PGEN_SERVICE_URL, '/') . $data[$key];
        }
    }

    return $data;
}

function h5pgen_multipart_body($file_path, $file_name, $type, $count, $boundary)
{
    $crlf = "\r\n";
    $body = '';

    $fields = [
        'content_type' => $type,
        'count' => (string) $count,
    ];
    foreach ($fields as $name => $value) {
        $body .= '--' . $boundary . $crlf;
        $body .= "Content-Disposition: form-data; name=\"{$name}\"" . $crlf . $crlf;
        $body .= $value . $crlf;
    }

    $content = file_get_contents($file_path);
    $body .= '--' . $boundary . $crlf;
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$file_name}\"" . $crlf;
    $body .= 'Content-Type: application/octet-stream' . $crlf . $crlf;
    $body .= $content . $crlf;
    $body .= '--' . $boundary . '--' . $crlf;

    return $body;
}
