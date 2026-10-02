<?php

if (!defined('ABSPATH')) {
    exit;
}

class AIAH5P_H5P_Builder
{
    public static function init()
    {
    }

    public static function supported_types()
    {
        return [
            'H5P.QuestionSet' => 'Quiz (Question Set)',
            'H5P.MultiChoice' => 'Multiple Choice Question',
            'H5P.Blanks' => 'Fill in the Blanks',
        ];
    }

    public static function schema_for($content_type)
    {
        $schemas = [
            'H5P.QuestionSet' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'questions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'question' => ['type' => 'string'],
                                'answers' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'text' => ['type' => 'string'],
                                            'correct' => ['type' => 'boolean'],
                                            'tip' => ['type' => 'string'],
                                        ],
                                        'required' => ['text', 'correct'],
                                    ],
                                ],
                            ],
                            'required' => ['question', 'answers'],
                        ],
                    ],
                ],
                'required' => ['title', 'questions'],
            ],
            'H5P.MultiChoice' => [
                'type' => 'object',
                'properties' => [
                    'question' => ['type' => 'string'],
                    'answers' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'text' => ['type' => 'string'],
                                'correct' => ['type' => 'boolean'],
                                'tip' => ['type' => 'string'],
                            ],
                            'required' => ['text', 'correct'],
                        ],
                    ],
                ],
                'required' => ['question', 'answers'],
            ],
            'H5P.Blanks' => [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string'],
                    'blanks' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'answer' => ['type' => 'string'],
                            ],
                            'required' => ['answer'],
                        ],
                    ],
                ],
                'required' => ['text'],
            ],
        ];

        if (!isset($schemas[$content_type])) {
            return new WP_Error('aiah5p_unsupported_type', sprintf(__('Unsupported content type: %s', 'ai-h5p-generator'), $content_type));
        }

        return $schemas[$content_type];
    }

    public static function build_content_json($content_type, $data)
    {
        switch ($content_type) {
            case 'H5P.QuestionSet':
                $questions = [];
                foreach ($data['questions'] as $q) {
                    $answers = [];
                    foreach ($q['answers'] as $a) {
                        $answers[] = [
                            'text' => $a['text'],
                            'correct' => (bool) $a['correct'],
                            'tip' => isset($a['tip']) ? $a['tip'] : '',
                        ];
                    }
                    $questions[] = [
                        'library' => 'H5P.MultiChoice 1.16',
                        'params' => [
                            'question' => $q['question'],
                            'answers' => $answers,
                            'overallFeedback' => [
                                ['from' => 0, 'to' => 100, 'feedback' => ''],
                            ],
                            'behaviour' => [
                                'enableRetry' => true,
                                'enableSolutionsButton' => true,
                                'type' => 'auto',
                            ],
                        ],
                    ];
                }
                return [
                    'title' => $data['title'],
                    'questions' => $questions,
                    'progressType' => 'textual',
                    'passPercentage' => 50,
                    'questionsPerProgress' => 1,
                    'randomQuestions' => false,
                    'initialQuestion' => 0,
                    'disableBackwardsNavigation' => false,
                    'enableStartTimeLimit' => false,
                    'startTimeLimit' => 0,
                    'endTime' => 0,
                    'limitType' => 'hard',
                    'timeLimit' => 0,
                    'enableFinishButton' => true,
                    'scoring' => [
                        'passPercentage' => 50,
                        'score' => 'percentage',
                        'maxScore' => 100,
                    ],
                    'texts' => [
                        'saveAnswerButton' => 'Save',
                        'showSolutionButton' => 'Show solution',
                        'retryButton' => 'Retry',
                        'finishButton' => 'Finish',
                        'timeSpent' => 'Time spent',
                        'score' => 'Score',
                        'yourResult' => 'Your result',
                        'scoreBarLabel' => 'You got :num out of :max points',
                        'answeredMaximum' => 'Answered maximum',
                    ],
                    'resultPage' => [],
                ];

            case 'H5P.MultiChoice':
                $answers = [];
                foreach ($data['answers'] as $a) {
                    $answers[] = [
                        'text' => $a['text'],
                        'correct' => (bool) $a['correct'],
                        'tip' => isset($a['tip']) ? $a['tip'] : '',
                    ];
                }
                return [
                    'question' => $data['question'],
                    'answers' => $answers,
                    'overallFeedback' => [
                        ['from' => 0, 'to' => 100, 'feedback' => ''],
                    ],
                    'behaviour' => [
                        'enableRetry' => true,
                        'enableSolutionsButton' => true,
                        'type' => 'auto',
                    ],
                ];

            case 'H5P.Blanks':
                $blanks = [];
                foreach ($data['blanks'] as $b) {
                    $blanks[] = [
                        'answer' => $b['answer'],
                        'display' => 'text',
                    ];
                }
                return [
                    'text' => $data['text'],
                    'blanks' => $blanks,
                    'behaviour' => [
                        'enableRetry' => true,
                        'enableSolutionsButton' => true,
                        'autoCheck' => true,
                        'caseSensitive' => false,
                        'showSolutionsRequiresInput' => true,
                        'separateLines' => false,
                        'confirmOnRetry' => true,
                        'ignoreScoring' => false,
                    ],
                ];
        }

        return new WP_Error('aiah5p_unsupported_type', sprintf(__('Unsupported content type: %s', 'ai-h5p-generator'), $content_type));
    }

    public static function resolve_libraries($content_type)
    {
        global $wpdb;
        $table = AIAH5P_DB::table('libraries');

        $main = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE machine_name = %s ORDER BY major_version DESC, minor_version DESC LIMIT 1",
            $content_type
        ), ARRAY_A);
        if (!$main) {
            return new WP_Error(
                'aiah5p_missing_library',
                sprintf(
                    /* translators: %s: H5P library name */
                    __('The library %s is not installed. Upload it under AI H5P → Libraries.', 'ai-h5p-generator'),
                    $content_type
                )
            );
        }

        $dependencies = AIAH5P_Library_Manager::dependencies_of((int) $main['id']);
        $libraries = [];
        foreach ($dependencies as $library) {
            $libraries[$library['id']] = $library;
        }
        $libraries[$main['id']] = $main;

        return [
            'main' => $main,
            'libraries' => array_values($libraries),
        ];
    }

    public static function write_content_dir($content_id, $title, $main_library, $content_json)
    {
        $target_dir = AIAH5P_Content_Store::content_dir($content_id);
        if (!wp_mkdir_p($target_dir)) {
            return new WP_Error('aiah5p_write_failed', __('Could not create the content directory in uploads.', 'ai-h5p-generator'));
        }

        $main_name = $main_library['machine_name'] . ' ' . $main_library['major_version'] . '.' . $main_library['minor_version'];

        file_put_contents(
            $target_dir . '/h5p.json',
            wp_json_encode([
                'title' => $title,
                'language' => 'und',
                'mainLibrary' => $main_library['machine_name'],
                'embedTypes' => ['div'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        file_put_contents(
            $target_dir . '/content.json',
            wp_json_encode($content_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        file_put_contents(
            $target_dir . '/library.json',
            wp_json_encode(['name' => $main_name], JSON_UNESCAPED_UNICODE)
        );

        return $target_dir;
    }

    public static function zip_content($content_id)
    {
        $content = AIAH5P_Content_Store::get($content_id);
        if (is_wp_error($content)) {
            return $content;
        }

        $libraries = AIAH5P_Content_Store::content_libraries($content_id);
        if (empty($libraries)) {
            return new WP_Error('aiah5p_missing_library', __('No libraries are associated with this content.', 'ai-h5p-generator'));
        }

        $base_dir = AIAH5P_Content_Store::base_dir();
        $zip_path = trailingslashit($base_dir) . 'content/' . $content_id . '.h5p';
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            return new WP_Error('aiah5p_zip_failed', __('Could not create the H5P zip archive.', 'ai-h5p-generator'));
        }

        $zip->addFile(
            AIAH5P_Content_Store::content_dir($content_id) . '/h5p.json',
            'h5p.json'
        );
        $content_json = json_decode($content['parameters'], true);
        $zip->addFromString(
            'content/content.json',
            wp_json_encode($content_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        $libraries_dir = AIAH5P_Library_Manager::libraries_dir();
        foreach ($libraries as $library) {
            $source = trailingslashit($libraries_dir) . $library['folder'];
            if (!is_dir($source)) {
                continue;
            }
            $local_base = $library['folder'];
            $zip->addEmptyDir($local_base);
            self::zip_add_dir($zip, $source, $local_base);
        }

        $zip->close();
        return $zip_path;
    }

    private static function zip_add_dir($zip, $src, $base)
    {
        $entries = new FilesystemIterator($src);
        foreach ($entries as $entry) {
            $local = $base === '' ? $entry->getFilename() : $base . '/' . $entry->getFilename();
            if ($entry->isDir()) {
                $zip->addEmptyDir($local);
                self::zip_add_dir($zip, $entry->getPathname(), $local);
            } else {
                $zip->addFile($entry->getPathname(), $local);
            }
        }
    }
}
