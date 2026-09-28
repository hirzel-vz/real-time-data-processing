#!/usr/bin/env python3
"""
JSON to H5P Branching Scenario Converter
Converts branching scenario JSON files (containing a "nodes" array) to
H5P Branching Scenario packages (H5P.BranchingScenario 1.10).

Node JSON format:
  - "type": "content" -> text screen (uses H5P.AdvancedText 1.1)
  - "type": "branch" -> branching question (uses H5P.BranchingQuestion 1.0)
  - "type": "quiz"   -> treated as a branching question

Navigation rules (matching the H5P player):
  - "next": "<node_id>" on a node, or "next": "<node_id>" inside a choice,
    points at the id of another node (the first node in "nodes" is index 0)
  - "end": true on a choice (or a node) ends the scenario at the default
    end screen
  - A node with an empty "choices" list is treated as an end point

Run it in the directory holding your scenario JSON files:

    python json2h5p_branching.py

Quiz JSON files (with a "questions" array) are ignored by this script;
convert those with json2h5p_question_set.py instead.
"""

import json
import os

from json2h5p_question_set import H5PConverter


class BranchingScenarioConverter(H5PConverter):
    BRANCHING_SCENARIO_VERSION = "1.10"
    ADVANCED_TEXT_VERSION = "1.1"
    BRANCHING_QUESTION_VERSION = "1.0"

    def convert(self, scenario_data, title):
        content = []
        id_to_index = {}
        node_ids = [node.get("id") for node in scenario_data["nodes"]]

        for index, node_id in enumerate(node_ids):
            id_to_index[node_id] = index

        for index, node in enumerate(scenario_data["nodes"]):
            content.append(self._convert_node(node, node_ids, id_to_index))

        return {
            "branchingScenario": {
                "title": title,
                "startScreen": {
                    "startScreenTitle": scenario_data.get("title", title),
                    "startScreenSubtitle": scenario_data.get("introduction", "")
                },
                "endScreens": [
                    {
                        "endScreenTitle": "Completed",
                        "endScreenSubtitle": "You have completed the scenario",
                        "endScreenScore": 0,
                        "contentId": -1
                    }
                ],
                "content": content,
                "scoringOption": "no-score",
                "behaviour": {
                    "enableBackwardsNavigation": False,
                    "forceContentFinished": False,
                    "randomizeBranchingQuestions": False
                }
            }
        }

    def _convert_node(self, node, node_ids, id_to_index):
        node_type = node.get("type", "content")
        choices = node.get("choices", []) or []

        if node_type in ("branch", "quiz") or len(choices) > 1:
            return self._convert_branching_question_node(node, id_to_index)
        return self._convert_text_node(node, id_to_index)

    def _next_id(self, ref, id_to_index, node):
        if ref in (None, "", "end"):
            return -1
        if isinstance(ref, str) and ref.lstrip("-").isdigit():
            return int(ref)
        if ref in id_to_index:
            return id_to_index[ref]
        print(f"  warning: unknown node id '{ref}' in node '{node.get('id', '?')}', ending scenario")
        return -1

    def _convert_text_node(self, node, id_to_index):
        choices = node.get("choices", []) or []
        if choices:
            next_id = self._next_id(choices[0].get("next"), id_to_index, node)
        elif "next" in node:
            next_id = self._next_id(node.get("next"), id_to_index, node)
        else:
            next_id = -1

        return {
            "type": {
                "library": f"H5P.AdvancedText {self.ADVANCED_TEXT_VERSION}",
                "params": {
                    "text": f"<p>{node['content']}</p>"
                },
                "subContentId": self._new_uuid(),
                "metadata": {
                    "contentType": "Text",
                    "license": "U",
                    "title": node.get("title", "Untitled Text")
                }
            },
            "showContentTitle": False,
            "nextContentId": next_id,
            "feedback": {
                "title": "",
                "subtitle": "",
                "image": {},
                "endScreenScore": 0
            }
        }

    def _convert_branching_question_node(self, node, id_to_index):
        question_text = node.get("question", node.get("content", ""))
        alternatives = []

        if node.get("choices"):
            for choice in node["choices"]:
                alternatives.append({
                    "text": choice["text"],
                    "nextContentId": self._next_id(choice.get("next"), id_to_index, node),
                    "feedback": {
                        "title": "",
                        "subtitle": choice.get("feedback", ""),
                        "image": {},
                        "endScreenScore": 0
                    }
                })
        else:
            for option in node.get("options", []):
                alternatives.append({
                    "text": option["text"],
                    "nextContentId": self._next_id(node.get("next"), id_to_index, node),
                    "feedback": {
                        "title": "",
                        "subtitle": option.get("feedback", ""),
                        "image": {},
                        "endScreenScore": 0
                    }
                })

        return {
            "type": {
                "library": f"H5P.BranchingQuestion {self.BRANCHING_QUESTION_VERSION}",
                "params": {
                    "branchingQuestion": {
                        "question": f"<p>{question_text}</p>",
                        "alternatives": alternatives
                    }
                },
                "subContentId": self._new_uuid(),
                "metadata": {
                    "contentType": "Branching Question",
                    "license": "U",
                    "title": node.get("title", "Untitled BranchingQuestion")
                }
            },
            "showContentTitle": False,
            "nextContentId": -1,
            "feedback": {
                "title": "",
                "subtitle": "",
                "image": {},
                "endScreenScore": 0
            }
        }

    def _new_uuid(self):
        import uuid
        return str(uuid.uuid4())


def main():
    """Convert branching scenario JSON files in the current directory to H5P."""
    converter = BranchingScenarioConverter()

    for file_name in sorted(os.listdir(".")):
        if not file_name.endswith(".json"):
            continue

        with open(file_name, "r", encoding="utf-8") as f:
            try:
                data = json.load(f)
            except json.JSONDecodeError as e:
                print(f"Skipping {file_name}: invalid JSON ({e})")
                continue

        output_path = os.path.splitext(file_name)[0] + ".h5p"

        if "nodes" in data:
            print(f"Converting branching scenario {file_name} to {output_path}...")
            content = converter.convert(data, data.get("title", file_name))
            converter._create_h5p_package(
                content,
                output_path,
                "branching_scenario",
                data.get("title", file_name)
            )
            print(f"Branching scenario conversion completed: {output_path}")
        else:
            print(f"Skipping {file_name}: no 'nodes' key found (quiz files are handled by json2h5p_question_set.py)")


if __name__ == "__main__":
    main()
