#!/usr/bin/env python3
"""Render structured Compose log lines for local diagnosis; preserve plain lines."""

import json
import sys


for line in sys.stdin:
    try:
        record = json.loads(line)
    except json.JSONDecodeError:
        print(line, end="")
        continue
    if not isinstance(record, dict) or "level_name" not in record:
        print(line, end="")
        continue
    context = record.get("context", {})
    if not isinstance(context, dict):
        print(line, end="")
        continue
    event = context.get("event_name", record.get("message", ""))
    print(f"{record.get('datetime', '')} {record['level_name']:<8} {event}")
    for key, value in context.items():
        if key not in {"event_name", "environment", "service"} and value is not None:
            print(f"  {key}: {value}")
