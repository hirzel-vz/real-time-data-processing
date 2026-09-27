#!/usr/bin/env python3
"""
JSON to H5P Converter
Converts quiz JSON files (containing a "questions" array) to H5P Question
Set packages. Run it in the directory holding your quiz JSON files:

    python json_to_h5p.py

Branching scenario JSON files (with a "nodes" array) are ignored by this
script; convert those with json_to_h5p_branching.py instead.
"""

import json
import zipfile
import os
import tempfile
import shutil
from typing import Dict, List, Any
import uuid


class H5PConverter:
    def __init__(self):
        self.h5p_libraries = {
            "question_set": {
                "machineName": "H5P.QuestionSet",
                "majorVersion": 1,
                "minorVersion": 20
            },
            "branching_scenario": {
                "machineName": "H5P.BranchingScenario", 
                "majorVersion": 1,
                "minorVersion": 8
            }
        }
    
    def convert_quiz_to_h5p(self, json_file_path: str, output_path: str, title: str = "Converted Content") -> None:
        """Convert a quiz JSON to H5P Question Set format."""
        with open(json_file_path, 'r', encoding='utf-8') as f:
            quiz_data = json.load(f)
        
        h5p_content = self._build_question_set_content(quiz_data)
        self._create_h5p_package(h5p_content, output_path, "question_set", title)
    
    def convert_branching_scenario_to_h5p(self, json_file_path: str, output_path: str, title: str = "Converted Content") -> None:
        """Convert a branching scenario JSON to H5P Branching Scenario format."""
        with open(json_file_path, 'r', encoding='utf-8') as f:
            scenario_data = json.load(f)
        
        h5p_content = self._build_branching_scenario_content(scenario_data)
        self._create_h5p_package(h5p_content, output_path, "branching_scenario", title)
    
    def _build_question_set_content(self, quiz_data: Dict[str, Any]) -> Dict[str, Any]:
        """Build H5P Question Set content structure."""
        questions = []
        
        for q in quiz_data["questions"]:
            if q["type"] == "multichoice":
                question = self._convert_multichoice_question(q)
                questions.append(question)
            elif q["type"] == "multiselect":
                question = self._convert_multiselect_question(q)
                questions.append(question)
            elif q["type"] == "shortanswer":
                question = self._convert_shortanswer_question(q)
                questions.append(question)
        
        content = {
            "introPage": {
                "showIntroPage": True,
                "startButtonText": "Start Quiz",
                "introduction": quiz_data.get("introduction", "")
            },
            "progressType": "textual",
            "passPercentage": quiz_data.get("passPercentage", 50),
            "questions": questions,
            "texts": {
                "prevButton": "Previous question",
                "nextButton": "Next question", 
                "finishButton": "Finish",
                "submitButton": "Submit"
            },
            "endGame": {
                "showResultPage": True,
                "showSolutionButton": quiz_data.get("showSolutions", True),
                "showRetryButton": quiz_data.get("allowRetry", True),
                "noResultMessage": "Finished",
                "message": "Your result:",
                "overallFeedback": self._convert_overall_feedback(quiz_data.get("overallFeedback", []))
            },
            "override": {
                "checkButton": "Check",
                "showSolutionButton": "Show solution",
                "tryAgainButton": "Retry",
                "showSolutionButton": "Show solution"
            },
            "disableBackwardsNavigation": False,
            "randomizeQuestions": quiz_data.get("randomizeQuestions", False)
        }
        
        return content
    
    def _convert_multichoice_question(self, question: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a multiple choice question to H5P format."""
        answers = []
        for i, option in enumerate(question["options"]):
            answers.append({
                "text": f"<div>{option['text']}</div>",
                "correct": option["correct"],
                "tipsAndFeedback": {
                    "tip": "",
                    "chosenFeedback": option.get("feedback", ""),
                    "notChosenFeedback": ""
                }
            })
        
        return {
            "library": "H5P.MultiChoice 1.16",
            "params": {
                "media": {
                    "disableImageZooming": False
                },
                "answers": answers,
                "overallFeedback": [
                    {"from": 0, "to": 100}
                ],
                "behaviour": {
                    "enableRetry": True,
                    "enableSolutionsButton": True,
                    "enableCheckButton": True,
                    "type": "auto",
                    "singlePoint": False,
                    "randomAnswers": True,
                    "showSolutionsRequiresInput": True,
                    "confirmCheckDialog": False,
                    "confirmRetryDialog": False,
                    "autoCheck": False,
                    "passPercentage": 100,
                    "showScorePoints": True
                },
                "UI": {
                    "checkAnswerButton": "Check",
                    "submitAnswerButton": "Submit",
                    "showSolutionButton": "Show solution",
                    "tryAgainButton": "Retry",
                    "tipsLabel": "Show tip",
                    "scoreBarLabel": "You got :num out of :total points",
                    "tipAvailable": "Tip available",
                    "feedbackAvailable": "Feedback available",
                    "readFeedback": "Read feedback",
                    "wrongAnswer": "Wrong answer",
                    "correctAnswer": "Correct answer",
                    "shouldCheck": "Should have been checked",
                    "shouldNotCheck": "Should not have been checked",
                    "noInput": "Please answer before viewing the solution",
                    "a11yCheck": "Check the answers. The responses will be marked as correct, incorrect, or unanswered.",
                    "a11yShowSolution": "Show the solution. The task will be marked with its correct solution.",
                    "a11yRetry": "Retry the task. Reset all responses and start the task over again."
                },
                "question": f"<p>{question['question']}</p>"
            },
            "subContentId": str(uuid.uuid4()),
            "metadata": {
                "contentType": "Multiple Choice",
                "license": "U",
                "title": "Untitled Multiple Choice"
            }
        }
    
    def _convert_multiselect_question(self, question: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a multi-select question to H5P format."""
        answers = []
        for option in question["options"]:
            answers.append({
                "text": f"<div>{option['text']}</div>",
                "correct": option["correct"],
                "tipsAndFeedback": {
                    "tip": "",
                    "chosenFeedback": "",
                    "notChosenFeedback": ""
                }
            })
        
        return {
            "library": "H5P.MultiChoice 1.16",
            "params": {
                "media": {
                    "disableImageZooming": False
                },
                "answers": answers,
                "overallFeedback": [
                    {"from": 0, "to": 100}
                ],
                "behaviour": {
                    "enableRetry": True,
                    "enableSolutionsButton": True,
                    "enableCheckButton": True,
                    "type": "auto",
                    "singlePoint": False,
                    "randomAnswers": False,
                    "showSolutionsRequiresInput": True,
                    "confirmCheckDialog": False,
                    "confirmRetryDialog": False,
                    "autoCheck": False,
                    "passPercentage": 100,
                    "showScorePoints": True
                },
                "UI": {
                    "checkAnswerButton": "Check",
                    "submitAnswerButton": "Submit", 
                    "showSolutionButton": "Show solution",
                    "tryAgainButton": "Retry",
                    "tipsLabel": "Show tip",
                    "scoreBarLabel": "You got :num out of :total points",
                    "tipAvailable": "Tip available",
                    "feedbackAvailable": "Feedback available",
                    "readFeedback": "Read feedback",
                    "wrongAnswer": "Wrong answer",
                    "correctAnswer": "Correct answer",
                    "shouldCheck": "Should have been checked",
                    "shouldNotCheck": "Should not have been checked",
                    "noInput": "Please answer before viewing the solution"
                },
                "question": f"<p>{question['question']}</p>"
            },
            "subContentId": str(uuid.uuid4()),
            "metadata": {
                "contentType": "Multiple Choice",
                "license": "U", 
                "title": "Untitled Multiple Choice"
            }
        }
    
    def _convert_shortanswer_question(self, question: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a short answer question to H5P format using Fill in the Blanks."""
        keywords = question.get("keywords", [])
        feedback = question.get("feedback", {})
        
        return {
            "library": "H5P.Blanks 1.14",
            "params": {
                "content": f"<p>{question['question']}</p><p>*{'/'.join(keywords)}*</p>",
                "overallFeedback": [
                    {"from": 0, "to": 100}
                ],
                "showSolutions": "Show solution",
                "tryAgain": "Retry",
                "checkAnswer": "Check",
                "submitAnswer": "Submit",
                "notFilledOut": "Please fill in all blanks to view solution",
                "answerIsCorrect": "':ans' is correct",
                "answerIsWrong": "':ans' is wrong",
                "answeredCorrectly": "Answered correctly",
                "answeredIncorrectly": "Answered incorrectly",
                "solutionLabel": "Correct answer:",
                "inputLabel": "Blank input @num of @total",
                "inputHasTipLabel": "Tip available",
                "tipLabel": "Tip",
                "behaviour": {
                    "enableRetry": True,
                    "enableSolutionsButton": True,
                    "enableCheckButton": True,
                    "autoCheck": False,
                    "caseSensitive": False,
                    "showSolutionsRequiresInput": True,
                    "separateLines": False,
                    "confirmCheckDialog": False,
                    "confirmRetryDialog": False,
                    "acceptSpellingErrors": True
                },
                "confirmCheck": {
                    "header": "Finish ?",
                    "body": "Are you sure you wish to finish ?",
                    "cancelLabel": "Cancel",
                    "confirmLabel": "Finish"
                },
                "confirmRetry": {
                    "header": "Retry ?",
                    "body": "Are you sure you wish to retry ?",
                    "cancelLabel": "Cancel", 
                    "confirmLabel": "Confirm"
                }
            },
            "subContentId": str(uuid.uuid4()),
            "metadata": {
                "contentType": "Fill in the Blanks",
                "license": "U",
                "title": "Untitled Fill in the Blanks"
            }
        }
    
    def _convert_overall_feedback(self, feedback_ranges: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Convert overall feedback ranges to H5P format."""
        h5p_feedback = []
        for feedback in feedback_ranges:
            h5p_feedback.append({
                "from": feedback["from"],
                "to": feedback["to"],
                "feedback": feedback["feedback"]
            })
        return h5p_feedback
    
    def _build_branching_scenario_content(self, scenario_data: Dict[str, Any]) -> Dict[str, Any]:
        """Build H5P Branching Scenario content structure."""
        content = []
        
        for node in scenario_data["nodes"]:
            content_item = self._convert_scenario_node(node)
            content.append(content_item)
        
        return {
            "branchingScenario": {
                "content": content,
                "startScreen": {
                    "startScreenTitle": scenario_data["title"],
                    "startScreenSubtitle": scenario_data.get("introduction", "")
                },
                "endScreens": [
                    {
                        "endScreenTitle": "Completed",
                        "endScreenSubtitle": "You have completed the scenario",
                        "contentId": -1
                    }
                ],
                "scoringOptionForAlternatives": "same-score-for-all"
            }
        }
    
    def _convert_scenario_node(self, node: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a scenario node to H5P format."""
        if node["type"] == "content":
            return {
                "type": {
                    "library": "H5P.AdvancedText 1.1",
                    "params": {
                        "text": f"<p>{node['content']}</p>"
                    },
                    "subContentId": str(uuid.uuid4()),
                    "metadata": {
                        "contentType": "Text",
                        "license": "U",
                        "title": "Untitled Text"
                    }
                },
                "showContentTitle": False,
                "nextContentId": self._get_next_content_id(node),
                "feedback": {
                    "title": "",
                    "subtitle": "",
                    "image": {},
                    "endScreenScore": 0
                }
            }
        elif node["type"] == "branch":
            return {
                "type": {
                    "library": "H5P.AdvancedText 1.1", 
                    "params": {
                        "text": f"<p>{node['content']}</p>"
                    },
                    "subContentId": str(uuid.uuid4()),
                    "metadata": {
                        "contentType": "Text",
                        "license": "U",
                        "title": "Untitled Text"
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
        elif node["type"] == "quiz":
            question_content = self._convert_scenario_quiz(node)
            return {
                "type": question_content,
                "showContentTitle": False,
                "nextContentId": self._get_next_content_id(node),
                "feedback": {
                    "title": "",
                    "subtitle": "",
                    "image": {},
                    "endScreenScore": 1
                }
            }
        
        return {}
    
    def _convert_scenario_quiz(self, quiz_node: Dict[str, Any]) -> Dict[str, Any]:
        """Convert a quiz node within branching scenario."""
        answers = []
        for option in quiz_node["options"]:
            answers.append({
                "text": f"<div>{option['text']}</div>",
                "correct": option["correct"],
                "tipsAndFeedback": {
                    "tip": "",
                    "chosenFeedback": option.get("feedback", ""),
                    "notChosenFeedback": ""
                }
            })
        
        return {
            "library": "H5P.MultiChoice 1.16",
            "params": {
                "media": {
                    "disableImageZooming": False
                },
                "answers": answers,
                "overallFeedback": [
                    {"from": 0, "to": 100}
                ],
                "behaviour": {
                    "enableRetry": False,
                    "enableSolutionsButton": True,
                    "enableCheckButton": True,
                    "type": "auto",
                    "singlePoint": False,
                    "randomAnswers": False,
                    "showSolutionsRequiresInput": True,
                    "confirmCheckDialog": False,
                    "confirmRetryDialog": False,
                    "autoCheck": False,
                    "passPercentage": 100,
                    "showScorePoints": True
                },
                "question": f"<p>{quiz_node['question']}</p>"
            },
            "subContentId": str(uuid.uuid4()),
            "metadata": {
                "contentType": "Multiple Choice",
                "license": "U",
                "title": "Untitled Multiple Choice"
            }
        }
    
    def _get_next_content_id(self, node: Dict[str, Any]) -> int:
        """Get the next content ID for navigation."""
        if "next" in node:
            return 1  # Simplified - would need proper ID mapping
        elif "choices" in node and node["choices"]:
            return 1  # Simplified
        return -1
    
    def _create_h5p_package(self, content: Dict[str, Any], output_path: str, content_type: str, title: str = "Converted Content") -> None:
        """Create the H5P package as a ZIP file."""
        with tempfile.TemporaryDirectory() as temp_dir:
            # Create h5p.json
            h5p_json = {
                "title": title,
                "language": "en",
                "mainLibrary": self.h5p_libraries[content_type]["machineName"],
                "embedTypes": ["div"],
                "license": "U",
                "preloadedDependencies": [
                    {
                        "machineName": self.h5p_libraries[content_type]["machineName"],
                        "majorVersion": self.h5p_libraries[content_type]["majorVersion"],
                        "minorVersion": self.h5p_libraries[content_type]["minorVersion"]
                    }
                ]
            }
            
            with open(os.path.join(temp_dir, "h5p.json"), "w", encoding="utf-8") as f:
                json.dump(h5p_json, f, indent=2)
            
            # Create content/content.json
            content_dir = os.path.join(temp_dir, "content")
            os.makedirs(content_dir)
            
            with open(os.path.join(content_dir, "content.json"), "w", encoding="utf-8") as f:
                json.dump(content, f, indent=2)
            
            # Create the ZIP file
            with zipfile.ZipFile(output_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
                for root, dirs, files in os.walk(temp_dir):
                    for file in files:
                        file_path = os.path.join(root, file)
                        arc_name = os.path.relpath(file_path, temp_dir)
                        zipf.write(file_path, arc_name)


def main():
    """Main function to convert JSON files to H5P."""
    converter = H5PConverter()

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

        if "questions" in data:
            print(f"Converting quiz {file_name} to {output_path}...")
            converter.convert_quiz_to_h5p(file_name, output_path, data.get("title", file_name))
            print(f"Quiz conversion completed: {output_path}")
        elif "nodes" in data:
            print(f"Skipping {file_name}: branching scenario detected (use json_to_h5p_branching.py)")
        else:
            print(f"Skipping {file_name}: no 'questions' key found")


if __name__ == "__main__":
    main()