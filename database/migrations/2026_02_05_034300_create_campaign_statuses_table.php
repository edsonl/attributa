<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaign_statuses', function (Blueprint $table) {
            // ID interno do status
            $table->id();
            // Nome legível do status (ex: Ativa, Pausada)
            $table->string('name');
            // Slug técnico único (ex: active, paused)
            $table->string('slug')->unique();
            // Cor em hexadecimal para UI
            $table->string('color_hex', 7);
            // Descrição opcional para tooltip/ajuda
            $table->text('description')->nullable();
            // Define se o status é nativo do sistema
            $table->boolean('is_system')->default(true);
            // Define se o status pode ser usado em campanhas
            $table->boolean('active')->default(true);
            // Timestamps padrão do Laravel
            $table->timestamps();
        });

        $now = now();

        DB::table('campaign_statuses')->insert([
            [
                'id' => 1,
                'name' => 'Rascunho',
                'slug' => 'draft',
                'color_hex' => '#6B7280',
                'description' => 'Campanha em configuração inicial.',
                'is_system' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Ativa',
                'slug' => 'active',
                'color_hex' => '#16A34A',
                'description' => 'Campanha em operação.',
                'is_system' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Pausada',
                'slug' => 'paused',
                'color_hex' => '#F59E0B',
                'description' => 'Campanha pausada temporariamente.',
                'is_system' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'Encerrada',
                'slug' => 'ended',
                'color_hex' => '#374151',
                'description' => 'Campanha finalizada.',
                'is_system' => true,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_statuses');
    }
};
