<?php

use App\Domain\Jira\Support\Adf;

test('editor HTML becomes ADF blocks and marks', function () {
    $document = Adf::fromHtml(<<<'HTML'
        <h2>Steps</h2>
        <p>Open <a href="https://app.test/x">the export</a> and press <strong>Run</strong><br>twice.</p>
        <ul><li><p>One</p></li><li>Two</li></ul>
        <blockquote><p>Quoted</p></blockquote>
        <pre><code>php artisan export</code></pre>
        <p><span data-type="mention" data-id="4" data-label="Ana">@Ana</span> please check <s>old</s> <code>v2</code></p>
        HTML);

    expect($document['type'])->toBe('doc')
        ->and($document['content'][0])->toBe(['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Steps']]])
        ->and($document['content'][1]['content'])->toBe([
            ['type' => 'text', 'text' => 'Open '],
            ['type' => 'text', 'text' => 'the export', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://app.test/x']]]],
            ['type' => 'text', 'text' => ' and press '],
            ['type' => 'text', 'text' => 'Run', 'marks' => [['type' => 'strong']]],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'twice.'],
        ])
        ->and($document['content'][2])->toBe(['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]]]],
        ]])
        ->and($document['content'][3]['type'])->toBe('blockquote')
        ->and($document['content'][4])->toBe(['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'php artisan export']]])
        ->and($document['content'][5]['content'][0])->toBe(['type' => 'text', 'text' => '@Ana']);
});

test('links other than web and mail addresses lose their link, not their text', function () {
    $document = Adf::fromHtml('<p><a href="javascript:alert(1)">click</a></p>');

    expect($document['content'][0]['content'])->toBe([['type' => 'text', 'text' => 'click']]);
});

test('ADF from Jira becomes HTML, escaped', function () {
    $html = Adf::toHtml(['type' => 'doc', 'version' => 1, 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Fix']]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => '<b>not bold</b> '],
            ['type' => 'text', 'text' => 'docs', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://docs.test']], ['type' => 'em']]],
            ['type' => 'mention', 'attrs' => ['text' => '@Dana']],
        ]],
        ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Step']]]]]]],
        ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'a < b']]],
        ['type' => 'someFutureNode', 'content' => [['type' => 'text', 'text' => 'kept']]],
    ]]);

    expect($html)->toBe('<h2>Fix</h2><p>&lt;b&gt;not bold&lt;/b&gt; <em><a href="https://docs.test">docs</a></em>@Dana</p><ol><li><p>Step</p></li></ol><pre><code>a &lt; b</code></pre>kept');
});
