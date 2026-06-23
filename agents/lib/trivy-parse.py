#!/usr/bin/env python3
"""Parse Trivy JSON output into PatchDashboard vulnerability records."""

import json
import sys


def parse_report(raw: str) -> list[dict]:
    if not raw.strip():
        return []

    data = json.loads(raw)
    results = []
    for result in data.get("Results", []):
        for vuln in result.get("Vulnerabilities") or []:
            cve_id = vuln.get("VulnerabilityID")
            if not cve_id:
                continue
            results.append(
                {
                    "cve_id": cve_id,
                    "severity": vuln.get("Severity", "UNKNOWN"),
                    "package": vuln.get("PkgName"),
                    "installed_version": vuln.get("InstalledVersion"),
                    "fixed_version": vuln.get("FixedVersion"),
                    "title": vuln.get("Title"),
                }
            )
    return results


if __name__ == "__main__":
    print(json.dumps(parse_report(sys.stdin.read())))
