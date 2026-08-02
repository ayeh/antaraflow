<?php

declare(strict_types=1);

it('loads the audio harness', function () {
    visit('/__audio-harness')
        ->assertSee('audio harness ready')
        ->assertNoJavaScriptErrors();
});
