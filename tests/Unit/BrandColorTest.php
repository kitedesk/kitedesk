<?php

use App\Domain\Branding\BrandColor;

test('text on the brand color is white or dark, whichever reads better', function () {
    expect((new BrandColor('#1e3a8a'))->prefersWhiteText())->toBeTrue()
        ->and((new BrandColor('#facc15'))->prefersWhiteText())->toBeFalse()
        ->and((new BrandColor('#facc15'))->foregroundHex())->toBe('#111827');
});

test('dark mode gets a lighter shade of the same hue', function () {
    $color = new BrandColor('#1e3a8a');
    $variables = $color->cssVariables();

    expect($variables['light']['--primary'])->toBe('#1e3a8a')
        ->and($variables['dark']['--primary'])->toStartWith('oklch(0.700 ')
        ->and($variables['dark']['--primary'])->toEndWith(sprintf('%.1f)', $color->hue));
});
