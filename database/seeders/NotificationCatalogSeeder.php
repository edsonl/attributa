<?php

namespace Database\Seeders;

use App\Models\NotificationCategory;
use App\Models\NotificationType;
use Illuminate\Database\Seeder;

class NotificationCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'system',
                'name' => 'Sistema e conta',
                'description' => 'Mensagens gerais sobre operação da plataforma.',
                'sort_order' => 10,
                'types' => [
                    ['slug' => 'system_maintenance', 'name' => 'Manutenção do sistema', 'severity' => 'info', 'sort_order' => 10, 'default_title' => 'Manutenção do sistema', 'default_message' => 'Existe uma manutenção programada para {{date}} às {{time}}.'],
                    ['slug' => 'system_feature_release', 'name' => 'Novidades da plataforma', 'severity' => 'info', 'sort_order' => 20, 'default_title' => 'Nova funcionalidade disponível', 'default_message' => 'Uma nova funcionalidade foi liberada: {{feature}}.'],
                ],
            ],
            [
                'slug' => 'billing',
                'name' => 'Plano e cobrança',
                'description' => 'Eventos de assinatura, vencimento e pagamento.',
                'sort_order' => 20,
                'types' => [
                    ['slug' => 'billing_plan_expiring', 'name' => 'Plano vencendo', 'severity' => 'warning', 'sort_order' => 10, 'default_title' => 'Seu plano vence em breve', 'default_message' => 'Seu plano vence em {{days}} dia(s).'],
                    ['slug' => 'billing_payment_failed', 'name' => 'Falha de pagamento', 'severity' => 'error', 'sort_order' => 20, 'default_title' => 'Falha no pagamento', 'default_message' => 'Falha no pagamento da sua assinatura em {{date}}.'],
                    ['slug' => 'billing_plan_renewed', 'name' => 'Plano renovado', 'severity' => 'success', 'sort_order' => 30, 'default_title' => 'Plano renovado', 'default_message' => 'Seu plano foi renovado com sucesso.'],
                ],
            ],
            [
                'slug' => 'lead',
                'name' => 'Leads',
                'description' => 'Eventos de recebimento e mudança de status do lead.',
                'sort_order' => 30,
                'types' => [
                    ['slug' => 'lead_created_processing', 'name' => 'Novo lead em processamento', 'severity' => 'info', 'sort_order' => 10, 'default_title' => 'Novo lead em processamento', 'default_message' => 'Lead {{lead_id}} recebido com status processing/hold.'],
                    ['slug' => 'lead_approved', 'name' => 'Compra aprovada', 'severity' => 'success', 'sort_order' => 20, 'default_title' => 'Compra aprovada', 'default_message' => 'Lead {{lead_id}} foi aprovado.'],
                    ['slug' => 'lead_rejected', 'name' => 'Lead rejeitado', 'severity' => 'warning', 'sort_order' => 30, 'default_title' => 'Lead rejeitado', 'default_message' => 'Lead {{lead_id}} foi rejeitado.'],
                    ['slug' => 'lead_trash', 'name' => 'Lead em lixo', 'severity' => 'warning', 'sort_order' => 40, 'default_title' => 'Lead marcado como lixo', 'default_message' => 'Lead {{lead_id}} foi marcado como lixo.'],
                    ['slug' => 'lead_cancelled', 'name' => 'Lead cancelado', 'severity' => 'warning', 'sort_order' => 50, 'default_title' => 'Lead cancelado', 'default_message' => 'Lead {{lead_id}} foi cancelado.'],
                    ['slug' => 'lead_refunded', 'name' => 'Lead reembolsado', 'severity' => 'warning', 'sort_order' => 60, 'default_title' => 'Lead reembolsado', 'default_message' => 'Lead {{lead_id}} foi reembolsado.'],
                    ['slug' => 'lead_chargeback', 'name' => 'Lead com chargeback', 'severity' => 'error', 'sort_order' => 70, 'default_title' => 'Chargeback detectado', 'default_message' => 'Lead {{lead_id}} recebeu chargeback.'],
                ],
            ],
            [
                'slug' => 'conversion',
                'name' => 'Conversões',
                'description' => 'Eventos de criação e exportação de conversões.',
                'sort_order' => 40,
                'types' => [
                    ['slug' => 'conversion_created', 'name' => 'Conversão criada', 'severity' => 'success', 'sort_order' => 10, 'default_title' => 'Conversão criada', 'default_message' => 'Conversão {{conversion_id}} foi criada.'],
                    ['slug' => 'conversion_exported_google', 'name' => 'Conversão exportada para Google', 'severity' => 'success', 'sort_order' => 20, 'default_title' => 'Conversão exportada', 'default_message' => 'Conversão {{conversion_id}} exportada para Google Ads.'],
                    ['slug' => 'conversion_export_failed', 'name' => 'Falha ao exportar conversão', 'severity' => 'error', 'sort_order' => 30, 'default_title' => 'Falha na exportação', 'default_message' => 'Falha ao exportar conversão {{conversion_id}} para Google Ads.'],
                ],
            ],
            [
                'slug' => 'integration',
                'name' => 'Integrações',
                'description' => 'Eventos técnicos de callback e mapeamento.',
                'sort_order' => 50,
                'types' => [
                    ['slug' => 'integration_callback_received', 'name' => 'Callback recebido', 'severity' => 'info', 'sort_order' => 10, 'default_title' => 'Callback recebido', 'default_message' => 'Callback recebido da plataforma {{platform}}.'],
                    ['slug' => 'integration_callback_invalid', 'name' => 'Callback inválido/ignorado', 'severity' => 'warning', 'sort_order' => 20, 'default_title' => 'Callback inválido', 'default_message' => 'Callback inválido ou ignorado da plataforma {{platform}}.'],
                    ['slug' => 'integration_mapping_missing', 'name' => 'Mapeamento ausente', 'severity' => 'warning', 'sort_order' => 30, 'default_title' => 'Mapeamento ausente', 'default_message' => 'Mapeamento ausente para {{field}} na plataforma {{platform}}.'],
                    ['slug' => 'integration_platform_unavailable', 'name' => 'Plataforma indisponível', 'severity' => 'error', 'sort_order' => 40, 'default_title' => 'Plataforma indisponível', 'default_message' => 'A plataforma {{platform}} está indisponível no momento.'],
                ],
            ],
            [
                'slug' => 'security',
                'name' => 'Segurança',
                'description' => 'Eventos de autenticação e risco de acesso.',
                'sort_order' => 60,
                'types' => [
                    ['slug' => 'security_new_login', 'name' => 'Novo login', 'severity' => 'info', 'sort_order' => 10, 'default_title' => 'Novo login identificado', 'default_message' => 'Novo login em {{date}} às {{time}}.'],
                    ['slug' => 'security_password_changed', 'name' => 'Senha alterada', 'severity' => 'warning', 'sort_order' => 20, 'default_title' => 'Senha alterada', 'default_message' => 'Sua senha foi alterada com sucesso.'],
                    ['slug' => 'security_suspicious_access', 'name' => 'Acesso suspeito', 'severity' => 'error', 'sort_order' => 30, 'default_title' => 'Acesso suspeito detectado', 'default_message' => 'Detectamos uma tentativa de acesso suspeito.'],
                ],
            ],
        ];

        foreach ($categories as $cat) {
            $category = NotificationCategory::query()->updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'active' => true,
                    'sort_order' => $cat['sort_order'],
                ]
            );

            foreach ($cat['types'] as $type) {
                NotificationType::query()->updateOrCreate(
                    ['slug' => $type['slug']],
                    [
                        'notification_category_id' => $category->id,
                        'name' => $type['name'],
                        'description' => null,
                        'default_title' => $type['default_title'],
                        'default_message' => $type['default_message'],
                        'severity' => $type['severity'],
                        'active' => true,
                        'sort_order' => $type['sort_order'],
                    ]
                );
            }
        }
    }
}
