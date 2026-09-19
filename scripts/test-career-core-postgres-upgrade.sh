#!/usr/bin/env bash
set -euo pipefail

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

compose=(docker compose)
pg_user="${POSTGRES_USER:-$("${compose[@]}" exec -T postgres printenv POSTGRES_USER | tr -d '\r\n')}"
database="cvortex_m12_upgrade_$$_${RANDOM}"

cleanup() {
  "${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
    -c "DROP DATABASE IF EXISTS \"$database\" WITH (FORCE)" >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${compose[@]}" exec -T postgres psql -U "$pg_user" -d postgres -v ON_ERROR_STOP=1 \
  -c "CREATE DATABASE \"$database\"" >/dev/null
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan migrate --force \
  --path=database/migrations/2026_09_12_000001_create_access_core_tables.php >/dev/null
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan migrate --force \
  --path=database/migrations/2026_09_13_000002_add_auth_generation_to_users.php >/dev/null

"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 <<'SQL' >/dev/null
CREATE TABLE career_profiles (
  id char(26) PRIMARY KEY,
  owner_id char(26) NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  created_at timestamp NULL,
  updated_at timestamp NULL
);
CREATE TABLE career_sources (
  id char(26) PRIMARY KEY,
  owner_id char(26) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  career_profile_id char(26) NOT NULL REFERENCES career_profiles(id) ON DELETE CASCADE,
  kind varchar(32) NOT NULL CHECK (kind IN ('pasted_text','manual_entry')),
  content text NOT NULL,
  content_hash varchar(64) NOT NULL,
  provenance varchar(32) NOT NULL,
  created_at timestamp NULL,
  updated_at timestamp NULL
);
CREATE TABLE llm_runs (
  id char(26) PRIMARY KEY,
  owner_id char(26) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  skill varchar(96) NOT NULL,
  skill_version varchar(32) NOT NULL,
  prompt_version varchar(32) NOT NULL,
  model_policy varchar(64) NOT NULL,
  provider varchar(64) NOT NULL,
  model varchar(128) NOT NULL,
  status varchar(32) NOT NULL,
  usage json NULL,
  created_at timestamp NULL,
  updated_at timestamp NULL
);
CREATE TABLE career_facts (
  id char(26) PRIMARY KEY,
  owner_id char(26) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  career_profile_id char(26) NOT NULL REFERENCES career_profiles(id) ON DELETE CASCADE,
  career_source_id char(26) NULL REFERENCES career_sources(id) ON DELETE SET NULL,
  kind varchar(64) NOT NULL,
  value json NOT NULL,
  source_excerpt text NULL,
  employment_context json NULL,
  state varchar(16) NOT NULL CHECK (state IN ('PENDING','CONFIRMED','REJECTED','DEPRECATED')),
  provenance varchar(32) NOT NULL CHECK (provenance IN ('llm_extraction','user_manual','human_review')),
  confidence numeric(5,4) NULL,
  reviewed_by char(26) NULL REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at timestamptz NULL,
  supersedes_id char(26) NULL,
  created_at timestamp NULL,
  updated_at timestamp NULL
);
CREATE TABLE claims (
  id char(26) PRIMARY KEY,
  owner_id char(26) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  statement text NOT NULL,
  created_at timestamp NULL,
  updated_at timestamp NULL
);
CREATE TABLE claim_evidence (
  id char(26) PRIMARY KEY,
  owner_id char(26) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  claim_id char(26) NOT NULL REFERENCES claims(id) ON DELETE CASCADE,
  career_fact_id char(26) NOT NULL REFERENCES career_facts(id) ON DELETE CASCADE,
  created_at timestamp NULL,
  updated_at timestamp NULL,
  UNIQUE (claim_id, career_fact_id)
);
INSERT INTO users (id,email,password,role,status,auth_generation,created_at,updated_at)
VALUES ('01JLEGACYUSER0000000000000','legacy-career@example.test','synthetic-hash','user','ACTIVE',0,now(),now());
INSERT INTO career_profiles VALUES ('01JLEGACYPROFILE0000000000','01JLEGACYUSER0000000000000',now(),now());
INSERT INTO career_sources VALUES ('01JLEGACYSOURCE00000000000','01JLEGACYUSER0000000000000','01JLEGACYPROFILE0000000000','manual_entry','Legacy synthetic source.','legacy-hash','llm_extraction',now(),now());
INSERT INTO career_facts VALUES ('01JLEGACYFACT0000000000000','01JLEGACYUSER0000000000000','01JLEGACYPROFILE0000000000','01JLEGACYSOURCE00000000000','experience','"Legacy synthetic fact."','Legacy synthetic fact.',NULL,'CONFIRMED','llm_extraction',0.8,'01JLEGACYUSER0000000000000',now(),NULL,now(),now());
INSERT INTO claims VALUES ('01JLEGACYCLAIM000000000000','01JLEGACYUSER0000000000000','Legacy synthetic fact.',now(),now());
INSERT INTO claim_evidence VALUES ('01JLEGACYEVIDENCE000000000','01JLEGACYUSER0000000000000','01JLEGACYCLAIM000000000000','01JLEGACYFACT0000000000000',now(),now());
INSERT INTO llm_runs VALUES ('01JLEGACYRUN00000000000000','01JLEGACYUSER0000000000000','career.fact-extraction','0.9.0','0.9.0','low_cost_structured_extraction','synthetic','legacy-model','COMPLETED','{"input_tokens":3}',now(),now());
INSERT INTO migrations (migration,batch) VALUES ('2026_09_13_000003_create_career_core_tables',2);
SQL

"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan migrate --force >/dev/null
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php tests/Support/verify_career_upgrade.php

before_rollback=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM career_facts')
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan migrate:rollback --step=3 --force >/dev/null
after_rollback=$("${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -Atqc 'SELECT count(*) FROM career_facts')
test "$before_rollback" = "$after_rollback"
"${compose[@]}" exec -T -e DB_DATABASE="$database" backend php artisan migrate --force >/dev/null

"${compose[@]}" exec -T postgres psql -U "$pg_user" -d "$database" -v ON_ERROR_STOP=1 <<'SQL' >/dev/null
INSERT INTO users (id,email,password,role,status,auth_generation,created_at,updated_at)
VALUES ('01JOTHERUSER00000000000000','other-career@example.test','synthetic-hash','user','ACTIVE',0,now(),now());
INSERT INTO career_profiles (id,owner_id,created_at,updated_at)
VALUES ('01JOTHERPROFILE00000000000','01JOTHERUSER00000000000000',now(),now());
INSERT INTO career_sources (id,owner_id,career_profile_id,kind,source_text,content_hash,extraction_status,created_at,updated_at)
VALUES ('01JOTHERSOURCE000000000000','01JOTHERUSER00000000000000','01JOTHERPROFILE00000000000','PASTED_TEXT','Other source.','other-source-hash','COMPLETED',now(),now());
INSERT INTO career_facts (id,owner_id,career_profile_id,provenance_type,fact_type,assertion_original,assertion_approved,source_excerpt,extracted_by,candidate_hash,status,reviewed_by,reviewed_at,created_at,updated_at)
VALUES ('01JOTHERFACT00000000000000','01JOTHERUSER00000000000000','01JOTHERPROFILE00000000000','user_manual','skill','Other fact.','Other fact.','Other fact.','user_manual','other-hash','CONFIRMED','01JOTHERUSER00000000000000',now(),now(),now());
DO $$
BEGIN
  BEGIN
    INSERT INTO claim_evidence (id,owner_id,claim_id,career_fact_id,created_at,updated_at)
    VALUES ('01JINVALIDEVIDENCE00000000','01JLEGACYUSER0000000000000','01JLEGACYCLAIM000000000000','01JOTHERFACT00000000000000',now(),now());
    RAISE EXCEPTION 'cross-owner claim evidence was accepted';
  EXCEPTION WHEN foreign_key_violation THEN
    NULL;
  END;
  BEGIN
    UPDATE claims
    SET resolved_career_fact_id = '01JOTHERFACT00000000000000'
    WHERE id = '01JLEGACYCLAIM000000000000';
    RAISE EXCEPTION 'cross-owner resolution choice was accepted';
  EXCEPTION WHEN foreign_key_violation THEN
    NULL;
  END;
  BEGIN
    INSERT INTO career_facts (id,owner_id,career_profile_id,career_source_id,provenance_type,fact_type,assertion_original,source_excerpt,extracted_by,candidate_hash,status,created_at,updated_at)
    VALUES ('01JINVALIDFACT000000000000','01JLEGACYUSER0000000000000','01JLEGACYPROFILE0000000000','01JOTHERSOURCE000000000000','paste_extraction','experience','Bad chain.','Bad chain.','synthetic','bad-chain','PENDING',now(),now());
    RAISE EXCEPTION 'cross-owner source was accepted';
  EXCEPTION WHEN foreign_key_violation THEN
    NULL;
  END;
  BEGIN
    INSERT INTO career_facts (id,owner_id,career_profile_id,provenance_type,fact_type,assertion_original,assertion_approved,source_excerpt,extracted_by,candidate_hash,status,reviewed_by,reviewed_at,created_at,updated_at)
    VALUES ('01JINVALIDPROFILEFACT00000','01JLEGACYUSER0000000000000','01JOTHERPROFILE00000000000','user_manual','skill','Bad profile.','Bad profile.','Bad profile.','user_manual','bad-profile','CONFIRMED','01JLEGACYUSER0000000000000',now(),now(),now());
    RAISE EXCEPTION 'cross-owner profile was accepted';
  EXCEPTION WHEN foreign_key_violation THEN
    NULL;
  END;
  BEGIN
    INSERT INTO career_facts (id,owner_id,career_profile_id,career_source_id,provenance_type,fact_type,assertion_original,source_excerpt,extracted_by,candidate_hash,status,created_at,updated_at)
    VALUES ('01JDUPLICATEFACT0000000000','01JLEGACYUSER0000000000000','01JLEGACYPROFILE0000000000','01JLEGACYSOURCE00000000000','paste_extraction','experience','Duplicate.','Duplicate.','synthetic','duplicate-hash','PENDING',now(),now());
    INSERT INTO career_facts (id,owner_id,career_profile_id,career_source_id,provenance_type,fact_type,assertion_original,source_excerpt,extracted_by,candidate_hash,status,created_at,updated_at)
    VALUES ('01JDUPLICATEFACT0000000001','01JLEGACYUSER0000000000000','01JLEGACYPROFILE0000000000','01JLEGACYSOURCE00000000000','paste_extraction','experience','Duplicate.','Duplicate.','synthetic','duplicate-hash','PENDING',now(),now());
    RAISE EXCEPTION 'duplicate extraction candidate was accepted';
  EXCEPTION WHEN unique_violation THEN
    NULL;
  END;
END $$;
SQL

echo 'career-core-postgres-upgrade: PASS (legacy data, extraction, manual entry, non-destructive rollback/re-up)'
