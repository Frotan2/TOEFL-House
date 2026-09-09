<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The shipped PHP-FPM artifacts must be loadable by PHP-FPM itself.
 *
 * A pool file is not a php.ini: FPM parses each `[pool]` section against a
 * fixed list of pool directives and aborts on anything else with
 * `ERROR: [file:NN] unknown entry 'x'` and exit code 78. The pool does not
 * start with "relaxed" settings in that case - it does not start at all. That
 * is how deploy/php-fpm.conf behaved while it carried opcache.* entries as pool
 * directives, and no test that merely read the text could see it.
 */
final class PhpFpmConfigTest extends TestCase
{
    /** Directives valid in an FPM pool section (fpm/conf.c). */
    private const POOL_DIRECTIVES = [
        'access.format', 'access.log', 'allow-access-from', 'chdir', 'chroot',
        'clear_env', 'catch_workers_output',
        'decorate_workers_output', 'env', 'group', 'listen', 'listen.allowed_clients',
        'listen.backlog', 'listen.group', 'listen.mode', 'listen.owner',
        'ping.path', 'ping.response', 'pm', 'pm.max_children', 'pm.max_requests',
        'pm.max_spare_servers', 'pm.min_spare_servers', 'pm.start_servers',
        'pm.status_listen', 'pm.status_path', 'process_control_timeout',
        'request_terminate_timeout', 'request_slowlog_timeout', 'rlimit_core', 'rlimit_files',
        'security.limit_extensions', 'php_value', 'php_admin_value',
        'php_flag', 'php_admin_flag', 'slowlog', 'user', 'working_directory',
    ];

    public function test_the_pool_file_uses_only_pool_directives(): void
    {
        $unknown = [];

        foreach ($this->activeAssignments('deploy/php-fpm.conf') as $key) {
            if (! in_array($key, self::POOL_DIRECTIVES, true)) {
                $unknown[] = $key;
            }
        }

        $this->assertSame([], $unknown, 'PHP-FPM refuses to start when a pool section carries a directive it does not know');
    }

    public function test_opcache_policy_is_shipped_as_a_conf_d_fragment(): void
    {
        $phpIniKeys = array_values(array_filter(
            $this->activeAssignments('deploy/php-fpm.conf'),
            static fn (string $key): bool => str_starts_with($key, 'opcache')
        ));
        $this->assertSame([], $phpIniKeys, 'opcache is PHP_INI_SYSTEM: it must not be set as a pool directive');

        $ini = (string) file_get_contents(base_path('deploy/opcache.ini'));
        foreach ([
            'opcache.enable=1',
            'opcache.enable_cli=0',
            'opcache.memory_consumption=128',
            'opcache.interned_strings_buffer=16',
            'opcache.max_accelerated_files=20000',
            'opcache.validate_timestamps=0',
        ] as $setting) {
            $this->assertStringContainsString($setting, $ini, "the conf.d fragment must still carry {$setting}");
        }

        $this->assertStringContainsString(
            'deploy/opcache.ini',
            (string) file_get_contents(base_path('deploy/php-fpm.conf')),
            'the pool file must tell the operator where the opcache policy lives'
        );
    }

    public function test_php_fpm_itself_accepts_the_shipped_pool_file(): void
    {
        $binary = $this->findPhpFpm();

        if ($binary === null) {
            $this->markTestSkipped('no php-fpm binary on this host (set PHP_FPM_BIN to run this check)');
        }

        // Only the host-bound paths and the identity are rewritten, so an
        // unprivileged runner can still make FPM parse the real directives.
        $dir = sys_get_temp_dir().'/toefl-fpm-'.uniqid('', true);
        mkdir($dir.'/logs', 0777, true);

        $conf = (string) file_get_contents(base_path('deploy/php-fpm.conf'));
        $conf = (string) preg_replace('/^listen = .*$/m', 'listen = '.$dir.'/fpm.sock', $conf);
        $conf = (string) preg_replace('/^user = .*$/m', 'user = '.get_current_user(), $conf);
        $conf = (string) preg_replace('/^group = .*$/m', 'group = '.get_current_user(), $conf);
        $conf = (string) preg_replace('/^listen\.owner = .*$/m', 'listen.owner = '.get_current_user(), $conf);
        $conf = (string) preg_replace('/^listen\.group = .*$/m', 'listen.group = '.get_current_user(), $conf);
        $conf = str_replace('/var/log/php/', $dir.'/logs/', $conf);
        $conf = "[global]\npid = {$dir}/fpm.pid\nerror_log = {$dir}/logs/fpm.log\ndaemonize = no\n\n".$conf;

        $file = $dir.'/php-fpm.conf';
        file_put_contents($file, $conf);

        $output = [];
        $exit = 0;
        exec(escapeshellarg($binary).' -t -y '.escapeshellarg($file).' 2>&1', $output, $exit);

        exec('rm -rf '.escapeshellarg($dir));

        $this->assertSame(
            0,
            $exit,
            "php-fpm -t refused the shipped pool configuration:\n".implode("\n", $output)
        );
    }

    /**
     * Keys of the non-comment assignments in an FPM-style ini file, with the
     * `php_admin_value[x]` form reduced to its base name.
     *
     * @return array<int, string>
     */
    private function activeAssignments(string $relative): array
    {
        $keys = [];

        foreach (file(base_path($relative), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, ';') || str_starts_with($trimmed, '[')) {
                continue;
            }

            $keys[] = trim((string) preg_replace('/\[.*$/u', '', trim(explode('=', $trimmed, 2)[0])));
        }

        return $keys;
    }

    public function test_the_deploy_script_tests_the_installed_pool_before_going_live(): void
    {
        $deploy = (string) file_get_contents(base_path('deploy/deploy.sh'));

        $this->assertStringContainsString('"$PHP_FPM_BIN" -t -y "$pool"', $deploy, 'the deploy script must ask fpm itself whether the pool parses');
        $this->assertStringContainsString('PHP_FPM_POOL', $deploy, 'the pool path must be overridable so the test can be run against a non-standard install');
    }

    private function findPhpFpm(): ?string
    {
        $configured = getenv('PHP_FPM_BIN');
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $candidates = [
            'php-fpm',
            '/usr/sbin/php-fpm'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            '/usr/bin/php-fpm'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            base_path('.runtime/php/php-fpm'),
        ];

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }

            $resolved = trim((string) shell_exec('command -v '.escapeshellarg($candidate).' 2>/dev/null'));
            if ($resolved !== '' && is_executable($resolved)) {
                return $resolved;
            }
        }

        return null;
    }
}
