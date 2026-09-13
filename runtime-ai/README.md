# CVortex runtime AI assets

This directory is the canonical repository location for versioned **product runtime** AI Skills, prompt definitions and their validation fixtures. It is intentionally separate from `.agents/`, which is reserved for development-agent instructions.

M1.2 introduces the bounded `career.fact-extraction` Skill. Its manifest, prompt, output schema and synthetic adversarial fixtures are versioned together under `skills/career-fact-extraction/v1/`. Provider adapters load these assets through application-owned contracts; the assets do not select a concrete provider or model.
