-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 12/12/2025 às 15:18
-- Versão do servidor: 8.2.0
-- Versão do PHP: 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `laravel12`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `website` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_meta` json DEFAULT NULL,
  `order` int UNSIGNED NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `companies`
--

DROP TABLE IF EXISTS `companies`;
CREATE TABLE IF NOT EXISTS `companies` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `corporate_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `companies_name_index` (`name`),
  KEY `companies_corporate_name_index` (`corporate_name`),
  KEY `companies_email_index` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `companies`
--

INSERT INTO `companies` (`id`, `name`, `corporate_name`, `phone`, `whatsapp`, `email`, `site`, `cnpj`, `notes`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Laymark Publicidade', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-10 07:06:18', '2025-10-10 07:06:18', NULL),
(2, 'WrCompany', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-10 07:06:25', '2025-10-10 07:06:25', NULL),
(3, 'Carlos Modesto -  Igroup', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-10 07:07:02', '2025-10-10 07:07:02', NULL),
(4, 'Rodrigo Zorzi', NULL, '(54) 99369-8560', '(54) 3024-2536', NULL, NULL, NULL, NULL, '2025-10-10 07:07:15', '2025-10-10 08:07:06', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(9, '2025_10_12_140800_create_clients_table', 4),
(10, '2025_10_17_182100_create_task_notes_table', 5),
(8, '2025_10_09_193246_create_tasks_table', 3),
(7, '2025_10_10_030535_create_companies_table', 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('KA1z2u1YDw9PSQ7YwSHdHtfmEZeGKzBP9TPs3cqD', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoic2JJT0NSYWN6QzJ0dm5kMXhiUTk3Y2gzRVlsSkJlaVp5aFBtUDcwTCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzQ6Imh0dHA6Ly9sYXJhdmVsMTIuc2l0ZS9wYWluZWwvdGFza3MiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=', 1765548291);

-- --------------------------------------------------------

--
-- Estrutura para tabela `tasks`
--

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','in_progress','done') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `assigned_to_id` bigint UNSIGNED DEFAULT NULL,
  `created_by_id` bigint UNSIGNED NOT NULL,
  `company_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_created_by_id_foreign` (`created_by_id`),
  KEY `tasks_company_id_foreign` (`company_id`),
  KEY `tasks_status_index` (`status`),
  KEY `tasks_priority_index` (`priority`),
  KEY `tasks_due_date_index` (`due_date`),
  KEY `tasks_assigned_to_id_index` (`assigned_to_id`)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `description`, `status`, `priority`, `due_date`, `assigned_to_id`, `created_by_id`, `company_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Teste', 'Teste olá', 'in_progress', 'low', '2025-10-06', 1, 1, NULL, '2025-10-10 07:15:33', '2025-10-10 08:25:08', '2025-10-10 08:25:08'),
(2, 'Teste', 'Teste', 'in_progress', 'low', '2025-10-08', 1, 1, 4, '2025-10-10 07:17:23', '2025-10-10 08:25:13', '2025-10-10 08:25:13'),
(3, 'Teste Olá', NULL, 'pending', 'medium', NULL, 1, 1, NULL, '2025-10-10 07:35:33', '2025-10-10 08:25:05', '2025-10-10 08:25:05'),
(4, 'Teste', '15/08 Pago R$ 50,00\n18/', 'pending', 'low', NULL, 1, 1, 2, '2025-10-10 17:44:05', '2025-10-10 19:18:06', '2025-10-10 19:18:06'),
(5, 'Pendencias - Pessoais', '- Aluguel - R$ 420,00\n- Luz - R$ 145 - OK\n- Internet - R$ 99 - OK\n- Voto/dízimo - r$ 300 - OK\n- Hospedagem - R$ 63 - OK\n- Enviar cobrança Coimca - OK', 'done', 'low', '2025-10-10', 1, 1, NULL, '2025-10-10 19:20:04', '2025-10-10 21:54:30', NULL),
(6, 'Site Celebrare', '- Criar páginas Padrão (Políticas etc)\n- Criar capas e cadastrar os produtos já contidos no site atual\n- Página do carrinho, simplificar header por enquanto\n- Checkout: Plugin de checkout e integração com o Gateway', 'in_progress', 'high', '2025-10-13', 1, 1, 4, '2025-10-10 21:46:16', '2025-10-18 05:06:08', NULL),
(7, 'Revisão - Solicitar Campanhas - Dalmóbile', 'Colocar online\nDetalhes no whats:\nVerificar erro ao validar formulário, tem a ver com a obrigatoriedade dos campos.', 'done', 'low', '2025-10-12', 1, 1, 1, '2025-10-10 21:47:59', '2025-10-15 15:19:29', NULL),
(8, 'Verificar Cupons - (Site Arke)', 'Ver whats', 'done', 'low', '2025-10-12', 1, 1, 1, '2025-10-10 21:48:49', '2025-10-18 04:51:06', NULL),
(9, 'Dalmobile - Solicitação de Campanhas', 'Verificar erro ao validar formulário, tem a ver com a obrigatoriedade dos campos.', 'pending', 'high', '2025-10-13', 1, 1, 1, '2025-10-13 23:40:30', '2025-10-13 23:41:10', '2025-10-13 23:41:10'),
(10, 'Atualizações - Cvalosdo Sul', 'Atualização: \nPágina do post:\nem mobile não usar a tarja de fundo. Colocar título e categoria centralizados.\nverificar o comportamento - tamanho de texto e espaçamento em mobile.\ncolocar o nome do auto abaixo do conteúdo.\n- Comparar conteúdo do post com os links abaixo: \nhttps://viverdeblog.com/site-wordpress-como-instalar/\nhttps://ge.globo.com/futebol/futebol-internacional/futebol-ingles/jogo/18-10-2025/nottingham-forest-Chelsea.ghtml\n- Ajustar a busca/pesquisa (Buscar posts do idioma correspondente)\n- No resultado da busca - Exibir anúncios somente quando o idioma for português', 'done', 'medium', '2025-10-21', 1, 1, 2, '2025-10-20 15:54:58', '2025-12-12 16:47:04', NULL),
(11, 'Tarefas do dia', '-> Pedir liberação do saldo - Doctor cash -> Ok\n-> Criar uma ordem de pagamento Doctor Cash -> OK Obs -Já solicitado o saque\n-> Iniciar nova campanha - Pesquisar produto', 'pending', 'medium', '2025-10-01', 1, 1, NULL, '2025-10-23 13:49:02', '2025-10-27 18:28:04', '2025-10-27 18:28:04'),
(12, 'Criação do Site nutra-call.shop', NULL, 'in_progress', 'medium', '2025-10-27', 1, 1, NULL, '2025-10-27 18:40:58', '2025-12-12 16:49:37', NULL),
(13, 'Coimca - Contagem de Cliques - Telefones', 'Contagem de Cliques - Telefones whatspp', 'pending', 'medium', '2025-12-16', 1, 1, NULL, '2025-12-12 16:48:08', '2025-12-12 16:49:30', NULL),
(14, 'Coimca - Contagem de Cliques', NULL, 'pending', 'medium', '2025-12-16', 1, 1, NULL, '2025-12-12 16:49:11', '2025-12-12 16:49:22', '2025-12-12 16:49:22'),
(15, 'Coimca - Verificação de bots', 'Verificar formas de diminuir scraping no site', 'pending', 'medium', NULL, 1, 1, NULL, '2025-12-12 16:50:26', '2025-12-12 16:50:49', NULL),
(16, 'Tarefas Diego', NULL, 'in_progress', 'medium', '2025-12-12', 1, 1, NULL, '2025-12-12 17:04:43', '2025-12-12 17:04:51', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `task_notes`
--

DROP TABLE IF EXISTS `task_notes`;
CREATE TABLE IF NOT EXISTS `task_notes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` bigint UNSIGNED NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime DEFAULT NULL,
  `time_minutes` int DEFAULT NULL,
  `value` decimal(10,2) DEFAULT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  `done` tinyint(1) NOT NULL DEFAULT '0',
  `user_id` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_notes_task_id_foreign` (`task_id`),
  KEY `task_notes_user_id_foreign` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `task_notes`
--

INSERT INTO `task_notes` (`id`, `task_id`, `description`, `date`, `time_minutes`, `value`, `paid`, `done`, `user_id`, `created_at`, `updated_at`) VALUES
(3, 8, 'Foi feita uma verificação no banco de dados. Já explicado para Monique e o Rafael;\nErro de gravação das sessões do woocommerce.', '2025-10-15 10:05:00', 90, 100.00, 0, 0, 1, '2025-10-17 22:03:01', '2025-10-18 04:51:17'),
(5, 7, 'Verificar erro ao validar formulário, tem a ver com a obrigatoriedade dos campos.\nEnviado para testes.', '2025-10-17 22:53:00', 150, NULL, 0, 0, 1, '2025-10-18 04:54:32', '2025-10-18 04:54:32'),
(6, 6, 'Criação do site e Implementação do Checkout', '2025-10-17 23:05:00', 1200, 4000.00, 0, 0, 1, '2025-10-18 05:05:55', '2025-10-18 05:06:35'),
(9, 6, '- Criar páginas Padrão (Políticas etc)', '2025-10-29 07:44:00', NULL, NULL, 0, 1, 1, '2025-10-29 13:44:31', '2025-10-29 14:07:24'),
(12, 6, 'Página do carrinho, simplificar header por enquanto', '2025-10-29 10:45:25', NULL, NULL, 0, 0, 1, '2025-10-29 13:45:25', '2025-10-29 13:45:25'),
(11, 6, 'Criar capas e cadastrar os produtos já contidos no site atual', '2025-10-29 10:45:13', NULL, NULL, 0, 0, 1, '2025-10-29 13:45:13', '2025-10-29 13:45:13'),
(13, 6, 'Checkout: Plugin de checkout e integração com o Gateway', '2025-10-29 10:45:00', NULL, NULL, 0, 0, 1, '2025-10-29 13:45:38', '2025-10-29 14:07:19');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Edson Luiz Siqueira', 'edson@master.dev.br', NULL, '$2y$12$qPcRAvQnKNULPug9XohHIOUyzkQz9ITTUIuWbW8szcuXXGKysQ8ti', 'cKWUyX9HoWm80cpPX2LmAlT2VzAJhoxtQXmqFv6SvGHvJnGUvDzD3EMNd139', '2025-10-08 02:57:34', '2025-10-08 13:22:03'),
(2, 'Paulo', 'email@gmail.com', NULL, '$2y$12$d7bEzfuPQgxjjuwg1a5fFej6dH0LzYkepoFHb/7lDkC6xzocSGyX6', NULL, '2025-10-08 14:22:10', '2025-10-08 14:22:10'),
(4, 'Teste', 'teste@gmail.com', NULL, '$2y$12$sJCw1VYIclfM69ZT8ZOQk.nlkGjO35XoOt1HO5gd/9C6M6/M6GelC', NULL, '2025-10-08 16:12:36', '2025-10-08 16:12:36'),
(5, 'Edson', 'edson.php@gmail.com', NULL, '$2y$12$woTEK2zrWMFEiyJxly3ke.eJAZS/3SJLCb6ICH1NgFaNgHDZyRaoO', 'cQnO5qaF4Clh5rS4V4Dtfe1rq5n8eUO0nd3jFhiTs4WJLpCklKWWFnCuxhHI', '2025-10-09 04:27:17', '2025-10-09 05:02:56');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
