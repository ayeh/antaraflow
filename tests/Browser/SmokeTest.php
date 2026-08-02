<?php

declare(strict_types=1);

it('loads the root page without javascript errors', function () {
    visit('/')->assertNoJavaScriptErrors();
});
