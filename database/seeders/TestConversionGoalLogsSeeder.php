<?php

namespace Database\Seeders;

use App\Models\ConversionGoal;
use App\Models\ConversionGoalLog;
use Illuminate\Database\Seeder;

class TestConversionGoalLogsSeeder extends Seeder
{
    public function run(): void
    {
        $goal = ConversionGoal::query()
            ->where('user_id', 1)
            ->where('goal_code', 'PX-TEST-001')
            ->first();

        if (!$goal) {
            $this->call(TestCampaignSeeder::class);
            $goal = ConversionGoal::query()
                ->where('user_id', 1)
                ->where('goal_code', 'PX-TEST-001')
                ->first();
        }

        if (!$goal) {
            $this->command?->warn('TestConversionGoalLogsSeeder: meta de teste nao encontrada.');
            return;
        }

        ConversionGoalLog::query()
            ->where('goal_id', $goal->id)
            ->delete();

        $baseTime = now();
        $rows = [
            ['message' => 'Requisição CSV do Google Ads recebida.', 'status' => 'info'],
            ['message' => 'Teste de conexão do Google Ads concluído com sucesso.', 'status' => 'success'],
            ['message' => 'Requisição CSV do Google Ads recebida.', 'status' => 'info'],
            ['message' => 'Autenticação da requisição CSV realizada com sucesso.', 'status' => 'success'],
            ['message' => 'Conversões encontradas: 1 registro(s).', 'status' => 'info'],
            ['message' => 'CSV gerado (260 bytes, timezone America/Sao_Paulo).', 'status' => 'success'],
            ['message' => 'Requisição CSV do Google Ads recebida.', 'status' => 'info'],
            ['message' => 'Credenciais inválidas na requisição CSV.', 'status' => 'error'],
            ['message' => 'Autenticação inválida na requisição CSV.', 'status' => 'error'],
            ['message' => 'Nenhuma conversão disponível para marcação de processamento.', 'status' => 'warning'],
        ];

        $insert = [];
        foreach ($rows as $index => $row) {
            $insert[] = [
                'goal_id' => $goal->id,
                'message' => $row['message'],
                'status' => $row['status'],
                'created_at' => $baseTime->copy()->subSeconds(count($rows) - $index),
                'updated_at' => $baseTime->copy()->subSeconds(count($rows) - $index),
            ];
        }

        ConversionGoalLog::query()->insert($insert);
    }
}

