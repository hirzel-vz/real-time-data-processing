# H5P Quiz Content Instructions

This folder converts source documents (PDF, DOCX, TXT, MD) into H5P packages.
Follow these rules exactly when generating quiz or scenario JSON.

## Folder layout

- `json2h5p_question_set.py` — converts quiz JSON (has `questions`) to H5P Question Set
- `json2h5p_branching.py` — converts scenario JSON (has `nodes`) to H5P Branching Scenario
- Source documents and generated JSON live in this folder

## Workflow

When asked to create a quiz or scenario from a source document:

1. Extract the text from the document (for PDF use pdftotext or a Python
   library; if the PDF is scanned images, tell the user OCR is needed)
2. Generate the JSON in the exact format below
3. Validate it (see Validation)
4. Run the matching converter script
5. Report the output file name and question/node count

## Quiz JSON format

```json
{
  "title": "Exam Title",
  "introduction": "Short description shown on the intro page",
  "questions": [
    {
      "type": "multichoice",
      "question": "Question text",
      "options": [
        { "text": "Option A", "correct": true, "feedback": "Correct because ..." },
        { "text": "Option B", "correct": false, "feedback": "Incorrect because ..." }
      ]
    }
  ],
  "passPercentage": 80,
  "allowRetry": true,
  "randomizeQuestions": false
}
```

Rules:
- Exactly one option per question has `"correct": true`
- 3 options per question unless the user asks otherwise
- Every option gets a `feedback` string explaining why it is right or wrong
- Write the JSON with Python's `json.dump` (ensure_ascii=True) or equivalent
  so all escaping is handled by a serializer, never by hand

## Branching scenario JSON format

```json
{
  "title": "Scenario Title",
  "introduction": "Start screen subtitle",
  "nodes": [
    {
      "id": "start",
      "type": "branch",
      "content": "Decision point text",
      "choices": [
        { "text": "Choice A", "next": "node_id_a" },
        { "text": "Choice B", "next": "node_id_b" }
      ]
    },
    {
      "id": "node_id_a",
      "type": "quiz",
      "question": "Question text",
      "options": [
        { "text": "Option A", "correct": true, "feedback": "Feedback shown after choosing" },
        { "text": "Option B", "correct": false, "feedback": "Feedback shown after choosing" }
      ],
      "next": "end_summary"
    },
    {
      "id": "end_summary",
      "type": "content",
      "content": "Closing text",
      "choices": []
    }
  ]
}
```

Rules:
- Every node has a unique `id`; `next`/`choices.next` reference existing ids,
  the literal string "end", or a node with empty `choices`
- Node types: `content` (text screen, max one proceed target), `branch`
  (decision with `choices`), `quiz` (options + `next`)
- A content node with multiple choices becomes a BranchingQuestion; prefer
  type `branch` for decision points
- The first node in `nodes` is the start node

## String sanitization (mandatory)

Every string value in the JSON must:

1. Be pure ASCII. Replace curly quotes with ", en/em dashes with -,
   math symbols with words ("square root of", "approximately", "<=", "x"),
   currency with codes (EUR, GBP, JPY, USD)
2. Escape double quotes inside strings as \"
3. Contain no literal line breaks; use the \n escape; render tables as
   "- item: value" lines
4. Contain no meta-commentary or artifact sentences (e.g. "Here are the
   next five questions formatted exactly as per your instructions")

## Validation (always run before converting)

```python
import json
d = json.load(open("output.json", encoding="utf-8"))
if "questions" in d:
    assert all(q["type"] == "multichoice" for q in d["questions"])
    assert all(sum(o["correct"] for o in q["options"]) == 1 for q in d["questions"])
elif "nodes" in d:
    ids = [n["id"] for n in d["nodes"]]
    assert len(ids) == len(set(ids))
json.dumps(d).encode("ascii")
```

If validation fails, fix the JSON and re-validate; do not hand the user
unvalidated JSON.

## Conversion

```bash
python json2h5p_question_set.py   # quizzes
python json2h5p_branching.py      # scenarios
```

The .h5p output appears next to the JSON file.
