<?php

declare(strict_types=1);

use Pharaonic\Diff\Diff;
use Pharaonic\Diff\InlineMode;
use Pharaonic\Diff\TextDiffOptions;

/**
 * Parse an HTML fragment as XML, failing the test when it is not well-formed
 */
function dom(string $html): DOMXPath
{
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML($html);
    libxml_use_internal_errors($previous);

    expect($loaded)->toBeTrue('The HTML is not well-formed: ' . $html);

    return new DOMXPath($document);
}

it('renders a GitHub-style table', function () {
    $html = Diff::text("alpha\nbeta\ngamma\n", "alpha\nbeta changed\ngamma\ndelta\n")->toHtml();
    $xpath = dom($html);
    $rows = $xpath->query('//tr[contains(@class, "ph-diff-line")]');

    expect($xpath->query('/table[@class="ph-diff"]/tbody[@class="ph-diff-hunk"]')->length)->toBe(1)
        ->and($xpath->evaluate('string(//td[@class="ph-diff-hunk-header-text"])'))->toBe('@@ -1,3 +1,4 @@')
        ->and($rows->length)->toBe(5)
        ->and(array_map(fn(DOMElement $row) => $row->getAttribute('class'), iterator_to_array($rows)))->toBe([
            'ph-diff-line ph-diff-line--context',
            'ph-diff-line ph-diff-line--removed',
            'ph-diff-line ph-diff-line--added',
            'ph-diff-line ph-diff-line--context',
            'ph-diff-line ph-diff-line--added',
        ]);
});

it('keeps line numbers and markers out of the copied text', function () {
    $xpath = dom(Diff::text("alpha\nbeta\n", "alpha\nbeta changed\n")->toHtml());
    $removed = $xpath->query('//tr[contains(@class, "ph-diff-line--removed")]/td')->getIterator();
    $cells = iterator_to_array($removed);

    expect($cells[0]->getAttribute('data-line-number'))->toBe('2')
        ->and($cells[1]->hasAttribute('data-line-number'))->toBeFalse()
        ->and($cells[0]->textContent . $cells[1]->textContent)->toBe('')
        ->and($cells[2]->textContent)->toBe('beta')
        ->and($xpath->evaluate('string(//tr[contains(@class, "ph-diff-line--added")]//span[@class="ph-diff-marker"]/@data-marker)'))->toBe('+');
});

it('wraps changed pieces in del and ins', function () {
    $xpath = dom(Diff::text("foo(1, 2);\n", "foo(1, 3);\n")->toHtml());

    expect($xpath->evaluate('string(//del[@class="ph-diff-inline--removed"])'))->toBe('2')
        ->and($xpath->evaluate('string(//ins[@class="ph-diff-inline--added"])'))->toBe('3')
        ->and($xpath->evaluate('string(//tr[contains(@class, "ph-diff-line--added")]/td[@class="ph-diff-code"])'))->toBe('foo(1, 3);');
});

it('highlights whole lines without inline pieces', function () {
    $html = Diff::text("beta\n", "beta changed\n", TextDiffOptions::create()->inline(InlineMode::NONE))->toHtml();

    expect($html)->not->toContain('<ins')->not->toContain('<del');
});

it('escapes every piece of text', function (string $old, string $new) {
    $html = Diff::text($old, $new)->toHtml();

    expect($html)->not->toContain('<script')
        ->not->toContain('onerror="')
        ->toContain('&lt;')
        ->and(dom($html)->evaluate('count(//script | //img | //*[@onerror])'))->toBe(0.0);
})->with([
    'script line' => ["safe\n", "safe\n<script>alert(1)</script>\n"],
    'script in a modified line' => ["x = '<b>';\n", "x = '<script>alert(1)</script>';\n"],
    'attribute breakout' => ["a\n", "\"><img src=x onerror=\"alert(1)\">\n"],
    'removed markup' => ["<img src=x onerror=alert(1)>\n", "text\n"],
]);

it('escapes quotes and ampersands', function () {
    expect(Diff::text("a\n", "Tom & 'Jerry' \"x\"\n")->toHtml())->toContain('Tom &amp; &#039;Jerry&#039; &quot;x&quot;');
});

it('replaces malformed UTF-8 instead of dropping the line', function () {
    $html = Diff::text("a\n", "b\xB1\n")->toHtml();

    expect($html)->toContain("b\u{FFFD}");
    dom($html);
});

it('marks a last line without a line ending', function () {
    $xpath = dom(Diff::text("a\nb", "a\nc\n")->toHtml());

    expect($xpath->query('//tr[@class="ph-diff-no-newline"]')->length)->toBe(1)
        ->and($xpath->evaluate('string(//tr[@class="ph-diff-no-newline"]/preceding-sibling::tr[1]/td[@class="ph-diff-code"])'))->toBe('b');
});

it('renders one tbody per hunk', function () {
    $old = implode("\n", range(1, 30)) . "\n";
    $new = str_replace(["\n3\n", "\n25\n"], ["\nthree\n", "\ntwenty-five\n"], $old);

    expect(dom(Diff::text($old, $new)->toHtml())->query('//tbody[@class="ph-diff-hunk"]')->length)->toBe(2);
});

it('renders nothing for equal texts', function () {
    expect(Diff::text("a\n", "a\n")->toHtml())->toBe('');
});

it('renders the same HTML for the same input', function () {
    expect(Diff::text("a\nb\n", "a\nc\n")->toHtml())->toBe(Diff::text("a\nb\n", "a\nc\n")->toHtml());
});

it('ships a stylesheet for every class it renders', function () {
    $css = (string) file_get_contents(__DIR__ . '/../../resources/diff.css');
    $html = Diff::text("a\nfoo(1);\nb", "a\nfoo(2);\nc\n")->toHtml();
    preg_match_all('/class="([^"]+)"/', $html, $matches);
    $classes = array_unique(explode(' ', implode(' ', $matches[1])));

    foreach ($classes as $class) {
        expect($css)->toContain(".{$class}");
    }
});
