-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 03, 2026 at 12:19 PM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u689131217_dashboard_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics_cache`
--

CREATE TABLE `analytics_cache` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `platform` varchar(50) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` text NOT NULL,
  `period` varchar(50) DEFAULT 'lifetime',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `analytics_cache`
--

INSERT INTO `analytics_cache` (`id`, `client_id`, `platform`, `metric_name`, `metric_value`, `period`, `updated_at`) VALUES
(1, 3, 'facebook', 'page_post_engagements', '0', 'day', '2026-07-28 06:20:17'),
(2, 3, 'facebook', 'page_views_total', '0', 'day', '2026-07-28 06:20:17'),
(3, 1, 'facebook', 'followers_count', '1', 'lifetime', '2026-07-28 10:33:24'),
(4, 1, 'facebook', 'fan_count', '1', 'lifetime', '2026-07-28 10:33:24'),
(5, 1, 'facebook', 'page_post_engagements', '0', 'day', '2026-07-28 10:33:24'),
(6, 1, 'facebook', 'page_views_total', '0', 'day', '2026-07-28 10:33:24');

-- --------------------------------------------------------

--
-- Table structure for table `client_hub_keys`
--

CREATE TABLE `client_hub_keys` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `hub_api_key` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_hub_keys`
--

INSERT INTO `client_hub_keys` (`id`, `client_id`, `hub_api_key`, `created_at`) VALUES
(1, 1, '94a21f797c70cf6c158c088c32a94bf89a1e400b646989b4cb445c8ff15a9978', '2026-07-18 09:49:26'),
(2, 2, '0c651584d962aebbc7709a223063d2f013d617e5fde87f7d7ac0bc7b9e2392c9', '2026-07-18 11:28:37'),
(3, 3, '788aae4b167847192acb0c5e1a2fc3875cf638eab97c559cd223632df0f452af', '2026-07-18 11:30:57'),
(4, 4, 'd50ee2ff45619f9b9a1a76f0d9ca32d6ca1f3de89525f9986674557f9cec1f3e', '2026-07-25 12:04:32');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reset_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `reset_token`, `expires_at`, `used`) VALUES
(1, 3, '5b6c92f0ca13da497571e09ccc93a9f8aa76abbbafd783f2ae9f4ac77c114b8d', '2026-07-18 09:49:49', 1),
(3, 5, '0b9666c5f7eb67b2345f397a7670121a55120975b8b40fd66d81242dbe5f1679', '2026-07-18 11:31:17', 1),
(4, 6, 'bac7d44c92ace3f3686d9893b5ccb0748cff57864916fa9e14db7de2f1ff608f', '2026-07-25 12:04:47', 1);

-- --------------------------------------------------------

--
-- Table structure for table `posts_cache`
--

CREATE TABLE `posts_cache` (
  `id` int(11) NOT NULL,
  `hub_post_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'draft',
  `platform` varchar(50) NOT NULL,
  `media_path` varchar(512) DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `external_post_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `views_count` int(11) NOT NULL DEFAULT 0,
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `comments_count` int(11) NOT NULL DEFAULT 0,
  `duration` varchar(50) DEFAULT NULL,
  `shares_count` int(11) NOT NULL DEFAULT 0,
  `impressions_count` int(11) NOT NULL DEFAULT 0,
  `reach_count` int(11) NOT NULL DEFAULT 0,
  `clicks_count` int(11) NOT NULL DEFAULT 0,
  `engagement_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts_cache`
--

INSERT INTO `posts_cache` (`id`, `hub_post_id`, `client_id`, `content`, `status`, `platform`, `media_path`, `scheduled_at`, `published_at`, `external_post_id`, `created_at`, `retry_count`, `last_attempt_at`, `views_count`, `likes_count`, `comments_count`, `duration`, `shares_count`, `impressions_count`, `reach_count`, `clicks_count`, `engagement_count`) VALUES
(1, 3, 1, 'at 6:00pm', 'deleted', 'facebook', 'clients/1/1784376342_1784376342_phone-call (2).png', '2026-07-18 12:30:00', NULL, NULL, '2026-07-18 12:05:42', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(5, 7, 1, 'fbsdfber', 'deleted', 'facebook', 'clients/1/1784377386_1784377386_web (1).png', NULL, NULL, NULL, '2026-07-18 12:23:07', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(6, 8, 1, 'testing', 'deleted', 'facebook', 'clients/1/1784378003_1784378003_gps.png', NULL, '2026-07-18 09:03:43', '122106334437392348', '2026-07-18 12:33:43', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(7, 9, 1, 'test 6:07', 'deleted', 'facebook', 'clients/1/1784378140_1784378140_ambedkar-jayanti.jpg', NULL, '2026-07-18 09:05:53', '122106336087392348', '2026-07-18 12:35:53', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(8, 10, 1, 'fwefwefwe', 'deleted', 'facebook', 'clients/1/1784522773_1784522773_08412fb854693e1cab68e1340ac6752d.jpg', NULL, NULL, NULL, '2026-07-20 04:46:15', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(9, 11, 1, 'fwefwefwe', 'deleted', 'facebook', 'clients/1/1784522794_1784522794_08412fb854693e1cab68e1340ac6752d.jpg', NULL, NULL, NULL, '2026-07-20 04:46:34', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(10, 12, 1, 'fwefwefwe', 'deleted', 'facebook', 'clients/1/1784523037_1784523037_08412fb854693e1cab68e1340ac6752d.jpg', NULL, NULL, NULL, '2026-07-20 04:50:39', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(11, 13, 1, 'esfwewegw', 'deleted', 'facebook', 'clients/1/1784523062_1784523062_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, NULL, NULL, '2026-07-20 04:51:10', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(12, 14, 1, 'wfqwefqe', 'deleted', 'facebook', 'clients/1/1784523265_1784523265_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 01:24:48', '122107861455392348', '2026-07-20 04:54:48', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(13, 15, 1, 'our first try #try', 'deleted', 'youtube', 'clients/1/1784524897_1784524897_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 01:51:48', 'Rui5dLKnWnM', '2026-07-20 05:21:48', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(14, 16, 1, 'wdqwdqedqwe', 'deleted', 'youtube', 'clients/1/1784525140_1784525140_16392302_1920_1080_30fps.mp4', NULL, '2026-07-20 01:56:28', 'SqDuJyncYBM', '2026-07-20 05:26:28', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(15, 17, 1, 'sfqwefwefw', 'deleted', 'facebook', 'clients/1/1784525287_1784525287_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 05:28:08', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(16, 18, 1, 'sfqwefwefw', 'deleted', 'youtube', 'clients/1/1784525287_1784525287_16392302_1920_1080_30fps.mp4', NULL, '2026-07-20 01:58:34', 'r6oCo0yYU2U', '2026-07-20 05:28:34', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(17, 19, 1, 'wrfwe', 'deleted', 'facebook', 'clients/1/1784526954_1784526954_16392302_1920_1080_30fps.mp4', '2026-07-20 05:56:00', NULL, NULL, '2026-07-20 05:55:55', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(18, 20, 1, 'wrfwe', 'deleted', 'youtube', 'clients/1/1784526954_1784526954_16392302_1920_1080_30fps.mp4', '2026-07-20 05:56:00', NULL, NULL, '2026-07-20 05:55:55', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(19, 21, 1, 'wdfqwfqf', 'deleted', 'facebook', 'clients/1/1784527091_1784527091_16392302_1920_1080_30fps.mp4', NULL, '2026-07-20 02:29:05', '1712561063400489', '2026-07-20 05:59:05', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(20, 22, 1, 'New Dashboard Upload', 'deleted', 'youtube', 'https://i.ytimg.com/vi/lQ7uv7PifeQ/maxresdefault.jpg', NULL, '2026-07-20 00:30:58', 'lQ7uv7PifeQ', '2026-07-20 05:59:53', 0, NULL, 13, 4, 6, NULL, 0, 13, 0, 0, 0),
(21, 23, 1, 'zsfaes', 'deleted', 'facebook', 'clients/1/1784527416_1784527416_16392302_1920_1080_30fps.mp4', '2026-07-20 06:05:00', NULL, NULL, '2026-07-20 06:03:36', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(22, 24, 1, 'zsfaes', 'deleted', 'youtube', 'clients/1/1784527416_1784527416_16392302_1920_1080_30fps.mp4', '2026-07-20 06:05:00', NULL, NULL, '2026-07-20 06:03:36', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(23, 25, 1, 'grthere', 'deleted', 'facebook', 'clients/1/1784527870_1784527870_16392302_1920_1080_30fps.mp4', '2026-07-20 06:13:00', NULL, NULL, '2026-07-20 06:11:10', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(24, 26, 1, 'grthere', 'deleted', 'youtube', 'clients/1/1784527870_1784527870_16392302_1920_1080_30fps.mp4', '2026-07-20 06:13:00', NULL, NULL, '2026-07-20 06:11:10', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(26, 28, 1, 'hello all its 12 08', 'deleted', 'facebook', 'clients/1/1784529440_1784529440_16392302_1920_1080_30fps.mp4', '2026-07-20 06:38:00', NULL, NULL, '2026-07-20 06:37:20', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(27, 29, 1, 'hello all its 12 08', 'deleted', 'youtube', 'clients/1/1784529440_1784529440_16392302_1920_1080_30fps.mp4', '2026-07-20 06:38:00', NULL, NULL, '2026-07-20 06:37:20', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(28, 30, 1, 'hello all its 12 08', 'deleted', 'linkedin', 'clients/1/1784529440_1784529440_16392302_1920_1080_30fps.mp4', '2026-07-20 06:38:00', NULL, NULL, '2026-07-20 06:37:20', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(29, 31, 1, 'hello', 'deleted', 'facebook', 'clients/1/1784531344_1784531344_16392302_1920_1080_30fps.mp4', '2026-07-20 07:10:00', NULL, NULL, '2026-07-20 07:09:04', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(30, 32, 1, 'hello', 'deleted', 'youtube', 'clients/1/1784531344_1784531344_16392302_1920_1080_30fps.mp4', '2026-07-20 07:10:00', NULL, NULL, '2026-07-20 07:09:04', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(31, 33, 1, 'hello', 'deleted', 'linkedin', 'clients/1/1784531344_1784531344_16392302_1920_1080_30fps.mp4', '2026-07-20 07:10:00', NULL, NULL, '2026-07-20 07:09:04', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(32, 50, 1, 'dfwerfwerfgerverdvrververververververvewsvasdfghwrjhejtneyjrdgntyf', 'deleted', 'facebook', 'clients/1/1784531992_1784531992_16392302_1920_1080_30fps.mp4', '2026-07-20 07:20:00', NULL, NULL, '2026-07-20 07:19:52', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(33, 51, 1, 'dfwerfwerfgerverdvrververververververvewsvasdfghwrjhejtneyjrdgntyf', 'deleted', 'youtube', 'clients/1/1784531992_1784531992_16392302_1920_1080_30fps.mp4', '2026-07-20 07:20:00', NULL, NULL, '2026-07-20 07:19:52', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(34, 52, 1, 'dfwerfwerfgerverdvrververververververvewsvasdfghwrjhejtneyjrdgntyf', 'deleted', 'linkedin', 'clients/1/1784531992_1784531992_16392302_1920_1080_30fps.mp4', '2026-07-20 07:20:00', NULL, NULL, '2026-07-20 07:19:52', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(35, 57, 1, 'qwdqwdqw', 'deleted', 'facebook', 'clients/1/1784532430_1784532430_16392302_1920_1080_30fps.mp4', '2026-07-20 07:30:00', '2026-07-20 07:31:03', '1369197587870528', '2026-07-20 07:27:10', 0, '2026-07-20 07:30:25', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(36, 58, 1, 'qwdqwdqw', 'deleted', 'youtube', 'clients/1/1784532430_1784532430_16392302_1920_1080_30fps.mp4', '2026-07-20 09:29:24', NULL, NULL, '2026-07-20 07:27:10', 2, '2026-07-20 08:49:24', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(39, 65, 1, 'ef3wgwrgwergvserfgvergesredfvergerg', 'deleted', 'instagram', 'clients/1/1784541666_1784541666_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 10:01:06', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(44, 70, 1, 'sdqweqwe', 'deleted', 'instagram', 'clients/1/1784544287_1784544287_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 10:44:49', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(45, 71, 1, 'sdqweqwe', 'deleted', 'instagram', 'clients/1/1784544307_1784544307_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:45:08', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(46, 72, 1, 'sdqweqwe', 'deleted', 'instagram', 'clients/1/1784544387_1784544387_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:46:28', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(47, 73, 1, 'wwedwd', 'deleted', 'instagram', 'clients/1/1784544718_1784544718_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:51:58', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(48, 74, 1, 'hello there !!!!', 'deleted', 'instagram', 'clients/1/1784544823_1784544823_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:53:44', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(49, 75, 1, 'hello', 'deleted', 'instagram', 'clients/1/1784544920_1784544920_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:55:21', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(50, 76, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784544998_1784544998_gps.png', NULL, NULL, NULL, '2026-07-20 10:56:39', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(51, 77, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545014_1784545014_gps.png', NULL, NULL, NULL, '2026-07-20 10:56:55', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(52, 78, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545023_1784545023_gps.png', NULL, NULL, NULL, '2026-07-20 10:57:04', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(53, 79, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545033_1784545033_gps.png', NULL, NULL, NULL, '2026-07-20 10:57:14', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(54, 80, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545107_1784545107_gps.png', NULL, NULL, NULL, '2026-07-20 10:58:28', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(55, 81, 1, 'hello', 'deleted', 'instagram', 'clients/1/1784546369_1784546369_gps.png', NULL, NULL, NULL, '2026-07-20 11:19:29', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(56, 82, 1, 'hello', 'published', 'instagram', 'clients/1/remote_media_8itlcukhpldtchgf74Y.jpg', NULL, '2026-07-20 05:55:28', '18090779117378374', '2026-07-20 11:25:33', 0, NULL, 1, 1, 0, NULL, 0, 1, 1, 0, 1),
(57, 83, 1, 'hello there', 'deleted', 'facebook', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, '2026-07-20 11:32:05', '122108090475392348', '2026-07-20 11:32:05', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(58, 84, 1, 'hello there', 'deleted', 'youtube', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 11:32:05', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(59, 85, 1, 'hello there', 'published', 'linkedin', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, '2026-07-20 11:32:06', NULL, '2026-07-20 11:32:06', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(60, 86, 1, 'hello there', 'deleted', 'instagram', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 11:32:18', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(62, 88, 1, 'hello !!!', 'published', 'instagram', 'clients/1/remote_media_1v44j6n22ng63P7OiPG.jpg', NULL, '2026-07-20 06:06:46', '18201211261362785', '2026-07-20 11:36:49', 0, NULL, 1, 1, 0, NULL, 0, 1, 1, 0, 1),
(63, 89, 1, 'hello 2 !!!!', 'published', 'facebook', 'clients/1/1784547456_1784547456_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 11:37:46', '122108092851392348', '2026-07-20 11:37:46', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(64, 90, 1, 'hello 2 !!!!', 'published', 'linkedin', 'clients/1/1784547456_1784547456_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 11:37:47', NULL, '2026-07-20 11:37:47', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(65, 91, 1, 'hello 2 !!!!', 'deleted', 'instagram', 'clients/1/remote_media_vdei5rlcdcjp3oHPDDJ.jpg', NULL, '2026-07-20 06:08:00', '18118078432677883', '2026-07-20 11:38:03', 0, NULL, 1, 1, 0, NULL, 0, 1, 1, 0, 1),
(66, 92, 1, 'hello 2 !!!!', 'published', 'youtube', 'clients/1/1784547456_1784547456_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 11:38:10', 'eTcPpOWwnP8', '2026-07-20 11:38:10', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(67, 93, 1, 'hello on 5:11', 'deleted', 'facebook', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 12:01:23', NULL, NULL, '2026-07-20 11:39:31', 1, '2026-07-20 11:41:23', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(68, 94, 1, 'hello on 5:11', 'deleted', 'linkedin', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 11:41:00', '2026-07-20 11:41:24', NULL, '2026-07-20 11:39:31', 0, '2026-07-20 11:41:07', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(69, 95, 1, 'hello on 5:11', 'deleted', 'instagram', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 12:01:24', NULL, NULL, '2026-07-20 11:39:31', 1, '2026-07-20 11:41:24', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(70, 96, 1, 'hello on 5:11', 'published', 'youtube', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 11:41:00', '2026-07-20 11:41:28', 'GI6JGlngLGg', '2026-07-20 11:39:31', 0, '2026-07-20 11:41:07', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(71, 97, 1, 'hiii its 5:19', 'deleted', 'facebook', 'clients/1/1784548074_1784548074_phone-call (2).png', '2026-07-20 11:49:00', '2026-07-20 11:49:46', '122108099163392348', '2026-07-20 11:47:54', 0, '2026-07-20 11:49:41', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(73, 99, 1, 'hiii its 5:19', 'deleted', 'instagram', 'clients/1/1784548074_1784548074_phone-call (2).png', '2026-07-20 11:57:20', NULL, NULL, '2026-07-20 11:47:54', 3, '2026-07-20 11:57:29', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(74, 100, 1, 'hiii its 5:19', 'deleted', 'youtube', 'clients/1/1784548074_1784548074_phone-call (2).png', '2026-07-20 11:49:00', '2026-07-20 11:49:51', 'mnzBpSDlwjE', '2026-07-20 11:47:54', 0, '2026-07-20 11:49:41', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(75, 101, 1, 'wedqawed', 'deleted', 'facebook', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', '2026-07-20 12:26:17', '122108122731392348', '2026-07-20 12:15:09', 0, '2026-07-20 12:26:07', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(76, 102, 1, 'wedqawed', 'deleted', 'linkedin', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', '2026-07-20 12:26:18', NULL, '2026-07-20 12:15:09', 0, '2026-07-20 12:26:07', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(77, 103, 1, 'wedqawed', 'deleted', 'instagram', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', NULL, NULL, '2026-07-20 12:15:09', 0, '2026-07-20 12:26:18', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(78, 104, 1, 'wedqawed', 'deleted', 'youtube', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', NULL, NULL, '2026-07-20 12:15:09', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(81, 107, 1, 'New Dashboard Upload', 'deleted', 'youtube', 'https://i.ytimg.com/vi/OJNllMu9a5E/maxresdefault.jpg', NULL, '2026-07-25 03:24:40', 'OJNllMu9a5E', '2026-07-25 08:54:10', 0, NULL, 11, 5, 5, NULL, 0, 11, 0, 0, 0),
(100, 131, 1, 'hello', 'published', 'facebook', 'https://scontent-bom2-4.xx.fbcdn.net/v/t39.30808-6/758006734_122111606799392348_4466492136901343597_n.jpg?stp=dst-jpg_s720x720_tt6&_nc_cat=106&ccb=1-7&_nc_sid=127cfc&_nc_ohc=-nQ7QmALTRsQ7kNvwHH4dBE&_nc_oc=Ado9DoJVy7N6rkCj_r3X1xKkg2p2FvXnAUQjyIJLjG3KFmNgH4EQW0rKOs3JacTDCIIlcVtHe8S6sQfygq1zkfRv&_nc_zt=23&_nc_ht=scontent-bom2-4.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGprbPlLpZDbJJhvJi1gaHgK3T0MCrsTIvy63IUl3X8JLR6lvdAalX_bkGoCZAbUYoh2deOlsfc4g&oh=00_AQG-dxvTz7d_AcuKyQZqf9cMqeBy3hBcZakSV', NULL, '2026-07-28 04:45:42', '1243224272200630_122111606817392348', '2026-07-28 10:15:55', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(102, 134, 1, 'hello', 'published', 'instagram', 'clients/1/remote_media_mjun981drid4dueMewf.jpg', NULL, '2026-07-28 06:08:04', '18169404568449252', '2026-07-28 11:38:07', 0, NULL, 11, 2, 1, NULL, 0, 11, 11, 0, 3),
(103, 136, 1, 'On the way....', 'published', 'facebook', 'https://scontent-bom5-2.xx.fbcdn.net/v/t39.30808-6/759676773_122112283629392348_5940113098475655729_n.jpg?_nc_cat=108&ccb=1-7&_nc_sid=127cfc&_nc_ohc=NHuwZmFdcbAQ7kNvwH2GBec&_nc_oc=Ado6H7h_LGNKkb6WnGXoBWZlqJEhYmnkTCEB4bTeZpL8FO9a9qUddtTsk8o8SfPS_FrDA0C0YtMMVFLk4LRHSNGU&_nc_zt=23&_nc_ht=scontent-bom5-2.xx&edm=AKIiGfEEAAAA&_nc_gid=IMnVNomPCnS08vSmBkEEeQ&_nc_tpa=Q5bMBQHc_01HJcs79reSyvJqtPOXo7Q80NnH7pWfPeXfejNiOavzk9IafIrH2UG3BXOLq53OEIFo3Q0DbQ&oh=00_AQEF4ejOfQ5ip6uV1OQWtkmZfU-QRBWjZ9eLDGnGiR4jMA&oe=6A70C746', NULL, '2026-07-29 23:36:06', '1243224272200630_122112283647392348', '2026-07-30 05:06:10', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(104, 137, 1, 'On the way....', 'published', 'instagram', 'clients/1/1785387965_cropped_image.jpg', NULL, '2026-07-30 05:06:21', '17899963317489329', '2026-07-30 05:06:21', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(106, 139, 1, 'cheers all... #new #pabloescobar #comingsoon', 'published', 'instagram', 'clients/1/1785388284_cropped_image.jpg', '2026-07-30 05:15:00', '2026-07-30 10:46:13', '18109163860823815', '2026-07-30 05:11:24', 0, '2026-07-30 10:46:01', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(107, 140, 1, 'Cheers all..', 'failed', 'facebook', 'clients/1/1785389312_cropped_image.jpg', '2026-07-30 05:30:00', NULL, NULL, '2026-07-30 05:28:32', 0, '2026-07-30 11:00:23', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(108, 141, 1, 'Cheers i am back........... #pabloescobar', 'failed', 'facebook', 'clients/1/1785390531_cropped_image.jpg', NULL, NULL, NULL, '2026-07-30 05:49:08', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(109, 142, 1, 'Cheers i am back........... #pabloescobar', 'published', 'instagram', 'clients/1/1785390531_cropped_image.jpg', NULL, '2026-07-30 05:49:21', '18616420645042111', '2026-07-30 05:49:21', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(110, 143, 1, 'Cheers boys...... #pabloescobar #new #viral #today', 'failed', 'facebook', 'clients/1/1785390641_cropped_image.jpg', '2026-07-30 05:55:00', NULL, NULL, '2026-07-30 05:50:41', 0, '2026-07-30 11:26:17', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(111, 144, 1, 'Cheers boys...... #pabloescobar #new #viral #today', 'failed', 'instagram', 'clients/1/1785390641_cropped_image.jpg', '2026-07-30 05:55:00', NULL, NULL, '2026-07-30 05:50:41', 0, '2026-07-30 11:26:19', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(115, 150, 1, 'Moonknight ....#marvel #marvel_new #new_post #marvel_character #marvelcharacter #moon', 'published', 'instagram', 'clients/1/1785393545_cropped_image.jpg', '2026-07-30 06:40:00', '2026-07-30 12:16:17', '18607439416022010', '2026-07-30 06:39:05', 0, '2026-07-30 12:16:01', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(119, 154, 1, 'hey', 'published', 'facebook', 'clients/1/1785402679_cropped_image.jpg', '2026-07-30 09:15:00', '2026-07-30 09:15:07', '122112362787392348', '2026-07-30 09:11:19', 0, '2026-07-30 09:15:02', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(120, 155, 1, 'monknight #moonknight\r\n#moon\r\n#marvel\r\n#character\r\n#today', 'published', 'facebook', 'clients/1/1785403028_cropped_image.jpg', '2026-07-30 09:20:00', '2026-07-30 09:21:11', '122112363375392348', '2026-07-30 09:17:08', 0, '2026-07-30 09:21:01', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(121, 156, 1, 'monknight #moonknight\r\n#moon\r\n#marvel\r\n#character\r\n#today', 'failed', 'instagram', 'clients/1/1785403028_cropped_image.jpg', '2026-07-30 09:20:00', NULL, NULL, '2026-07-30 09:17:09', 0, '2026-07-30 09:21:11', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(127, 162, 1, 'moon', 'published', 'instagram', 'clients/1/1785404714_cropped_image.jpg', '2026-07-30 09:50:00', '2026-07-30 09:50:31', '18006785648761463', '2026-07-30 09:45:14', 0, '2026-07-30 09:50:02', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(129, 164, 1, 'new', 'published', 'instagram', 'clients/1/1785404805_cropped_image.jpg', '2026-07-30 09:50:00', '2026-07-30 09:50:43', '18092760587317588', '2026-07-30 09:46:45', 0, '2026-07-30 09:50:02', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(142, 177, 1, '15551515515515', 'failed', 'instagram', 'clients/1/1785407764_123 (2).mp4', '2026-07-30 10:40:00', NULL, NULL, '2026-07-30 10:36:05', 0, '2026-07-30 10:55:01', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(143, 178, 1, '15551515515515', 'failed', 'youtube', 'clients/1/1785407764_123 (2).mp4', '2026-07-30 10:40:00', NULL, NULL, '2026-07-30 10:36:05', 0, '2026-07-30 10:55:01', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(190, NULL, 3, 'Test post from API at 2026-07-29 17:17:43', 'published', 'facebook', NULL, NULL, '2026-07-29 06:17:44', '1169742216226175_122107701807400851', '2026-08-03 05:52:49', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(191, NULL, 3, 'Test post from API at 2026-07-29 17:17:38', 'published', 'facebook', NULL, NULL, '2026-07-29 06:17:39', '1169742216226175_122107701783400851', '2026-08-03 05:52:49', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(192, NULL, 3, 'Vibe’s…🧡', 'published', 'instagram', 'clients/3/remote_media_t8fb3vt9tgtm9WTzHtt.jpg', NULL, '2024-11-11 09:21:24', '17854713096262076', '2026-08-03 05:52:49', 0, NULL, 0, 118, 4, NULL, 0, 0, 0, 0, 0),
(193, NULL, 3, '😎😮‍💨…', 'published', 'instagram', 'clients/3/remote_media_hekdjq9ajr84cFzSqh9.jpg', NULL, '2023-12-12 13:10:11', '18011394518311732', '2026-08-03 05:52:50', 0, NULL, 0, 108, 5, NULL, 0, 0, 0, 0, 0),
(194, NULL, 3, 'Strike a pose, it’s picture-perfect! 💚', 'published', 'instagram', 'clients/3/remote_media_pvj0mn0mjlqm5pvbZj9.jpg', NULL, '2023-11-03 02:44:32', '17851327296070262', '2026-08-03 05:52:50', 0, NULL, 0, 113, 11, NULL, 0, 0, 0, 0, 0),
(195, NULL, 3, 'Moments 😎… ;)', 'published', 'instagram', 'clients/3/remote_media_tivh1713chq48VbE3n3.jpg', NULL, '2023-01-25 09:31:36', '17952988073414115', '2026-08-03 05:52:50', 0, NULL, 0, 105, 22, NULL, 0, 0, 0, 0, 0),
(196, NULL, 3, '', 'published', 'instagram', 'clients/3/remote_media_r6dntjasdampbvdMrQM.jpg', NULL, '2022-12-25 07:37:22', '17912593169668181', '2026-08-03 05:52:50', 0, NULL, 0, 66, 9, NULL, 0, 0, 0, 0, 0),
(207, NULL, 4, 'Need  an income source  without  chain system,  without  degree , without experience?🤨🤔\nYou are at right place ✅\n If you are watching 👀  this reel, this is your sign to text me and start  working 💪.\n\nJust save this reel, Comment \'Interested \' and dm me \'Earn\'  to get started , I\'ll share  the details with you✅\n\n#earnonlinemoney #earnandlearnprogram #earningopportunity #makemoneyonlinefast #makemoneyfromyourphone #bizgurukulcourses #bizgurukulcommunity #bizgurukul #bizgurukulcontent #beyourownbosstoday #mindset #affiliatemarketingtipps #affiliate #affiliateworld #enterprenuerlifestyle #enterpreneurship #enterpreneur #indiamoney #financialfreedom💰 #financialfreedom #learnandearn #learnandearnonline', 'published', 'instagram', 'clients/4/remote_media_sdfg8n0dl2kn0DFZGQP.mp4', NULL, '2022-07-24 04:00:09', '17886017144678262', '2026-08-03 05:55:55', 0, NULL, 30, 69, 3, NULL, 0, 0, 0, 0, 0),
(208, NULL, 4, 'Start today💥\n\n#businessownermindset #busniesspassion #motivationalquotes #motivationquotes #motivational #learnandgrow #learnandearn #affiliatemarketingtips #affiliatemarketingtipps #affiliateindia #affiliatemarketingbusinessmodel #beyourownbosstoday #bizgurukulfamily #bizgurukul #bizgurukulindia❤️ #learningathome #workfromhomejobs #workworkworkworkwork #workhard #earnonlinemoney #earnandlearnprogram #digitalmarketing #digitalmarketingtips', 'published', 'instagram', 'clients/4/remote_media_g8rc9o94dkng4xXec6R.jpg', NULL, '2021-09-23 22:23:28', '18007331371345170', '2026-08-03 05:55:55', 0, NULL, 26, 20, 0, NULL, 0, 0, 0, 0, 0),
(209, NULL, 4, 'Benfits of affiliate marketing:\n1)Low cost of start-up\n\nAn affiliate program does not require that you have an advertising team for ad visuals or purchase ad space.\nRather than that, you’ll have to depend on your affiliates to come up with their marketing content. Other than the initial effort of selecting and vetting affiliates, there’s little effort required from you to market your products, which is one of the reasons it’s become such a popular method of marketing.\n\n2)Flexibility\n\nYou can easily make your affiliate program smaller or bigger at little or no cost. It also offers you a great way to scale up your business without breaking the bank.\n\n3)It’s Versatile\n\nAs an affiliate, you can choose to focus on one type of industry or work with any company that has an affiliate program irrespective of what they are selling. \nThe affiliate marketing model can work with any industry. Therefore, whether they are selling goods or services, affiliates can still sell using the same model.\n\n4)It’s Performance-Based\n\nThe affiliate program is entirely performance-based because affiliates are paid on a commission once they deliver a client. This motivates them to want to do more so that they can deliver the conversion that the advertiser is looking for.\nAffiliate marketing strategy also ensures that only valuable or profitable traffic is driven to the website. This is one of the significant benefits of affiliate marketing that publishers enjoy. \n\n Earn and learn 🤑💷\n\nFollow @shinewithfalguni \n\nDm me to join\n \n#reelkarofeelkaro❤️❤️❤️❤️ #reelsofficial #reeloftheday #affiliatemarketingtips #instagram #affiliatemarketingtips #growyourbrand #personalbrandingonline #personalbrand #beliveinyourself #bosslady #bossgirl #peronalbranding #instagrammarketingtips #instagrammarketing #growypurbusiness #workfromanywhereintheworld #workfromhomejobs #workfromyoursmartphone #bizgurukulcourses #bizgurukulcommunity #bizgurukulindia❤️ #bizgurukulfamily #digitalmarketing #marketingstrategy #marketingideas #conentcreator #socialmedia #socialmediamarketing', 'published', 'instagram', 'clients/4/remote_media_u833ngrem4hd4qblBOS.mp4', NULL, '2021-09-23 12:54:04', '17884840268402784', '2026-08-03 05:55:55', 0, NULL, 27, 52, 2, NULL, 0, 0, 0, 0, 0),
(210, NULL, 4, 'Affiliatemarketing is  the Future💯\nAccording  to IAMAI, the digital commerce market has seen a growth by 33% \nThe objective  of this research  paper is to analyze  adaptability  of affiliate  marketing  in Indian businesses and the future potential  of it\n\nFor more info dm me\n\nFollow @shinewithfalguni\n\n#affiliatemarketingtipps#affiliatemarketingtips#affiliatemarketingbusinessmodel #digitalmarketing #affiliateprogram #affilatemarketer #internetmarketingtraining #earnmoney #finanicalfreedom #finanicalhelp #passiveincomeideas #passiveincomeonline #passiveincome #enterpreneurlife #enterpreneurship #beliveinyourself #bizgurukulfamily #bizgurukulcourses #bizgurukulcommunity #bizgurukulindia❤️ #bizgurukulfamily❤️ #makemoneyfromyourphone #workfromanywhereintheworld #digitalmarketing #digitalmarketingtips #networkmarketing #businessownermindset #mindset #earnonlinemoney #learnandearn #learnandearn📚💰', 'published', 'instagram', 'clients/4/remote_media_2jjl9mg972pkfjZ1Yy3.jpg', NULL, '2021-08-30 05:56:59', '18199008850129514', '2026-08-03 05:55:55', 0, NULL, 14, 24, 0, NULL, 0, 0, 0, 0, 0),
(211, NULL, 4, '💯\n Follow for more\nDm @shinewithfalguni \n\n#motivation #motivationalquotes #enterprenuerlifestyle #enterpreneurlife #entrepreneurs #growthmindset #growth #grow #mindset #affiliatemarketingtips😎 #affiliatemarketingtipps #digitalmarketing #income #independent #financialfreedom #passiveincomestream #passiveincomeonline #earnandlearnprogram #earings #tipsforbusiness #businessowner #bosslife #beyourownbosstoday #beliveinyourself #beyourownbosstoday #earnfromyoursmartphone #digitalmarketingtips #bizgurukulfamily #freelancing #youdecide #decidenow #growth', 'published', 'instagram', 'clients/4/remote_media_c2r842f4cde2fsQXHdB.jpg', NULL, '2021-07-07 07:30:16', '18113447359243649', '2026-08-03 05:55:55', 0, NULL, 13, 33, 0, NULL, 0, 0, 0, 0, 0),
(212, NULL, 4, 'You are 3 steps away from becoming  financially independent \nEarn 2k to 4k daily \n\nDm @shinewithfalguni \n\n#financialfreedom #financialfreedom💰 #enterprenuerlifestyle #enrollnow #entrepreneurship #beyourownbosstoday #beliveinyourself❤️ #believer #affiliatemarketingtips😎 #affiliateindia #affiliatemarketingbusinessmodel #affiliateworld #digitalmarketing #passiveincomestream #passiveincomestream #earnandlearnprogram #earnmoney #workfromanywhereintheworld #workfromyourphone #bizgurukulfamily #bizgurukulindia❤️ #bizgurukulfamily❤️ #bosslife #businessowner #ownboss24x7 #ownbusinessowner #socialbusiness #income #investor #moneymoney #smallbusiness', 'published', 'instagram', 'clients/4/remote_media_tb4a5b3c85j4dRG51Rq.jpg', NULL, '2021-07-06 07:39:00', '17890389842261291', '2026-08-03 05:55:55', 0, NULL, 18, 52, 1, NULL, 0, 0, 0, 0, 0),
(213, NULL, 4, 'Be your own brand with bizgurukul \nEarn 2k to 4k daily \n Dm me for more information \nFollow @shinewithfalguni \n\n#bizgurukulindia❤️ #bizgurukulfamily❤️ #bizgurukul #affiliatemarketingbusinessmodel #affiliateindia #affiliatemarketingtips #affiliatemarketingtips😎 #enterprenuerlifestyle #enterpreneur #enterpreneurlife #beyourownbosstoday #workfromyourphone #beliveinyourself #beliveinyourself❤️ #brands #earings #earnmoney #income #passiveincomestream #passiveincomeonline #salestips #digitalmarketing #investor #learningathome #earnandlearn #earnandlearnprogram #independent #instagood #financialfreedom #freelancing', 'published', 'instagram', 'clients/4/remote_media_iqabsef8vd1o35sYnO3.jpg', NULL, '2021-07-04 01:06:29', '17914072894750980', '2026-08-03 05:55:55', 0, NULL, 21, 24, 3, NULL, 0, 0, 0, 0, 0),
(214, NULL, 4, 'It\'s all about your hard work🌞\nKeep hustling💪\n\n#enterpreneurlife #entrepreneurship #indiamoney #money #motivationalquotes #motivation #financialfreedom #financialfreedom dom💰 #investment #investor #bizgurukulindia❤️ #bizgurukulfamily #freelancing #freelancework #workfromanywhereintheworld #workfromyourphone #earning #earnfromyoursmartphone #businessownermindset #businessownermindset✔️ #independentwoman #ownbusinessowner #ownboss😎 #ownbusiness #workworkworkworkwork #affiliatemarketingtips😎 #affiliateindia #digitalmarketing #makemoney #opportunity #beliveinyourself', 'published', 'instagram', 'clients/4/remote_media_svauq0ebo1blaG7a74v.jpg', NULL, '2021-07-01 10:52:18', '17905526038971499', '2026-08-03 05:55:55', 0, NULL, 17, 34, 1, NULL, 0, 0, 0, 0, 0),
(215, NULL, 4, 'Become financially independent 💰 work from your phone 📱 and Earn💸\nEarn 2k to 4k daily💲\nDm me for more information \n\n#ownbusinessowner #ownboss😎 #tredingreels #trendingreelsvideo❤️💯 #careergoals #careergirl #reelsinsta #reelsofficialindia #reelkarofeelkaro❤️❤️❤️❤️ #earnfromyoursmartphone #earnmoney #earning #exploremore #bizgurukulindia❤️ #bizgurukulfamily #businessowner #businessownermindset #businessideas #passiveincomestream #passiveincomeonline #passiveincomes #tredingreels #trending #smallbussinessowner #ownbusinessowners #workfromyourphone #workfromanywhereintheworld #affiliatemarketingtips', 'published', 'instagram', 'clients/4/remote_media_mhak6poljmkj017qpo7.mp4', NULL, '2021-06-30 11:09:40', '17894293025174435', '2026-08-03 05:55:55', 0, NULL, 19, 60, 2, NULL, 0, 0, 0, 0, 0),
(216, NULL, 4, 'Reminder 🛎🔔\n\n#motivationalquotes #motivation #money #mondaymotivation #moneymoney #busniess #businessowner #businessownermindset #busniesspassion #earnfromyoursmartphone #earnmoney #earning #enterprenuerlifestyle #enterpreneurlife #enterpreneur #entrepreneur #entrepreneurs #workworkworkworkwork #workfromhomelife #affiliatemarketingtips #affiliateindia #bizgurukulindia❤️ #bizgurukulfamily #bizgurukul #affiliatemarketingbusinessmodel #affiliatemarketing #affiliateworld #digitalmarketing #student #salesalesale', 'published', 'instagram', 'clients/4/remote_media_arg1s570nof1f4r4xgy.jpg', NULL, '2021-06-28 07:39:39', '18231530350001784', '2026-08-03 05:55:55', 0, NULL, 11, 33, 2, NULL, 0, 0, 0, 0, 0),
(217, NULL, 4, 'Get started  today💫🌞\nDm me for more info💬👨‍💻\n#salesalesale #sale #affiliateindia #affiliatemarketingtips #affiliatemarketingbusinessmodel #earnfromyoursmartphone #earnmoney #earning #enterpreneurlife #enterpreneur #indiamoney #bizgurukulindia❤️ #bizgurukulfamily❤️ #motivationalquotes #motivation #onlinelearningplatform #onlineearnmoney #onlinebusiness #freelancing #freelancework #digitalmarketing #affordable #busniesspassion #busniess #onlinenetworkmarketing #affiliateincomes #salestips #motivated #motivationquotes #workworkworkworkwork', 'published', 'instagram', 'clients/4/remote_media_ipi3n4kefnee6Vb6ZbH.jpg', NULL, '2021-06-26 23:39:23', '17892001994193847', '2026-08-03 05:55:55', 0, NULL, 10, 30, 2, NULL, 0, 0, 0, 0, 0),
(218, NULL, 4, 'Working on yourself never ends👨‍💻\n\nStart your journey of becoming financially independent 💸with me💲💰💳\n\nDm me for more information 💬\n\n#entrepreneurship\n#enterpreneur\n#indiamoney #financialfreedom #investment #investor #independentwoman #independent #affiliatemarketingtips #affiliatemarketing #student #bizgurukulfamily #bizgurukulindia❤️ #bizgurukul #enterpreneurlife #enterprenuerlifestyle #opportunity #money #customer #investment #believer #beliveinyourself #earnmoney #ownbusiness #earnfromyoursmartphone #workfromyourphone #workfromanywhere #workworkworkworkwork #businessownermindset  #earning #digitalmarketing', 'published', 'instagram', 'clients/4/remote_media_dsshd3cg4agm6fMZWn5.jpg', NULL, '2021-06-23 13:02:26', '18081781759272937', '2026-08-03 05:55:55', 0, NULL, 13, 68, 3, NULL, 0, 0, 0, 0, 0),
(219, NULL, 4, 'Everything depends on you 🤵‍♀️\nWith bizgurukul you can starting earning today💲💰\n#salesalesale #onlinesale #sale #online #onlinebusiness #onlineearnmoney #onlinelearningplatform #freelancing #freelancework #affiliatemarketingtips #affiliatemarketing #affiliateworld #affordable #digitalmarketing #workfromhomelife #workfromyourphone #workfromanywhere #affiliateindia #affiliateincomes #onlinenetworkmarketing #affiliatemarketingbusinessmodel #businessowner #bizgurukulfamily #bizgurukulfamily❤️ #bizgurukul #busniess #earning #earnmoney #busniesspassion #motivation', 'published', 'instagram', 'clients/4/remote_media_qq641iopg8j76UtJgPv.jpg', NULL, '2021-06-19 05:49:42', '17898384388998411', '2026-08-03 05:55:55', 0, NULL, 12, 36, 0, NULL, 0, 0, 0, 0, 0),
(220, NULL, 4, 'Start working at home today 🌞\n\n#bizgurukulfamily❤️ #affiliatemarketing #indiamoney #workfromhome #businessowner #bizgurukul #affiliatemarketingtips #workfromanywhere #digitalmarketing #bizgurukulindia❤️ #workfromhomelife #motivation #motivationalquotes #enterpreneur #entrepreneurship #makemoneyfromyourphone #motivationalquotes #money', 'published', 'instagram', 'clients/4/remote_media_8u669522m88vfz1eT9q.jpg', NULL, '2021-06-18 06:57:23', '17898122714007105', '2026-08-03 05:55:55', 0, NULL, 8, 28, 1, NULL, 0, 0, 0, 0, 0),
(221, NULL, 4, '\"Do your things in your way\", work in your comfort from your home.🎀🤝\n \nDm me ✉now and get started \nFirst learn✍ and then Earn💵💵\n\n#workfromhome \n#enterpreneur#financialfreedom💰#affiliatemarketing#affirmationsoftheday#workfromanywhere#quẩntinelife#marketing#businessowner#bizgurukul#bizgurukulindia❤️ #bizgurukulfamily#money#makemoney#makemoneyfromyourphone', 'published', 'instagram', 'clients/4/remote_media_97rtm53do5i03eQlyG2.jpg', NULL, '2021-06-18 02:18:00', '17849559989587896', '2026-08-03 05:55:55', 0, NULL, 7, 28, 0, NULL, 0, 0, 0, 0, 0),
(222, 193, 1, 'poster of the spiderman #spiderman #spider', 'published', 'youtube', 'clients/1/1785736742_123 (2).mp4', '2026-08-03 06:00:00', '2026-08-03 06:00:04', 'W7ptXNE_W4c', '2026-08-03 05:59:03', 0, '2026-08-03 06:00:01', 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(879, NULL, 1, 'MoonKnight...#moonknight #marvel #newpost #viralpost', 'published', 'instagram', 'clients/1/remote_media_ml5fe9lqpqpi4TYUkjX.jpg', NULL, '2026-07-30 08:51:15', '17890059891410788', '2026-08-03 10:21:32', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(880, NULL, 1, 'Guyysss i AM BACK...... #pabloescobar\r\n#new\r\n#today\r\n#viral', 'published', 'instagram', 'clients/1/remote_media_r0mojactjv18aFsxgS7.jpg', NULL, '2026-07-30 06:20:15', '18090382997109812', '2026-08-03 10:21:32', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(881, NULL, 1, 'Cheers boys...... #pabloescobar #new #viral #today', 'published', 'facebook', 'https://scontent-bom5-1.xx.fbcdn.net/v/t39.30808-6/758896603_122112395781392348_8874597547058410809_n.jpg?_nc_cat=105&ccb=1-7&_nc_sid=127cfc&_nc_ohc=p8CMh5RWbeMQ7kNvwGA3DeH&_nc_oc=AdoJQDYXTKdbr4DVTyiPip7ho2xKHz0wKeIG8CeYhUOSwYSky6sX9-3uKXXnwbKUTD-RxC-GWkogzzyqwC9ozzlQ&_nc_zt=23&_nc_ht=scontent-bom5-1.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQG1A9TUJtXD3-zq3-pZUbw8VX2fwt4j5eEOFTnHnembkfrz3JRGdHd9yaqsh19dOw6ne-0cxGr6bg&oh=00_AQG0lNjpkvPDueBQTXIfJXYYQ6WCBJqLv9El70O6yh530Q&oe=6A7631C5', NULL, '2026-07-30 05:56:01', '1243224272200630_122112395871392348', '2026-08-03 10:21:32', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(882, NULL, 1, 'Cheers all..', 'published', 'facebook', 'https://scontent-bom2-4.xx.fbcdn.net/v/t39.30808-6/759239456_122112390303392348_631064883206315431_n.jpg?_nc_cat=107&ccb=1-7&_nc_sid=127cfc&_nc_ohc=w0AjSoAIzS4Q7kNvwHcTVl2&_nc_oc=Adrd1KcsMPUKI-dIriM4vHc3tGTslsG2IpynkNMAKgyB-g7HffvynvkW0RvDxm3l3WuMCThbzqe3fn45cIFnqj03&_nc_zt=23&_nc_ht=scontent-bom2-4.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQESbmeBfnVppJr_HzBl8Tow9iLGqsHAUBaEY1WYXMQrA1kb0oBjp6v1Vy3RiTumyUEDX9ME7Qy3ZQ&oh=00_AQGXPwwQUR5nOsg22drvbaVcgFhDOYvDILbcZPNhYQnWqg&oe=6A764E4B', NULL, '2026-07-30 05:30:02', '1243224272200630_122112390321392348', '2026-08-03 10:21:32', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(883, NULL, 1, 'monknight #moonknight\n#moon\n#marvel\n#character\n#today', 'published', 'facebook', 'https://scontent-bom5-2.xx.fbcdn.net/v/t39.30808-6/760826120_122112363381392348_2655681471693002805_n.jpg?_nc_cat=108&ccb=1-7&_nc_sid=127cfc&_nc_ohc=N7RVfU5vV4wQ7kNvwG5TlYY&_nc_oc=AdoBLu_xEc8PYe0MmUtc84xpsGsi_KJgaQG0KlYl_135OitVb51RFolJyCpSNhfRL8jB7FfeIKfBRnpUO5MM_02K&_nc_zt=23&_nc_ht=scontent-bom5-2.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGDJZBCcMPyqDtYreaHu0cclbEBZAm6_Rw38N08yjq91BYNb_i6Ha5HGFgp0ztxUOlFhMXs-_QEFw&oh=00_AQFFO9VfAjstyzY_44dP6DgMl9X38xJ1FHveAYEV7VEo1w&oe=6A763A36', NULL, '2026-07-30 03:51:01', '1243224272200630_122112363405392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(884, NULL, 1, 'hey', 'published', 'facebook', 'https://scontent-bom2-4.xx.fbcdn.net/v/t39.30808-6/760566435_122112362793392348_1496848133006406578_n.jpg?_nc_cat=107&ccb=1-7&_nc_sid=127cfc&_nc_ohc=UFldolhFga8Q7kNvwHKIqOj&_nc_oc=Adr54KAzURm9jZlSPo6D78qQz8SeB-Sb5ZOdb7hL4lyM0obGKLBBkX7WBgd4l4vzTgUwqGonEj8pMJax0z0Dn0xr&_nc_zt=23&_nc_ht=scontent-bom2-4.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGKHQiQ75JuYpYqRrw3RPbHusS9HmVb1monwgTbByx4SG0PTk_71eXbGGkVZ4grL4BV_TPA1xQbgg&oh=00_AQEb5c1dslvoMoOPeU9ShXLd5o4TBee1lvdi8ziaxLjb9w&oe=6A76330D', NULL, '2026-07-30 03:45:02', '1243224272200630_122112362811392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(885, NULL, 1, 'Cheers i am back........... #pabloescobar', 'published', 'facebook', 'https://scontent-bom2-4.xx.fbcdn.net/v/t39.30808-6/759188555_122112295155392348_7693484569957742127_n.jpg?_nc_cat=107&ccb=1-7&_nc_sid=127cfc&_nc_ohc=EjB_sscnwKsQ7kNvwGpO7ie&_nc_oc=AdoSV7_oyshCZ4SPAMEDDJt8_YP_ijemdYpTdfdT5ufFNa_DKPJ3-6h-aZSFMW6stF-6JY1acIHhgC_l_jT0wqzd&_nc_zt=23&_nc_ht=scontent-bom2-4.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGyAW6t0Na3a1qF62nyPHbKHda0dqQu_IkGcZUp2ftTaFQDr_TklKRTWthZheYtTAGXn9QkRZjqaA&oh=00_AQHbD7nHCk4TVX-MYQ6JLYtegEi28hC-0uzFJErnx2qXPw&oe=6A764AE4', NULL, '2026-07-30 00:18:51', '1243224272200630_122112295191392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(887, NULL, 1, 'hello', 'published', 'instagram', 'clients/1/remote_media_331kupg6c4f60bieff3.jpg', NULL, '2026-07-28 04:46:04', '18093185954059867', '2026-08-03 10:21:33', 0, NULL, 1, 1, 0, NULL, 0, 1, 1, 0, 1),
(889, NULL, 1, 'hello', 'published', 'instagram', 'clients/1/remote_media_46hje5fs00ib3T8rSy6.jpg', NULL, '2026-07-25 15:13:25', '18093097946077596', '2026-08-03 10:21:33', 0, NULL, 2, 1, 0, NULL, 0, 2, 2, 0, 1),
(890, NULL, 1, 'hello', 'published', 'facebook', 'https://scontent-bom5-2.xx.fbcdn.net/v/t39.30808-6/753738912_122110659525392348_3502999721078280508_n.jpg?stp=dst-jpg_s720x720_tt6&_nc_cat=104&ccb=1-7&_nc_sid=127cfc&_nc_ohc=NAeHeM7bG3IQ7kNvwFOTgQi&_nc_oc=AdpA8eHphuqA-N3LfNa3aP4Tbcs7BZzJF4rl-arUuXeYUJZMe1nBxmZWU4GcJzp-m-Eop_tW8SA34ekDXbjKjuby&_nc_zt=23&_nc_ht=scontent-bom5-2.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQHe9GNrxTyLTRcbHQ2ozryYmpSUJ8P1VqUw3YpDorpUyJihShJVYBBZE1-pbshdR_y9ecuHAEWxIw&oh=00_AQFwD5C29Q6Omk5PEVk9Bcc0Pmg5f3KRxNGZF', NULL, '2026-07-25 15:13:09', '1243224272200630_122110659543392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(891, NULL, 1, 'hello', 'published', 'facebook', 'https://scontent-bom5-1.xx.fbcdn.net/v/t39.30808-6/754971208_122110658217392348_4494282663688144820_n.jpg?stp=dst-jpg_s720x720_tt6&_nc_cat=109&ccb=1-7&_nc_sid=127cfc&_nc_ohc=k7hscQR63v0Q7kNvwHKAzNk&_nc_oc=AdpQP-PZP3SUySFCtwsQKKIhwfXMJ_v-sMheJaDrim8XLdyXaDJLH2DP5cFJEQ1MCHDayrKp0RHzD556Wq37g2UZ&_nc_zt=23&_nc_ht=scontent-bom5-1.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGpJLHKtwaaRGswPmR1RLDC_rEoff-eoQ13MIq8EkTGF-Mq_d5TgZPiR5SydKb6ctK5WRIrOOve9w&oh=00_AQES18tz_KyCCGxsYKGa17zMzY2cuzsofSwzn', NULL, '2026-07-25 15:07:24', '1243224272200630_122110658235392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(893, NULL, 1, 'hello on 5:11', 'published', 'facebook', 'https://scontent-bom2-4.xx.fbcdn.net/v/t39.30808-6/751414054_122108094321392348_5991082365736782364_n.jpg?_nc_cat=107&ccb=1-7&_nc_sid=127cfc&_nc_ohc=PkJQS6m21dAQ7kNvwFGwc0w&_nc_oc=Adqz0hBhyiO2rEkrQcq4bW1gYQI9eQCvbyJIE-aakFkFYYRIcoPVR0MZKYFplura1cZkIEiYQytpTmLyZdnpMDLE&_nc_zt=23&_nc_ht=scontent-bom2-4.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGw9UZovi3FyY1104mC2Itpo8Fnera3bICGrQEnHn_xddtMQm9zbSdqU8IKtMdoP6gmZqeSh0JZUQ&oh=00_AQH8Mgt1Iyl8TPRqPzgX-ufNXbuKhlBG1qZNa8CH3KN3Uw&oe=6A763406', NULL, '2026-07-20 06:11:08', '1243224272200630_122108094375392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(895, NULL, 1, 'hello 2 !!!!', 'published', 'facebook', 'https://scontent-bom5-2.xx.fbcdn.net/v/t39.30808-6/749440850_122108092857392348_8744737724372928927_n.jpg?stp=dst-jpg_p720x720_tt6&_nc_cat=104&ccb=1-7&_nc_sid=127cfc&_nc_ohc=gy4KG97rapYQ7kNvwESECJx&_nc_oc=AdoTJOPgyxFFboNfTVTPRAlOcto7TJxYPTBZQqCWOruRV8D2-D2z7YUpmmlvEUnMKyL0scDs97AvsOwrV7pc_cvn&_nc_zt=23&_nc_ht=scontent-bom5-2.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQF9cafKV8-75yIE2kyZ8SbcTB5P_Z7jS2wYaH1lqrHLjRaX6nX5Wv8HNMRjWlMUwk6hWhR2jXng5Q&oh=00_AQE4RFhmtTpY84KC04oQnn-gjADOkTBZWrpwl', NULL, '2026-07-20 06:07:41', '1243224272200630_122108092869392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(897, NULL, 1, 'hello there', 'published', 'facebook', 'https://scontent-bom2-4.xx.fbcdn.net/v/t39.30808-6/745045910_122108090481392348_8880582374278155689_n.jpg?stp=dst-jpg_p720x720_tt6&_nc_cat=107&ccb=1-7&_nc_sid=127cfc&_nc_ohc=76VeJCgDQMsQ7kNvwElGZ-q&_nc_oc=AdqRlJVHYsWjWOLS64xISXOHvozkp4xdsR9Bdelq9lJhzMYuzkKR9qLT0XQ5dnDypXTQKF77E5cqILCB6oO7wrFh&_nc_zt=23&_nc_ht=scontent-bom2-4.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGzDVqbJKizCGPUnJuvAQ9KtOdp5BMGnl41n9Qkkr0YyHV77mAlHXJh9pwEajHLtmNDVQTdI1YJAA&oh=00_AQHystVWVE5vOstj0pHM36kmfY0pJk8T_2j2U', NULL, '2026-07-20 06:02:00', '1243224272200630_122108090505392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(899, NULL, 1, 'qwdqwdqw', 'published', 'facebook', 'https://scontent-bom5-2.xx.fbcdn.net/v/t15.5256-10/752198522_1525562105195578_2322749928816866318_n.jpg?stp=dst-jpg_s720x720_tt6&_nc_cat=108&ccb=1-7&_nc_sid=5fad0e&_nc_ohc=vbziNzrXkhAQ7kNvwG1fUQo&_nc_oc=AdqEmZluwHn3FU4Ld57n6WjygbRkJ5SxeCWOY0pIvNtQSdUN3j2UHal5IiO3GkUPpzq_m0lAdldrtrs8guFDUFu8&_nc_zt=23&_nc_ht=scontent-bom5-2.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQFCO5bYjNp6wxqcGgEk1e61VVOaY9kisz7GEHGwAYAp0GpIHkR6EE-oSUB5fXJqIye0enEiod1GKA&oh=00_AQFsR4LsN9wVtyxRDoiLxhpB-nxnizWot89VkeN', NULL, '2026-07-20 02:01:16', '1243224272200630_122107932633392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(900, NULL, 1, 'dfwerfwerfgerverdvrververververververvewsvasdfghwrjhejtneyjrdgntyf', 'published', 'facebook', 'https://scontent-bom5-2.xx.fbcdn.net/v/t15.5256-10/750775429_2148544555704017_6394772601098819733_n.jpg?stp=dst-jpg_s720x720_tt6&_nc_cat=102&ccb=1-7&_nc_sid=5fad0e&_nc_ohc=xMmXT8_kZXMQ7kNvwEKnXy3&_nc_oc=AdoIeG2NcBXq4Nb3i0Kv85LKgtvG08PnE0-lxikH3W3F2TTUDTy89h2wF6abX6n4_iBxKhlW2ROaRrHEA9Ptp1h1&_nc_zt=23&_nc_ht=scontent-bom5-2.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQHvZFScFTjGIANr2u8qUcy0xZJAVtrqCDoxYXalnjkaw7jqxCEXySkcFaGaVeVaGYN9nVRuz_TPmg&oh=00_AQFMT56b4QEenWjZiyH9KoWxfq-KPb_wEcHJdFk', NULL, '2026-07-20 01:53:18', '1243224272200630_122107928541392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(902, NULL, 1, 'wdfqwfqf', 'published', 'facebook', 'https://scontent-bom5-2.xx.fbcdn.net/v/t15.5256-10/752475098_1450059856936143_8411001720372344029_n.jpg?stp=dst-jpg_s720x720_tt6&_nc_cat=104&ccb=1-7&_nc_sid=5fad0e&_nc_ohc=Dsrt4I6EPVAQ7kNvwHtiKob&_nc_oc=AdoDi9slX9Pq87iht-GVQ2htGtSD2UKJzA0lp-OrAGsX56RqBrsHnykQvbJ7swCP5OZJ6Yb5-7gKVEp3sevnXnrA&_nc_zt=23&_nc_ht=scontent-bom5-2.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQEA7n5faW-mscBivPZeXwHHyMYhBESWu2858VOyqf0pF7zNoz7hPnMFre1AD6RU-bVAy_--jvG2DA&oh=00_AQF70P_-X8FtqHDSv0LTmkhcRsRuyrV-WYkImHI', NULL, '2026-07-20 00:29:27', '1243224272200630_122107885677392348', '2026-08-03 10:21:33', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(903, NULL, 1, 'wfqwefqe', 'published', 'facebook', 'https://scontent-bom2-4.xx.fbcdn.net/v/t39.30808-6/750540084_122107861467392348_8338222611963360585_n.jpg?stp=dst-jpg_p720x720_tt6&_nc_cat=106&ccb=1-7&_nc_sid=127cfc&_nc_ohc=tl1QDHCwNt4Q7kNvwFfPhaT&_nc_oc=AdrW9q9NGdxKlPXOxuWgJAXyxOkPgkxatnDK1hEiLriER15MaU3d3tLVuRSJrmetP3wEXfxu7TNMgCYechZ9cwGm&_nc_zt=23&_nc_ht=scontent-bom2-4.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQHbBCqLxm9XrsefyhY_W376pkAep9UWpaxLletOTsGsHHv-eS1vixamVpE98-2a-7eoCQAJmyCpFg&oh=00_AQEtn4o3QNMNXnRURcjUiBZOUmx58UsZJKfRD', NULL, '2026-07-19 23:24:25', '1243224272200630_122107861491392348', '2026-08-03 10:21:34', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0),
(904, NULL, 1, 'testing', 'published', 'facebook', 'https://scontent-bom5-1.xx.fbcdn.net/v/t39.30808-6/749652228_122106334443392348_374487022661390376_n.jpg?_nc_cat=110&ccb=1-7&_nc_sid=127cfc&_nc_ohc=nefY62VQ8FsQ7kNvwHgrw6E&_nc_oc=AdpThJr_Ap97sZjhdp3_Q2PRb8Kf189FkaVasUeh0NG3yIkcnNlSE-KlCR5UnOxx_RV4IlfqZsih8eUtLKaTdvOC&_nc_zt=23&_nc_ht=scontent-bom5-1.xx&edm=AKIiGfEEAAAA&_nc_gid=uVfp3yyZZqoH0sAZ2QJ06A&_nc_tpa=Q5bMBQGPIE3R4DFPNH9N6TSJP4ZubRqLPJ3hMf58r-vIq1vPIzAhWbiCDrwOvdMJcgsyD5RWCSojTwBv2w&oh=00_AQE-waaq5OIkanl7NqFbvrftpROH06uA92LCZx5Ya_C5CA&oe=6A763E68', NULL, '2026-07-18 07:03:27', '1243224272200630_122106334473392348', '2026-08-03 10:21:34', 0, NULL, 0, 0, 0, NULL, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('client','staff','admin') NOT NULL DEFAULT 'client',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `client_id`, `email`, `password`, `role`, `created_at`, `last_login_at`) VALUES
(1, NULL, 'admin@ourcompany.com', 'adminpassword', 'admin', '2026-07-18 08:04:53', '2026-08-03 10:22:58'),
(3, 1, 'acctuse9@gmail.com', 'clientpass', 'client', '2026-07-18 09:49:26', '2026-08-03 10:02:15'),
(5, 3, 'patel3y3r@gmail.com', 'Clientpass', 'client', '2026-07-18 11:30:57', '2026-07-30 05:02:35'),
(6, 4, 'falguni@gmail.com', 'clientpass', 'client', '2026-07-25 12:04:32', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics_cache`
--
ALTER TABLE `analytics_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_client_plat_metric` (`client_id`,`platform`,`metric_name`);

--
-- Indexes for table `client_hub_keys`
--
ALTER TABLE `client_hub_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_id` (`client_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempt_lookup` (`email`,`ip_address`,`attempted_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reset_token` (`reset_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `posts_cache`
--
ALTER TABLE `posts_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_hub_post` (`hub_post_id`),
  ADD UNIQUE KEY `idx_platform_post` (`client_id`,`platform`,`external_post_id`),
  ADD KEY `idx_posts_cache_status_sched` (`status`,`scheduled_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics_cache`
--
ALTER TABLE `analytics_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `client_hub_keys`
--
ALTER TABLE `client_hub_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `posts_cache`
--
ALTER TABLE `posts_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=905;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
