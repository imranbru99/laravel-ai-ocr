# Quality gate

Every native page receives a score in [0,1]. Near-empty text loses 0.6; excessive
control characters lose 0.5; known mojibake, replacement characters, abnormal
Latin-1 symbols or legacy Bangla sequences lose 0.8. These conservative penalties
are configurable through the gate threshold and feature switches. Native minimal
PDF extraction is capped at confidence 0.5 because glyph mapping is unverified.

For Tesseract pages, the score cannot exceed average word confidence. Confidence
below `ocr.min_confidence / 100` caps the score at 0.5. AI transcription confidence
also caps the page score. AI self-confidence is not independently calibrated.

`languages(['ben'])` asks for a Bengali script check; `['ben','eng']` permits
mixed text. `quality.expected_script` can explicitly require `bengali` or `arabic`.
The legacy-Bangla detector recognizes evidence of common glyph encodings; it is
not a complete dictionary/entropy classifier and cannot recover Bijoy by itself.

A page below `quality.min_score` (default 0.75) first tries local OCR. Better local
results replace native text. The remaining failed pages can reach AI only when
provider readiness, privacy, image policy and budget all permit it. Manual-only
never reaches AI. Good text with missing schema fields takes a text-only repair
path; it does not need to send its image.

Trace entries include page reasons, scores, AI model/provider and a final gate
summary (`pass`, `partial`, `fail`) with failed page numbers and field paths.
`minConfidence(0.85)` overrides the page threshold per call. Failed fields affect
result confidence and warnings even when page text is readable.

A native page or AI result can contain plausible but incorrect text. Tune the
threshold with a representative labeled corpus and review domain cross-checks;
there is no universal OCR accuracy percentage.
