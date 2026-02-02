#!/usr/bin/env php
<?php
/**
 * Project Health Check Script
 * Verify Solaris Config Editor is ready for publication
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     Solaris Config Editor - Project Health Check               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$projectRoot = __DIR__;
$checks = [];

// Check PHP version
echo "🔍 Checking environment...\n";
$phpVersion = phpversion();
$checks['PHP Version'] = version_compare($phpVersion, '8.2.0', '>=') ? "✅ {$phpVersion}" : "❌ {$phpVersion} (requires 8.2+)";

// Check required files
echo "📋 Checking required files...\n";
$requiredFiles = [
    'composer.json',
    'LICENSE',
    'README.md',
    'src/ConfigEditor.php',
    'tests/ConfigEditorTest.php',
    'phpunit.xml',
    '.gitignore',
];

foreach ($requiredFiles as $file) {
    $path = "{$projectRoot}/{$file}";
    $exists = file_exists($path);
    $checks[$file] = $exists ? '✅' : '❌ Missing';
}

// Check directories
echo "📁 Checking directories...\n";
$requiredDirs = [
    'src',
    'tests',
];

foreach ($requiredDirs as $dir) {
    $path = "{$projectRoot}/{$dir}";
    $exists = is_dir($path);
    $checks["Directory: {$dir}"] = $exists ? '✅' : '❌ Missing';
}

// Check composer.json validity
echo "⚙️  Validating composer.json...\n";
$composerPath = "{$projectRoot}/composer.json";
if (file_exists($composerPath)) {
    $composer = json_decode(file_get_contents($composerPath), true);
    $checks['composer.json valid'] = json_last_error() === JSON_ERROR_NONE ? '✅' : '❌ Invalid JSON';
    $checks['Package name'] = isset($composer['name']) ? "✅ {$composer['name']}" : '❌ Missing name';
    $checks['PHP requirement'] = isset($composer['require']['php']) ? "✅ {$composer['require']['php']}" : '❌ Missing';
    $checks['License'] = isset($composer['license']) ? "✅ {$composer['license']}" : '❌ Missing';
} else {
    $checks['composer.json'] = '❌ Not found';
}

// Check PSR-4 autoload
if (file_exists($composerPath)) {
    $autoload = isset($composer['autoload']['psr-4']) ? $composer['autoload']['psr-4'] : [];
    foreach ($autoload as $namespace => $path) {
        $checks["Autoload: {$namespace}"] = "✅ → {$path}";
    }
}

// Check License file
echo "📄 Checking license...\n";
$licensePath = "{$projectRoot}/LICENSE";
if (file_exists($licensePath)) {
    $content = file_get_contents($licensePath);
    $checks['MIT License'] = strpos($content, 'MIT') !== false ? '✅' : '⚠️  Check manually';
} else {
    $checks['LICENSE file'] = '❌ Missing';
}

// Display results
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        Check Results                           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

foreach ($checks as $check => $result) {
    printf("%-35s %s\n", $check . ':', $result);
}

// Summary
echo "\n";
$totalChecks = count($checks);
$passedChecks = count(array_filter($checks, function($v) { return strpos($v, '✅') === 0; }));
$failedChecks = count(array_filter($checks, function($v) { return strpos($v, '❌') === 0; }));

echo "╔════════════════════════════════════════════════════════════════╗\n";
printf("║ Total: %d  |  Passed: ✅ %d  |  Failed: ❌ %d                   ║\n", $totalChecks, $passedChecks, $failedChecks);
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

if ($failedChecks === 0) {
    echo "🎉 Project is READY FOR PUBLICATION! 🚀\n\n";
    exit(0);
} else {
    echo "⚠️  Please fix the failing checks before publishing.\n\n";
    exit(1);
}
