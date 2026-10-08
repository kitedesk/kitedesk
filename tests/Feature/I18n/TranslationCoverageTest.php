<?php

use Symfony\Component\Finder\Finder;

/**
 * Translation keys used in the given directories: literal first arguments of t()/tChoice()
 * in TypeScript, and of __()/trans_choice() in PHP.
 *
 * @param  list<string>  $directories
 * @return array<string, string> Key => first file it appears in.
 */
function translationKeysIn(array $directories, string $pattern, string $function): array
{
    $keys = [];
    $finder = Finder::create()->files()->in($directories)->name($pattern)->notPath(['actions', 'routes', 'wayfinder']);

    foreach ($finder as $file) {
        preg_match_all('/(?<![\w.$])'.$function.'\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1/s', $file->getContents(), $matches);

        foreach ($matches[2] as $index => $raw) {
            $key = stripcslashes($raw);
            $keys[$key] ??= $file->getRelativePathname();
        }
    }

    return $keys;
}

test('every interface string has a Brazilian Portuguese translation', function () {
    $translations = json_decode((string) file_get_contents(lang_path('pt_BR.json')), true, flags: JSON_THROW_ON_ERROR);

    $keys = [
        ...translationKeysIn([resource_path('js')], '*.ts*', '(?:t|tChoice)'),
        ...translationKeysIn([app_path()], '*.php', '(?:__|trans_choice)'),
    ];

    $missing = array_filter(
        $keys,
        fn (string $file, string $key): bool => trim((string) ($translations[$key] ?? '')) === '',
        ARRAY_FILTER_USE_BOTH,
    );

    expect($missing)->toBeEmpty('Missing pt_BR translations: '.json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
});

test('translations keep the placeholders of their key', function () {
    $translations = json_decode((string) file_get_contents(lang_path('pt_BR.json')), true, flags: JSON_THROW_ON_ERROR);

    $mismatched = collect($translations)->filter(function (string $translation, string $key): bool {
        preg_match_all('/:[a-z_]+/i', $key, $expected);
        preg_match_all('/:[a-z_]+/i', $translation, $actual);

        return collect($expected[0])->sort()->values()->all() !== collect($actual[0])->unique()->sort()->values()->all()
            && collect($expected[0])->diff($actual[0])->isNotEmpty();
    });

    expect($mismatched->all())->toBeEmpty();
});
