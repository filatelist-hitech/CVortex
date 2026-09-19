# CVortex runtime AI assets

This directory is the canonical repository location for versioned **product runtime** AI Skills, prompt definitions and their validation fixtures. It is intentionally separate from `.agents/`, which is reserved for development-agent instructions.

M1.2 introduces the bounded `career.fact-extraction` Skill. M1.3 adds `vacancy.requirement-extraction`, which extracts only source-supported vacancy requirements and never performs matching or recommendation. Each Skill's manifest, prompt, output schema and synthetic adversarial fixtures are versioned together under its `skills/*/v1/` directory. Provider adapters load these assets through application-owned contracts; the assets do not select a concrete provider or model.
