-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 13, 2026 at 11:02 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `backoffice_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `image_path`, `updated_at`) VALUES
(1, 'uploads/r1.png', '2026-02-28 06:56:37'),
(2, 'uploads/banner_2_1766117823.jpg', '2025-12-19 04:17:03'),
(3, 'uploads/r1.jpg', '2026-02-28 06:37:19');

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `game_name` varchar(255) NOT NULL,
  `game_image` varchar(255) DEFAULT NULL,
  `game_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `title` varchar(255) NOT NULL,
  `game_url` varchar(255) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`id`, `game_name`, `game_image`, `game_link`, `created_at`, `title`, `game_url`, `cover_image`, `sort_order`, `is_active`) VALUES
(1, '', NULL, NULL, '2026-03-12 02:12:44', 'เกมที่ 1', 'play.php', 'uploads/games/game_1_1773281773.jpg', 1, 1),
(2, '', NULL, NULL, '2026-03-12 02:12:44', 'เกมที่ 2', 'play2.php', 'uploads/games/game_2_1773281761.jpg', 2, 1),
(3, '', NULL, NULL, '2026-03-12 06:21:38', 'เกมที่ 3', 'play3.php', 'uploads/games/game_3_1773296568.jpg', 3, 1),
(4, '', NULL, NULL, '2026-03-12 07:02:07', 'เกมที่ 3', 'play3.php', 'uploads/games/game_4_1773298949.png', 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `short_desc` text NOT NULL,
  `content` longtext NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `news_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `title`, `short_desc`, `content`, `image`, `category`, `news_date`, `created_at`) VALUES
(5, 'SPECIAL LECTURE PUBLISHING IN HIGH IMPACT JOURNALS', '', 'SPECIAL LECTURE PUBLISHING IN HIGH IMPACT JOURNALS\r\nSTRATEGIES FOR  SUBMITTING TO SCIENCE AND NATURE\r\nㆍHOW TO FRAME RESEARCH FOR HIGH-IMPACT JOURNALS\r\nㆍEDITORIAL EXPECTATIONS AND PEER-REVIEW PROCESSES\r\nㆍCOMMON REASONS FOR REJECTION\r\nㆍSTRATEGIES TO IMPROVE MANUSCRIPT QUALITY AND ACCEPTANCE RATE\r\n25 MARCH 2026 10:00-12:00 A.M.\r\nWALAI RUKHAVEJ MEETING ROOM MAHASARAKHAM UNIVERSITY\r\nSpeaker\r\nProf. Dr. Alan D. Ziegler\r\nFaculty of Fisheries, Kasetsart University', '1773303950_1.jpg', NULL, NULL, '2026-03-12 08:25:50');

-- --------------------------------------------------------

--
-- Table structure for table `popup_news`
--

CREATE TABLE `popup_news` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `popup_news`
--

INSERT INTO `popup_news` (`id`, `image_path`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'uploads/news/news_20260301_051604_832a109b.png', 1, 1, '2026-03-01 04:16:04'),
(2, 'uploads/news/news_20260301_051611_23277162.jpg', 1, 2, '2026-03-01 04:16:11'),
(3, 'uploads/news/news_20260301_051616_6bbee8c1.jpg', 1, 3, '2026-03-01 04:16:16');

-- --------------------------------------------------------

--
-- Table structure for table `site_banners`
--

CREATE TABLE `site_banners` (
  `id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_banners`
--

INSERT INTO `site_banners` (`id`, `image_path`, `created_at`) VALUES
(1, 'uploads/banners/banner_20260303_203244_a26ff0be.jpg', '2026-03-03 19:32:44');

-- --------------------------------------------------------

--
-- Table structure for table `tourists`
--

CREATE TABLE `tourists` (
  `id` int(11) NOT NULL,
  `nickname` varchar(100) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `user_type` varchar(50) DEFAULT NULL,
  `visit_date` date DEFAULT NULL,
  `visit_time` time DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tourists`
--

INSERT INTO `tourists` (`id`, `nickname`, `gender`, `age`, `user_type`, `visit_date`, `visit_time`, `created_at`, `archived`) VALUES
(1, 'OK', 'หญิง', 29, 'บุคลากร', '2026-03-12', '09:37:00', '2026-03-12 08:40:36', 1),
(2, 'art', 'ชาย', 20, 'นักศึกษา', '2026-03-12', '09:41:00', '2026-03-12 08:41:54', 1),
(3, 'มาเล', 'ชาย', 22, 'นักท่องเที่ยว', '2026-03-12', '09:41:00', '2026-03-12 08:42:06', 1),
(4, 'ออ', 'หญิง', 13, 'นักท่องเที่ยว', '2026-03-12', '09:42:00', '2026-03-12 08:42:19', 1),
(5, 'อาม', 'อื่นๆ', 15, 'นักท่องเที่ยว', '2026-03-12', '09:42:00', '2026-03-12 08:42:30', 1),
(6, 'เดฟ', 'หญิง', 21, 'นักท่องเที่ยว', '2026-03-13', '10:45:00', '2026-03-13 09:45:32', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `popup_news`
--
ALTER TABLE `popup_news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_banners`
--
ALTER TABLE `site_banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tourists`
--
ALTER TABLE `tourists`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `popup_news`
--
ALTER TABLE `popup_news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `site_banners`
--
ALTER TABLE `site_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tourists`
--
ALTER TABLE `tourists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
