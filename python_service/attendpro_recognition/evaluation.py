"""Evaluate AttendPro recognition using separate reference and probe image sets.

The manifests are CSV files. Reference rows require ``person_id,path``; probe
rows require ``person_id,path`` and may use ``unknown`` as the person ID.  The
same image must never appear in both manifests.
"""

from __future__ import annotations

import argparse
import csv
import json
import statistics
import time
from collections import defaultdict
from pathlib import Path
from typing import Any

from .face_engine import FaceEngine
from .face_index import FaceIndex
from .model_manager import ModelManager
from .settings import Settings


def read_manifest(path: Path) -> list[dict[str, str]]:
    with path.open(newline="", encoding="utf-8") as handle:
        rows = list(csv.DictReader(handle))
    if not rows or not {"person_id", "path"}.issubset(rows[0]):
        raise ValueError(f"{path} must contain person_id,path CSV columns.")
    for row in rows:
        row["path"] = str((path.parent / row["path"]).resolve()) if not Path(row["path"]).is_absolute() else row["path"]
    return rows


def build_engine(settings: Settings):
    if settings.face_backend == "insightface":
        from .insightface_engine import InsightFaceEngine

        return InsightFaceEngine(
            settings.model_dir,
            settings.insightface_pack,
            settings.insightface_det_size,
            settings.insightface_ctx_id,
            settings.minimum_face_ratio,
            min_face_width=settings.min_face_width, min_face_height=settings.min_face_height,
            min_blur_score=settings.min_blur_score, min_brightness=settings.min_brightness,
            max_brightness=settings.max_brightness, max_roll=settings.max_roll,
            max_yaw_proxy=settings.max_yaw_proxy, max_pitch_proxy=settings.max_pitch_proxy,
        )
    if settings.face_backend == "dlib":
        from .dlib_engine import DlibEngine

        return DlibEngine(
            settings.dlib_detector, settings.dlib_upsample, settings.dlib_jitters,
            settings.minimum_face_ratio,
            min_face_width=settings.min_face_width, min_face_height=settings.min_face_height,
            min_blur_score=settings.min_blur_score, min_brightness=settings.min_brightness,
            max_brightness=settings.max_brightness, max_roll=settings.max_roll,
            max_yaw_proxy=settings.max_yaw_proxy, max_pitch_proxy=settings.max_pitch_proxy,
        )
    return FaceEngine(
        ModelManager(settings.model_dir), settings.detection_score, settings.minimum_face_ratio,
        min_face_width=settings.min_face_width, min_face_height=settings.min_face_height,
        min_blur_score=settings.min_blur_score, min_brightness=settings.min_brightness,
        max_brightness=settings.max_brightness, max_roll=settings.max_roll,
        max_yaw_proxy=settings.max_yaw_proxy, max_pitch_proxy=settings.max_pitch_proxy,
    )


def load_references(rows: list[dict[str, str]], engine) -> tuple[FaceIndex, list[dict[str, str]]]:
    model_name = getattr(engine, "MODEL_NAME", "opencv_sface_2021dec")
    records: list[dict[str, Any]] = []
    rejected: list[dict[str, str]] = []
    person_keys: dict[str, int] = {}
    for sequence, row in enumerate(rows, start=1):
        try:
            extracted = engine.extract(Path(row["path"]).read_bytes())
            person_key = person_keys.setdefault(row["person_id"], len(person_keys) + 1)
            records.append({
                "profile_id": sequence, "person_id": person_key, "institution_id": row["person_id"],
                "full_name": row.get("full_name") or row["person_id"], "person_type": "student",
                "model": model_name, "dimensions": len(extracted.embedding), "embedding": extracted.embedding, "version": 1,
            })
        except Exception as error:  # report bad data instead of hiding it
            rejected.append({"path": row["path"], "reason": str(error)})
    index = FaceIndex(
        model_name=model_name,
        metric=getattr(engine, "METRIC", "cosine"),
    )
    index.replace(records)
    return index, rejected


def evaluate(index: FaceIndex, rows: list[dict[str, str]], engine, threshold: float, margin: float) -> dict[str, Any]:
    counts = defaultdict(int)
    latencies: list[float] = []
    conditions: dict[str, defaultdict[str, int]] = defaultdict(lambda: defaultdict(int))
    confusions: list[dict[str, Any]] = []

    for row in rows:
        expected = row["person_id"].strip()
        unknown = expected.lower() in {"", "unknown", "none", "null"}
        condition = row.get("condition") or "unspecified"
        started = time.perf_counter()
        try:
            extracted = engine.extract(Path(row["path"]).read_bytes())
            match = index.best_match(extracted.embedding)
            accepted = bool(match and match.confidence >= threshold and match.margin >= margin)
            predicted = match.profile.institution_id if accepted and match else None
            latency = time.perf_counter() - started
            latencies.append(latency)
        except Exception as error:
            accepted, predicted, match = False, None, None
            counts["quality_or_processing_rejected"] += 1
            row = {**row, "error": str(error)}

        bucket = conditions[condition]
        if unknown:
            counts["unknown_attempts"] += 1
            bucket["attempts"] += 1
            if accepted:
                counts["false_accepts"] += 1
                bucket["false_accepts"] += 1
            else:
                counts["correct_unknown_rejections"] += 1
                bucket["correct_rejections"] += 1
            continue

        counts["registered_attempts"] += 1
        bucket["attempts"] += 1
        if predicted == expected:
            counts["correct_identifications"] += 1
            bucket["correct"] += 1
        elif accepted:
            counts["wrong_identifications"] += 1
            bucket["wrong"] += 1
            confusions.append({
                "actual": expected, "predicted": predicted, "path": row["path"],
                "confidence": round(match.confidence, 6) if match else None,
                "second_best_confidence": round(match.second_confidence, 6) if match and match.second_confidence is not None else None,
                "condition": condition,
            })
        else:
            counts["registered_rejections"] += 1
            bucket["rejected"] += 1

    registered = counts["registered_attempts"]
    unknown_attempts = counts["unknown_attempts"]
    accepted_total = counts["correct_identifications"] + counts["wrong_identifications"] + counts["false_accepts"]
    precision = counts["correct_identifications"] / accepted_total if accepted_total else 0.0
    recall = counts["correct_identifications"] / registered if registered else 0.0
    return {
        "threshold": threshold, "minimum_margin": margin,
        "model": getattr(engine, "MODEL_NAME", "opencv_sface_2021dec"), "counts": dict(counts),
        "identification_accuracy": counts["correct_identifications"] / registered if registered else None,
        "false_accept_rate": counts["false_accepts"] / unknown_attempts if unknown_attempts else None,
        "false_reject_rate": counts["registered_rejections"] / registered if registered else None,
        "unknown_rejection_rate": counts["correct_unknown_rejections"] / unknown_attempts if unknown_attempts else None,
        "precision": precision, "recall": recall, "f1": 2 * precision * recall / (precision + recall) if precision + recall else 0.0,
        "latency_seconds": {
            "average": statistics.mean(latencies) if latencies else None,
            "median": statistics.median(latencies) if latencies else None,
            "p95": sorted(latencies)[max(0, int(len(latencies) * .95) - 1)] if latencies else None,
        },
        "conditions": {name: dict(values) for name, values in conditions.items()}, "confusions": confusions,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Evaluate AttendPro with held-out face images.")
    parser.add_argument("--references", required=True, type=Path, help="CSV: person_id,path for enrollment/reference images")
    parser.add_argument("--probes", required=True, type=Path, help="CSV: person_id,path[,condition] for a separate test session")
    parser.add_argument("--thresholds", default="0.55,0.60,0.65,0.70,0.75")
    parser.add_argument("--margin", type=float, default=Settings.load().minimum_margin)
    parser.add_argument("--output", type=Path, help="Optional JSON report path")
    args = parser.parse_args()

    settings = Settings.load()
    engine = build_engine(settings)
    index, rejected_references = load_references(read_manifest(args.references), engine)
    if not index.count:
        raise SystemExit("No valid reference images were loaded; fix the reference capture data.")
    reports = [evaluate(index, read_manifest(args.probes), engine, float(value.strip()), args.margin) for value in args.thresholds.split(",")]
    output = {"reference_profiles_loaded": index.count, "rejected_references": rejected_references, "reports": reports}
    print(json.dumps(output, indent=2))
    if args.output:
        args.output.write_text(json.dumps(output, indent=2), encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
