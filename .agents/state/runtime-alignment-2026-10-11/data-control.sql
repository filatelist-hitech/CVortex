BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY;
SELECT format('SELECT json_build_object(''table'', %L, ''count'', count(*), ''digest'', md5(COALESCE(string_agg(row_digest, '''' ORDER BY row_digest), ''''))) FROM (SELECT md5((to_jsonb(t) - CASE WHEN %L = ''chatgpt_connections'' THEN ''oauth_generation'' ELSE ''__rcv_absent_key__'' END)::text) AS row_digest FROM %I.%I AS t) AS r;', tablename, tablename, schemaname, tablename) FROM pg_tables WHERE schemaname='public' AND tablename <> 'migrations' ORDER BY tablename
\gexec
SELECT json_build_object('control_schema', md5(string_agg(value, '' ORDER BY value))) FROM (
SELECT 'column:'||table_name||':'||column_name||':'||data_type||':'||is_nullable||':'||coalesce(column_default,'') AS value FROM information_schema.columns WHERE table_schema='public' AND NOT (table_name='chatgpt_connections' AND column_name='oauth_generation')
UNION ALL SELECT 'policy:'||tablename||':'||policyname||':'||coalesce(qual,'')||':'||coalesce(with_check,'') FROM pg_policies WHERE schemaname='public'
UNION ALL SELECT 'constraint:'||conrelid::regclass::text||':'||conname||':'||pg_get_constraintdef(oid) FROM pg_constraint WHERE connamespace='public'::regnamespace
UNION ALL SELECT 'table:'||relname||':'||relrowsecurity::text||':'||relforcerowsecurity::text||':'||relowner::regrole::text||':'||coalesce(relacl::text,'') FROM pg_class WHERE relnamespace='public'::regnamespace AND relkind='r'
) s;
ROLLBACK;
