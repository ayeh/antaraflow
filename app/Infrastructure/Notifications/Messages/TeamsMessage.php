<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Messages;

class TeamsMessage
{
    private string $title = '';

    private string $content = '';

    private string $color = '#7C3AED';

    /** @var array<int, array{label: string, url: string}> */
    private array $actions = [];

    /** @var array<int, array{label: string, value: string}> */
    private array $facts = [];

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function action(string $label, string $url): self
    {
        $this->actions[] = ['label' => $label, 'url' => $url];

        return $this;
    }

    public function fact(string $label, string $value): self
    {
        $this->facts[] = ['label' => $label, 'value' => $value];

        return $this;
    }

    /** @return array<string, mixed> */
    public function toAdaptiveCard(): array
    {
        $body = [];

        if ($this->title !== '') {
            $body[] = [
                'type' => 'TextBlock',
                'size' => 'Medium',
                'weight' => 'Bolder',
                'text' => $this->title,
                'color' => 'Accent',
            ];
        }

        if ($this->content !== '') {
            $body[] = [
                'type' => 'TextBlock',
                'text' => $this->content,
                'wrap' => true,
            ];
        }

        if ($this->facts !== []) {
            $body[] = [
                'type' => 'FactSet',
                'facts' => array_map(fn (array $fact) => [
                    'title' => $fact['label'],
                    'value' => $fact['value'],
                ], $this->facts),
            ];
        }

        $actions = array_map(fn (array $action) => [
            'type' => 'Action.OpenUrl',
            'title' => $action['label'],
            'url' => $action['url'],
        ], $this->actions);

        return [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'body' => $body,
                        'actions' => $actions,
                    ],
                ],
            ],
        ];
    }
}
