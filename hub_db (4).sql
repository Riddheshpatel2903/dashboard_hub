-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 25, 2026 at 02:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hub_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `website_url` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `website_url`, `created_at`) VALUES
(1, 'Demo Acct', 'https://clienttheme.com', '2026-07-18 09:49:26'),
(2, 'patel', 'https://pateltheme.com', '2026-07-18 11:28:37'),
(3, 'Riddh Patel', 'https://pateltheme.com', '2026-07-18 11:30:57'),
(4, 'falguni thakor', 'https://falguni.com', '2026-07-25 12:04:32');

-- --------------------------------------------------------

--
-- Table structure for table `client_api_keys`
--

CREATE TABLE `client_api_keys` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_api_keys`
--

INSERT INTO `client_api_keys` (`id`, `client_id`, `api_key`, `created_at`) VALUES
(1, 1, '94a21f797c70cf6c158c088c32a94bf89a1e400b646989b4cb445c8ff15a9978', '2026-07-18 09:49:26'),
(2, 2, '0c651584d962aebbc7709a223063d2f013d617e5fde87f7d7ac0bc7b9e2392c9', '2026-07-18 11:28:37'),
(3, 3, '788aae4b167847192acb0c5e1a2fc3875cf638eab97c559cd223632df0f452af', '2026-07-18 11:30:57'),
(4, 4, 'd50ee2ff45619f9b9a1a76f0d9ca32d6ca1f3de89525f9986674557f9cec1f3e', '2026-07-25 12:04:32');

-- --------------------------------------------------------

--
-- Table structure for table `media_files`
--

CREATE TABLE `media_files` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `storage_path` varchar(512) NOT NULL,
  `file_type` enum('image','video') NOT NULL,
  `file_size_bytes` bigint(20) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delete_after` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_connections`
--

CREATE TABLE `platform_connections` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `platform` enum('facebook','instagram','whatsapp','youtube','linkedin','google_business') NOT NULL,
  `external_account_id` varchar(255) NOT NULL,
  `status` enum('connected','disconnected','expired','expiring') NOT NULL DEFAULT 'connected',
  `connected_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_connections`
--

INSERT INTO `platform_connections` (`id`, `client_id`, `platform`, `external_account_id`, `status`, `connected_at`) VALUES
(25, 1, 'facebook', '1243224272200630', 'connected', '2026-07-25 09:35:53'),
(26, 1, 'instagram', '17841451698722819', 'connected', '2026-07-25 09:36:05'),
(27, 1, 'youtube', 'UCQfkt17JKe9XS-MjVGim0nA', 'connected', '2026-07-25 10:38:36'),
(28, 3, 'facebook', '1169742216226175', 'connected', '2026-07-25 11:01:46'),
(29, 3, 'instagram', '17841457169186153', 'connected', '2026-07-25 11:14:41');

-- --------------------------------------------------------

--
-- Table structure for table `platform_tokens`
--

CREATE TABLE `platform_tokens` (
  `id` int(11) NOT NULL,
  `platform_connection_id` int(11) NOT NULL,
  `access_token_encrypted` text NOT NULL,
  `refresh_token_encrypted` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_tokens`
--

INSERT INTO `platform_tokens` (`id`, `platform_connection_id`, `access_token_encrypted`, `refresh_token_encrypted`, `expires_at`) VALUES
(25, 25, 'EZwMOpIURtH/oNvni4ufqrb59rPemT8ax6XvCana/qnJ3cvdkFfvSepCYiGgCxUlVVBuGgeirrC/BsdFTAlijfJtBrLDq6MmhI8mYARzo+oceJn+qR2tpuUgPgfjcBTI+Vt5mGy5V43SDxKnArTjWvB/+Ua0c3uHoc78AVKCDHpTh4ILDsJE4M7fz12Pcr0i6JuUMCp7k1umbNfRBKlHqXetwPB67o2BAfIgPag+sMClRF+irFSJPe5vwKuYVryuXb8OE6o/eivT76Ppw8HP55Jrdp1TJ6N6QlolWpG84Xk=', NULL, NULL),
(26, 26, 'a3xdV/+yfQBQjS42XfSaKwZUS9odY5ALr1ZmlffjlWSsURYS6GCDzudM2JkyMdwPzB/vEsAjLrn+2FhaygzZ614waSaDc8/r7Lm6kxOigwLrjuaPONfxoxwLghjPOLoyrAm58q0skmjdwnMTgit1RjwiNtaEYge3cNXw1AU+IZvEW0WqOdoxaSm+v3cLiYivKiQohSnUkv5/FkXcN2IgyQyEYggTPc7Pk83zzI1b1jg85ThCDgUkdxl920q61vai5XHZ1dA948PXR+0PSbyLZlgcTdczESbNLbx2a+y8Sp4=', NULL, NULL),
(27, 27, 'II4k/80pJlNXYeKwOoIXNBPDjeQAhrnWOVTM8kmJbIBgGYKb3lFgfC8qC5uIqweW6ezbnI2fzsXdCC65FhMzZ/mEX2ge/3B/lDX/OlsUqq57eOguPA/N3k1EVyipBRMmbqMIKxIqrmMxUkdhhmt3fcY0eAm6ox++iqlRSBSZtIWEJysgxv+ldq27eeD6947EPppGAUFV8Zt6hoKyzLqtcpRLIpkQjxQSdqrMTeF5OWWq2G+zemc/DJT6VhN1JKcLRaW7e2TnCc+kcUNuP6VKHoHcx6ihsrjkDyz3kubYvSbt9FeToP3T1P+rtGn+2bux3Xitr+viA2KzlZlXYqvc2CrIIRrVCLjEtDWTC/2l9l4=', 'RSF7U1ofr1ADhfqv7Y0Jwb9UHSBgc7SHDkWSj/WQzRaL2tPO1tyAaDh8FK/rsFc3KraZxtWEZEYy9xxTj1iyXopUzOso6Lm6cNZ4IqsweNStnHkGfEHtjuGy0D6PcIKz2d5OdNc7JQK2NcpfFfIE6PSI0LLtDMMgZEXz7bztQLs=', '2026-07-25 11:38:33'),
(28, 28, '1vI9mUojb0A7yDkPS3cZ7tVUn9f8KtH9qYA8xqEyYpfkqnWBYuCZjqm+guNkkd0+gAimEKUkIsR8UGZW3oQG+N5rUPgvwaE5luTnZ1THk+COjM4tVEWQB4iKog8HXsD6kv7nLx0s8KF52fCdUhA75X36V/HAO9YDZGGE007KmUk2xHgtH9y/7OZtVl9N46jle8hV7w7gEKuPXhBm/bALco3KdDHlZqxFDQuZ78nUIbhReWO033RuazblT3xeB5NiroHMQD1pxsnewwvDe6PrOYiljErVT2G/GDoHIX/Lex0=', NULL, NULL),
(29, 29, 'oPPfrZRhDQs24JyZvsq9YKnYHNMDYvMVjptnTpqhLHiehjyFwUYL9zrBH2wptev/6cfuz2H/o/v9OvAfOkyva6DJ/KgdT3MURpWAyOUAHZ+7EEgp++itnJXAjRMjUc8cMmLx/9nB/VeUIZOfK6wQwNB85A+YYr3XyGnJ3FaIumdbJEWMkeYbsrHVfVk+8kk/xHGwbRTPQxDA8WOmoVkeL9LU54VCf92lQ9Q3uxMIIgGwFONByoXvnAEH0Y1Xj1iIOX5XtC2kbF3t2oy1Ds4of5uHb4g00PGrvmwsSUHBjLk=', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `platform_connection_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `media_temp_path` varchar(512) DEFAULT NULL,
  `external_post_id` varchar(255) DEFAULT NULL,
  `status` enum('draft','scheduled','processing','queued','publishing','published','failed','deleted') NOT NULL DEFAULT 'draft',
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `last_attempt_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `client_id`, `platform_connection_id`, `content`, `media_temp_path`, `external_post_id`, `status`, `scheduled_at`, `published_at`, `created_at`, `retry_count`, `last_attempt_at`) VALUES
(108, 1, 25, 'new one', 'clients/1/1784972307_1784972307_ChatGPT Image Jul 22, 2026, 05_49_25 PM.png', '122110481721392348', 'published', NULL, '2026-07-25 09:38:35', '2026-07-25 09:38:27', 0, NULL),
(109, 1, 26, 'new one', 'clients/1/1784972307_1784972307_ChatGPT Image Jul 22, 2026, 05_49_25 PM.png', NULL, 'failed', NULL, NULL, '2026-07-25 09:38:35', 0, NULL),
(110, 1, 25, 'new one', 'clients/1/1784972387_1784972387_ChatGPT Image Jul 22, 2026, 05_49_25 PM.png', '122110482123392348', 'published', NULL, '2026-07-25 09:39:54', '2026-07-25 09:39:47', 0, NULL),
(111, 1, 26, 'new one', 'clients/1/1784972387_1784972387_ChatGPT Image Jul 22, 2026, 05_49_25 PM.png', NULL, 'failed', NULL, NULL, '2026-07-25 09:39:54', 0, NULL),
(112, 1, 25, 'hey there', 'clients/1/1784972431_1784972431_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', '122110482309392348', 'published', NULL, '2026-07-25 09:40:36', '2026-07-25 09:40:31', 0, NULL),
(113, 1, 26, 'hey there', 'clients/1/1784972431_1784972431_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', NULL, 'failed', NULL, NULL, '2026-07-25 09:40:37', 0, NULL),
(114, 1, 25, 'neqwwwwwwwwww', 'clients/1/1784972619_1784972619_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', '122110483017392348', 'published', NULL, '2026-07-25 09:43:45', '2026-07-25 09:43:39', 0, NULL),
(115, 1, 26, 'neqwwwwwwwwww', 'clients/1/1784972619_1784972619_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', NULL, 'failed', NULL, NULL, '2026-07-25 09:43:45', 0, NULL),
(116, 1, 25, 'hello', 'clients/1/1784972937_1784972937_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', '122110484097392348', 'published', NULL, '2026-07-25 09:49:08', '2026-07-25 09:48:57', 0, NULL),
(117, 1, 26, 'hello', 'clients/1/1784972937_1784972937_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', NULL, 'failed', NULL, NULL, '2026-07-25 09:49:08', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `post_logs`
--

CREATE TABLE `post_logs` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `http_status_code` int(11) NOT NULL,
  `response_body` text NOT NULL,
  `success` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `post_logs`
--

INSERT INTO `post_logs` (`id`, `post_id`, `attempted_at`, `http_status_code`, `response_body`, `success`) VALUES
(148, 108, '2026-07-25 09:38:35', 200, '{\"id\":\"122110481721392348\",\"post_id\":\"1243224272200630_122110481739392348\"}', 1),
(149, 109, '2026-07-25 09:38:35', 500, 'Public media URL is unreachable. Local server connection to tunnel failed: Could not resolve host: hnxai-2402-a00-405-b941-811d-c844-83b7-c2dc.free.pinggy.net. Please ensure your public tunnel (e.g. lhr.life or ngrok) is active and running.', 0),
(150, 110, '2026-07-25 09:39:54', 200, '{\"id\":\"122110482123392348\",\"post_id\":\"1243224272200630_122110482147392348\"}', 1),
(151, 111, '2026-07-25 09:39:54', 500, 'Public media URL is unreachable. Local server connection to tunnel failed: Empty reply from server. Please ensure your public tunnel (e.g. lhr.life or ngrok) is active and running.', 0),
(152, 112, '2026-07-25 09:40:36', 200, '{\"id\":\"122110482309392348\",\"post_id\":\"1243224272200630_122110482339392348\"}', 1),
(153, 113, '2026-07-25 09:40:37', 500, 'Public media URL is unreachable. Local server connection to tunnel failed: Empty reply from server. Please ensure your public tunnel (e.g. lhr.life or ngrok) is active and running.', 0),
(154, 114, '2026-07-25 09:43:45', 200, '{\"id\":\"122110483017392348\",\"post_id\":\"1243224272200630_122110483041392348\"}', 1),
(155, 115, '2026-07-25 09:43:47', 500, 'Public media URL is unreachable. Local server connection to tunnel failed: Empty reply from server. Please ensure your public tunnel (e.g. lhr.life or ngrok) is active and running.', 0),
(156, 116, '2026-07-25 09:49:08', 200, '{\"id\":\"122110484097392348\",\"post_id\":\"1243224272200630_122110484115392348\"}', 1),
(157, 117, '2026-07-25 09:49:09', 500, 'Public media URL is unreachable. Local server connection to tunnel failed: Empty reply from server. Please ensure your public tunnel is active and running.', 0),
(158, 117, '2026-07-25 10:10:21', 42, 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'p.media_path\' in \'field list\'', 0),
(159, 115, '2026-07-25 10:10:27', 42, 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'p.media_path\' in \'field list\'', 0),
(167, 113, '2026-07-25 10:11:27', 42, 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'p.media_path\' in \'field list\'', 0),
(168, 111, '2026-07-25 10:11:32', 42, 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'p.media_path\' in \'field list\'', 0),
(169, 109, '2026-07-25 10:11:37', 42, 'SQLSTATE[42S22]: Column not found: 1054 Unknown column \'p.media_path\' in \'field list\'', 0);

-- --------------------------------------------------------

--
-- Table structure for table `webhook_events`
--

CREATE TABLE `webhook_events` (
  `id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `raw_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`raw_payload`)),
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_messages`
--

CREATE TABLE `whatsapp_messages` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `sender_number` varchar(50) NOT NULL,
  `message_text` text DEFAULT NULL,
  `message_type` varchar(50) NOT NULL DEFAULT 'text',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `client_api_keys`
--
ALTER TABLE `client_api_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_key` (`api_key`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `media_files`
--
ALTER TABLE `media_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `platform_connections`
--
ALTER TABLE `platform_connections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_client_platform_account` (`client_id`,`platform`,`external_account_id`);

--
-- Indexes for table `platform_tokens`
--
ALTER TABLE `platform_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_platform_connection` (`platform_connection_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `platform_connection_id` (`platform_connection_id`),
  ADD KEY `idx_posts_status_sched` (`status`,`scheduled_at`);

--
-- Indexes for table `post_logs`
--
ALTER TABLE `post_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `webhook_events`
--
ALTER TABLE `webhook_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `client_api_keys`
--
ALTER TABLE `client_api_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `media_files`
--
ALTER TABLE `media_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_connections`
--
ALTER TABLE `platform_connections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `platform_tokens`
--
ALTER TABLE `platform_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT for table `post_logs`
--
ALTER TABLE `post_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=172;

--
-- AUTO_INCREMENT for table `webhook_events`
--
ALTER TABLE `webhook_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `client_api_keys`
--
ALTER TABLE `client_api_keys`
  ADD CONSTRAINT `client_api_keys_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media_files`
--
ALTER TABLE `media_files`
  ADD CONSTRAINT `media_files_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `platform_connections`
--
ALTER TABLE `platform_connections`
  ADD CONSTRAINT `platform_connections_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `platform_tokens`
--
ALTER TABLE `platform_tokens`
  ADD CONSTRAINT `platform_tokens_ibfk_1` FOREIGN KEY (`platform_connection_id`) REFERENCES `platform_connections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`platform_connection_id`) REFERENCES `platform_connections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `post_logs`
--
ALTER TABLE `post_logs`
  ADD CONSTRAINT `post_logs_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `webhook_events`
--
ALTER TABLE `webhook_events`
  ADD CONSTRAINT `webhook_events_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  ADD CONSTRAINT `whatsapp_messages_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
