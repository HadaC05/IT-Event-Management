<?php

declare(strict_types=1);

require_once __DIR__.'/ApiSupport.php';

function expectAccepted(array $input, string $label): void
{
    RequestSecurity::assertValid($input, $label);
}

function expectRejected(array $input, string $expectedMessage): void
{
    try {
        RequestSecurity::assertValid($input);
    } catch (InvalidArgumentException $exception) {
        if (str_contains($exception->getMessage(), $expectedMessage)) return;
        throw new RuntimeException("Unexpected validation message: {$exception->getMessage()}");
    }

    throw new RuntimeException("Expected request validation to reject: {$expectedMessage}");
}

// Security-looking text is valid user content. SQL injection is prevented by
// bound parameters, while XSS is prevented when values are rendered/encoded.
expectAccepted(['text' => "' OR 1=1 -- <script>alert('x')</script>"], 'test');
expectAccepted(['nested' => ['value' => 'ordinary text']], 'test');

expectRejected(['value' => "contains\0null"], 'control characters');
expectRejected(['value' => "\xC3\x28"], 'valid UTF-8');
expectRejected(['value' => str_repeat('a', 100001)], 'too long');

$nested = ['value' => 'end'];
for ($index = 0; $index < 13; $index++) $nested = ['child' => $nested];
expectRejected($nested, 'nested too deeply');

$many = [];
for ($index = 0; $index < 10001; $index++) $many['field_'.$index] = 'value';
expectRejected($many, 'too many values');

echo "Request security regression tests passed.\n";
