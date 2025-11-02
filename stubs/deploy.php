<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/npm.php';

// Project name
set('application', 'forge-app');

// Project repository
set('repository', 'git@github.com:skylence-be/skylence.be.git'); // UPDATE THIS WITH YOUR REPO

// PHP binary
set('bin/php', '/usr/bin/php8.4');
set('bin/composer', 'composer');

// Allocate tty for git clone
set('git_tty', true);

// Shared files/dirs between deploys
set('shared_files', [
    '.env',
]);

set('shared_dirs', [
    'storage',
]);

// Writable dirs by web server
set('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

// Number of releases to keep (for rollback)
set('keep_releases', 2);

// Windows-specific SSH configuration
if (PHP_OS_FAMILY === 'Windows') {
    set('ssh_type', 'native'); // or 'phpseclib' if native doesn't work
    set('ssh_multiplexing', false);
}

// Hosts
host('production')
    ->set('hostname', '3.67.44.86')
    ->set('remote_user', 'forge')
    ->set('port', 22)
    ->set('deploy_path', '/home/forge/deployer/skylence.be')
    ->set('branch', 'main')
    ->set('ssh_multiplexing', false); // Disable multiplexing for compatibility

// Only set identity file for local Windows development
if (PHP_OS_FAMILY === 'Windows' && ! getenv('GITHUB_ACTIONS')) {
    host('production')->set('identity_file', 'C:\\Users\\jonas\\.ssh\\id_personal');
}

// Tasks

desc('Update release version in .env');
task('deploy:update_release_version', function () {
    cd('{{release_path}}');

    // Get the release name (timestamp) from Deployer
    $release = get('release_name');

    // Update or add RELEASE_VERSION in .env file
    if (test('grep -q "^RELEASE_VERSION=" .env')) {
        run("sed -i 's/^RELEASE_VERSION=.*/RELEASE_VERSION=$release/' .env");
    } else {
        run("echo 'RELEASE_VERSION=$release' >> .env");
    }

    // Also add a cache busting timestamp for views
    $timestamp = date('YmdHis');
    if (test('grep -q "^VIEW_CACHE_BUST=" .env')) {
        run("sed -i 's/^VIEW_CACHE_BUST=.*/VIEW_CACHE_BUST=$timestamp/' .env");
    } else {
        run("echo 'VIEW_CACHE_BUST=$timestamp' >> .env");
    }

    // Silent - no output
});

desc('Build assets');
task('deploy:build', function () {
    cd('{{release_path}}');

    // Clean build cache quickly
    run('rm -rf public/build node_modules/.vite');

    // Install dependencies (use --frozen-lockfile for speed)
    run('pnpm install --frozen-lockfile --silent');

    // Build assets with environment variables
    run('export $(grep -v "^#" .env | xargs) && pnpm run build --silent 2>/dev/null || pnpm run build');

    // Clean up dev dependencies
    run('pnpm prune --prod --silent');

    // Quick verification
    run('test -f public/build/manifest.json');
});

desc('Run database migrations');
task('artisan:migrate', function () {
    cd('{{release_path}}');
    run('{{bin/php}} artisan migrate --force --quiet');
});

desc('Cache Laravel config, routes and views');
task('artisan:optimize', function () {
    cd('{{release_path}}');
    // Cache everything in parallel for speed
    run('{{bin/php}} artisan config:cache --quiet & {{bin/php}} artisan route:cache --quiet & {{bin/php}} artisan view:cache --quiet & wait');
});

desc('Restart queue workers');
task('artisan:queue:restart', function () {
    cd('{{release_path}}');
    run('{{bin/php}} artisan queue:restart --quiet');
});

desc('Restart Pulse');
task('artisan:pulse:restart', function () {
    cd('{{current_path}}');
    run('{{bin/php}} artisan pulse:restart --quiet 2>/dev/null || true');
});

desc('Restart Laravel Reverb');
task('artisan:reverb:restart', function () {
    cd('{{current_path}}');
    run('{{bin/php}} artisan reverb:restart --quiet 2>/dev/null || true');
});

desc('Restart PHP-FPM');
task('php-fpm:restart', function () {
    // PHP-FPM is managed by the system, we can't restart it without sudo
    // It will pick up changes automatically when files change
    // PHP-FPM restart skipped (requires sudo)
});

desc('Install vendors');
task('deploy:vendors', function () {
    cd('{{release_path}}');

    // Ensure bootstrap/cache directory exists and is writable
    run('mkdir -p bootstrap/cache');
    run('chmod 775 bootstrap/cache');

    // Fast composer install
    run('{{bin/composer}} install --no-dev --no-interaction --prefer-dist --optimize-autoloader --classmap-authoritative --quiet');
});

// Main deploy task
desc('Deploy the application');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:shared',  // Creates symlinks to .env
    'deploy:update_release_version',  // Update RELEASE_VERSION in .env
    'deploy:build',   // Build assets
    'artisan:storage:link',
    'artisan:migrate',
    'artisan:optimize',  // Cache config, routes, views
    'deploy:publish',
]);

// Hooks
after('deploy:failed', 'deploy:unlock');

// After symlink is switched, restart all services
after('deploy:symlink', function () {
    // Ensure the symlink from skylence.be to current is correct
    // Remove whatever exists (symlink or directory)
    run('cd /home/forge && ([ -L skylence.be ] && rm -f skylence.be || true)');
    run('cd /home/forge && ([ -d skylence.be ] && [ ! -L skylence.be ] && rm -rf skylence.be || true)');
    // Create the symlink
    run('cd /home/forge && ln -sfn /home/forge/deployer/skylence.be/current skylence.be');

    // Restart queue workers (they need to pick up new code)
    run('{{bin/php}} {{current_path}}/artisan queue:restart --quiet 2>/dev/null || true');

    // Force stop Octane to ensure fresh restart with new manifest
    run('{{bin/php}} {{current_path}}/artisan octane:stop --quiet 2>/dev/null || true');
});

// Rollback task
desc('Rollback to previous release');
task('rollback', function () {
    $releases = get('releases_list');
    if (count($releases) < 2) {
        writeln('<error>No previous releases to rollback to.</error>');

        return;
    }

    $previousRelease = $releases[count($releases) - 2];

    // Rolling back to release: $previousRelease
    run("ln -nfs {{deploy_path}}/releases/$previousRelease {{deploy_path}}/current");

    // Re-cache after rollback
    cd('{{deploy_path}}/current');
    run('{{bin/php}} artisan config:cache --quiet');
    run('{{bin/php}} artisan route:cache --quiet');
    run('{{bin/php}} artisan view:cache --quiet');

    // Restart services
    invoke('artisan:queue:restart');

    // Rollback complete
});

// Additional Forge-specific task
