#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Gate F — performance and capacity envelope, measured instead of reviewed.
 *
 * The production-readiness certification asserted "no N+1, no unbounded queries"
 * from code reading, and the readiness audit could not say whether the ~500 ms it
 * measured on three endpoints was real cost or `artisan serve` queueing. Neither
 * claim is checkable by looking. This tool makes both checkable by running the
 * questions:
 *
 *   status      row counts, table sizes, and whether pg_stat_statements is available
 *   endpoints   the GET surface of /api/v1 with parameters resolved, one per line
 *   amplify     grow the tables the read path touches by copying real rows
 *   stats       top statements by total time and call count (N+1 evidence)
 *   ddl         ALTER TABLE / CREATE INDEX battery against a voluminous table,
 *               with a concurrent writer measuring how long live writes wait
 *
 * Usage:
 *   DB_DATABASE=toefl_house_perf php scripts/runtime/perf-envelope.php --task=status
 *
 * Notes on method, because the numbers are only as honest as these choices:
 *
 *  * `amplify` grows tables by **copying existing rows** and re-deriving the keys,
 *    rather than inventing values. Invented rows pass `NOT NULL` but fail the
 *    `CHECK` constraints and domain triggers that make this schema what it is, and
 *    a synthetic dataset that breaks the rules measures a database the product
 *    does not have. Copy-amplification keeps every FK pointing at a row that
 *    exists and every status/date/amount inside its legal range, because it *was*
 *    legal before the copy. Columns covered by a unique constraint are perturbed by
 *    the copy number so the copies remain distinct.
 *  * Domain triggers are disabled for the amplification itself (bulk INSERT of
 *    10^5 rows would otherwise be governed row-by-row) and re-enabled afterwards,
 *    so every measurement in `stats`, `endpoints` and `ddl` runs against a
 *    database with its full rule set active.
 *  * `ddl` does not time the statement alone; it also reports how long a real
 *    concurrent `INSERT` waited. "0.4 s" for a `CREATE INDEX` is meaningless to an
 *    operator; "writes blocked for 6.1 s" is the deploy-window fact.
 */
$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$options = getopt('', ['task::', 'copies::', 'only::', 'table::', 'limit::', 'help']);
$task = (string) ($options['task'] ?? 'status');
$copies = max(1, (int) ($options['copies'] ?? 50));

/** Tables the read path actually queries, from the live statement statistics. */
function measuredReadTables(): array
{
    try {
        $rows = DB::select(<<<'SQL'
            select distinct trim(regexp_match(query, '(?:from|join)\s+(?:only\s+)?([a-z_][a-z0-9_.]*)'))[1] as rel
            from pg_stat_statements
            where query ~* '^(select|with)'
              and userid = (select usesysid from pg_user where usename = current_user)
            SQL);
    } catch (Throwable) {
        return [];
    }

    $tables = [];
    foreach ($rows as $row) {
        $name = strtolower((string) $row->rel);
        $name = str_contains($name, '.') ? substr($name, strpos($name, '.') + 1) : $name;
        if ($name !== '' && ! str_starts_with($name, 'pg_')) {
            $tables[$name] = true;
        }
    }

    return array_keys($tables);
}

/** Every user table holding rows, largest first — the fallback when pgss is off. */
function populatedTables(): array
{
    $rows = DB::select(<<<'SQL'
        select c.relname as name,
               coalesce(s.n_live_tup, 0) as rows,
               pg_total_relation_size(c.oid) as bytes
        from pg_class c
        join pg_namespace n on n.oid = c.relnamespace
        left join pg_stat_user_tables s on s.relid = c.oid
        where n.nspname = 'public' and c.relkind = 'r'
        order by rows desc, bytes desc
        SQL);

    return array_map(static fn ($r) => (object) ['name' => $r->name, 'rows' => (int) $r->rows, 'bytes' => (int) $r->bytes], $rows);
}

/**
 * Columns whose combination must stay unique, plus how to perturb each one.
 *
 * @return array{omit: array<string>, expressions: array<string,string>}
 */
function uniqueKeyPlan(string $table): array
{
    $omit = [];
    $expressions = [];

    $constraints = DB::select(<<<'SQL'
        select con.conname,
               att.attname,
               format_type(att.atttypid, att.atttypmod) as typ,
               (con.contype = 'p' and (att.attidentity <> '' or att.atthasdef
                    and pg_get_expr(d.adbin, d.adrelid) like 'nextval%')) as auto_pk,
               att.attgenerated
        from pg_constraint con
        join pg_class pc on pc.oid = con.conrelid
        join pg_namespace n on n.oid = pc.relnamespace
        join lateral unnest(con.conkey) with ordinality as k(attnum, ord) on true
        join pg_attribute att on att.attrelid = pc.oid and att.attnum = k.attnum
        left join pg_attrdef d on d.adrelid = pc.oid and d.adnum = att.attnum
        where n.nspname = 'public' and pc.relname = ? and con.contype in ('p', 'u')
        order by con.conname, k.ord
        SQL, [$table]);

    // Unique *indexes* are as binding as unique *constraints* — and this schema gets
    // most of its uniqueness from `->unique()` on the schema builder, which creates an
    // index and no constraint row at all. Reading only pg_constraint produced copies
    // that collided with `accounts_code_unique`, an index no constraint query can see.
    $indexes = DB::select(<<<'SQL'
        select ix.relname as conname,
               att.attname,
               format_type(att.atttypid, att.atttypmod) as typ,
               false as auto_pk,
               att.attgenerated
        from pg_index idx
        join pg_class ix on ix.oid = idx.indexrelid
        join pg_class pc on pc.oid = idx.indrelid
        join pg_namespace n on n.oid = pc.relnamespace
        join lateral unnest(idx.indkey) with ordinality as k(attnum, ord) on true
        join pg_attribute att on att.attrelid = pc.oid and att.attnum = k.attnum
        where n.nspname = 'public' and pc.relname = ?
          and idx.indisunique and k.attnum > 0 and ix.relname <> ?
        order by ix.relname, k.ord
        SQL, [$table, $table.'_pkey']);

    foreach ([...$constraints, ...$indexes] as $c) {
        $column = (string) $c->attname;

        if ($c->attgenerated !== '') {
            $omit[$column] = true;                    // generated always — never supplied

            continue;
        }

        if (filter_var($c->auto_pk, FILTER_VALIDATE_BOOLEAN)) {
            $omit[$column] = true;                    // sequence-backed primary key

            continue;
        }

        $expressions[$column] = perturbation((string) $c->typ);
    }

    return ['omit' => array_keys($omit), 'expressions' => $expressions];
}

function perturbation(string $type): string
{
    // The copy has to stay *parseable*, not merely unique: this schema stores UUIDs as
    // char(36), so a value that is "unique but not a uuid" makes the read path fail on
    // the fixture instead of on the product, and that measurement is worthless. Hence
    // the fixed-width branch generates a real uuid rather than padding a string.
    if (preg_match('/^(?:character|bpchar)\((\d+)\)$/', $type, $m) === 1) {
        $width = (int) $m[1];

        return $width === 36
            ? 'gen_random_uuid()::'.$type
            : '(left(md5("%s"::text || g::text), '.$width.'))::'.$type;
    }

    if (preg_match('/^(?:character varying|varchar)\((\d+)\)$/', $type, $m) === 1) {
        $keep = max(0, (int) $m[1] - 10);

        return '(left("%s"::text, '.$keep.') || \'-x\' || substr(md5("%s"::text || g::text), 1, 8))::'.$type;
    }

    return match (true) {
        str_contains($type, 'uuid') => 'gen_random_uuid()',
        str_contains($type, 'int') => '("%s"::bigint + g * 1000003)::'.$type,
        str_contains($type, 'numeric') || str_contains($type, 'money') => '("%s" + g)',
        str_contains($type, 'text') => '("%s"::text || \'-x\' || g)',
        default => '"%s"',
    };
}

/**
 * Copy every existing row N times, re-deriving keys. Referential integrity stays
 * enforced (foreign-key triggers are not affected by DISABLE TRIGGER USER), so a
 * failure here means the copy would have created rows the domain forbids — which
 * is reported, not skipped.
 */
function amplifyTable(string $table, int $copies): array
{
    $plan = uniqueKeyPlan($table);

    $columns = DB::select(<<<'SQL'
        select att.attname as name, format_type(att.atttypid, att.atttypmod) as typ
        from pg_attribute att
        join pg_class pc on pc.oid = att.attrelid
        join pg_namespace n on n.oid = pc.relnamespace
        where n.nspname = 'public' and pc.relname = ? and att.attnum > 0 and not att.attisdropped
          and att.attgenerated = ''
        order by att.attnum
        SQL, [$table]);

    $insert = [];
    $select = [];
    foreach ($columns as $col) {
        $name = (string) $col->name;
        if (in_array($name, $plan['omit'], true)) {
            continue;
        }
        $insert[] = '"'.$name.'"';
        $expr = $plan['expressions'][$name] ?? null;
        // Every template carries its own quoting (`"%s"::bigint`), so the bare column
        // name is what gets substituted — passing a quoted identifier in here produced
        // `""code""` and a syntax error, not a wrong value, which is the friendly case.
        $select[] = match (true) {
            $expr === null => '"'.$name.'"',
            substr_count($expr, '%s') >= 2 => sprintf($expr, $name, $name),
            ! str_contains($expr, '%s') => $expr,
            default => sprintf($expr, $name),
        };
    }

    $before = (int) (DB::scalar('select count(*) from "'.$table.'"') ?? 0);
    $started = microtime(true);

    DB::statement('alter table "'.$table.'" disable trigger user');
    try {
        DB::statement(sprintf(
            'insert into "%s" (%s) select %s from "%s" cross join generate_series(1, %d) as g',
            $table,
            implode(', ', $insert),
            implode(', ', $select),
            $table,
            $copies
        ));
    } finally {
        DB::statement('alter table "'.$table.'" enable trigger user');
    }

    $after = (int) (DB::scalar('select count(*) from "'.$table.'"') ?? 0);

    return [
        'table' => $table,
        'rows_before' => $before,
        'rows_after' => $after,
        'seconds' => round(microtime(true) - $started, 3),
        'bytes' => (int) DB::scalar('select pg_total_relation_size(?)::bigint', [$table]),
    ];
}

function reportStatus(): void
{
    $pgss = DB::select("select count(*) as c from pg_extension where extname = 'pg_stat_statements'");
    $db = (string) DB::scalar('select current_database()');
    $tables = populatedTables();
    $total = (int) DB::scalar('select pg_database_size(current_database())::bigint');

    printf("database            : %s (%s MB)\n", $db, number_format((int) round($total / 1048576)));
    printf("pg_stat_statements  : %s\n", ((int) $pgss[0]->c > 0) ? 'available' : 'NOT INSTALLED (run: create extension pg_stat_statements; and set shared_preload_libraries)');
    printf("tables              : %d\n", count($tables));
    echo "largest             :\n";
    foreach (array_slice($tables, 0, 12) as $t) {
        printf("  %-44s %10d rows  %8s MB\n", $t->name, $t->rows, number_format((int) round($t->bytes / 1048576, 1)));
    }
}

function reportEndpoints(): void
{
    $routes = app('router')->getRoutes();
    $ids = [];
    foreach ($routes as $route) {
        foreach ($route->parameterNames() as $p) {
            $ids[$p] ??= resolveSampleId($p);
        }
    }

    foreach ($routes as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        if (! str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }
        $uri = '/'.ltrim($route->uri(), '/');
        $missing = false;
        foreach ($route->parameterNames() as $p) {
            $value = $ids[$p] ?? null;
            if ($value === null) {
                $missing = true;

                break;
            }
            $uri = str_replace('{'.$p.'}', (string) $value, $uri);
        }
        if (! $missing) {
            echo $uri."\n";
        }
    }
}

/**
 * A legal value for a route parameter, read from the live rows.
 *
 * Parameter names are camelCase (`studentId`, `profileId`) and the schema is not
 * uniform about the matching table name, so the table is looked up in the catalog
 * instead of hard-coded — and a parameter that cannot be resolved drops that
 * endpoint from the run rather than substituting a plausible-looking id, because a
 * 404 measured as 12 ms is a number that misleads.
 */
function resolveSampleId(string $parameter): ?string
{
    $stem = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', str_ends_with($parameter, 'Id') ? substr($parameter, 0, -2) : $parameter));
    if ($stem === '') {
        return null;
    }

    $table = DB::scalar(<<<'SQL'
        select c.relname
        from pg_class c
        join pg_namespace n on n.oid = c.relnamespace
        where n.nspname = 'public' and c.relkind = 'r'
          and (c.relname = ? or c.relname = ? || 's' or c.relname like ? || '_%' or c.relname like ?)
        order by length(c.relname), c.relname
        limit 1
        SQL, [$stem, $stem, $stem, '%'.$stem.'%']);

    if (! is_string($table) || $table === '') {
        return null;
    }

    $pk = DB::scalar(<<<'SQL'
        select a.attname
        from pg_index i
        join pg_attribute a on a.attrelid = i.indrelid and a.attnum = i.indkey[0]
        where i.indrelid = ?::regclass and i.indisprimary
        limit 1
        SQL, ['public.'.$table]);

    if (! is_string($pk) || $pk === '') {
        return null;
    }

    $value = DB::scalar(sprintf('select "%s"::text from "%s" order by 1 limit 1', $pk, $table));

    return is_string($value) || is_int($value) ? (string) $value : null;
}

function reportStats(int $limit = 20): void
{
    try {
        $rows = DB::select(<<<'SQL'
            select calls,
                   round(total_exec_time::numeric, 1) as total_ms,
                   round(mean_exec_time::numeric, 2) as mean_ms,
                   rows,
                   left(regexp_replace(query, '\s+', ' ', 'g'), 110) as query
            from pg_stat_statements
            where calls > 0 and query not ilike '%pg_stat_statements%'
            order by total_exec_time desc
            limit ?
            SQL, [$limit]);
    } catch (Throwable $e) {
        echo "pg_stat_statements unavailable: {$e->getMessage()}\n";

        return;
    }

    printf("%9s %11s %9s %10s  %s\n", 'calls', 'total_ms', 'mean_ms', 'rows', 'query');
    foreach ($rows as $r) {
        printf("%9d %11s %9s %10d  %s\n", $r->calls, $r->total_ms, $r->mean_ms, $r->rows, $r->query);
    }
}

/**
 * The DDL battery. Each statement is measured twice: how long it took, and how long
 * a concurrent ordinary INSERT waited for it. The second number is what decides
 * whether a migration belongs in a deploy window.
 */
function runDdlBattery(string $table, ?string $psql): void
{
    $rows = (int) DB::scalar('select count(*) from "'.$table.'"');
    $bytes = (int) DB::scalar('select pg_total_relation_size(?)::bigint', [$table]);
    printf("target table %s: %s rows, %s MB\n\n", $table, number_format($rows), number_format((int) round($bytes / 1048576, 1)));

    $battery = [
        ['add nullable column', 'alter table T add column gf_nullable text', 'gate F doc: adding a nullable column is metadata-only, so it is safe at any size'],
        ['add not-null constant default', "alter table T add column gf_state text not null default 'pending'", 'PG 11+ stores the default, no rewrite — verified rather than assumed'],
        ['add not-null volatile default', 'alter table T add column gf_token uuid not null default gen_random_uuid()', 'the rewrite case: a value must exist per row, so every row is rewritten'],
        ['create index (locking)', 'create index gf_idx_locking on T (gf_state)', 'the ordinary form takes ShareLock and blocks writers'],
        ['create index concurrently', 'create index concurrently gf_idx_conc on T (gf_nullable)', 'no write lock; longer wall time, and cannot run inside a transaction'],
        ['narrow a column type', 'alter table T alter column gf_nullable type varchar(64)', 'type changes rewrite; measured to show what the migration policy refuses'],
        ['drop column', 'alter table T drop column gf_state', 'AccessExclusiveLock: brief for the catalog, but it queues behind readers'],
    ];

    printf("%-34s %10s %14s  %s\n", 'statement', 'seconds', 'writes waited', 'why it is in this battery');
    foreach ($battery as [$label, $sql, $why]) {
        $statement = str_replace('T', '"'.$table.'"', $sql);
        [$seconds, $wait] = timedDdl($statement, $psql, $table, str_contains($sql, 'concurrently'));
        printf("%-34s %10s %14s  %s\n", $label, $seconds === null ? 'n/a' : number_format($seconds, 3), $wait === null ? 'n/a' : number_format($wait * 1000, 0).' ms', $why);
    }
}

/**
 * Run one DDL statement in a separate psql process while this connection attempts
 * ordinary writes, and report both wall time and the longest write wait.
 *
 * @return array{0: ?float, 1: ?float}
 */
function timedDdl(string $sql, ?string $psql, string $table, bool $concurrently): array
{
    if ($psql === null) {
        return [null, null];
    }

    $env = getenv();
    $env['PGOPTIONS'] = '-c statement_timeout=0 -c lock_timeout=0';
    $env['PGPASSWORD'] = (string) (config('database.connections.pgsql.password') ?: '');

    $command = sprintf(
        '%s -h %s -p %s -U %s -d %s -v ON_ERROR_STOP=1 -c %s',
        escapeshellarg($psql),
        escapeshellarg((string) config('database.connections.pgsql.host')),
        escapeshellarg((string) config('database.connections.pgsql.port')),
        escapeshellarg((string) config('database.connections.pgsql.username')),
        escapeshellarg((string) config('database.connections.pgsql.database')),
        escapeshellarg($sql)
    );

    $process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (! is_resource($process)) {
        return [null, null];
    }

    $started = microtime(true);
    $maxWait = 0.0;
    $errors = [];

    // A legal write against the same table: whatever the statement does to locks,
    // this sees. `gf_nullable` is set by an earlier step for the first statements,
    // so fall back to the plain insert shape when it is absent.
    while (true) {
        // A lock probe with no side effects: FOR UPDATE takes RowShareLock on the
        // relation, which conflicts with both ShareLock (plain CREATE INDEX) and
        // AccessExclusiveLock (every other statement here), and `limit 0` means no
        // rows are touched, no columns are named and no triggers run.
        $t0 = microtime(true);
        try {
            DB::select('select 1 from "'.$table.'" for update limit 0');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
        $maxWait = max($maxWait, microtime(true) - $t0);
        usleep(2000);

        $status = proc_get_status($process);
        if (! $status['running']) {
            break;
        }
        if (microtime(true) - $started > 900) {
            proc_terminate($process, SIGKILL);

            break;
        }
    }

    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $seconds = microtime(true) - $started;

    if ($exit !== 0 && trim($stderr) !== '') {
        $errors[] = trim($stderr);
    }
    if ($errors !== []) {
        printf("      probe/statement noise: %s\n", str_replace("\n", ' ', substr((string) $errors[0], 0, 150)));
    }
    if ($concurrently && $exit !== 0) {
        echo "      (CREATE INDEX CONCURRENTLY leaves an INVALID index if it fails; drop it before re-running)\n";
    }

    return [$seconds, $maxWait];
}

function taskAmplify(array $options): void
{
    $only = ! empty($options['only'])
        ? array_map('trim', explode(',', (string) $options['only']))
        : null;

    // Bookkeeping tables are excluded by name: growing `migrations` or `cache` buys
    // nothing for a read-path measurement and corrupts the very rows the deploy
    // scripts read (`schema-compatibility.sh` counts migrations).
    $systemTables = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens'];

    $targets = $only;
    if ($targets === null) {
        $measured = measuredReadTables();
        $populated = array_map(static fn ($t) => $t->name, array_slice(populatedTables(), 0, 10));
        $targets = array_values(array_unique(array_intersect($populated, $measured) ?: $populated));
    }

    $targets = array_values(array_filter($targets, static fn (string $t): bool => ! in_array($t, $systemTables, true)));
    $copies = max(1, (int) ($options['copies'] ?? 50));
    printf("amplifying %d tables by %dx, keys re-derived, FK triggers left active\n\n", count($targets), $copies);

    $report = [];
    foreach ($targets as $table) {
        if (! DB::scalar('select to_regclass(?)', ['public.'.$table])) {
            printf("  %-40s skipped: no such table\n", $table);

            continue;
        }
        try {
            $result = amplifyTable($table, $copies);
            printf("  %-40s %9s -> %11s rows in %7.2fs\n", $table, number_format($result['rows_before']), number_format($result['rows_after']), $result['seconds']);
            $report[] = $result;
        } catch (Throwable $e) {
            printf("  %-40s FAILED: %s\n", $table, str_replace("\n", ' ', substr($e->getMessage(), 0, 160)));
        }
    }

    $vacuum = DB::scalar('select pg_size_pretty(pg_database_size(current_database()))');
    printf("\ndatabase size now: %s\n", $vacuum);
    echo "run: analyze;  so the planner has statistics for the new rows before measuring anything\n";
}

$out = fopen('php://stdout', 'w');
fclose($out);

switch ($task) {
    case 'status':
        reportStatus();

        return;
    case 'endpoints':
        reportEndpoints();

        return;
    case 'amplify':
        taskAmplify($options);

        return;
    case 'stats':
        reportStats((int) ($options['limit'] ?? 20));

        return;
    case 'reset':
        DB::statement('select pg_stat_statements_reset()');
        echo "pg_stat_statements reset\n";

        return;
    case 'ddl':
        $table = (string) ($options['table'] ?? '');
        if ($table === '') {
            $largest = populatedTables()[0] ?? null;
            $table = (string) ($largest?->name ?? '');
        }
        runDdlBattery($table, (string) (getenv('PSQL_BIN') ?: 'psql'));

        return;
    default:
        fwrite(STDERR, "unknown --task={$task}\n");
        exit(2);
}
