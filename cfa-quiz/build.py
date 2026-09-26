#!/usr/bin/env python3
"""
Builds 'cfa-level1-mock-exam.json' (json2h5p quiz format) from the Google Doc
"2026 CFA Program LI Mock Exam 2 Session 1".

Usage:  python build.py
Input:  doc.txt (plain-text export of the Google Doc) placed next to this
        script, OR the script downloads it directly from Google Docs.
Output: cfa-level1-mock-exam.json  (feed this to json2h5p, e.g.
        H5PConverter().convert_quiz_to_h5p(...))

Tested against artturner/json2h5p: converts cleanly to an H5P Question Set
with all 90 questions, per-option correct flags and feedback.
"""

import json
import re
import sys
import urllib.request

DOC_URL = (
    "https://docs.google.com/document/d/"
    "1LtU00wZzIaH5uhhNYm5PNUDEGgmmfwMktyxKtwPRFg0/export?format=txt"
)
OUTPUT = "cfa-level1-mock-exam.json"


def load_text():
    try:
        with open("doc.txt", encoding="utf-8-sig") as f:
            return f.read()
    except FileNotFoundError:
        print("doc.txt not found, downloading from Google Docs...")
        req = urllib.request.Request(DOC_URL, headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=60) as r:
            data = r.read()
        with open("doc.txt", "wb") as f:
            f.write(data)
        return data.decode("utf-8-sig")


def build_quiz(text):
    lines = text.splitlines()

    blocks = {}
    current = None
    for ln in lines:
        m = re.match(r"#\s*Question\s+(\d+)\.", ln.strip())
        if m:
            current = int(m.group(1))
            blocks[current] = []
        elif current is not None:
            blocks[current].append(ln.strip())

    opt_re = re.compile(r"^([A-C])\.\s+(.*)$")
    questions = []

    for qnum in sorted(blocks):
        blines = [l for l in blocks[qnum] if l and not re.match(r"^_{5,}$", l)]
        if not blines:
            continue

        ca_idx = next(
            (i for i, ln in enumerate(blines) if ln.startswith("Correct answer:")), None
        )
        if ca_idx is None:
            raise ValueError(f"Question {qnum}: no 'Correct answer:' line")

        qtext_lines, options = [], {}
        for ln in blines[:ca_idx]:
            m = opt_re.match(ln)
            if m:
                options[m.group(1)] = m.group(2).strip()
            elif options:
                last = list(options)[-1]
                options[last] = options[last] + "\n" + ln
            else:
                qtext_lines.append(ln)

        correct_letter = re.match(
            r"^Correct answer:\s*([A-C])\b", blines[ca_idx]
        ).group(1)

        tail_lines = [
            re.sub(r"^Correct answer:\s*[A-C]\b\.?\s*", "", blines[ca_idx]).strip()
        ]
        tail_lines.extend(blines[ca_idx + 1:])
        cleaned = []
        for l in tail_lines:
            l = l.strip()
            if l.startswith("*") and not l.startswith("* "):
                l = "* " + l[1:]
            if l:
                cleaned.append(l)
        tail = " ".join(cleaned)

        feedback = {}
        parts = re.split(r"(?:^|\s)([A-C])\.\s*Feedback:\s*", tail)
        correct_fb = re.sub(r"^\s*Feedback:\s*", "", parts[0]).strip()
        correct_fb = re.sub(r"\s*Incorrect answers:\s*$", "", correct_fb).strip()
        feedback[correct_letter] = correct_fb
        for i in range(1, len(parts) - 1, 2):
            feedback[parts[i]] = parts[i + 1].strip()

        if set(options) != {"A", "B", "C"} or set(feedback) != {"A", "B", "C"}:
            raise ValueError(f"Question {qnum}: could not parse all options/feedback")

        questions.append({
            "type": "multichoice",
            "question": "\n".join(qtext_lines).strip(),
            "options": [
                {
                    "text": options[letter],
                    "correct": letter == correct_letter,
                    "feedback": feedback[letter],
                }
                for letter in ("A", "B", "C")
            ],
        })

    if len(questions) != 90:
        print(f"WARNING: expected 90 questions, parsed {len(questions)}", file=sys.stderr)

    return {
        "title": "2026 CFA Program Level I Mock Exam 2 - Session 1",
        "introduction": (
            "90 multiple-choice questions covering ethics and professional "
            "standards, quantitative methods, economics, financial reporting and "
            "analysis, corporate issuers, equity investments, and portfolio "
            "management, based on the 2026 CFA Program Level I curriculum."
        ),
        "questions": questions,
        "passPercentage": 80,
        "allowRetry": True,
        "randomizeQuestions": False,
    }


def main():
    quiz = build_quiz(load_text())
    with open(OUTPUT, "w", encoding="utf-8") as f:
        json.dump(quiz, f, indent=2, ensure_ascii=False)
    answers = " ".join(
        "ABC"[[o["correct"] for o in q["options"]].index(True)] for q in quiz["questions"]
    )
    print(f"Written {OUTPUT} with {len(quiz['questions'])} questions.")
    print("Answer key:", answers)


if __name__ == "__main__":
    main()
