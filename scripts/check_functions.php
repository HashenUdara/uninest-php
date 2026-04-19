#!/usr/bin/env php
<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

$coreFiles = glob($basePath . '/core/*.php') ?: [];
$moduleModelFiles = glob($basePath . '/modules/*/models.php') ?: [];
$moduleControllerFiles = glob($basePath . '/modules/*/controllers.php') ?: [];
$moduleModelPartFiles = glob($basePath . '/modules/*/models/*.php') ?: [];
$moduleControllerPartFiles = glob($basePath . '/modules/*/controllers/*.php') ?: [];

$scanFiles = array_merge(
    $coreFiles,
    $moduleModelFiles,
    $moduleControllerFiles,
    $moduleModelPartFiles,
    $moduleControllerPartFiles
);
sort($scanFiles);

$functionIndex = [];
$duplicateErrors = [];
$prefixErrors = [];
$modulePrefixAllowlist = [
    'onboarding' => ['onboarding_', 'admin_', 'moderator_', 'university_', 'universities_'],
];

foreach ($scanFiles as $filePath) {
    $functions = parse_named_functions($filePath);
    $moduleName = module_name_for_file($filePath);

    foreach ($functions as $function) {
        $nameLower = strtolower($function['name']);
        $relativePath = relative_path($filePath, $basePath);

        if (isset($functionIndex[$nameLower])) {
            $existing = $functionIndex[$nameLower];
            $duplicateErrors[] = sprintf(
                'Function "%s" is defined in %s:%d and %s:%d.',
                $function['name'],
                $existing['file'],
                $existing['line'],
                $relativePath,
                $function['line']
            );
        } else {
            $functionIndex[$nameLower] = [
                'name' => $function['name'],
                'file' => $relativePath,
                'line' => $function['line'],
            ];
        }

        if ($moduleName !== null) {
            $allowedPrefixes = $modulePrefixAllowlist[$moduleName] ?? [$moduleName . '_'];
            $hasAllowedPrefix = false;
            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($nameLower, strtolower($prefix))) {
                    $hasAllowedPrefix = true;
                    break;
                }
            }

            if (!$hasAllowedPrefix) {
                $prefixErrors[] = sprintf(
                    '%s:%d uses "%s" but module functions must start with one of: %s.',
                    $relativePath,
                    $function['line'],
                    $function['name'],
                    implode(', ', $allowedPrefixes)
                );
            }
        }
    }
}

$routeErrors = [];
$routesPath = $basePath . '/routes.php';
if (file_exists($routesPath)) {
    foreach (parse_route_handlers($routesPath) as $routeHandler) {
        if (!isset($functionIndex[strtolower($routeHandler['handler'])])) {
            $routeErrors[] = sprintf(
                'routes.php:%d references missing handler "%s".',
                $routeHandler['line'],
                $routeHandler['handler']
            );
        }
    }
}

$hasErrors = !empty($duplicateErrors) || !empty($prefixErrors) || !empty($routeErrors);

if ($hasErrors) {
    fwrite(STDERR, "Function checks failed.\n\n");

    if (!empty($duplicateErrors)) {
        fwrite(STDERR, "Duplicate functions:\n");
        foreach ($duplicateErrors as $error) {
            fwrite(STDERR, ' - ' . $error . "\n");
        }
        fwrite(STDERR, "\n");
    }

    if (!empty($prefixErrors)) {
        fwrite(STDERR, "Module prefix violations:\n");
        foreach ($prefixErrors as $error) {
            fwrite(STDERR, ' - ' . $error . "\n");
        }
        fwrite(STDERR, "\n");
    }

    if (!empty($routeErrors)) {
        fwrite(STDERR, "Missing route handlers:\n");
        foreach ($routeErrors as $error) {
            fwrite(STDERR, ' - ' . $error . "\n");
        }
        fwrite(STDERR, "\n");
    }

    exit(1);
}

printf(
    "Function checks passed. Scanned %d files, %d named functions, %d route handlers.\n",
    count($scanFiles),
    count($functionIndex),
    count(parse_route_handlers($routesPath))
);

/**
 * @return array<int, array{name:string,line:int}>
 */
function parse_named_functions(string $filePath): array
{
    $source = file_get_contents($filePath);
    if ($source === false) {
        return [];
    }

    $tokens = token_get_all($source);
    $functions = [];
    $count = count($tokens);

    $ampersandTokens = [];
    if (defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG')) {
        $ampersandTokens[] = T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG;
    }
    if (defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG')) {
        $ampersandTokens[] = T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG;
    }

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $j = $i + 1;
        while ($j < $count) {
            $next = $tokens[$j];
            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $j++;
                continue;
            }

            if ($next === '&') {
                $j++;
                continue;
            }

            if (is_array($next) && in_array($next[0], $ampersandTokens, true)) {
                $j++;
                continue;
            }

            break;
        }

        if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
            $functions[] = [
                'name' => $tokens[$j][1],
                'line' => (int) $tokens[$j][2],
            ];
        }
    }

    return $functions;
}

function module_name_for_file(string $filePath): ?string
{
    $normalized = str_replace('\\', '/', $filePath);
    if (preg_match('#/modules/([^/]+)/#', $normalized, $matches) === 1) {
        return strtolower($matches[1]);
    }
    return null;
}

function relative_path(string $filePath, string $basePath): string
{
    $normalizedFile = str_replace('\\', '/', $filePath);
    $normalizedBase = rtrim(str_replace('\\', '/', $basePath), '/');
    return ltrim(str_replace($normalizedBase, '', $normalizedFile), '/');
}

/**
 * @return array<int, array{handler:string,line:int}>
 */
function parse_route_handlers(string $routesFile): array
{
    $source = file_get_contents($routesFile);
    if ($source === false) {
        return [];
    }

    $pattern = '/route\(\s*[\'"][A-Z]+[\'"]\s*,\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]/m';
    $matches = [];
    preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);

    $handlers = [];
    foreach ($matches[1] ?? [] as $match) {
        $handlerName = (string) $match[0];
        $offset = (int) $match[1];
        $line = substr_count(substr($source, 0, $offset), "\n") + 1;
        $handlers[] = [
            'handler' => $handlerName,
            'line' => $line,
        ];
    }

    return $handlers;
}
