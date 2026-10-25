#!/usr/bin/env python3
"""Offline pixel difference of a real UI screenshot against a target PNG.

Does not resize or alter either image; DPI, content, font, zoom and window
coordinates MUST match before a pixel-by-pixel claim is meaningful.
"""
from __future__ import annotations
import argparse
import json
from pathlib import Path
from PIL import Image, ImageChops, ImageEnhance

def main() -> int:
    parser = argparse.ArgumentParser(description="TVRTKE reference screenshot comparison")
    parser.add_argument("reference", type=Path)
    parser.add_argument("actual", type=Path)
    parser.add_argument("--diff", type=Path, default=Path("tvrtke-pixel-diff.png"))
    parser.add_argument("--tolerance", type=int, default=12)
    args = parser.parse_args()
    if not 0 <= args.tolerance <= 255:
        parser.error("tolerance must be 0..255")
    with Image.open(args.reference) as ref, Image.open(args.actual) as current:
        a = ref.convert("RGB")
        b = current.convert("RGB")
    if a.size != b.size:
        print(json.dumps({"result": "incomparable", "reason": "different pixel dimensions",
            "reference": list(a.size), "actual": list(b.size)}, ensure_ascii=False))
        return 2
    difference = ImageChops.difference(a, b)
    changed = 0
    mae_total = 0
    max_channel = 0
    data = list(difference.getdata())
    for r, g, bl in data:
        mae_total += r + g + bl
        max_channel = max(max_channel, r, g, bl)
        if max(r, g, bl) > args.tolerance:
            changed += 1
    altered = ImageEnhance.Contrast(difference).enhance(3)
    args.diff.parent.mkdir(parents=True, exist_ok=True)
    altered.save(args.diff)
    count = a.width * a.height
    print(json.dumps({
        "result": "comparison", "size": [a.width, a.height],
        "mean_absolute_channel_difference": round(mae_total / (count * 3), 3),
        "changed_pixels": changed, "changed_percent": round(100 * changed / count, 4),
        "max_channel_difference": max_channel,
        "threshold_per_channel": args.tolerance,
        "diff_png": str(args.diff),
    }, ensure_ascii=False, indent=2))
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
