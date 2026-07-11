<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_model_prices', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('pattern');
            $table->boolean('is_regex')->default(false);
            $table->decimal('input_per_mtok', 12, 6)->nullable();
            $table->decimal('output_per_mtok', 12, 6)->nullable();
            $table->decimal('cached_input_per_mtok', 12, 6)->nullable();
            $table->decimal('per_minute', 12, 6)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['provider']);
            $table->index(['is_regex', 'priority']);
        });

        // Seed built-in prices from config so the registry is populated and editable.
        $now = now();
        $rows = [];

        foreach ((array) config('ai.pricing.chat', []) as $model => $rates) {
            $rows[] = [
                'provider' => $this->providerFor($model),
                'pattern' => $model,
                'is_regex' => false,
                'input_per_mtok' => $rates['input'] ?? null,
                'output_per_mtok' => $rates['output'] ?? null,
                'cached_input_per_mtok' => $rates['cached_input'] ?? null,
                'per_minute' => null,
                'priority' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ((array) config('ai.pricing.transcription', []) as $model => $rates) {
            $rows[] = [
                'provider' => 'openai',
                'pattern' => $model,
                'is_regex' => false,
                'input_per_mtok' => null,
                'output_per_mtok' => null,
                'cached_input_per_mtok' => null,
                'per_minute' => $rates['per_minute'] ?? null,
                'priority' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('ai_model_prices')->insert($rows);
        }
    }

    private function providerFor(string $model): string
    {
        return match (true) {
            str_contains($model, 'claude') => 'anthropic',
            str_contains($model, 'gemini') => 'google',
            default => 'openai',
        };
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_prices');
    }
};
