<?php

declare(strict_types=1);

it('renders language select component with default english selected', function (): void {
    $view = $this->blade('<x-language-select />');

    $view->assertSee('English');
    $view->assertSee('Bahasa Melayu');
});

it('renders with specified language pre-selected', function (): void {
    $view = $this->blade('<x-language-select selected="ms" />');

    $view->assertSee('Bahasa Melayu');
    $view->assertSee('value="ms"', false);
});

it('renders with custom name attribute', function (): void {
    $view = $this->blade('<x-language-select name="audio_language" />');

    $view->assertSee('name="audio_language"', false);
});
