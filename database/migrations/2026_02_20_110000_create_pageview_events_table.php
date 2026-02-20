<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pageview_events', function (Blueprint $table) {
            // Padrao do projeto para suporte total a utf8 e integridade referencial.
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // Contexto do dono dos dados do evento.
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Campanha relacionada ao evento (redundancia controlada para filtros rapidos).
            $table->foreignId('campaign_id')
                ->constrained('campaigns')
                ->cascadeOnDelete();

            // Relacao obrigatoria: nao existe evento sem pageview.
            $table->foreignId('pageview_id')
                ->constrained('pageviews')
                ->cascadeOnDelete();

            // Tipo canonico do evento enviado pelo script (page_engaged, link_click, form_submit, etc.).
            $table->string('event_type', 30);

            // Metadados do elemento alvo da interacao.
            $table->text('target_url')->nullable();
            $table->string('element_id', 191)->nullable();
            $table->string('element_name', 191)->nullable();
            $table->string('element_classes', 500)->nullable();

            // Resumo de preenchimento de formulario (sem persistir payload sensivel).
            $table->unsignedInteger('form_fields_checked')->nullable();
            $table->unsignedInteger('form_fields_filled')->nullable();
            $table->boolean('form_has_user_data')->nullable();

            // Timestamp do cliente em milissegundos, quando disponivel.
            $table->unsignedBigInteger('event_ts_ms')->nullable();

            $table->timestamps();

            // Indices voltados para consultas por dono, campanha, pageview e timeline do fluxo.
            $table->index('user_id');
            $table->index('campaign_id');
            $table->index('pageview_id');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pageview_events');
    }
};
