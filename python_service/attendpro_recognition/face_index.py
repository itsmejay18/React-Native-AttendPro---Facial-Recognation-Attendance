from __future__ import annotations

import threading
from dataclasses import dataclass
from typing import Any

import numpy as np

from .face_engine import MODEL_NAME


@dataclass(frozen=True, slots=True)
class FaceProfile:
    profile_id: int
    person_id: int
    institution_id: str
    full_name: str
    person_type: str
    embedding: np.ndarray
    version: int


@dataclass(frozen=True, slots=True)
class FaceMatch:
    profile: FaceProfile
    similarity: float
    confidence: float
    second_profile: FaceProfile | None = None
    second_similarity: float | None = None
    second_confidence: float | None = None

    @property
    def margin(self) -> float:
        return self.confidence - (self.second_confidence or 0.0)


class FaceIndex:
    """In-memory index. ``model_name=None`` accepts any model.

    ``metric`` selects the comparison: ``cosine`` (SFace backend,
    confidence = (similarity + 1) / 2) or ``euclidean`` (dlib backend,
    confidence = 1 - distance, matching face_recognition's distance
    semantics where 0.6 is the classic match boundary).
    """

    def __init__(self, model_name: str | None = MODEL_NAME, metric: str = "cosine"):
        self._model_name = model_name
        self._metric = metric if metric in {"cosine", "euclidean"} else "cosine"
        self._profiles: tuple[FaceProfile, ...] = ()
        self._matrix = np.empty((0, 128), dtype=np.float32)
        self._lock = threading.RLock()

    @property
    def count(self) -> int:
        with self._lock:
            return len(self._profiles)

    def replace(self, records: list[dict[str, Any]]) -> int:
        profiles: list[FaceProfile] = []
        vectors: list[np.ndarray] = []

        for record in records:
            if self._model_name is not None and record.get("model") != self._model_name:
                continue
            vector = np.asarray(record.get("embedding", []), dtype=np.float32).reshape(-1)
            if vector.size != int(record.get("dimensions", 0)) or vector.size == 0 or not np.all(np.isfinite(vector)):
                continue
            norm = float(np.linalg.norm(vector))
            if norm <= 0:
                continue
            vector = vector / norm
            profiles.append(FaceProfile(
                profile_id=int(record.get("profile_id") or record["person_id"]),
                person_id=int(record["person_id"]),
                institution_id=str(record["institution_id"]),
                full_name=str(record["full_name"]),
                person_type=str(record["person_type"]),
                embedding=vector,
                version=int(record.get("version", 1)),
            ))
            vectors.append(vector)

        dimension = vectors[0].size if vectors else 128
        compatible = [(profile, vector) for profile, vector in zip(profiles, vectors) if vector.size == dimension]
        with self._lock:
            self._profiles = tuple(profile for profile, _ in compatible)
            self._matrix = np.vstack([vector for _, vector in compatible]).astype(np.float32) \
                if compatible else np.empty((0, dimension), dtype=np.float32)

        return len(compatible)

    def upsert(self, record: dict[str, Any]) -> int:
        if self._model_name is not None and record.get("model") != self._model_name:
            return self.count

        vector = np.asarray(record.get("embedding", []), dtype=np.float32).reshape(-1)
        if vector.size != int(record.get("dimensions", 0)) or vector.size == 0 or not np.all(np.isfinite(vector)):
            return self.count

        norm = float(np.linalg.norm(vector))
        if norm <= 0:
            return self.count
        vector = vector / norm

        profile = FaceProfile(
            profile_id=int(record.get("profile_id") or record["person_id"]),
            person_id=int(record["person_id"]),
            institution_id=str(record["institution_id"]),
            full_name=str(record["full_name"]),
            person_type=str(record["person_type"]),
            embedding=vector,
            version=int(record.get("version", 1)),
        )

        with self._lock:
            existing = [
                (current, current.embedding)
                for current in self._profiles
                if current.profile_id != profile.profile_id
            ]
            compatible = [(profile, vector), *existing]
            dimension = compatible[0][1].size
            compatible = [(current, embedding) for current, embedding in compatible if embedding.size == dimension]
            self._profiles = tuple(current for current, _ in compatible)
            self._matrix = np.vstack([embedding for _, embedding in compatible]).astype(np.float32)

            return len(compatible)

    def remove_person(self, person_id: int) -> int:
        with self._lock:
            remaining = [(profile, profile.embedding) for profile in self._profiles if profile.person_id != person_id]
            dimension = remaining[0][1].size if remaining else self._matrix.shape[1]
            self._profiles = tuple(profile for profile, _ in remaining)
            self._matrix = np.vstack([embedding for _, embedding in remaining]).astype(np.float32) \
                if remaining else np.empty((0, dimension), dtype=np.float32)
            return len(self._profiles)

    def best_match(self, embedding: list[float]) -> FaceMatch | None:
        vector = np.asarray(embedding, dtype=np.float32).reshape(-1)
        norm = float(np.linalg.norm(vector))
        if norm <= 0:
            return None
        vector /= norm

        with self._lock:
            if not self._profiles or self._matrix.shape[1] != vector.size:
                return None
            if self._metric == "euclidean":
                return self._best_euclidean(vector)
            return self._best_cosine(vector)

    def _best_cosine(self, vector: np.ndarray) -> FaceMatch | None:
        similarities = self._matrix @ vector
        # Retain the strongest sample for each person, then compare people,
        # not individual enrollment frames, to prevent prolific samples from
        # biasing the result.
        best_by_person: dict[int, tuple[int, float]] = {}
        for index, similarity in enumerate(similarities):
            current = best_by_person.get(self._profiles[index].person_id)
            if current is None or float(similarity) > current[1]:
                best_by_person[self._profiles[index].person_id] = (index, float(similarity))
        ranked = sorted(best_by_person.values(), key=lambda candidate: candidate[1], reverse=True)
        index, similarity = ranked[0]
        confidence = max(0.0, min(1.0, (similarity + 1.0) / 2.0))
        if len(ranked) == 1:
            return FaceMatch(self._profiles[index], similarity, confidence)
        second_index, second_similarity = ranked[1]
        second_confidence = max(0.0, min(1.0, (second_similarity + 1.0) / 2.0))
        return FaceMatch(
            self._profiles[index], similarity, confidence,
            self._profiles[second_index], second_similarity, second_confidence,
        )

    def _best_euclidean(self, vector: np.ndarray) -> FaceMatch | None:
        distances = np.sqrt(((self._matrix - vector) ** 2).sum(axis=1))
        # Retain the closest sample for each person, then compare people.
        best_by_person: dict[int, tuple[int, float]] = {}
        for index, distance in enumerate(distances):
            current = best_by_person.get(self._profiles[index].person_id)
            if current is None or float(distance) < current[1]:
                best_by_person[self._profiles[index].person_id] = (index, float(distance))
        ranked = sorted(best_by_person.values(), key=lambda candidate: candidate[1])
        index, distance = ranked[0]
        # similarity is stored negated so "higher is better" still holds.
        similarity, confidence = -distance, max(0.0, min(1.0, 1.0 - distance))
        if len(ranked) == 1:
            return FaceMatch(self._profiles[index], similarity, confidence)
        second_index, second_distance = ranked[1]
        second_confidence = max(0.0, min(1.0, 1.0 - second_distance))
        return FaceMatch(
            self._profiles[index], similarity, confidence,
            self._profiles[second_index], -second_distance, second_confidence,
        )
