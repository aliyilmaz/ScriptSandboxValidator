# ScriptSandboxValidator

PHP library to validate Bash, Python, and BAT scripts against a sandbox directory.

## Features

- Detects paths that escape the sandbox
- Detects dynamic paths (`$VAR`, `${VAR}`, backticks, etc.)
- Detects dangerous system commands (`rm`, `shutdown`, `del`, etc.)
- Reports violations with line numbers
- Cross-platform (Linux/Windows/Unix)
- Strict mode enabled

## Installation

```bash
composer require aliyilmaz/script-sandbox-validator
```

Or include `src/ScriptSandboxValidator.php` manually.

## Usage
```php
use ScriptSandboxValidator\ScriptSandboxValidator;

$validator = new ScriptSandboxValidator();

$script = 'touch sandbox/file1.txt; rm /etc/passwd; echo $HOME/file';
$sandbox = __DIR__ . '/sandbox';

$result = $validator->validateScript($script, $sandbox, 'bash');

print_r($result);
```

## Example Output
```php
Array
(
    [valid] => false
    [violations] => Array
        (
            [0] => Array
                (
                    [type] => path_escape
                    [value] => /etc/passwd
                    [line] => 1
                    [reason] => Outside sandbox directory
                )

            [1] => Array
                (
                    [type] => dynamic_path
                    [value] => $HOME
                    [line] => 1
                    [reason] => Dynamic path cannot be validated
                )

            [2] => Array
                (
                    [type] => dangerous_command
                    [value] => rm
                    [line] => 1
                    [reason] => System-level or dangerous command is blocked
                )

        )

)
```

## Warning / Caution

- This validator does not execute scripts. It only parses the content and checks for paths and commands.
- Dynamic paths (e.g., `$HOME`, `${VAR}`) are flagged because their runtime value cannot be verified.
- It may not catch all possible ways to escape the sandbox, especially with highly obfuscated scripts.
- Always test new scripts in a safe environment before deployment.
- Designed for sandboxed environments; do not rely solely on this for full system security.