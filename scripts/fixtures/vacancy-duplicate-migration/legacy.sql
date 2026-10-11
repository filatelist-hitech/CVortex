INSERT INTO users (id, email, password, role, status, created_at, updated_at)
VALUES ('00000000000000000000000001', 'duplicate-migration@example.test', 'synthetic', 'user', 'ACTIVE', now(), now());

INSERT INTO vacancies (id, owner_id, source_type, source_url, analysis_status, error_code, created_at, updated_at)
VALUES
('00000000000000000000000011', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/pending', 'COMPLETED', NULL, '2024-01-01 00:00:00+00', now()),
('00000000000000000000000012', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/pending', 'PENDING', NULL, '2024-01-02 00:00:00+00', now()),
('00000000000000000000000013', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/failed', 'COMPLETED', NULL, '2024-02-01 00:00:00+00', now()),
('00000000000000000000000014', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/failed', 'FAILED', 'PROVIDER_ERROR', '2024-02-02 00:00:00+00', now()),
('00000000000000000000000015', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/interleaved', 'COMPLETED', NULL, '2024-03-01 00:00:00+00', now()),
('00000000000000000000000016', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/interleaved', 'PENDING', NULL, '2024-03-02 00:00:00+00', now()),
('00000000000000000000000017', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/completed', 'PENDING', NULL, '2024-04-01 00:00:00+00', now()),
('00000000000000000000000018', '00000000000000000000000001', 'PASTED_TEXT', 'https://jobs.example.test/matching-failed', 'FAILED', 'ANALYSIS_ERROR', '2024-05-01 00:00:00+00', now());

INSERT INTO vacancy_snapshots (id, owner_id, vacancy_id, version, raw_text, source_url, content_hash, imported_at, created_at, updated_at)
VALUES
('00000000000000000000000021', '00000000000000000000000001', '00000000000000000000000011', 1, 'pending t1', 'https://jobs.example.test/pending', repeat('1', 64), '2024-01-01 00:00:00+00', '2024-01-01 00:00:00+00', now()),
('00000000000000000000000022', '00000000000000000000000001', '00000000000000000000000012', 1, 'pending t2', 'https://jobs.example.test/pending', repeat('2', 64), '2024-01-02 00:00:00+00', '2024-01-02 00:00:00+00', now()),
('00000000000000000000000023', '00000000000000000000000001', '00000000000000000000000013', 1, 'failed t1', 'https://jobs.example.test/failed', repeat('3', 64), '2024-02-01 00:00:00+00', '2024-02-01 00:00:00+00', now()),
('00000000000000000000000024', '00000000000000000000000001', '00000000000000000000000014', 1, 'failed t2', 'https://jobs.example.test/failed', repeat('4', 64), '2024-02-02 00:00:00+00', '2024-02-02 00:00:00+00', now()),
('00000000000000000000000025', '00000000000000000000000001', '00000000000000000000000015', 1, 'chronology t1', 'https://jobs.example.test/interleaved', repeat('5', 64), '2024-03-01 00:00:00+00', '2024-03-01 00:00:00+00', now()),
('00000000000000000000000026', '00000000000000000000000001', '00000000000000000000000016', 1, 'chronology t2', 'https://jobs.example.test/interleaved', repeat('6', 64), '2024-03-02 00:00:00+00', '2024-03-02 00:00:00+00', now()),
('00000000000000000000000027', '00000000000000000000000001', '00000000000000000000000016', 2, 'chronology t3', 'https://jobs.example.test/interleaved', repeat('7', 64), '2024-03-03 00:00:00+00', '2024-03-03 00:00:00+00', now()),
('00000000000000000000000028', '00000000000000000000000001', '00000000000000000000000015', 2, 'chronology t4', 'https://jobs.example.test/interleaved', repeat('8', 64), '2024-03-04 00:00:00+00', '2024-03-04 00:00:00+00', now()),
('00000000000000000000000029', '00000000000000000000000001', '00000000000000000000000017', 1, 'completed current snapshot', 'https://jobs.example.test/completed', repeat('9', 64), '2024-04-01 00:00:00+00', '2024-04-01 00:00:00+00', now()),
('00000000000000000000000030', '00000000000000000000000001', '00000000000000000000000018', 1, 'matching failed after extraction', 'https://jobs.example.test/matching-failed', repeat('a', 64), '2024-05-01 00:00:00+00', '2024-05-01 00:00:00+00', now());

INSERT INTO vacancy_analyses (id, owner_id, vacancy_id, vacancy_snapshot_id, career_signature, recommendation, key_reasons, material_gaps, uncertainties, analysis_version, created_at, updated_at)
VALUES
('00000000000000000000000031', '00000000000000000000000001', '00000000000000000000000011', '00000000000000000000000021', repeat('a', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000032', '00000000000000000000000001', '00000000000000000000000013', '00000000000000000000000023', repeat('b', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000033', '00000000000000000000000001', '00000000000000000000000015', '00000000000000000000000025', repeat('c', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000034', '00000000000000000000000001', '00000000000000000000000015', '00000000000000000000000028', repeat('d', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now()),
('00000000000000000000000036', '00000000000000000000000001', '00000000000000000000000017', '00000000000000000000000029', repeat('e', 64), 'MAYBE', '[]', '[]', '[]', '1.0.0', now(), now());

INSERT INTO vacancy_llm_runs (id, owner_id, vacancy_snapshot_id, workflow, skill_id, skill_version, prompt_version, model_policy, status, validation_result, error_category, created_at, updated_at)
VALUES
('00000000000000000000000035', '00000000000000000000000001', '00000000000000000000000024', 'vacancy_requirement_extraction', 'vacancy.requirement-extraction', '1.0.0', '1.0.0', 'default', 'FAILED', 'NOT_VALIDATED', 'PROVIDER_ERROR', now(), now()),
('00000000000000000000000037', '00000000000000000000000001', '00000000000000000000000028', 'vacancy_requirement_extraction', 'vacancy.requirement-extraction', '1.0.0', '1.0.0', 'default', 'RUNNING', 'NOT_VALIDATED', NULL, now(), now()),
('00000000000000000000000038', '00000000000000000000000001', '00000000000000000000000030', 'vacancy_requirement_extraction', 'vacancy.requirement-extraction', '1.0.0', '1.0.0', 'default', 'COMPLETED', 'PASS', NULL, now(), now());
