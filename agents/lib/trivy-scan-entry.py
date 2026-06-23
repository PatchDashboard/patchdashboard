#!/usr/bin/env python3
"""Run Trivy and append one scan entry to a JSONL file."""

import json
import os
import subprocess
import sys


def parse_vulns(report: str, parser_path: str) -> list:
    if not report.strip():
        return []
    result = subprocess.run(
        [sys.executable, parser_path],
        input=report,
        capture_output=True,
        text=True,
        check=False,
    )
    if result.returncode != 0 or not result.stdout.strip():
        return []
    return json.loads(result.stdout)


def append_entry(output_path: str, runtime: str, runtime_id: str, name: str, image: str, vulns: list) -> None:
    entry = {
        "runtime": runtime,
        "runtime_id": runtime_id,
        "name": name,
        "image": image,
        "status": "scanned",
        "vulnerabilities": vulns,
    }
    with open(output_path, "a", encoding="utf-8") as handle:
        handle.write(json.dumps(entry) + "\n")


def main() -> int:
    if len(sys.argv) < 8:
        print("usage: trivy-scan-entry.py <output.jsonl> <parser.py> <runtime> <runtime_id> <name> <image> <trivy> <args...>", file=sys.stderr)
        return 1

    output_path = sys.argv[1]
    parser_path = sys.argv[2]
    runtime = sys.argv[3]
    runtime_id = sys.argv[4]
    name = sys.argv[5]
    image = sys.argv[6]
    trivy_cmd = sys.argv[7:]

    result = subprocess.run(trivy_cmd, capture_output=True, text=True, check=False)
    report = result.stdout or ""
    vulns = parse_vulns(report, parser_path)
    append_entry(output_path, runtime, runtime_id, name, image, vulns)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
