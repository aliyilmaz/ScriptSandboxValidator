<?php

require_once __DIR__ . '/../src/ScriptSandboxValidator.php';

use ScriptSandboxValidator\ScriptSandboxValidator;

$validator = new ScriptSandboxValidator();

$script = 'touch sandbox/file1.txt; rm /etc/passwd; echo $HOME/file';
$sandbox = __DIR__ . '/sandbox';

$result = $validator->validateScript($script, $sandbox, 'bash');

echo "<pre>";
print_r($result);
echo "</pre>";
