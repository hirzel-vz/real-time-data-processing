#!/usr/bin/env python3
"""
H5P Generation Service
FastAPI service that converts source documents (PDF, DOCX, TXT, MD) into
H5P packages: text extraction -> Mistral API (structured JSON) ->
validation -> conversion via json2h5p_question_set.py /
json2h5p_branching.py.

Run:  uvicorn h5p_generation_service:app --host 127.0.0.1 --port 8000
"""

import json
import os
import subprocess
import sys
import tempfile
import uuid
from typing import Optional

import requests
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse, JSONResponse

MISTRAL_API_KEY = os.environ.get("MISTRAL_API_KEY", "")
MISTRAL_API_URL = "https://api.mistral.ai/v1/chat/completions"
MISTRAL_MODEL = "mistral-medium-latest"

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CONVERTERS = {
    "quiz": os.path.join(BASE_DIR, "json2h5p_question_set.py"),
    "scenario": os.path.join(BASE_DIR, "json2h5p_branching.py"),
}

QUIZ_SYSTEM_PROMPT = """You create H5P quiz JSON from educational documents.
Return ONLY a JSON object, no markdown fences, no commentary, with exactly
this structure:
{
  "title": "Exam title from the document",
  "introduction": "One-sentence description",
  "questions": [
    {
      "type": "multichoice",
      "question": "Question text",
      "options": [
        {"text": "Option A", "correct": true, "feedback": "Correct because ..."},
        {"text": "Option B", "correct": false, "feedback": "Incorrect because ..."},
        {"text": "Option C", "correct": false, "feedback": "Incorrect because ..."}
      ]
    }
  ],
  "passPercentage": 80,
  "allowRetry": true,
  "randomizeQuestions": false
}
Rules:
- Exactly one option per question has "correct": true
- Every option has a feedback string explaining why it is right or wrong
- Every string value is pure ASCII: replace curly quotes with \", en/em
  dashes with -, math symbols with words ("square root of",
  "approximately"), currency with codes (EUR, GBP, JPY, USD)
- No literal line breaks inside strings; use \\n escapes
- No meta-commentary or artifact sentences
"""

SCENARIO_SYSTEM_PROMPT = """You create H5P branching scenario JSON from
educational documents. Return ONLY a JSON object, no markdown fences, no
commentary, with exactly this structure:
{
  "title": "Scenario title from the document",
  "introduction": "Start screen subtitle",
  "nodes": [
    {
      "id": "start",
      "type": "branch",
      "content": "Decision point text",
      "choices": [
        {"text": "Choice A", "next": "node_id_a"},
        {"text": "Choice B", "next": "node_id_b"}
      ]
    },
    {
      "id": "node_id_a",
      "type": "quiz",
      "question": "Question text",
      "options": [
        {"text": "Option A", "correct": true, "feedback": "Feedback after choosing"},
        {"text": "Option B", "correct": false, "feedback": "Feedback after choosing"}
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
Rules:
- Every node has a unique id; next references existing ids or "end"
- Node types: content (max one proceed target), branch (choices), quiz
  (options + next); first node is the start node
- Coherent storytelling through the document's material
- Every string value is pure ASCII; no literal line breaks (use \\n);
  no meta-commentary
"""

app = FastAPI(title="H5P Generation Service")


def extract_text(file_path: str) -> str:
    """Extract text from PDF, DOCX, TXT or MD files."""
    lower = file_path.lower()
    if lower.endswith(".pdf"):
        from pypdf import PdfReader
        reader = PdfReader(file_path)
        pages = [page.extract_text() or "" for page in reader.pages]
        text = "\n".join(pages)
    elif lower.endswith(".docx"):
        import docx
        document = docx.Document(file_path)
        text = "\n".join(p.text for p in document.paragraphs)
    elif lower.endswith((".txt", ".md")):
        with open(file_path, "r", encoding="utf-8", errors="replace") as f:
            text = f.read()
    else:
        raise HTTPException(400, f"Unsupported file type: {file_path}")

    if not text or len(text.strip()) < 100:
        raise HTTPException(
            422,
            "Could not extract meaningful text. The document may be scanned "
            "(OCR would be required) or empty.",
        )
    return text


def generate_quiz_json(text: str, content_type: str, count: int) -> dict:
    """Call the Mistral API to generate quiz or scenario JSON."""
    if not MISTRAL_API_KEY:
        raise HTTPException(500, "MISTRAL_API_KEY environment variable is not set")

    system_prompt = QUIZ_SYSTEM_PROMPT if content_type == "quiz" else SCENARIO_SYSTEM_PROMPT
    if content_type == "quiz":
        user_prompt = (
            f"Create a quiz with {count} multiple-choice questions from the "
            f"following document. Ground every question in the document's "
            f"actual content.\n\nDOCUMENT:\n{text}"
        )
    else:
        user_prompt = (
            f"Create a branching scenario with {count} nodes from the "
            f"following document. Coherent storytelling through the "
            f"document's material.\n\nDOCUMENT:\n{text}"
        )

    response = requests.post(
        MISTRAL_API_URL,
        headers={
            "Authorization": f"Bearer {MISTRAL_API_KEY}",
            "Content-Type": "application/json",
        },
        json={
            "model": MISTRAL_MODEL,
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
            "temperature": 0.4,
            "response_format": {"type": "json_object"},
        },
        timeout=300,
    )
    if response.status_code != 200:
        raise HTTPException(502, f"Mistral API error {response.status_code}: {response.text[:500]}")

    content = response.json()["choices"][0]["message"]["content"]
    try:
        return json.loads(content)
    except json.JSONDecodeError as e:
        raise HTTPException(502, f"Model returned invalid JSON: {e}")


def validate_content_json(data: dict) -> None:
    """Validate generated JSON against the converter requirements."""
    try:
        json.dumps(data, ensure_ascii=False).encode("ascii")
    except UnicodeEncodeError:
        raise HTTPException(422, "Generated content contains non-ASCII characters; regenerate with ASCII-only strings")

    if "questions" in data:
        questions = data["questions"]
        if not questions:
            raise HTTPException(422, "Quiz has no questions")
        for q in questions:
            if q.get("type") != "multichoice":
                raise HTTPException(422, f"Unsupported question type: {q.get('type')}")
            correct_count = sum(1 for o in q.get("options", []) if o.get("correct") is True)
            if correct_count != 1:
                raise HTTPException(422, f"Question must have exactly one correct option: {q.get('question', '?')[:60]}")
    elif "nodes" in data:
        nodes = data["nodes"]
        if not nodes:
            raise HTTPException(422, "Scenario has no nodes")
        ids = [n.get("id") for n in nodes]
        if len(ids) != len(set(ids)):
            raise HTTPException(422, "Scenario has duplicate node ids")
    else:
        raise HTTPException(422, "JSON has neither 'questions' nor 'nodes'")


def run_converter(work_dir: str, json_path: str) -> str:
    """Run the matching json2h5p converter on the JSON file."""
    data = json.load(open(json_path, encoding="utf-8"))
    converter = CONVERTERS["quiz"] if "questions" in data else CONVERTERS["scenario"]
    result = subprocess.run(
        [sys.executable, converter],
        cwd=work_dir,
        capture_output=True,
        text=True,
        timeout=120,
    )
    if result.returncode != 0:
        raise HTTPException(500, f"Converter failed: {result.stderr[:500]}")
    return result.stdout


@app.post("/generate")
async def generate(
    file: UploadFile = File(...),
    content_type: str = Form(...),
    count: Optional[int] = Form(None),
):
    """Generate an H5P package from an uploaded document.

    content_type: "quiz" or "scenario"
    count: number of questions (quiz) or nodes (scenario)
    Returns: the .h5p file; the JSON is stored alongside for review.
    """
    if content_type not in ("quiz", "scenario"):
        raise HTTPException(400, "content_type must be 'quiz' or 'scenario'")
    if count is None:
        count = 10 if content_type == "quiz" else 8

    with tempfile.TemporaryDirectory() as work_dir:
        # copy converters into the work dir (they operate on the whole folder)
        for converter_path in CONVERTERS.values():
            os.symlink(os.path.abspath(converter_path), os.path.join(work_dir, os.path.basename(converter_path)))
            mod_name = os.path.basename(converter_path).replace(".py", "")
            init_path = os.path.join(os.path.dirname(os.path.abspath(converter_path)), mod_name + ".py")

        suffix = os.path.splitext(file.filename or "document")[1] or ".pdf"
        doc_path = os.path.join(work_dir, "source" + suffix)
        with open(doc_path, "wb") as f:
            f.write(await file.read())

        text = extract_text(doc_path)
        data = generate_quiz_json(text, content_type, count)
        validate_content_json(data)

        json_path = os.path.join(work_dir, "output.json")
        with open(json_path, "w", encoding="ascii") as f:
            json.dump(data, f, indent=2)

        run_converter(work_dir, json_path)
        h5p_path = os.path.join(work_dir, "output.h5p")
        if not os.path.exists(h5p_path):
            raise HTTPException(500, "Converter did not produce output.h5p")

        # persist outputs for the review step
        result_dir = os.path.join(BASE_DIR, "generated", uuid.uuid4().hex)
        os.makedirs(result_dir, exist_ok=True)
        for name in ("output.h5p", "output.json"):
            os.replace(os.path.join(work_dir, name), os.path.join(result_dir, name))
        with open(os.path.join(result_dir, "meta.json"), "w") as f:
            json.dump({"source": file.filename, "content_type": content_type, "count": count}, f)

        return JSONResponse({
            "status": "ok",
            "h5p_url": f"/download/{os.path.basename(result_dir)}",
            "json_url": f"/review/{os.path.basename(result_dir)}",
            "result_id": os.path.basename(result_dir),
        })


@app.get("/download/{result_id}")
def download(result_id: str):
    """Download a generated .h5p file."""
    if not result_id.isalnum():
        raise HTTPException(400, "Invalid id")
    path = os.path.join(BASE_DIR, "generated", result_id, "output.h5p")
    if not os.path.exists(path):
        raise HTTPException(404, "Not found")
    return FileResponse(path, filename=f"generated_{result_id}.h5p")


@app.get("/review/{result_id}")
def review(result_id: str):
    """Fetch the intermediate JSON for editor review."""
    if not result_id.isalnum():
        raise HTTPException(400, "Invalid id")
    path = os.path.join(BASE_DIR, "generated", result_id, "output.json")
    if not os.path.exists(path):
        raise HTTPException(404, "Not found")
    return FileResponse(path, filename=f"generated_{result_id}.json", media_type="application/json")


@app.get("/health")
def health():
    return {
        "status": "ok",
        "api_key_set": bool(MISTRAL_API_KEY),
        "converters_present": all(os.path.exists(p) for p in CONVERTERS.values()),
    }
