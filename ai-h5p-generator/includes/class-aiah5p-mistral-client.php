<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_Mistral_Client
{
    public static function init()
    {
    }

    public static function generate_content($prompt, $content_type)
    {
        $api_key = AIAH5P_Settings::get('api_key');
        if ($api_key === '' || $api_key === null) {
            return new WP_Error('aiah5p_no_api_key', __('No Mistral API key configured. Set it under AI H5P Generator → Settings.', 'ai-h5p-generator'));
        }
        $model = AIAH5P_Settings::get('model');

        $system_prompt = self::build_system_prompt($content_type);
        $schema = AIAH5P_H5P_Builder::schema_for($content_type);
        if (is_wp_error($schema)) {
            return $schema;
        }

        $response = wp_remote_post('https://api.mistral.ai/v1/chat/completions', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system_prompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'schema' => $schema,
                        'name' => 'h5p_content',
                        'strict' => true,
                    ],
                ],
                'temperature' => 0.4,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $message = isset($body['message']) ? $body['message'] : ('HTTP ' . $code);
            return new WP_Error('aiah5p_api_error', sprintf(__('Mistral API error: %s', 'ai-h5p-generator'), $message));
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return new WP_Error('aiah5p_invalid_response', __('The API response could not be parsed as JSON.', 'ai-h5p-generator'));
        }

        return $decoded;
    }

    private static function build_system_prompt($content_type)
    {
        return sprintf(
            "You are an expert instructional designer. Given a topic or request from the user, produce engaging H5P content of type \"%s\". Follow the provided JSON schema exactly. Keep questions, answers, and feedback concrete, educational and concise. Return only valid JSON.",
            $content_type
        );
    }
}
