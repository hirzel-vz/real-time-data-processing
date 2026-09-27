# JSON to H5P Quiz Authoring Guide

Tools and conventions for converting quiz JSON files into `.h5p` packages using the
patched `json_to_h5p.py` converter in this folder.

## Files in this folder

- `json_to_h5p.py` — quiz converter: converts `.json` files containing a `questions`
  array to H5P **Question Set** packages. Differences from the upstream
  [artturner/json2h5p](https://github.com/artturner/json2h5p) version:
  1. `main()` converts **every** quiz `.json` file in the current directory (upstream only
     converts two hardcoded filenames and silently ignores everything else).
  2. Invalid JSON is reported with a clear error message instead of being silently ignored.
  3. The quiz `title` from your JSON is written into `h5p.json` (upstream hardcoded
     "Converted Content").
  4. Branching scenario files are skipped with a pointer to `json_to_h5p_branching.py`.
- `json_to_h5p_branching.py` — scenario converter: converts `.json` files containing
  a `nodes` array to H5P **Branching Scenario** packages. Same fixes as above; quiz
  files are skipped with a pointer to `json_to_h5p.py`.
- `cfa_mock_exam_2_session_1.json` — example quiz (90 multiple-choice questions),
  fully sanitized and verified.
- `Week1_CivicLab_Philadelphia1787.json` — example branching scenario.

## Quiz JSON schema

```json
{
  "title": "Quiz Title",
  "introduction": "Quiz description",
  "questions": [
    {
      "type": "multichoice",
      "question": "Question text",
      "options": [
        {
          "text": "Option A",
          "correct": true,
          "feedback": "Explanation"
        },
        {
          "text": "Option B",
          "correct": false,
          "feedback": "Explanation"
        }
      ]
    }
  ],
  "passPercentage": 80,
  "allowRetry": true,
  "randomizeQuestions": false
}
```

Rules:

- Exactly **one** option per question has `"correct": true`.
- `passPercentage`, `allowRetry`, `randomizeQuestions` are optional (defaults: 50 / true / false).
- Supported question types: `multichoice`, `multiselect`, `shortanswer`.

## String sanitization rules (important)

The JSON must parse cleanly and survive any editor/encoding. Every string value must:

1. **Be pure ASCII.** Replace curly quotes (`“”‘’`), en/em dashes (`–`, `—`),
   math symbols (`√`, `∑`, `≤`, `≈`, `²`, `×`), and currency signs (`€`, `£`, `¥`, `−`)
   with plain equivalents: `"` via `\"`, `-`, "the square root of", "approximately",
   `EUR`, `GBP`, `JPY`.
2. **Escape double quotes** inside strings: `\"soft dollars\"` — a raw `"` inside a
   string breaks the parser.
3. **No literal line breaks inside strings.** Use the `\n` escape for multi-line
   text. Tables work well as `- item: value` lists.
4. **No leftover LLM artifacts.** Remove stray sentences like
   "Here are the next five questions formatted exactly as per your instructions:".

## How to convert

Two scripts, one per content type — run the one matching your file(s):

```bash
cd quiz
python json_to_h5p.py            # quizzes only (files with a "questions" array)
python json_to_h5p_branching.py   # branching scenarios only (files with a "nodes" array)
```

Each script converts only its own file type and tells you what it skipped and why.
For a single file with custom output name:

```python
from json_to_h5p import H5PConverter
H5PConverter().convert_quiz_to_h5p("my_quiz.json", "my_quiz.h5p", "My Quiz Title")
```

## Validate before converting

```python
import json
d = json.load(open("my_quiz.json", encoding="utf-8"))
assert all(sum(o["correct"] for o in q["options"]) == 1 for q in d["questions"])
```

## Platform caveat

The generated `.h5p` contains only `h5p.json` + `content/content.json` — no library
folders. The target platform (Moodle, WordPress, Lumi) must already have
`H5P.QuestionSet 1.20` and `H5P.MultiChoice 1.16` installed (one-time install from
the H5P Hub), otherwise the upload is rejected with a "missing library" error.
