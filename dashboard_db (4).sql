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
-- Database: `dashboard_db`
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
(1, 3, 'facebook', 'page_post_engagements', '0', 'day', '2026-07-25 11:42:23'),
(2, 3, 'facebook', 'page_views_total', '0', 'day', '2026-07-25 11:42:23');

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
  `hub_post_id` int(11) NOT NULL,
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
  `views_count` int(11) DEFAULT 0,
  `likes_count` int(11) DEFAULT 0,
  `comments_count` int(11) DEFAULT 0,
  `duration` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts_cache`
--

INSERT INTO `posts_cache` (`id`, `hub_post_id`, `client_id`, `content`, `status`, `platform`, `media_path`, `scheduled_at`, `published_at`, `external_post_id`, `created_at`, `retry_count`, `last_attempt_at`, `views_count`, `likes_count`, `comments_count`, `duration`) VALUES
(1, 3, 1, 'at 6:00pm', 'deleted', 'facebook', 'clients/1/1784376342_1784376342_phone-call (2).png', '2026-07-18 12:30:00', NULL, NULL, '2026-07-18 12:05:42', 0, NULL, 0, 0, 0, NULL),
(2, 0, 1, 'hello first from the dashboard', 'deleted', 'facebook', 'clients/1/1784376823_1784376823_web (1).png', NULL, '2026-07-18 08:36:57', NULL, '2026-07-18 12:06:57', 0, NULL, 0, 0, 0, NULL),
(5, 7, 1, 'fbsdfber', 'deleted', 'facebook', 'clients/1/1784377386_1784377386_web (1).png', NULL, NULL, NULL, '2026-07-18 12:23:07', 0, NULL, 0, 0, 0, NULL),
(6, 8, 1, 'testing', 'deleted', 'facebook', 'clients/1/1784378003_1784378003_gps.png', NULL, '2026-07-18 09:03:43', '122106334437392348', '2026-07-18 12:33:43', 0, NULL, 0, 0, 0, NULL),
(7, 9, 1, 'test 6:07', 'deleted', 'facebook', 'clients/1/1784378140_1784378140_ambedkar-jayanti.jpg', NULL, '2026-07-18 09:05:53', '122106336087392348', '2026-07-18 12:35:53', 0, NULL, 0, 0, 0, NULL),
(8, 10, 1, 'fwefwefwe', 'deleted', 'facebook', 'clients/1/1784522773_1784522773_08412fb854693e1cab68e1340ac6752d.jpg', NULL, NULL, NULL, '2026-07-20 04:46:15', 0, NULL, 0, 0, 0, NULL),
(9, 11, 1, 'fwefwefwe', 'deleted', 'facebook', 'clients/1/1784522794_1784522794_08412fb854693e1cab68e1340ac6752d.jpg', NULL, NULL, NULL, '2026-07-20 04:46:34', 0, NULL, 0, 0, 0, NULL),
(10, 12, 1, 'fwefwefwe', 'deleted', 'facebook', 'clients/1/1784523037_1784523037_08412fb854693e1cab68e1340ac6752d.jpg', NULL, NULL, NULL, '2026-07-20 04:50:39', 0, NULL, 0, 0, 0, NULL),
(11, 13, 1, 'esfwewegw', 'deleted', 'facebook', 'clients/1/1784523062_1784523062_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, NULL, NULL, '2026-07-20 04:51:10', 0, NULL, 0, 0, 0, NULL),
(12, 14, 1, 'wfqwefqe', 'deleted', 'facebook', 'clients/1/1784523265_1784523265_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 01:24:48', '122107861455392348', '2026-07-20 04:54:48', 0, NULL, 0, 0, 0, NULL),
(13, 15, 1, 'our first try #try', 'deleted', 'youtube', 'clients/1/1784524897_1784524897_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 01:51:48', 'Rui5dLKnWnM', '2026-07-20 05:21:48', 0, NULL, 0, 0, 0, NULL),
(14, 16, 1, 'wdqwdqedqwe', 'deleted', 'youtube', 'clients/1/1784525140_1784525140_16392302_1920_1080_30fps.mp4', NULL, '2026-07-20 01:56:28', 'SqDuJyncYBM', '2026-07-20 05:26:28', 0, NULL, 0, 0, 0, NULL),
(15, 17, 1, 'sfqwefwefw', 'deleted', 'facebook', 'clients/1/1784525287_1784525287_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 05:28:08', 0, NULL, 0, 0, 0, NULL),
(16, 18, 1, 'sfqwefwefw', 'deleted', 'youtube', 'clients/1/1784525287_1784525287_16392302_1920_1080_30fps.mp4', NULL, '2026-07-20 01:58:34', 'r6oCo0yYU2U', '2026-07-20 05:28:34', 0, NULL, 0, 0, 0, NULL),
(17, 19, 1, 'wrfwe', 'deleted', 'facebook', 'clients/1/1784526954_1784526954_16392302_1920_1080_30fps.mp4', '2026-07-20 05:56:00', NULL, NULL, '2026-07-20 05:55:55', 0, NULL, 0, 0, 0, NULL),
(18, 20, 1, 'wrfwe', 'deleted', 'youtube', 'clients/1/1784526954_1784526954_16392302_1920_1080_30fps.mp4', '2026-07-20 05:56:00', NULL, NULL, '2026-07-20 05:55:55', 0, NULL, 0, 0, 0, NULL),
(19, 21, 1, 'wdfqwfqf', 'deleted', 'facebook', 'clients/1/1784527091_1784527091_16392302_1920_1080_30fps.mp4', NULL, '2026-07-20 02:29:05', '1712561063400489', '2026-07-20 05:59:05', 0, NULL, 0, 0, 0, NULL),
(20, 22, 1, 'wdfqwfqf', 'deleted', 'youtube', 'clients/1/1784527091_1784527091_16392302_1920_1080_30fps.mp4', NULL, '2026-07-20 02:29:53', 'lQ7uv7PifeQ', '2026-07-20 05:59:53', 0, NULL, 0, 0, 0, NULL),
(21, 23, 1, 'zsfaes', 'deleted', 'facebook', 'clients/1/1784527416_1784527416_16392302_1920_1080_30fps.mp4', '2026-07-20 06:05:00', NULL, NULL, '2026-07-20 06:03:36', 0, NULL, 0, 0, 0, NULL),
(22, 24, 1, 'zsfaes', 'deleted', 'youtube', 'clients/1/1784527416_1784527416_16392302_1920_1080_30fps.mp4', '2026-07-20 06:05:00', NULL, NULL, '2026-07-20 06:03:36', 0, NULL, 0, 0, 0, NULL),
(23, 25, 1, 'grthere', 'deleted', 'facebook', 'clients/1/1784527870_1784527870_16392302_1920_1080_30fps.mp4', '2026-07-20 06:13:00', NULL, NULL, '2026-07-20 06:11:10', 0, NULL, 0, 0, 0, NULL),
(24, 26, 1, 'grthere', 'deleted', 'youtube', 'clients/1/1784527870_1784527870_16392302_1920_1080_30fps.mp4', '2026-07-20 06:13:00', NULL, NULL, '2026-07-20 06:11:10', 0, NULL, 0, 0, 0, NULL),
(26, 28, 1, 'hello all its 12 08', 'deleted', 'facebook', 'clients/1/1784529440_1784529440_16392302_1920_1080_30fps.mp4', '2026-07-20 06:38:00', NULL, NULL, '2026-07-20 06:37:20', 0, NULL, 0, 0, 0, NULL),
(27, 29, 1, 'hello all its 12 08', 'deleted', 'youtube', 'clients/1/1784529440_1784529440_16392302_1920_1080_30fps.mp4', '2026-07-20 06:38:00', NULL, NULL, '2026-07-20 06:37:20', 0, NULL, 0, 0, 0, NULL),
(28, 30, 1, 'hello all its 12 08', 'deleted', 'linkedin', 'clients/1/1784529440_1784529440_16392302_1920_1080_30fps.mp4', '2026-07-20 06:38:00', NULL, NULL, '2026-07-20 06:37:20', 0, NULL, 0, 0, 0, NULL),
(29, 31, 1, 'hello', 'deleted', 'facebook', 'clients/1/1784531344_1784531344_16392302_1920_1080_30fps.mp4', '2026-07-20 07:10:00', NULL, NULL, '2026-07-20 07:09:04', 0, NULL, 0, 0, 0, NULL),
(30, 32, 1, 'hello', 'deleted', 'youtube', 'clients/1/1784531344_1784531344_16392302_1920_1080_30fps.mp4', '2026-07-20 07:10:00', NULL, NULL, '2026-07-20 07:09:04', 0, NULL, 0, 0, 0, NULL),
(31, 33, 1, 'hello', 'deleted', 'linkedin', 'clients/1/1784531344_1784531344_16392302_1920_1080_30fps.mp4', '2026-07-20 07:10:00', NULL, NULL, '2026-07-20 07:09:04', 0, NULL, 0, 0, 0, NULL),
(32, 50, 1, 'dfwerfwerfgerverdvrververververververvewsvasdfghwrjhejtneyjrdgntyf', 'deleted', 'facebook', 'clients/1/1784531992_1784531992_16392302_1920_1080_30fps.mp4', '2026-07-20 07:20:00', NULL, NULL, '2026-07-20 07:19:52', 0, NULL, 0, 0, 0, NULL),
(33, 51, 1, 'dfwerfwerfgerverdvrververververververvewsvasdfghwrjhejtneyjrdgntyf', 'deleted', 'youtube', 'clients/1/1784531992_1784531992_16392302_1920_1080_30fps.mp4', '2026-07-20 07:20:00', NULL, NULL, '2026-07-20 07:19:52', 0, NULL, 0, 0, 0, NULL),
(34, 52, 1, 'dfwerfwerfgerverdvrververververververvewsvasdfghwrjhejtneyjrdgntyf', 'deleted', 'linkedin', 'clients/1/1784531992_1784531992_16392302_1920_1080_30fps.mp4', '2026-07-20 07:20:00', NULL, NULL, '2026-07-20 07:19:52', 0, NULL, 0, 0, 0, NULL),
(35, 57, 1, 'qwdqwdqw', 'deleted', 'facebook', 'clients/1/1784532430_1784532430_16392302_1920_1080_30fps.mp4', '2026-07-20 07:30:00', '2026-07-20 07:31:03', '1369197587870528', '2026-07-20 07:27:10', 0, '2026-07-20 07:30:25', 0, 0, 0, NULL),
(36, 58, 1, 'qwdqwdqw', 'deleted', 'youtube', 'clients/1/1784532430_1784532430_16392302_1920_1080_30fps.mp4', '2026-07-20 09:29:24', NULL, NULL, '2026-07-20 07:27:10', 2, '2026-07-20 08:49:24', 0, 0, 0, NULL),
(39, 65, 1, 'ef3wgwrgwergvserfgvergesredfvergerg', 'deleted', 'instagram', 'clients/1/1784541666_1784541666_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 10:01:06', 0, NULL, 0, 0, 0, NULL),
(44, 70, 1, 'sdqweqwe', 'deleted', 'instagram', 'clients/1/1784544287_1784544287_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 10:44:49', 0, NULL, 0, 0, 0, NULL),
(45, 71, 1, 'sdqweqwe', 'deleted', 'instagram', 'clients/1/1784544307_1784544307_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:45:08', 0, NULL, 0, 0, 0, NULL),
(46, 72, 1, 'sdqweqwe', 'deleted', 'instagram', 'clients/1/1784544387_1784544387_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:46:28', 0, NULL, 0, 0, 0, NULL),
(47, 73, 1, 'wwedwd', 'deleted', 'instagram', 'clients/1/1784544718_1784544718_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:51:58', 0, NULL, 0, 0, 0, NULL),
(48, 74, 1, 'hello there !!!!', 'deleted', 'instagram', 'clients/1/1784544823_1784544823_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:53:44', 0, NULL, 0, 0, 0, NULL),
(49, 75, 1, 'hello', 'deleted', 'instagram', 'clients/1/1784544920_1784544920_16392302_1920_1080_30fps.mp4', NULL, NULL, NULL, '2026-07-20 10:55:21', 0, NULL, 0, 0, 0, NULL),
(50, 76, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784544998_1784544998_gps.png', NULL, NULL, NULL, '2026-07-20 10:56:39', 0, NULL, 0, 0, 0, NULL),
(51, 77, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545014_1784545014_gps.png', NULL, NULL, NULL, '2026-07-20 10:56:55', 0, NULL, 0, 0, 0, NULL),
(52, 78, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545023_1784545023_gps.png', NULL, NULL, NULL, '2026-07-20 10:57:04', 0, NULL, 0, 0, 0, NULL),
(53, 79, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545033_1784545033_gps.png', NULL, NULL, NULL, '2026-07-20 10:57:14', 0, NULL, 0, 0, 0, NULL),
(54, 80, 1, 'hello its working !!!!!', 'deleted', 'instagram', 'clients/1/1784545107_1784545107_gps.png', NULL, NULL, NULL, '2026-07-20 10:58:28', 0, NULL, 0, 0, 0, NULL),
(55, 81, 1, 'hello', 'deleted', 'instagram', 'clients/1/1784546369_1784546369_gps.png', NULL, NULL, NULL, '2026-07-20 11:19:29', 0, NULL, 0, 0, 0, NULL),
(56, 82, 1, 'hello', 'published', 'instagram', 'clients/1/1784546714_1784546714_gps.png', NULL, '2026-07-20 11:25:33', '18090779117378374', '2026-07-20 11:25:33', 0, NULL, 0, 0, 0, NULL),
(57, 83, 1, 'hello there', 'deleted', 'facebook', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, '2026-07-20 11:32:05', '122108090475392348', '2026-07-20 11:32:05', 0, NULL, 0, 0, 0, NULL),
(58, 84, 1, 'hello there', 'deleted', 'youtube', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 11:32:05', 0, NULL, 0, 0, 0, NULL),
(59, 85, 1, 'hello there', 'published', 'linkedin', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, '2026-07-20 11:32:06', NULL, '2026-07-20 11:32:06', 0, NULL, 0, 0, 0, NULL),
(60, 86, 1, 'hello there', 'deleted', 'instagram', 'clients/1/1784547089_1784547089_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, NULL, NULL, '2026-07-20 11:32:18', 0, NULL, 0, 0, 0, NULL),
(61, 87, 1, 'hello', 'published', 'youtube', 'clients/1/1784547357_1784547357_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, '2026-07-20 11:36:08', 'bgL8DeVKJqw', '2026-07-20 11:36:08', 0, NULL, 0, 0, 0, NULL),
(62, 88, 1, 'hello !!!', 'published', 'instagram', 'clients/1/1784547388_1784547388_c6ed503c-adde-4adf-8f18-82baef1acf95.png', NULL, '2026-07-20 11:36:49', '18201211261362785', '2026-07-20 11:36:49', 0, NULL, 0, 0, 0, NULL),
(63, 89, 1, 'hello 2 !!!!', 'published', 'facebook', 'clients/1/1784547456_1784547456_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 11:37:46', '122108092851392348', '2026-07-20 11:37:46', 0, NULL, 0, 0, 0, NULL),
(64, 90, 1, 'hello 2 !!!!', 'published', 'linkedin', 'clients/1/1784547456_1784547456_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 11:37:47', NULL, '2026-07-20 11:37:47', 0, NULL, 0, 0, 0, NULL),
(65, 91, 1, 'hello 2 !!!!', 'deleted', 'instagram', 'clients/1/1784547456_1784547456_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 11:38:03', '18118078432677883', '2026-07-20 11:38:03', 0, NULL, 0, 0, 0, NULL),
(66, 92, 1, 'hello 2 !!!!', 'published', 'youtube', 'clients/1/1784547456_1784547456_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', NULL, '2026-07-20 11:38:10', 'eTcPpOWwnP8', '2026-07-20 11:38:10', 0, NULL, 0, 0, 0, NULL),
(67, 93, 1, 'hello on 5:11', 'deleted', 'facebook', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 12:01:23', NULL, NULL, '2026-07-20 11:39:31', 1, '2026-07-20 11:41:23', 0, 0, 0, NULL),
(68, 94, 1, 'hello on 5:11', 'deleted', 'linkedin', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 11:41:00', '2026-07-20 11:41:24', NULL, '2026-07-20 11:39:31', 0, '2026-07-20 11:41:07', 0, 0, 0, NULL),
(69, 95, 1, 'hello on 5:11', 'deleted', 'instagram', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 12:01:24', NULL, NULL, '2026-07-20 11:39:31', 1, '2026-07-20 11:41:24', 0, 0, 0, NULL),
(70, 96, 1, 'hello on 5:11', 'published', 'youtube', 'clients/1/1784547571_1784547571_web (1).png', '2026-07-20 11:41:00', '2026-07-20 11:41:28', 'GI6JGlngLGg', '2026-07-20 11:39:31', 0, '2026-07-20 11:41:07', 0, 0, 0, NULL),
(71, 97, 1, 'hiii its 5:19', 'deleted', 'facebook', 'clients/1/1784548074_1784548074_phone-call (2).png', '2026-07-20 11:49:00', '2026-07-20 11:49:46', '122108099163392348', '2026-07-20 11:47:54', 0, '2026-07-20 11:49:41', 0, 0, 0, NULL),
(72, 98, 1, 'hiii its 5:19', 'published', 'linkedin', 'clients/1/1784548074_1784548074_phone-call (2).png', '2026-07-20 11:49:00', '2026-07-20 11:49:46', NULL, '2026-07-20 11:47:54', 0, '2026-07-20 11:49:41', 0, 0, 0, NULL),
(73, 99, 1, 'hiii its 5:19', 'deleted', 'instagram', 'clients/1/1784548074_1784548074_phone-call (2).png', '2026-07-20 11:57:20', NULL, NULL, '2026-07-20 11:47:54', 3, '2026-07-20 11:57:29', 0, 0, 0, NULL),
(74, 100, 1, 'hiii its 5:19', 'deleted', 'youtube', 'clients/1/1784548074_1784548074_phone-call (2).png', '2026-07-20 11:49:00', '2026-07-20 11:49:51', 'mnzBpSDlwjE', '2026-07-20 11:47:54', 0, '2026-07-20 11:49:41', 0, 0, 0, NULL),
(75, 101, 1, 'wedqawed', 'deleted', 'facebook', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', '2026-07-20 12:26:17', '122108122731392348', '2026-07-20 12:15:09', 0, '2026-07-20 12:26:07', 0, 0, 0, NULL),
(76, 102, 1, 'wedqawed', 'deleted', 'linkedin', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', '2026-07-20 12:26:18', NULL, '2026-07-20 12:15:09', 0, '2026-07-20 12:26:07', 0, 0, 0, NULL),
(77, 103, 1, 'wedqawed', 'deleted', 'instagram', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', NULL, NULL, '2026-07-20 12:15:09', 0, '2026-07-20 12:26:18', 0, 0, 0, NULL),
(78, 104, 1, 'wedqawed', 'deleted', 'youtube', 'clients/1/1784549709_1784549709_Gemini_Generated_Image_6ddffq6ddffq6ddf.png', '2026-07-20 12:20:00', NULL, NULL, '2026-07-20 12:15:09', 0, NULL, 0, 0, 0, NULL),
(81, 107, 1, 'new', 'published', 'youtube', 'clients/1/1784969632_1784969632_16392302_1920_1080_30fps.mp4', NULL, '2026-07-25 08:54:10', 'OJNllMu9a5E', '2026-07-25 08:54:10', 0, NULL, 0, 0, 0, NULL),
(82, 108, 1, 'new one', 'published', 'facebook', 'clients/1/1784972307_1784972307_ChatGPT Image Jul 22, 2026, 05_49_25 PM.png', NULL, '2026-07-25 09:38:35', '122110481721392348', '2026-07-25 09:38:35', 0, NULL, 0, 0, 0, NULL),
(84, 110, 1, 'new one', 'published', 'facebook', 'clients/1/1784972387_1784972387_ChatGPT Image Jul 22, 2026, 05_49_25 PM.png', NULL, '2026-07-25 09:39:54', '122110482123392348', '2026-07-25 09:39:54', 0, NULL, 0, 0, 0, NULL),
(86, 112, 1, 'hey there', 'published', 'facebook', 'clients/1/1784972431_1784972431_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', NULL, '2026-07-25 09:40:36', '122110482309392348', '2026-07-25 09:40:36', 0, NULL, 0, 0, 0, NULL),
(88, 114, 1, 'neqwwwwwwwwww', 'published', 'facebook', 'clients/1/1784972619_1784972619_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', NULL, '2026-07-25 09:43:45', '122110483017392348', '2026-07-25 09:43:45', 0, NULL, 0, 0, 0, NULL),
(90, 116, 1, 'hello', 'published', 'facebook', 'clients/1/1784972937_1784972937_ChatGPT Image Jul 22, 2026, 06_03_15 PM.png', NULL, '2026-07-25 09:49:08', '122110484097392348', '2026-07-25 09:49:08', 0, NULL, 0, 0, 0, NULL);

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
(1, NULL, 'admin@ourcompany.com', 'adminpassword', 'admin', '2026-07-18 08:04:53', '2026-07-25 12:03:59'),
(3, 1, 'acctuse9@gmail.com', 'clientpass', 'client', '2026-07-18 09:49:26', '2026-07-25 09:23:21'),
(5, 3, 'patel3y3r@gmail.com', 'Clientpass', 'client', '2026-07-18 11:30:57', '2026-07-25 11:42:22'),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `client_hub_keys`
--
ALTER TABLE `client_hub_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `posts_cache`
--
ALTER TABLE `posts_cache`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

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
