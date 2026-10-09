<?php

declare(strict_types=1);

namespace Deployer;

use Deployer\Exception\GracefulShutdownException;

/*
 * Deploy to the Combell subsite skigame.yappa.be:
 * the working tree is uploaded, public/ is copied to the www root next to deploy/,
 * and www/index.php points to deploy/current. Run it with `task deploy:staging`.
 *
 * Before the first deploy, on the server: deploy/shared/.env with APP_ENV=prod,
 * APP_SECRET, DEFAULT_URI and the DATABASE_URL of the Combell database.
 */
require 'recipe/symfony.php';

import(__DIR__.'/config/servers.yaml');

set('application', 'yapland-skigame');
set('default_timeout', 600);
set('keep_releases', 3);

set('shared_files', ['.env']);
set('shared_dirs', ['var/log', 'public/uploads']);
set('writable_dirs', []);
set('writable_mode', 'chmod');
set('use_relative_symlink', false);

// Combell: always the php and composer the account is set to
set('bin/php', static fn () => 'PHPBIN=`which php`; PHPPATH=`readlink -f $PHPBIN`; eval $PHPPATH');
set('bin/composer', static fn () => 'PHPBIN=`which php`; PHPPATH=`readlink -f $PHPBIN`; COMPOSERBIN=`which composer`; eval "$PHPPATH $COMPOSERBIN"');
set('composer_options', '--verbose --no-progress --no-interaction --optimize-autoloader --no-scripts');
set('release_name', static fn () => date('YmdHis'));

// ── Code: upload the working tree instead of cloning ─────────────────────────
//
// The server does not need access to this repository. What stays home is in .deployignore.
task('deploy:check_tree', static function (): void {
    if ('' !== trim(runLocally('git status --porcelain --untracked-files=no'))) {
        throw new GracefulShutdownException('De werkboom heeft niet-gecommitte wijzigingen: commit of stash ze eerst.');
    }
})->desc('Check that the local tree is committed');

task('deploy:update_code', static function (): void {
    upload(__DIR__.'/', '{{release_path}}', [
        'options' => ['--exclude-from='.__DIR__.'/.deployignore', '--delete'],
    ]);
    run(\sprintf('echo %s > {{release_path}}/REVISION', escapeshellarg(trim(runLocally('git rev-parse HEAD')))));
})->desc('Upload the working tree to the release');

before('deploy:prepare', 'deploy:check_tree');

// composer runs with --no-scripts, so the AssetMapper files are compiled here
task('deploy:asset_map', static function (): void {
    run('cd {{release_path}} && {{bin/console}} asset-map:compile');
})->desc('Compile the AssetMapper files into public/assets');

after('deploy:vendors', 'deploy:asset_map');

task('deploy:copy_index', static function (): void {
    run('mv -f {{release_path}}/public/index_combell.php {{release_path}}/public/index.php');
})->desc('Use the Combell front controller');

after('deploy:shared', 'deploy:copy_index');

// ── Basic auth on the admin ─────────────────────────────────────────────────
//
// Only /admin, /login and /logout: clients open /claim, /ski and /afmelden without it.
// THE_REQUEST and not REQUEST_URI: the latter becomes /index.php after the rewrite.
// The block goes on top of the release's .htaccess, before it is copied to www;
// the committed public/.htaccess stays clean. .htpasswd sits next to www/, outside the docroot.
task('deploy:basic_auth', static function (): void {
    if (!has('basic_auth')) {
        return;
    }

    $htpasswd = '{{deploy_path}}/../.htpasswd';
    run(\sprintf('echo %s > %s && chmod 644 %s', escapeshellarg(get('basic_auth')), $htpasswd, $htpasswd));

    $block = <<<'HTACCESS'
        # ── Basic auth on the admin (added by deploy:basic_auth, see deploy.php) ──
        <If "%%{THE_REQUEST} =~ m#^[A-Z]+ /(admin|login|logout)([/?\s]|$)#">
            AuthType Basic
            AuthName "Yapland skigame admin"
            AuthUserFile %s
            Require valid-user
        </If>

        HTACCESS;

    $htaccess = '{{release_path}}/public/.htaccess';
    $authUserFile = trim(run('readlink -f '.$htpasswd));
    run(\sprintf(
        'printf "%%s\n" %s | cat - %s > %s.tmp && mv %s.tmp %s',
        escapeshellarg(\sprintf($block, $authUserFile)),
        $htaccess,
        $htaccess,
        $htaccess,
        $htaccess,
    ));
})->desc('Protect the admin with basic auth');

// ── Cron: mail queue and the score purge ────────────────────────────────────
//
// Combell only runs the account's crontab: ~/.crontab in the account root (what `crontab -l`
// shows), not a .crontab in the subsite. etc/crontab goes in there between markers, so a
// redeploy replaces our block and leaves the other sites' jobs alone. `crontab -T` validates it;
// if that fails, the previous file comes back. The previous file is also kept next to www/.
task('deploy:install_crontab', static function (): void {
    $marker = 'yapland-skigame';
    $crontab = '~/.crontab';
    $backup = '{{deploy_path}}/../crontab.before-deploy';
    run(\sprintf('touch %1$s && cp %1$s %2$s', $crontab, $backup));
    run(\sprintf(
        '{ sed "/#### START %2$s ####/,/#### END %2$s ####/d" %3$s; echo "#### START %2$s ####"; cat {{release_path}}/etc/crontab; echo "#### END %2$s ####"; } > %1$s',
        $crontab,
        $marker,
        $backup,
    ));
    run(\sprintf('crontab -T || { cp %s %s; exit 1; }', $backup, $crontab));
    // The first deploys wrote a .crontab in the subsite that Combell never read
    run('rm -f {{deploy_path}}/../.crontab');
})->desc('Install etc/crontab into the account crontab');

before('deploy:symlink', 'deploy:install_crontab');

// public/ → www, after everything in public/ is final
task('deploy:rsync_public_to_www', static function (): void {
    run('rsync -rtul --delete {{release_path}}/public/. {{deploy_path}}/../www');
})->desc('Copy public/ to the www root');

before('deploy:rsync_public_to_www', 'deploy:basic_auth');
before('deploy:symlink', 'database:migrate');
before('deploy:symlink', 'deploy:rsync_public_to_www');

task('deploy:reload_php', static function (): void {
    run('reloadPHP.sh');
})->desc('Reload PHP-FPM');

after('deploy:symlink', 'deploy:reload_php');

task('deploy:unlock_own', static function (): void {
    $lock = '{{deploy_path}}/.dep/deploy.lock';
    if (test("[ -f {$lock} ]") && trim(run("cat {$lock}")) === get('user')) {
        run("rm -f {$lock}");
    }
})->desc('Remove the deploy lock if this deployer set it');

after('deploy:failed', 'deploy:unlock_own');
