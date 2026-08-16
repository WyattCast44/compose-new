<?php

declare(strict_types=1);

use Compose\Exception\SafetyException;
use Compose\Execution\DotenvFileEditor;
use Compose\Execution\JsonFileEditor;
use Compose\Execution\PhpFileEditor;
use Compose\Execution\TextFileEditor;

it('applies preconditioned text and JSON edits', function (): void {
    $root = testDirectory('editors');
    file_put_contents($root.'/message.txt', 'hello world');
    file_put_contents($root.'/package.json', "{\n    \"scripts\": {}\n}\n");

    (new TextFileEditor)->edit($root, 'message.txt', 'replace', [
        'search' => 'world', 'replacement' => 'compose', 'expected' => 1,
    ]);
    (new JsonFileEditor)->edit($root, 'package.json', 'set', [
        'key' => 'scripts.test', 'value' => 'pest',
    ]);

    expect(file_get_contents($root.'/message.txt'))->toBe('hello compose')
        ->and(json_decode(file_get_contents($root.'/package.json') ?: '{}', true)['scripts']['test'])->toBe('pest');
});

it('edits PHP structures through the AST', function (): void {
    $root = testDirectory('php');
    file_put_contents($root.'/Example.php', <<<'PHP'
<?php

namespace App;

class Example
{
}
PHP);
    file_put_contents($root.'/config.php', "<?php\n\nreturn ['app' => ['name' => 'Old']];\n");
    $editor = new PhpFileEditor;
    $editor->edit($root, 'Example.php', 'add_import', ['class' => 'DateTimeImmutable']);
    $editor->edit($root, 'Example.php', 'add_interface', ['interface' => 'Stringable']);
    $editor->edit($root, 'Example.php', 'add_method', [
        'name' => '__toString', 'body' => "return 'compose';", 'visibility' => 'public', 'returnType' => 'string',
    ]);
    $editor->edit($root, 'config.php', 'config_set', ['key' => 'app.name', 'value' => 'Compose']);

    $class = file_get_contents($root.'/Example.php');
    expect($class)->toContain('use DateTimeImmutable;')
        ->and($class)->toContain('implements Stringable')
        ->and($class)->toContain('function __toString(): string')
        ->and(require $root.'/config.php')->toBe(['app' => ['name' => 'Compose']]);
});

it('preserves dotenv layout while changing exact keys', function (): void {
    $root = testDirectory('dotenv');
    file_put_contents($root.'/.env', "APP_NAME=Old\n# APP_DEBUG=true\n");
    $editor = new DotenvFileEditor;
    $editor->edit($root, '.env', 'set', ['key' => 'APP_NAME', 'value' => 'Compose App']);
    $editor->edit($root, '.env', 'uncomment', ['key' => 'APP_DEBUG']);

    expect(file_get_contents($root.'/.env'))->toBe("APP_NAME=\"Compose App\"\nAPP_DEBUG=true\n");
});

it('blocks edits outside the step directory', function (): void {
    expect(fn () => (new TextFileEditor)->edit(testDirectory('safety'), '../outside', 'append', ['contents' => 'x']))
        ->toThrow(SafetyException::class);
});
