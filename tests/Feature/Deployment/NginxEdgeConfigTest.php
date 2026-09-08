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

        $this->assertStringContainsString('source "$SCRIPT_DIR/lib/nginx-edge-config.sh"', $script);
        $this->assertStringContainsString(
            'install_nginx_edge_config "$RELEASE_DIR/deploy/nginx/toefl-house.conf"',
            $script,
            'the install must run against the activated release, not the working tree, or a rollback leaves the newer edge config live'
        );
        $this->assertStringContainsString('restore_nginx_edge_config', $script, 'the rollback path must undo the edge change too');
        $this->assertStringNotContainsString(
            'systemctl reload nginx 2>/dev/null || true',
            $script,
            'a reload that ends in `|| true` reports success while the host keeps running an old header set'
        );
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
    }

    /* --- fixtures ------------------------------------------------------------ */

    private function fixture(): string
    {
        $dir = sys_get_temp_dir().'/edge-config-'.bin2hex(random_bytes(4));
        mkdir($dir.'/bin', 0777, true);
        mkdir($dir.'/host', 0777, true);
        mkdir($dir.'/conf', 0777, true);

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
        // The no-nginx case deliberately keeps the inherited PATH: this environment has no
        // nginx binary, which is exactly the situation the refusal is for.
        if (($env['PATH_NO_NGINX'] ?? '') !== '1') {
            $lines[] = sprintf('PATH=%s', escapeshellarg($dir.'/bin:'.(getenv('PATH') ?: '/usr/bin:/bin')));
        }
        unset($env['PATH_NO_NGINX']);

        foreach (['NGINX_CONF_DEST', 'RELEASE_ID', 'NGINX_RELOAD_CMD', 'SRC'] as $key) {
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
}
