-- phpMyAdmin SQL Dump
-- version 5.0.4deb2+deb11u2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 28, 2026 at 01:04 PM
-- Server version: 10.5.29-MariaDB-0+deb11u1
-- PHP Version: 7.4.33

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
-- Table structure for table `about_content`
--

CREATE TABLE `about_content` (
  `id` int(11) NOT NULL,
  `section_tag` varchar(50) NOT NULL DEFAULT 'ABOUT',
  `title` varchar(255) NOT NULL DEFAULT 'ประวัติ',
  `lead_text` text DEFAULT NULL,
  `paragraph1` text DEFAULT NULL,
  `paragraph2` text DEFAULT NULL,
  `paragraph3` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `image_badge_title` varchar(255) DEFAULT NULL,
  `image_badge_text` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `about_content`
--

INSERT INTO `about_content` (`id`, `section_tag`, `title`, `lead_text`, `paragraph1`, `paragraph2`, `paragraph3`, `image_path`, `image_badge_title`, `image_badge_text`, `updated_at`) VALUES
(1, 'ABOUT', 'ประวัติ', 'สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม เป็นหน่วยงานด้านวิจัยและบริการวิชาการที่มุ่งเน้นความหลากหลายทางชีวภาพ การอนุรักษ์ทรัพยากรธรรมชาติ และภูมิปัญญาท้องถิ่นอย่างยั่งยืน', 'สถาบันวิจัยวลัยรุกขเวช มหาวิทยาลัยมหาสารคาม (สวนรุกขเวช มมส) ก่อตั้งเมื่อปี พ.ศ. 2530 โดยความร่วมมือระหว่าง มศว.มหาสารคามและจังหวัด เพื่ออนุรักษ์พรรณไม้พื้นเมืองอีสาน ได้รับพระราชทานนาม “สวนวลัยรุกขเวช” จากสมเด็จพระเจ้าลูกเธอ เจ้าฟ้าจุฬาภรณวลัยลักษณ์ เมื่อวันที่ 28 กันยายน 2531 ปัจจุบันตั้งอยู่บนพื้นที่ 2 แห่ง ได้แก่ สถานีปฏิบัติการบ้านเกิ้ง (อ.เมือง) และสถาบันวิจัยฯ (อ.นาดูน) ดำเนินงานด้านวิจัย ความหลากหลายทางชีวภาพ และอนุรักษ์ธรรมชาติ', 'จุดเริ่มต้นของสถาบันเริ่มจากโครงการสวนพฤกษชาติและศูนย์สนเทศพรรณไม้อีสาน โดยความร่วมมือของมหาวิทยาลัยศรีนครินทรวิโรฒ วิทยาเขตมหาสารคาม และจังหวัดมหาสารคาม ใช้พื้นที่สาธารณประโยชน์กุดแดง บ้านเกิ้ง ตำบลเกิ้ง อำเภอเมือง จังหวัดมหาสารคาม จำนวนประมาณ 270 ไร่ ต่อมาได้รับพระราชทานนามว่า “สวนวลัยรุกขเวช” และในช่วงปี พ.ศ. 2532–2535 ได้มีการขยายพื้นที่เพิ่มเติมที่อำเภอนาดูน พร้อมการสนับสนุนงบประมาณจากโครงการอีสานเขียว', 'เมื่อวันที่ 22 ตุลาคม 2535 สถาบันได้รับการจัดตั้งเป็นหน่วยงานระดับสถาบันวิจัย และได้รับพระราชทานนามหน่วยงานว่า “สถาบันวิจัยวลัยรุกขเวช” ปัจจุบันเป็นหน่วยงานที่มุ่งเน้นงานวิจัยและบริการวิชาการด้านความหลากหลายทางชีวภาพ เกษตรอินทรีย์ และภูมิปัญญาท้องถิ่น', 'uploads/1774340522_a0.jpg', 'Walai Rukhavej Research Institute', 'สถาบันวิจัยด้านความหลากหลายทางชีวภาพ เกษตรอินทรีย์ และภูมิปัญญาท้องถิ่น', '2026-03-24 08:22:02');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `password`) VALUES
(1, '$2y$10$OYw7bhV3Dhuw/t6SDT1CMOXEFPAR9F9eG7jaWrDkgopnXS6D92Wju');

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
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `guests` int(11) DEFAULT 1,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_otps`
--

CREATE TABLE `email_otps` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_otps`
--

INSERT INTO `email_otps` (`id`, `email`, `otp_code`, `expires_at`, `is_used`, `created_at`) VALUES
(22, 'adminrukkhawet@gmail.com', '854797', '2026-03-27 15:31:38', 0, '2026-03-27 08:26:38'),
(27, '67010974003@msu.ac.th', '524558', '2026-03-27 15:45:38', 1, '2026-03-27 08:40:38'),
(29, 'sueatchada2547@gmail.com', '835601', '2026-03-27 16:45:00', 0, '2026-03-27 09:40:00'),
(30, 'suratchada2547@gmail.com', '791003', '2026-03-27 16:46:57', 1, '2026-03-27 09:41:57'),
(31, 'kanathip4123@gmail.com', '881149', '2026-03-27 17:02:48', 1, '2026-03-27 09:57:48'),
(32, '67010974001@msu.ac.th', '748649', '2026-03-27 21:15:49', 0, '2026-03-27 14:10:49'),
(33, '67010974010@msu.ac.th', '885520', '2026-03-27 21:18:10', 1, '2026-03-27 14:13:10'),
(34, 'kanathip2321@gmail.com', '879454', '2026-03-28 01:11:13', 0, '2026-03-27 18:06:13');

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
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(255) NOT NULL,
  `room_type` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('show','hide') NOT NULL DEFAULT 'show',
  `room_size` varchar(100) DEFAULT NULL,
  `bed_type` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_rooms` int(11) NOT NULL DEFAULT 5,
  `max_guests` int(11) NOT NULL DEFAULT 2,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `room_type`, `price`, `image`, `description`, `status`, `room_size`, `bed_type`, `capacity`, `updated_at`, `total_rooms`, `max_guests`, `image_path`) VALUES
(3, 'ห้องพัก VIP 101', 'VIP', '0.00', 'uploads/rooms/room_20260326_145202_6110.jpg', '0', 'show', '', '', 2, '2026-03-27 08:46:31', 5, 2, 'uploads/rooms/room_20260326_145202_6110.jpg'),
(4, 'ห้องพัก VIP 102', 'VIP', '500.00', 'uploads/rooms/room_20260326_145218_9123.jpg', '0', 'show', '', '', 2, '2026-03-27 08:55:07', 5, 2, 'uploads/rooms/room_20260326_145218_9123.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `room_bookings`
--

CREATE TABLE `room_bookings` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `room_type` varchar(100) NOT NULL,
  `guests` int(11) NOT NULL,
  `checkin_date` date NOT NULL,
  `checkout_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled','completed') DEFAULT 'pending',
  `booking_status` enum('pending','approved','cancelled') DEFAULT 'pending',
  `archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `room_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_bookings`
--

INSERT INTO `room_bookings` (`id`, `full_name`, `phone`, `email`, `room_type`, `guests`, `checkin_date`, `checkout_date`, `note`, `status`, `booking_status`, `archived`, `created_at`, `room_id`) VALUES
(16, 'Kanathip Khwakutkhae', '0611360322', 'kanathip4123@gmail.com', 'ห้องพัก VIP 102', 1, '2026-03-28', '2026-03-29', '', 'pending', 'pending', 0, '2026-03-28 04:54:06', 4);

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
(6, 'เดฟ', 'หญิง', 21, 'นักท่องเที่ยว', '2026-03-13', '10:45:00', '2026-03-13 09:45:32', 0),
(7, 'มาเล', 'หญิง', 40, 'นักท่องเที่ยว', '2026-03-24', '15:41:00', '2026-03-24 08:41:31', 0),
(8, 'บอส', 'ชาย', 21, 'นักศึกษา', '2026-03-24', '17:30:00', '2026-03-24 10:30:42', 0),
(9, 'n', 'ชาย', 18, 'นักศึกษา', '2026-03-25', '17:03:00', '2026-03-25 10:03:41', 1),
(10, 'OK', 'หญิง', 21, 'นักศึกษา', '2026-03-26', '08:05:00', '2026-03-26 01:05:13', 1),
(11, 'art', 'ชาย', 42, 'นักท่องเที่ยว', '2026-03-26', '08:05:00', '2026-03-26 01:06:09', 1),
(12, 'มาเลๆ', 'ชาย', 20, 'นักท่องเที่ยว', '2026-03-26', '16:34:00', '2026-03-26 09:34:30', 1),
(13, 'อาม', 'อื่นๆ', 12, 'นักท่องเที่ยว', '2026-03-26', '16:34:00', '2026-03-26 09:35:02', 0),
(14, 'art', 'ชาย', 10, 'นักท่องเที่ยว', '2026-03-27', '12:16:00', '2026-03-27 05:16:16', 0),
(15, 'นานา', 'ชาย', 20, 'นักศึกษา', '2026-03-27', '16:25:00', '2026-03-27 09:26:31', 0),
(16, 'OK', 'ชาย', 20, 'นักศึกษา', '2026-03-27', '20:11:00', '2026-03-27 13:11:28', 0),
(17, 'art', 'ชาย', 10, 'บุคลากร', '2026-03-27', '20:12:00', '2026-03-27 13:12:25', 0),
(18, 'งอเเง', 'หญิง', 23, 'นักศึกษา', '2003-06-24', '21:07:00', '2026-03-27 14:07:56', 0),
(19, 'งอเเง', 'หญิง', 23, 'นักศึกษา', '2003-06-24', '21:13:00', '2026-03-27 14:14:40', 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('ชาย','หญิง','อื่นๆ') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `phone`, `password`, `created_at`, `is_verified`, `role`, `avatar`, `birth_date`, `gender`, `address`, `bio`, `updated_at`) VALUES
(7, 'คณาธิป ขวากุดแข้', '67010974003@msu.ac.th', '0967846147', '$2y$10$rtqx84RxG6pt2I7jBrY.UO9eZ.DpCtz18/lqtNvGTsaWBsmfNDbqq', '2026-03-26 17:51:35', 1, 'admin', 'uploads/avatars/avatar_7_1774622798.jpg', NULL, NULL, NULL, NULL, '2026-03-27 14:46:38'),
(10, 'Admin', 'adminrukkhawet@gmail.com', '0903262100', '$2y$10$cPy4//rqH3jyRfbSeJAu6urOrAP1W75BXIvRgmAmHBFSfPVyhhFZC', '2026-03-27 05:43:14', 1, 'user', NULL, NULL, NULL, NULL, NULL, '2026-03-27 09:28:14'),
(12, 'น้องเนย', 'suratchada2547@gmail.com', '0611360322', '$2y$10$/69uD2c1DSQxCujzhyrXPekEc1DNioWGyMtK4JZbBrk/ABKbtf5jK', '2026-03-27 09:41:47', 1, 'user', NULL, NULL, NULL, NULL, NULL, '2026-03-27 09:42:17'),
(13, 'kanathip', 'kanathip4123@gmail.com', '0622301236', '$2y$10$Fe6pp05.8HiERg..qNE9eu9g8j1CYM5mevclgUpluTc8ADWee9Iou', '2026-03-27 09:57:48', 1, 'user', NULL, NULL, NULL, NULL, NULL, '2026-03-27 09:58:15'),
(14, 'เจี๊ยบ', '67010974001@msu.ac.th', '0980102523', '$2y$10$DbJwM18.NmPDBnZJv/tqD.v0ZePWmlwDhYoam1zWXcioylpeBGhfK', '2026-03-27 14:10:49', 0, 'user', NULL, NULL, NULL, NULL, NULL, '2026-03-27 14:10:49'),
(15, 'เจี๊ยบ0', '67010974010@msu.ac.th', '0980102523', '$2y$10$mgWAw3ksBsEasbXqbrXKSORB2yoQZ1iOeg2OnToVyAHP8xqB6nuQy', '2026-03-27 14:13:10', 1, 'user', 'uploads/avatars/avatar_15_1774630123_749f54.jpg', NULL, 'หญิง', '', '', '2026-03-27 16:48:43'),
(16, 'ssh', 'kanathip2321@gmail.com', '0622301236', '$2y$10$OO5To7tr/NVC/teo36YJYOWEDlzUGmzKCyfTbz.vrQDFkwmnpDtCC', '2026-03-27 18:06:13', 0, 'user', NULL, NULL, NULL, NULL, NULL, '2026-03-27 18:06:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `about_content`
--
ALTER TABLE `about_content`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `email_otps`
--
ALTER TABLE `email_otps`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `room_bookings`
--
ALTER TABLE `room_bookings`
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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `about_content`
--
ALTER TABLE `about_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_otps`
--
ALTER TABLE `email_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

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
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `room_bookings`
--
ALTER TABLE `room_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `site_banners`
--
ALTER TABLE `site_banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tourists`
--
ALTER TABLE `tourists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
