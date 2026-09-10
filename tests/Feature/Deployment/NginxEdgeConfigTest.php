<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * The edge configuration is versioned in the repository, so *something* has to put it
 * on the host. Before this test existed, nothing did.
 *
 * deploy.sh reloaded nginx with `nginx -t && systemctl reload nginx || true` and the
 * reload succeeded — reloading the host's existing file. `deploy/nginx/toefl-house.conf`
 * is never installed by any step in the repository, so anything changed there in a
 * release (the Content-Security-Policy added at Gate E being the live example) reaches
 * PHP responses via middleware and never reaches the web server. That matters precisely
 * for the responses PHP cannot touch: static assets, 404 pages, 5xx pages. `|| true`
 * hid it, and a rehearsal cluster with no nginx binary at all could not reveal the gap
 * either — which is how it survived the certification.
 *
 * These tests execute deploy/lib/nginx-edge-config.sh in a temporary directory against a
 * stub `nginx`, so each case is about what the file on disk and the reload marker say, not
 * about what the script appears to do:
 *
 *   * unset NGINX_CONF_DEST warns instead of silently "succeeding";
 *   * an identical installed file is not reloaded (the release flip needs no reload,
 *     because `root` is the `current` symlink and nginx resolves it per request);
 *   * a config that fails `nginx -t` after being installed is rolled back and the deploy
 *     stops, rather than leaving a broken edge in place of a working one;
 *   * a host whose *existing* config is already invalid is refused before anything is
 *     overwritten, so the failure stays attributable;
 *   * and a config that validates but cannot be reloaded is also put back — an installed
 *     file that no process is reading is the same "reported but not true" failure that
 *     Gate D recorded for PHP-FPM.
 */
final class NginxEdgeConfigTest extends TestCase
{
    private string $lib;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lib = base_path('deploy/lib/nginx-edge-config.sh');

        $this->assertTrue(is_file($this->lib), 'the edge-config helper must ship next to the other deploy helpers');
    }

    public function test_the_deploy_script_sources_the_helper_it_relies_on(): void
    {
        $script = (string) file_get_contents(base_path('deploy/deploy.sh'));
        $helper = (string) file_get_contents($this->lib);

        $this->assertStringContainsString('source "$SCRIPT_DIR/lib/nginx-edge-config.sh"', $script);
        $this->assertStringContainsString(
            'install_nginx_edge_config "$RELEASE_DIR/deploy/nginx/toefl-house.conf"',
            $script,
            'the install must run against the activated release, not the working tree, or a rollback leaves the newer edge config live'
        );
        $this->assertStringContainsString('restore_nginx_edge_config', $script, 'the rollback path must undo the edge change too');
        $this->assertStringContainsString(
            "preflight_nginx_edge_config \"\$RELEASE_DIR/deploy/nginx/toefl-house.conf\"\n\n# 2. Dependencies",
            $script,
            'missing edge input must stop before build/migration, not after the release becomes live'
        );
        $this->assertStringContainsString('render_nginx_edge_config "$src" "$tmp"', $helper, 'host-specific values must be rendered before nginx validates the release template');
        $this->assertStringContainsString('NGINX_SERVER_NAME="${NGINX_SERVER_NAME:-}"', $script, 'the one managed-edge hostname must be an explicit host fact');
        $this->assertStringNotContainsString(
            'systemctl reload nginx 2>/dev/null || true',
            $script,
            'a reload that ends in `|| true` reports success while the host keeps running an old header set'
        );
    }

    public function test_a_managed_release_template_is_rendered_before_install_and_reload(): void
    {
        $dir = $this->fixture();
        $dest = $dir.'/host/toefl-house.conf';
        $root = $dir.'/managed-root';

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'NGINX_SERVER_NAME' => 'school.example',
            'DEPLOY_ROOT' => $root,
            'NGINX_TLS_CERTIFICATE' => '/etc/ssl/certs/school.example.fullchain.pem',
            'NGINX_TLS_CERTIFICATE_KEY' => '/etc/ssl/private/school.example.key',
            'NGINX_RELOAD_CMD' => "touch {$dir}/reloaded",
            'SRC' => base_path('deploy/nginx/toefl-house.conf'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(0, $run['exit'], $run['out']);
        $this->assertFileExists($dir.'/reloaded', 'the rendered configuration must be made live in the same guarded install');
        $rendered = (string) file_get_contents($dest);
        $this->assertStringNotContainsString('__TOEFL_', $rendered, 'nginx cannot expand shell placeholders at runtime');
        $this->assertSame(2, substr_count($rendered, 'server_name school.example;'));
        $this->assertStringContainsString("root {$root}/current/public;", $rendered);
        $this->assertStringContainsString("root {$root}/acme-challenge;", $rendered);
        $this->assertStringContainsString('ssl_certificate     /etc/ssl/certs/school.example.fullchain.pem;', $rendered);
        $this->assertStringContainsString('ssl_certificate_key /etc/ssl/private/school.example.key;', $rendered);
        $this->assertStringContainsString('return 301 https://$host$request_uri;', $rendered, 'the renderer may substitute only its own tokens, never nginx runtime variables');
    }

    public function test_a_hostile_template_value_is_rejected_before_the_existing_edge_is_touched(): void
    {
        $dir = $this->fixture();
        $dest = $dir.'/host/toefl-house.conf';
        $existing = '# known-good existing edge';
        $this->write($dest, $existing);

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'NGINX_SERVER_NAME' => 'school.example; return 200',
            'SRC' => base_path('deploy/nginx/toefl-house.conf'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(1, $run['exit'], $run['out']);
        $this->assertStringContainsString('NGINX_SERVER_NAME must be one hostname', $run['out']);
        $this->assertSame($existing."\n", (string) file_get_contents($dest));
    }

    public function test_managed_edge_input_is_preflighted_before_an_install_is_attempted(): void
    {
        $dir = $this->fixture();
        $dest = $dir.'/host/toefl-house.conf';

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'SRC' => base_path('deploy/nginx/toefl-house.conf'),
        ], 'preflight_nginx_edge_config "$SRC"');

        $this->assertSame(1, $run['exit'], $run['out']);
        $this->assertStringContainsString('NGINX_SERVER_NAME must be one hostname', $run['out']);
        $this->assertStringContainsString('before the deployment changed application state', $run['out']);
        $this->assertFileDoesNotExist($dest, 'input preflight must not touch the live edge file');
    }

    public function test_the_stdout_renderer_refuses_unknown_tokens_without_leaking_a_partial_config(): void
    {
        $dir = $this->fixture();
        $template = $dir.'/conf/unknown-token.conf';
        $this->write($template, 'server { # __TOEFL_UNKNOWN_TOKEN__ }');

        $run = $this->execute($dir, ['SRC' => $template], 'render_nginx_edge_config "$SRC" /dev/stdout');

        $this->assertSame(1, $run['exit'], $run['out']);
        $this->assertStringContainsString('nginx template contains an unrendered TOEFL placeholder', $run['out']);
        $this->assertStringNotContainsString('__TOEFL_UNKNOWN_TOKEN__', $run['out'], 'stdout must stay empty when template validation fails');
    }

    public function test_the_certbot_deploy_hook_validates_before_reloading_nginx(): void
    {
        $dir = $this->fixture();
        $log = $dir.'/certbot-hook.log';
        $nginx = $dir.'/certbot-nginx';
        $systemctl = $dir.'/certbot-systemctl';
        $this->write($nginx, <<<'SH'
#!/bin/sh
printf 'nginx %s\n' "$*" >> "$HOOK_LOG"
exit "${HOOK_NGINX_EXIT:-0}"
SH);
        $this->write($systemctl, <<<'SH'
#!/bin/sh
printf 'systemctl %s\n' "$*" >> "$HOOK_LOG"
exit "${HOOK_SYSTEMCTL_EXIT:-0}"
SH);
        chmod($nginx, 0755);
        chmod($systemctl, 0755);

        $hook = base_path('deploy/certbot/reload-nginx.sh');
        $common = [
            'HOOK_LOG' => $log,
            'NGINX_BIN' => $nginx,
            'SYSTEMCTL_BIN' => $systemctl,
        ];

        $reloaded = $this->executeScript($hook, $common);
        $this->assertSame(0, $reloaded['exit'], $reloaded['out']);
        $this->assertSame("nginx -t\nsystemctl reload nginx\n", (string) file_get_contents($log));
        $this->assertStringContainsString('nginx configuration verified and reloaded via systemctl', $reloaded['out']);

        file_put_contents($log, '');
        $fallback = $this->executeScript($hook, $common + ['HOOK_SYSTEMCTL_EXIT' => '1']);
        $this->assertSame(0, $fallback['exit'], $fallback['out']);
        $this->assertSame("nginx -t\nsystemctl reload nginx\nnginx -s reload\n", (string) file_get_contents($log));
        $this->assertStringContainsString('reloaded via nginx signal', $fallback['out']);

        file_put_contents($log, '');
        $blocked = $this->executeScript($hook, $common + ['HOOK_NGINX_EXIT' => '1']);
        $this->assertSame(1, $blocked['exit'], $blocked['out']);
        $this->assertStringContainsString('nginx configuration test failed', $blocked['out']);
        $this->assertSame("nginx -t\n", (string) file_get_contents($log), 'a failed syntax test must block the reload');
    }

    public function test_acme_bootstrap_and_renewal_keep_the_http_exception_narrow_and_persistent(): void
    {
        $full = (string) file_get_contents(base_path('deploy/nginx/toefl-house.conf'));
        $bootstrap = (string) file_get_contents(base_path('deploy/nginx/toefl-house-acme-bootstrap.conf'));
        $hook = (string) file_get_contents(base_path('deploy/certbot/reload-nginx.sh'));
        $runbook = (string) file_get_contents(base_path('docs/operations/production-deployment.md'));

        $challenge = <<<'NGINX'
location ^~ /.well-known/acme-challenge/ {
        root __TOEFL_DEPLOY_ROOT__/acme-challenge;
        default_type text/plain;
        try_files $uri =404;
    }
NGINX;
        $this->assertStringContainsString($challenge, $full);
        $this->assertStringContainsString($challenge, $bootstrap);
        $this->assertSame(2, substr_count($full, 'location ~ /\. {'), 'both HTTP and HTTPS listeners must still deny other dotfiles');
        $this->assertStringContainsString("location / {\n        return 301 https://\$host\$request_uri;", $full);
        $this->assertStringContainsString("location / {\n        return 404;", $bootstrap, 'the one-time bootstrap may not serve or redirect the application');
        $this->assertStringNotContainsString('fastcgi_pass', $bootstrap, 'ACME bootstrap must not reach PHP');
        $this->assertStringContainsString('__TOEFL_SERVER_NAME__', $full, 'the checked-in template must retain a renderer-only hostname token');
        $this->assertTrue(is_executable(base_path('deploy/render-nginx-config.sh')));
        $this->assertTrue(is_executable(base_path('deploy/certbot/reload-nginx.sh')));
        $this->assertStringContainsString('"$NGINX_BIN" -t', $hook);
        $this->assertStringContainsString('"$SYSTEMCTL_BIN" reload nginx', $hook);
        $this->assertStringContainsString('elif "$NGINX_BIN" -s reload; then', $hook);
        $validateAt = strpos($hook, '"$NGINX_BIN" -t');
        $reloadAt = strpos($hook, '"$SYSTEMCTL_BIN" reload nginx');
        $this->assertNotFalse($validateAt);
        $this->assertNotFalse($reloadAt);
        $this->assertLessThan(
            $reloadAt,
            $validateAt,
            'the Certbot hook must validate the current edge configuration before it asks nginx to reload'
        );
        $this->assertStringContainsString('toefl-house-acme-bootstrap.conf', $runbook);
        $this->assertStringContainsString('certbot certonly --webroot', $runbook);
        $this->assertStringContainsString('renewal-hooks/deploy/10-toefl-house-nginx-reload', $runbook);
    }

    public function test_an_unmanaged_edge_is_reported_instead_of_assumed_ok(): void
    {
        $dir = $this->fixture();

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => '',
            'SRC' => $this->conf($dir, 'release'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(0, $run['exit'], $run['out']);
        $this->assertStringContainsString('NGINX_CONF_DEST is unset', $run['out']);
        $this->assertStringContainsString('static files, 404s, 5xx pages', $run['out']);
        $this->assertFileDoesNotExist($dir.'/host/toefl-house.conf', 'nothing may be written when the operator did not ask for it');
    }

    public function test_the_release_config_is_installed_and_the_edge_reloaded(): void
    {
        $dir = $this->fixture();
        $dest = $dir.'/host/toefl-house.conf';
        $this->write($dest, "# the host's own config\n");

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'RELEASE_ID' => 'r1',
            'NGINX_RELOAD_CMD' => "touch {$dir}/reloaded",
            'SRC' => $this->conf($dir, 'release'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(0, $run['exit'], $run['out']);
        $this->assertStringContainsString('edge config installed from the release', $run['out']);
        $this->assertStringContainsString('nginx reloaded via NGINX_RELOAD_CMD', $run['out']);
        $this->assertFileExists($dir.'/reloaded', 'the installed config must be made live in the same run');
        $this->assertStringContainsString("server_name release;\n", (string) file_get_contents($dest));
        $this->assertStringContainsString('add_header Content-Security-Policy', (string) file_get_contents($dest));
        $this->assertSame('0644', substr(sprintf('%o', (int) fileperms($dest)), -4), 'the web server user has to read it');
        $this->assertFileExists($dir.'/host/toefl-house.conf.pre-r1', 'the replaced file must be recoverable');
    }

    public function test_an_unchanged_config_is_not_reloaded(): void
    {
        $dir = $this->fixture();
        $conf = $this->conf($dir, 'release');
        $dest = $dir.'/host/toefl-house.conf';
        copy($conf, $dest);

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'NGINX_RELOAD_CMD' => "touch {$dir}/reloaded",
            'SRC' => $conf,
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(0, $run['exit'], $run['out']);
        $this->assertStringContainsString('no reload needed', $run['out']);
        $this->assertFileDoesNotExist($dir.'/reloaded', 'a reload of an identical config is churn on a live edge; only the release flip happens, and nginx resolves `root` per request');
    }

    public function test_a_config_nginx_rejects_is_put_back_and_the_deploy_stops(): void
    {
        $dir = $this->fixture();
        $dest = $dir.'/host/toefl-house.conf';
        $this->write($dest, "# working config\nserver {\n    server_name working;\n}\n");

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'RELEASE_ID' => 'r2',
            'NGINX_RELOAD_CMD' => "touch {$dir}/reloaded",
            'SRC' => $this->conf($dir, 'bogus'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(1, $run['exit'], $run['out']);
        $this->assertStringContainsString('failed nginx -t', $run['out']);
        $this->assertStringContainsString('# working config', (string) file_get_contents($dest), 'a broken release config may not replace a working edge');
        $this->assertStringNotContainsString('BOGUS', (string) file_get_contents($dest));
    }

    public function test_a_host_whose_current_config_is_already_invalid_is_refused_before_overwriting(): void
    {
        $dir = $this->fixture();
        $dest = $dir.'/host/toefl-house.conf';
        $this->write($dest, "# already broken on the host\nBOGUS-directive here;\n");

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'SRC' => $this->conf($dir, 'release'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(1, $run['exit'], $run['out']);
        $this->assertStringContainsString('already fails', $run['out']);
        $this->assertStringContainsString('BOGUS-directive here;', (string) file_get_contents($dest), 'overwriting an already-broken config would make the pre-existing failure look like the deploy caused it');
    }

    public function test_a_config_that_validates_but_cannot_be_reloaded_is_reverted(): void
    {
        $dir = $this->fixture();
        $dest = $dir.'/host/toefl-house.conf';
        $this->write($dest, "# working config\nserver {\n    server_name working;\n}\n");

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dest,
            'RELEASE_ID' => 'r3',
            'NGINX_RELOAD_CMD' => 'false',
            'SRC' => $this->conf($dir, 'release'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(1, $run['exit'], $run['out']);
        $this->assertStringContainsString('NGINX_RELOAD_CMD', $run['out']);
        $this->assertStringContainsString('# working config', (string) file_get_contents($dest), 'an installed file no process is reading is not a deployed config');
    }

    public function test_the_helper_refuses_when_no_nginx_exists_to_validate_with(): void
    {
        $dir = $this->fixture();

        $run = $this->execute($dir, [
            'NGINX_CONF_DEST' => $dir.'/host/toefl-house.conf',
            'PATH_NO_NGINX' => '1',
            'SRC' => $this->conf($dir, 'release'),
        ], 'install_nginx_edge_config "$SRC"');

        $this->assertSame(1, $run['exit'], $run['out']);
        $this->assertStringContainsString('no nginx binary', $run['out']);
        $this->assertFileDoesNotExist($dir.'/host/toefl-house.conf', 'a config that cannot be validated must not be installed');
    }

    /* --- fixtures ------------------------------------------------------------ */

    private function fixture(): string
    {
        $dir = sys_get_temp_dir().'/edge-config-'.bin2hex(random_bytes(4));
        mkdir($dir.'/bin', 0777, true);
        mkdir($dir.'/bin-bare', 0777, true);
        mkdir($dir.'/host', 0777, true);
        mkdir($dir.'/conf', 0777, true);

        // Every case runs with PATH set to exactly one of these two directories and
        // nothing else. Inheriting the ambient PATH made this test depend on the host
        // twice over — it passed in a normal checkout and failed on a machine whose
        // PATH happened to contain an `nginx` (measured: a rehearsal stub in a
        // directory exported for the deploy run), and `systemctl` coming and going
        // would silently change which reload path the helper takes.
        foreach (['sh', 'bash', 'cp', 'mv', 'rm', 'chmod', 'cmp', 'grep', 'sed', 'touch', 'date', 'dirname', 'cat', 'printf', 'mktemp'] as $tool) {
            $resolved = trim((string) shell_exec('command -v '.escapeshellarg($tool).' 2>/dev/null'));
            if ($resolved !== '') {
                @symlink($resolved, $dir.'/bin/'.$tool);
                @symlink($resolved, $dir.'/bin-bare/'.$tool);
            }
        }

        // Stub nginx: `-t` passes unless the *installed* config contains the marker the
        // bogus fixture carries; `-s reload` records that it was asked.
        $this->write($dir.'/bin/nginx', <<<'SH'
        #!/bin/sh
        : "${NGINX_CONF_DEST:?stub nginx needs NGINX_CONF_DEST to know what to validate}"
        case "$1" in
            -t) grep -q 'BOGUS' "$NGINX_CONF_DEST" && exit 1; exit 0 ;;
            -s) touch "${NGINX_CONF_DEST%/*}/reloaded-by-nginx-signal"; exit 0 ;;
        esac
        exit 0
        SH);
        chmod($dir.'/bin/nginx', 0755);

        return $dir;
    }

    private function conf(string $dir, string $kind): string
    {
        $body = $kind === 'bogus'
            ? "server {\n    server_name release;\n    BOGUS unknown-directive;\n}\n"
            : "server {\n    server_name release;\n    add_header Content-Security-Policy \"default-src 'self'\" always;\n}\n";

        $this->write($dir."/conf/$kind.conf", $body);

        return $dir."/conf/$kind.conf";
    }

    private function write(string $path, string $contents): void
    {
        file_put_contents($path, $contents."\n");
    }

    /** @param array<string,string> $env */
    private function execute(string $dir, array $env, string $call): array
    {
        $lines = ['set -uo pipefail'];
        $lines[] = sprintf(
            'PATH=%s',
            escapeshellarg($dir.(($env['PATH_NO_NGINX'] ?? '') === '1' ? '/bin-bare' : '/bin'))
        );
        unset($env['PATH_NO_NGINX']);

        foreach ([
            'NGINX_CONF_DEST', 'RELEASE_ID', 'NGINX_RELOAD_CMD', 'SRC',
            'DEPLOY_ROOT', 'NGINX_SERVER_NAME', 'NGINX_TLS_CERTIFICATE', 'NGINX_TLS_CERTIFICATE_KEY',
        ] as $key) {
            if (array_key_exists($key, $env)) {
                $lines[] = sprintf('%s=%s', $key, escapeshellarg($env[$key]));
            }
        }

        // The stub nginx has to see NGINX_CONF_DEST (that is the file it validates) and
        // NGINX_RELOAD_CMD has to be usable by `bash -c`, so both are exported. Without
        // this the stub greps an empty path, reports "config valid" for everything, and
        // every assertion about rejection passes vacuously.
        $lines[] = 'export NGINX_CONF_DEST NGINX_RELOAD_CMD';

        $lines[] = sprintf('source %s', escapeshellarg($this->lib));
        $lines[] = $call;

        $output = [];
        $exit = 0;
        exec('bash -c '.escapeshellarg(implode("\n", array_filter($lines, static fn (string $l): bool => $l !== ''))).' 2>&1', $output, $exit);

        return ['exit' => $exit, 'out' => implode("\n", $output)];
    }

    /** @param array<string,string> $env */
    private function executeScript(string $script, array $env): array
    {
        $assignments = [];
        foreach ($env as $key => $value) {
            $assignments[] = $key.'='.escapeshellarg($value);
        }

        $output = [];
        $exit = 0;
        exec(implode(' ', $assignments).' '.escapeshellarg($script).' 2>&1', $output, $exit);

        return ['exit' => $exit, 'out' => implode("\n", $output)];
    }
}
