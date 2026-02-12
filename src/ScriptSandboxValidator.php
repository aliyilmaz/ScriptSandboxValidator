<?php

namespace ScriptSandboxValidator;

class ScriptSandboxValidator
{
    private array $dangerousCommands = [
        'shutdown', 'reboot', 'halt', 'init', 'poweroff',
        'rm', 'mv', 'del', 'rmdir', 'mkdir',
        'copy', 'cp', 'echo', 'chmod', 'chown',
        'system', 'exec', 'eval', 'subprocess', 'bash', 'cmd'
    ];

    private array $pathPatterns = [
        '/(["\'])(\/(?:[^\s"\']+\/?)+)\1/',             // Unix absolute
        '/(["\'])([A-Za-z]:\\\\(?:[^\\\s"\']+\\\\?)+)\1/', // Windows absolute
        '/(["\'])(\.\.?(?:\/|\\\\)[^"\']*)\1/'        // Relative
    ];

    private array $dynamicPatterns = [
        '/\$\{?[A-Za-z0-9_]+\}?/', // $VAR or ${VAR}
        '/`.*?`/',                  // backticks
        '/\$\(.+?\)/',              // $(...)
    ];

    public function validateScript(string $scriptContent, string $sandboxPath, string $scriptType = 'bash'): array
    {
        $violations = [];
        $lines = preg_split("/\r\n|\n|\r/", $scriptContent);

        foreach ($lines as $i => $line) {
            $lineNumber = $i + 1;

            $line = $this->stripComments($line, $scriptType);
            if (trim($line) === '') continue;

            // Dynamic path
            foreach ($this->dynamicPatterns as $pattern) {
                if (preg_match($pattern, $line, $match)) {
                    $violations[] = [
                        'type' => 'dynamic_path',
                        'value' => $match[0],
                        'line' => $lineNumber,
                        'reason' => 'Dynamic path cannot be validated'
                    ];
                }
            }

            // Dangerous commands
            foreach ($this->dangerousCommands as $cmd) {
                if (preg_match('/\b' . preg_quote($cmd, '/') . '\b/', $line)) {
                    $violations[] = [
                        'type' => 'dangerous_command',
                        'value' => $cmd,
                        'line' => $lineNumber,
                        'reason' => 'System-level or dangerous command is blocked'
                    ];
                }
            }

            // Path checks
            foreach ($this->pathPatterns as $pattern) {
                if (preg_match_all($pattern, $line, $matches)) {
                    foreach ($matches[2] as $path) {
                        $normalized = $this->normalizePath($path, $sandboxPath);
                        if (!$this->isPathInsideSandbox($normalized, $sandboxPath)) {
                            $violations[] = [
                                'type' => 'path_escape',
                                'value' => $path,
                                'line' => $lineNumber,
                                'reason' => 'Outside sandbox directory'
                            ];
                        }
                    }
                }
            }
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations
        ];
    }

    private function stripComments(string $line, string $scriptType): string
    {
        switch (strtolower($scriptType)) {
            case 'bash':
            case 'python':
                return preg_replace('/#.*$/', '', $line);
            case 'bat':
                return preg_replace('/^\s*(REM|::).*/i', '', $line);
            default:
                return $line;
        }
    }

    private function normalizePath(string $path, string $sandboxPath): string
    {
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        $sandboxPath = rtrim($sandboxPath, DIRECTORY_SEPARATOR);

        if (!preg_match('/^' . preg_quote(DIRECTORY_SEPARATOR, '/') . '|[A-Za-z]:/', $path)) {
            $path = $sandboxPath . DIRECTORY_SEPARATOR . $path;
        }

        $parts = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') array_pop($parts);
            else $parts[] = $segment;
        }

        if (DIRECTORY_SEPARATOR === '\\' && preg_match('/^[A-Za-z]:$/', $parts[0])) {
            return implode(DIRECTORY_SEPARATOR, $parts);
        }

        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function isPathInsideSandbox(string $path, string $sandboxPath): bool
    {
        $sandboxPath = rtrim($sandboxPath, DIRECTORY_SEPARATOR);
        return str_starts_with($path, $sandboxPath);
    }
}
