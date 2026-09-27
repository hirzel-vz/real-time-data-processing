#!/usr/bin/env python3
"""
JSON to H5P Branching Scenario Converter
Converts branching scenario JSON files (containing a "nodes" array) to
H5P Branching Scenario packages. Run it in the directory holding your
scenario JSON files:

    python json_to_h5p_branching.py

Quiz JSON files (with a "questions" array) are ignored by this script;
convert those with json_to_h5p.py instead.
"""

import json
import os

from json_to_h5p import H5PConverter


def main():
    """Convert branching scenario JSON files in the current directory to H5P."""
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

        if "nodes" in data:
            print(f"Converting branching scenario {file_name} to {output_path}...")
            converter.convert_branching_scenario_to_h5p(file_name, output_path, data.get("title", file_name))
            print(f"Branching scenario conversion completed: {output_path}")
        else:
            print(f"Skipping {file_name}: no 'nodes' key found (quiz files are handled by json_to_h5p.py)")


if __name__ == "__main__":
    main()
