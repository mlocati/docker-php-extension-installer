<?php

declare(strict_types=1);

// Check that the JSON files in the data directory (except the JSON schemas) declare a JSON schema of this repository, and that they are valid.

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new RuntimeException("Error {$errno}: {$errstr}" . ($errfile === '' ? '' : " in {$errfile}" . ($errline === 0 ? '' : " at line {$errline}")));
    },
    -1,
);

require_once __DIR__ . '/../vendor/autoload.php';

const DATA_DIR = __DIR__ . '/../data';

/**
 * @throws RuntimeException
 */
function readJsonFile(string $path): mixed
{
    try {
        return json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $x) {
        throw new RuntimeException("Failed to parse {$path}: {$x->getMessage()}");
    }
}

/**
 * @throws RuntimeException
 *
 * @return string[] the errors found (empty array if the file is valid)
 */
function checkJsonFile(Validator $validator, string $path): array
{
    $data = readJsonFile($path);
    $schema = is_object($data) ? ($data->{'$schema'} ?? null) : null;
    if (!is_string($schema) || $schema === '') {
        return ['the file must declare its JSON schema in the "$schema" property'];
    }
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $schema)) {
        return ["the JSON schema must be a file of this repository (found {$schema})"];
    }
    $schemaPath = dirname($path) . '/' . $schema;
    if (!is_file($schemaPath)) {
        return ["the JSON schema file {$schema} does not exist"];
    }
    $result = $validator->validate($data, readJsonFile($schemaPath));
    if ($result->isValid()) {
        return [];
    }
    $errors = [];
    foreach ((new ErrorFormatter())->format($result->error()) as $pointer => $messages) {
        foreach ($messages as $message) {
            $errors[] = "{$pointer}: {$message}";
        }
    }

    return $errors;
}

function main(): int
{
    $validator = new Validator();
    $validator->setMaxErrors(100);
    $rc = 0;

    foreach (glob(DATA_DIR . '/*.json') ?: [] as $path) {
        if (str_ends_with($path, '.schema.json')) {
            continue;
        }
        $name = 'data/' . basename($path);

        try {
            $errors = checkJsonFile($validator, $path);
        } catch (RuntimeException $x) {
            $errors = [$x->getMessage()];
        }
        if ($errors === []) {
            echo "{$name}: valid\n";
        } else {
            $rc = 1;
            fwrite(STDERR, "{$name}: INVALID\n");
            foreach ($errors as $error) {
                fwrite(STDERR, "- {$error}\n");
            }
        }
    }

    return $rc;
}

exit(main());
