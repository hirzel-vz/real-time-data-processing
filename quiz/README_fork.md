# json2h5p

Convert educational content from simple JSON files into **H5P packages** (`.h5p`) that can be uploaded to any H5P-compatible platform (Moodle, WordPress, Lumi, Canvas, ...).

Based on [artturner/json2h5p](https://github.com/artturner/json2h5p), with the following fixes and improvements over the upstream version:

- **Two dedicated scripts** — one per content type:
  - `json2h5p_question_set.py` converts **quizzes** (files with a `questions` array) to H5P **Question Sets**
  - `json2h5p_branching.py` converts **branching scenarios** (files with a `nodes` array) to H5P **Branching Scenarios**
- **Directory scanning** — each script converts every matching `.json` file in its folder (upstream only converts two hardcoded filenames and silently ignores everything else)
- **Clear errors** — invalid JSON is reported with file, line, and column instead of failing silently
- **Correct library handling** — all embedded H5P libraries are declared in `preloadedDependencies` (upstream declared only the main library, causing import failures)
- **Valid Branching Scenario structure** — quiz nodes use `H5P.BranchingQuestion` (the only valid question type inside a Branching Scenario), navigation uses real numeric `nextContentId` indexes (upstream embedded `H5P.MultiChoice`, which platforms reject)
- **Real titles** — the `title` from your JSON is written into `h5p.json` (upstream hardcoded "Converted Content")

## Requirements

- Python 3.6+ (standard library only)
- An H5P-compatible platform with the required libraries installed (see [Platform notes](#platform-notes))

## Usage

Put your JSON files and both scripts in the same folder, then run the script matching your content type:

```bash
python json2h5p_question_set.py   # converts every quiz .json in the folder
python json2h5p_branching.py      # converts every scenario .json in the folder
```

Each script converts only its own file type and prints what it skipped and why (quiz files are skipped by the scenario script and vice versa). The output `.h5p` file is created next to each input file.

## Quiz format (`json2h5p_question_set.py`)

```json
{
  "title": "Quiz Title",
  "introduction": "Quiz description shown on the intro page",
  "questions": [
    {
      "type": "multichoice",
      "question": "Question text",
      "options": [
        { "text": "Option A", "correct": true, "feedback": "Shown when this option is chosen" },
        { "text": "Option B", "correct": false, "feedback": "Shown when this option is chosen" }
      ]
    }
  ],
  "passPercentage": 80,
  "allowRetry": true,
  "randomizeQuestions": false
}
```

Rules:

- Exactly **one** option per question has `"correct": true`
- `passPercentage`, `allowRetry`, `randomizeQuestions` are optional (defaults: 50 / true / false)
- Optional `overallFeedback` list with `from`/`to`/`feedback` ranges

## Branching scenario format (`json2h5p_branching.py`)

```json
{
  "title": "Scenario Title",
  "introduction": "Subtitle on the start screen",
  "nodes": [
    {
      "id": "start",
      "type": "branch",
      "content": "Which way do you go?",
      "choices": [
        { "text": "Left", "next": "left_end" },
        { "text": "Right", "next": "right_end" }
      ]
    },
    {
      "id": "left_end",
      "type": "quiz",
      "question": "Answer this to finish:",
      "options": [
        { "text": "Option A", "correct": true, "feedback": "Feedback shown after choosing" },
        { "text": "Option B", "correct": false, "feedback": "Feedback shown after choosing" }
      ],
      "next": "end"
    },
    {
      "id": "right_end",
      "type": "content",
      "content": "The end. Thanks for playing!",
      "choices": []
    }
  ]
}
```

Node types:

| Type | H5P library | Purpose |
|---|---|---|
| `content` | H5P.AdvancedText 1.1 | Text screen with a single "Proceed" target |
| `branch` | H5P.BranchingQuestion 1.0 | Question with multiple alternatives |
| `quiz` | H5P.BranchingQuestion 1.0 | Same as `branch`; `options` + `next` instead of `choices` |

Navigation rules:

- `"next": "<node_id>"` points at the `id` of another node — the first node in `nodes` is where the scenario starts
- A `content` node with **multiple** `choices` automatically becomes a BranchingQuestion (text screens support only one target)
- `"next": "end"` (or a node with empty `choices` / no `next`) ends the scenario at the default end screen
- Inside a Branching Scenario, questions are pure branching: there is no correct/incorrect scoring, so the `correct` flag is ignored — use per-option `feedback` text
- Unknown `next` ids print a warning and end the scenario

## String sanitization rules (important)

JSON must parse cleanly and survive any editor/encoding. Keep every string value:

1. **Pure ASCII** — replace curly quotes (`“”`), en/em dashes (`–` `—`), math symbols (`√ ∑ ≤ ≈ ² ×`), and currency signs (`€ £ ¥`) with plain equivalents (`\"`, `-`, "the square root of", "approximately", `EUR`/`GBP`/`JPY`)
2. **Escape double quotes** inside strings: `\"soft dollars\"`
3. **No literal line breaks inside strings** — use the `\n` escape; tables work well as `- item: value` lists
4. **No leftover artifacts** — remove stray generator sentences like "Here are the next five questions formatted exactly as per your instructions:"

Quick validation before converting:

```python
import json
d = json.load(open("my_quiz.json", encoding="utf-8"))
assert all(sum(o["correct"] for o in q["options"]) == 1 for q in d["questions"])
```

## Platform notes

The generated `.h5p` contains `h5p.json` + `content/content.json` but **no library code** — your platform resolves the libraries from its installed set:

| Library | Used by |
|---|---|
| H5P.QuestionSet 1.20 | quizzes |
| H5P.MultiChoice 1.16 | quizzes |
| H5P.BranchingScenario 1.10 | scenarios |
| H5P.BranchingQuestion 1.0 | scenarios |
| H5P.AdvancedText 1.1 | scenarios |

If a library version is missing, install it once from the H5P Hub (or via *Libraries → Upload*), then re-import the `.h5p`. The versions above can be adjusted in the scripts' library constants if your platform ships different ones.

## License

MIT — see the upstream [artturner/json2h5p](https://github.com/artturner/json2h5p) repository.
