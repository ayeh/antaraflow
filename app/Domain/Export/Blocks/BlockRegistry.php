<?php

declare(strict_types=1);

namespace App\Domain\Export\Blocks;

use InvalidArgumentException;

final class BlockRegistry
{
    /** @var array<string, BlockRenderer> */
    private array $renderers = [];

    public function register(BlockRenderer $renderer): void
    {
        $this->renderers[$renderer->type()] = $renderer;
    }

    public function get(string $type): BlockRenderer
    {
        if (! isset($this->renderers[$type])) {
            throw new InvalidArgumentException("No block renderer registered for type [{$type}].");
        }

        return $this->renderers[$type];
    }

    public function has(string $type): bool
    {
        return isset($this->renderers[$type]);
    }

    /** @return array<string> */
    public function types(): array
    {
        return array_keys($this->renderers);
    }
}
