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

    public static function library_source($content_type)
    {
        return AIAH5P_PLUGIN_DIR . 'h5p-libraries/' . $content_type;
    }

    public static function build_content_dir($id, $content_type, $content_json)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $base_dir = AIAH5P_Content_Store::base_dir();
        $target_dir = trailingslashit($base_dir) . $id;

        $library_source = self::library_source($content_type);
        if (!is_dir($library_source)) {
            return new WP_Error(
                'aiah5p_missing_library',
                sprintf(
                    /* translators: %s: H5P library name */
                    __('The H5P library files for "%s" are not bundled with the plugin.', 'ai-h5p-generator'),
                    $content_type
                )
            );
        }

        if (!wp_mkdir_p($target_dir . '/content')) {
            return new WP_Error('aiah5p_write_failed', __('Could not create the content directory in uploads.', 'ai-h5p-generator'));
        }

        file_put_contents(
            $target_dir . '/content/content.json',
            wp_json_encode($content_json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        self::copy_dir($library_source, $target_dir);

        $main_library = self::main_library_name($content_type);
        file_put_contents(
            $target_dir . '/h5p.json',
            wp_json_encode([
                'title' => isset($content_json['title']) ? $content_json['title'] : $id,
                'language' => 'und',
                'mainLibrary' => $main_library,
                'embedTypes' => ['div'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $target_dir;
    }

    public static function zip_content($id)
    {
        $dir = AIAH5P_Content_Store::content_dir($id);
        if (is_wp_error($dir)) {
            return $dir;
        }

        $zip_path = trailingslashit(AIAH5P_Content_Store::base_dir()) . $id . '.h5p';
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            return new WP_Error('aiah5p_zip_failed', __('Could not create the H5P zip archive.', 'ai-h5p-generator'));
        }
        self::zip_add_dir($zip, $dir, '');
        $zip->close();

        return $zip_path;
    }

    private static function main_library_name($content_type)
    {
        $map = [
            'H5P.QuestionSet' => 'H5P.QuestionSet',
            'H5P.MultiChoice' => 'H5P.MultiChoice',
            'H5P.Blanks' => 'H5P.Blanks',
        ];
        return isset($map[$content_type]) ? $map[$content_type] : $content_type;
    }

    private static function copy_dir($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($src . '/' . $file)) {
                self::copy_dir($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
        closedir($dir);
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
