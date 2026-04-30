-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 25, 2026 at 07:09 PM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 8.4.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `watercolor_erpwlk`
--

-- --------------------------------------------------------

--
-- Table structure for table `google_reviews`
--

CREATE TABLE `google_reviews` (
  `id` int(11) NOT NULL,
  `review_id` varchar(255) NOT NULL,
  `place_id` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `review_text` longtext DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `profile_picture_local_path` varchar(500) DEFAULT NULL,
  `profile_picture_remote_url` varchar(500) DEFAULT NULL,
  `owner_response` longtext DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `imported_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `google_reviews`
--

INSERT INTO `google_reviews` (`id`, `review_id`, `place_id`, `author`, `rating`, `review_text`, `review_date`, `profile_picture_local_path`, `profile_picture_remote_url`, `owner_response`, `language`, `is_active`, `imported_at`, `updated_at`) VALUES
(1, 'Ci9DQUlRQUNvZENodHljRjlvT25kTVJURmtaSFZTTUdwUVMyRkVkWGgwTlUxTlVuYxAB', '0x2184ad78e8563da7:0', 'Dharsha Samarasinha', 5.0, 'Excellent customer service and prompt delivery.\nThe products are reasonably priced and are of very good quality.\n\nCan easily recommend to any watercolour artists or enthusiasts.', '2026-01-25 17:58:10', 'importer/reviews_images/profiles/ALV-UjWYKINaN1GeI0r2cXl08GDfgMHAWqNfrph7WuWGiboOQbK19LQ.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjWYKINaN1GeI0r2cXl08GDfgMHAWqNfrph7WuWGiboOQbK19LQ=w1200-h1200-no', 'Thank you so much for your kind words. It means a lot', 'en', 1, '2026-04-25 17:47:45', '2026-04-25 18:01:47'),
(2, 'Ci9DQUlRQUNvZENodHljRjlvT2tGT1ZWZzJUblJtZUU0MFpFZzJNazVJVnpSM2JrRRAB', '0x2184ad78e8563da7:0', 'Lanka Gunasekera', 5.0, 'Goods were carefully packed and received on time good customer service', '2026-03-26 17:58:09', 'importer/reviews_images/profiles/ALV-UjXmugta_wkIHMnDijIx2fgCUPpuPgH4C0IEej6-c-4gSwGsmQ4.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjXmugta_wkIHMnDijIx2fgCUPpuPgH4C0IEej6-c-4gSwGsmQ4=w1200-h1200-no', 'Thank You so much for you comment. ðŸ˜Š â€¦', 'en', 1, '2026-04-25 17:47:45', '2026-04-25 18:01:47'),
(3, 'Ci9DQUlRQUNvZENodHljRjlvT21FMU4yRkNiRXBRVFdSSGJFNDRiM0kxZFZaRFIyYxAB', '0x2184ad78e8563da7:0', 'Dulsas Sugathapala', 4.0, 'Excellent customer service â€” it truly made my day.\nThe products are absolutely good quality for reasonable prices, and delivery was right on time.\nThe website could be improved with more regular updates and better user-friendliness.\nOverall, a very positive experience with great service and products.', '2025-12-26 17:58:08', 'importer/reviews_images/profiles/ALV-UjXy9hDO-LzsDN32VL_Y6A32BgyQwHMf4MmQRToJFd3L2WBio2M.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjXy9hDO-LzsDN32VL_Y6A32BgyQwHMf4MmQRToJFd3L2WBio2M=w1200-h1200-no', 'Thank you for your kind feedback. Weâ€™re glad you enjoyed our products and service. We appreciate your suggestion about the website and are actively working on improvements. Looking forward to serving you again soon!', 'en', 1, '2026-04-25 17:47:45', '2026-04-25 18:01:48'),
(4, 'Ci9DQUlRQUNvZENodHljRjlvT2kxNmFFRkpkSGMwTlZobmExRlBTblJ4ZG05RGRYYxAB', '0x2184ad78e8563da7:0', 'Nipuni Anuththara Herath', 5.0, 'Friendly customer service. Quick delivery. Materials were carefully packed.', '2026-03-26 17:58:06', 'importer/reviews_images/profiles/ALV-UjV_9UOOMHFHDTb_2v3_HmHNsmsPMU_mpPucHN6XIH9br6q_Xe4.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjV_9UOOMHFHDTb_2v3_HmHNsmsPMU_mpPucHN6XIH9br6q_Xe4=w1200-h1200-no', 'Thank you so much for your feedback. Happy painting', 'en', 1, '2026-04-25 17:47:45', '2026-04-25 18:01:48'),
(5, 'Ci9DQUlRQUNvZENodHljRjlvT25GWVEyNXplSGRoUTBaYVpYWmZRekJDYTJFMU4wRRAB', '0x2184ad78e8563da7:0', 'Sachinthani Munasinghe', 5.0, 'Good service and have quality products, Excellent customer service', '2026-03-26 17:58:05', 'importer/reviews_images/profiles/ALV-UjX1CtToTQmypVz5ZOh7YXVhVqOeAkOsK2FosFwQxCF4oCcS0k-tHQ.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjX1CtToTQmypVz5ZOh7YXVhVqOeAkOsK2FosFwQxCF4oCcS0k-tHQ=w1200-h1200-no', 'Thank you so much..! Means a lot for us. Happy Painting', 'en', 1, '2026-04-25 17:47:45', '2026-04-25 18:01:48'),
(6, 'Ci9DQUlRQUNvZENodHljRjlvT25Nd1JFWlpXSFp5WVhOd2VXeFBkR2hMUWt0c2FGRRAB', '0x2184ad78e8563da7:0', 'Shashika Karawita', 5.0, '', '2025-11-26 17:58:18', 'importer/reviews_images/profiles/ACg8ocKmsi9prylmF1QRFMmfnobOnN3jFgIm4Pu-GBZcqbVH4QUa4w.jpg', 'https://lh3.googleusercontent.com/a/ACg8ocKmsi9prylmF1QRFMmfnobOnN3jFgIm4Pu-GBZcqbVH4QUa4w=w1200-h1200-no', 'Thank you so much', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(7, 'Ci9DQUlRQUNvZENodHljRjlvT2w5aFRuUnFWMEV5YW5vM1pFMXZOalpmTFRjelluYxAB', '0x2184ad78e8563da7:0', 'Mohottalalage Bandara', 5.0, 'â­ï¸â­ï¸â­ï¸â­ï¸â­ï¸â­ï¸', '2025-10-27 17:58:17', 'importer/reviews_images/profiles/ALV-UjVQGGYmPIpVi0lJ0CRocO17_1oKTlzB8tkzvrFQoF_dGWjrjTg.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjVQGGYmPIpVi0lJ0CRocO17_1oKTlzB8tkzvrFQoF_dGWjrjTg=w1200-h1200-no', 'Thank You so much', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(8, 'Ci9DQUlRQUNvZENodHljRjlvT21SSlJpMUdUbUZSYkdaTlJ6ZFdiM0p5WVMxcE1rRRAB', '0x2184ad78e8563da7:0', 'dinoj Malshan', 5.0, 'à¶‘à¶š à¶±à·’à¶ºà¶¸à¶ºà·“â¤ï¸', '2025-10-27 17:58:17', 'importer/reviews_images/profiles/ACg8ocI4pyYvEZiHQzoBX5Nj_O2X0X9p5dseuGmrYzxc-8QOcIm9Mcg.jpg', 'https://lh3.googleusercontent.com/a/ACg8ocI4pyYvEZiHQzoBX5Nj_O2X0X9p5dseuGmrYzxc-8QOcIm9Mcg=w1200-h1200-no', 'Thank You so much for the feedback.', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(9, 'Ci9DQUlRQUNvZENodHljRjlvT21OUExYaHFiWGswYVhOeGVtMWxkakZpYzBvelVHYxAB', '0x2184ad78e8563da7:0', 'Iyanthi Kulatilaka', 5.0, 'Purchased a set of water colors and sketch book, that Iâ€™m very happy about. Very good quality! Also very prompt and reliable service.', '2026-04-04 17:58:16', 'importer/reviews_images/profiles/ALV-UjWGTcxdHtjMY6vTGZeiVRuzoQdjpruw_41glxeOsQyLDOfAXhVm.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjWGTcxdHtjMY6vTGZeiVRuzoQdjpruw_41glxeOsQyLDOfAXhVm=w1200-h1200-no', 'Thank you so much for your feedback. It means a lot for us. Lets build a strong watercolor culture in Sri Lanka', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(10, 'Ci9DQUlRQUNvZENodHljRjlvT25SU1RqUmZOSFZxVDJGWFJXWk9VRFk1YzE4MlRWRRAB', '0x2184ad78e8563da7:0', 'Dilan Bandara', 5.0, 'Excellent service and good products !\nHighly recommended â¤ï¸â¤ï¸â¤ï¸', '2026-04-23 17:58:16', 'importer/reviews_images/profiles/ALV-UjVMNLpK6iSNlW_sLi4IVEidc-1RZY6rodNr_-cYehsOGGYOshq6gA.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjVMNLpK6iSNlW_sLi4IVEidc-1RZY6rodNr_-cYehsOGGYOshq6gA=w1200-h1200-no', 'Thank you so much..! Happy Painting', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(11, 'Ci9DQUlRQUNvZENodHljRjlvT21FNFlXdGxVM2QxV0daTWFHMUZiVll0ZFcwNWVGRRAB', '0x2184ad78e8563da7:0', 'Dammika Chaminda', 5.0, 'Good stuff and equipment', '2025-10-27 17:58:14', 'importer/reviews_images/profiles/ACg8ocLtW34Zw8O2tqnEphMJ9bFNIfkR23CJOUb8nfjR49Ym2JDCFw.jpg', 'https://lh3.googleusercontent.com/a/ACg8ocLtW34Zw8O2tqnEphMJ9bFNIfkR23CJOUb8nfjR49Ym2JDCFw=w1200-h1200-no', 'Thanks so much! I really appreciate the feedback and hope you have a great time using your new art tools.', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(12, 'Ci9DQUlRQUNvZENodHljRjlvT2t4eVkxQTJibTVJV1dsRmFEbHdiMjlGYW1OVU5VRRAB', '0x2184ad78e8563da7:0', 'Suchira Cooray', 5.0, 'Exceptional customer service', '2025-11-26 17:58:13', 'importer/reviews_images/profiles/ACg8ocK544Dp04_3gb4VvoYjYIY6NVJ5C664y-aBPPF9fyK2HA5yoQA.jpg', 'https://lh3.googleusercontent.com/a/ACg8ocK544Dp04_3gb4VvoYjYIY6NVJ5C664y-aBPPF9fyK2HA5yoQA=w1200-h1200-no', 'Thank You So Much ðŸ˜Š  â€¦', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(13, 'Ci9DQUlRQUNvZENodHljRjlvT2xObWEyaHpRamRGYTA1c1JWQlZiVTVzTFY5TGFWRRAB', '0x2184ad78e8563da7:0', 'Sarasi Gunasena', 5.0, 'Good product . Highly rerecommended', '2026-02-24 17:58:13', 'importer/reviews_images/profiles/ALV-UjVS6Jp_dku3T9a6YLy9v_mxPx1KcSYtshir0uZnqu2O2ahi_F-b.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjVS6Jp_dku3T9a6YLy9v_mxPx1KcSYtshir0uZnqu2O2ahi_F-b=w1200-h1200-no', 'Thank you so much..! Happy Painting', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(14, 'Ci9DQUlRQUNvZENodHljRjlvT25sbVIwUk5kRGsxUmxKR05tNHhlblp4ZEd0QlQyYxAB', '0x2184ad78e8563da7:0', 'Buddhini Jayasuriya', 5.0, 'Verry good and super colity fast delivering servise and verry help full', '2026-02-24 17:58:12', 'importer/reviews_images/profiles/ALV-UjWkT1a7iEbbLCHqMoAzuX62P1fvQ6-JmsTS2lyekNMc0SJiO691.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjWkT1a7iEbbLCHqMoAzuX62P1fvQ6-JmsTS2lyekNMc0SJiO691=w1200-h1200-no', 'Thank you so much..!! Happy Painting', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47'),
(15, 'Ci9DQUlRQUNvZENodHljRjlvT2s5UlduRjBUbTFqT1MxMmNISXdRVlZ4VEVOMmMwRRAB', '0x2184ad78e8563da7:0', 'Marez art', 5.0, 'Excellent Service â¤ï¸', '2026-01-25 17:58:11', 'importer/reviews_images/profiles/ALV-UjUVOYNCVKBGufjjj7gHVC_od3GOIIlyk9x5g1oAhMY7UFCdvHeg.jpg', 'https://lh3.googleusercontent.com/a-/ALV-UjUVOYNCVKBGufjjj7gHVC_od3GOIIlyk9x5g1oAhMY7UFCdvHeg=w1200-h1200-no', 'Thank you so much', 'en', 1, '2026-04-25 18:01:47', '2026-04-25 18:01:47');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(80) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `erp_sync_status` varchar(40) NOT NULL DEFAULT 'pending',
  `erp_sell_id` varchar(100) DEFAULT NULL,
  `sync_error` text DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_name`, `customer_phone`, `customer_email`, `payment_method`, `notes`, `status`, `erp_sync_status`, `erp_sell_id`, `sync_error`, `synced_at`, `created_at`, `updated_at`) VALUES
(1, 'Dinu Sri Madhusanka', '44444444', '', 'bank_transfer', '', 'pending', 'synced', NULL, NULL, '2026-04-25 12:40:42', '2026-04-25 12:40:41', '2026-04-25 12:40:42');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `erp_product_id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(100) NOT NULL DEFAULT '',
  `quantity` decimal(12,3) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `erp_product_id`, `sku`, `quantity`, `unit_price`, `created_at`) VALUES
(1, 1, 33, 67, 'BC01', 1.000, 7499.00, '2026-04-25 12:40:41');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `erp_product_id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(100) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_name` varchar(150) NOT NULL DEFAULT '',
  `brand_name` varchar(150) NOT NULL DEFAULT '',
  `unit_short_name` varchar(20) NOT NULL DEFAULT 'Pc',
  `image_url` varchar(500) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_qty` decimal(12,3) NOT NULL DEFAULT 0.000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `erp_product_id`, `sku`, `name`, `description`, `category_name`, `brand_name`, `unit_short_name`, `image_url`, `price`, `stock_qty`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 21, 'TT05', 'Dark Green Short Calligraphy Brush (Size S)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 3049.00, 1.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(2, 22, 'TT17', 'Yutang Brush Set with Pouch (7-Piece)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2849.00, 10.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(3, 23, 'TT16', 'Moyuan Brush Set (6-Piece)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 6549.00, 5.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(4, 24, 'TT01', 'RED & BLUE Brush Set (2-Piece)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4099.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(5, 27, 'TT06', 'Yingxiong 2-Piece Brush Set in Gift Box', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4099.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(6, 28, 'TT04', 'Shede Short Weasel Hair Calligraphy Brush', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2299.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(7, 29, 'TT12', 'Thin & Tiny Hair Detail Brush', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025383_13.jpg', 599.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(8, 30, 'TT02', 'Mingxiu Black Flat Brush (30mm)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 3749.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(9, 31, 'TT03', 'Mingxiu Black Flat Brush (50mm)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4749.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(10, 25, 'TT14-01', 'Jinghong  Calligraphy Brush (No. 1)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4399.00, 4.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(11, 26, 'TT14-05', 'Jinghong  Calligraphy Brush (No. 5)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 3099.00, 4.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(12, 32, 'TT11', 'Mu Flat Brush -White Hair, White Handle (40mm)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4499.00, 3.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(13, 33, 'TT10', 'Mu Flat Brush -Black Hair, Black Handle (40mm)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 7499.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(14, 34, 'TT09', 'Mu Flat Brush -Black Hair, White Handle (40mm)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 7299.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(15, 35, 'TT08', 'Red Little Hair Detail Brush', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025263_6.jpg', 799.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(16, 36, 'TT07', 'Molong Long Hair Brush - Black Handle (Size M)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025162_3.jpg', 2199.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(17, 37, 'TT13', 'Mingruixiang Large (L) Calligraphy Brush', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 3599.00, 4.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(18, 38, 'PC02', 'Watercolor Sketch Book - 16X16cm', '', '', 'Potentate', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025091_2.jpg', 3099.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(19, 39, 'PC03', 'Watercolor Sketch Book - 12X12cm', '', '', 'Potentate', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2399.00, 31.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(20, 40, 'PC01', 'Watercolor Sketch Book - 13X19cm', '', '', 'Potentate', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025061_1.jpg', 3899.00, 32.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(21, 41, 'TT22', 'RED Handle Brush from Couple', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025228_5.jpg', 2099.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(22, 42, 'TT21', 'BLUE Handle Brush from Couple', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025191_4.jpg', 2099.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(23, 43, 'TT18', 'Chinese Calligraphy/Watercolor Brush TT18', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025291_7.jpg', 499.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(24, 44, 'TT30', 'Synthetic Hair Travel Brush with Cap - Size 2', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025319_8.jpg', 499.00, 25.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(25, 45, 'TT29', 'Synthetic Hair Travel Brush with Cap - Size 8', '', '', '', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1754025348_9.jpg', 599.00, 25.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(26, 46, 'TT24', 'Sketchers Watercolor Water Cup', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 589.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(27, 47, 'TT31', 'Chinese Watercolor Brush Weasel/Nylon Blend, Short Handle', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2129.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(28, 48, 'TT32', 'Chinese Watercolor Brush Sheep/Nylon Blend, Short Handle', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2129.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(29, 49, 'TT33', 'Chinese Watercolor Brush Set — 2-Piece Round, Weasel & Sheep Hair', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4258.98, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(30, 50, 'TT34', 'Chinese Watercolor Brush Set — 2-Piece Round (Long Hair), Mixed Hair, Red & Blue Long Handles (Sizes M & L)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4099.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(31, 51, 'SC01', 'Sinours Professional Watercolor 14 Colors Full Pan Metal Set - Inorganic', '', '', 'Sinours', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1756884595_sinours%2014%20full%20pan%20professional%20watercolor%20metal%20box.webp', 10999.00, 23.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(32, 52, 'TT23', 'Beginner Brush Pouch — Bamboo Brush Roll, 6 Slots (25×50 cm)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 399.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(33, 67, 'BC01', 'Baohong Academy A3 Cold Press Watercolor Pad', '', '', 'Baohong', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1767161206_a3.jpg', 7499.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(34, 68, 'BC02', 'Baohong Academy A4 Cold Press Watercolor Pad', '', '', 'Baohong', 'Pc(s)', 'https://erppro.lk/public/uploads/img/1767161614_variant_imageSd0cece479d654ff3b64410ecf4340a55E.jpg', 3999.00, 9.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(35, 69, 'PC04', 'Potentate 190x260mm Zip Lock Paper Bag', '', '', 'Potentate', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 999.00, 58.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(36, 70, 'PC05', 'Potentate 260x380mm Zip Lock Paper Bag', '', '', 'Potentate', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1899.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(37, 71, 'TT26-S', 'Yutang White Hair, Bambo Handle Brush Small', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 449.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(38, 72, 'TT26-M', 'Yutang White Hair, Bambo Handle Brush Medium', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 509.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(39, 73, 'TT26-L', 'Yutang White Hair, Bambo Handle Brush Large', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 549.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(40, 74, 'TT25-XS', 'Yutang Brown Hair, Woodn Handle Brush XS', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 339.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(41, 75, 'TT25-S', 'Yutang Brown Hair, Woodn Handle Brush Small', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 449.00, 6.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(42, 76, 'TT25-M', 'Yutang Brown Hair, Woodn Handle Brush Medium', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 499.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(43, 77, 'TT25-L', 'Yutang Brown Hair, Woodn Handle Brush Large', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 509.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(44, 78, 'TT27-SS', 'Moyuan White Hair, Vintage Handle Brush Small', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 909.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(45, 79, 'TT27-SW', 'Moyuan Brown Hair, Vintage Handle Brush Small', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 909.00, 9.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(46, 80, 'TT27-MS', 'Moyuan White Hair, Vintage Handle Brush Medium', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 999.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(47, 81, 'TT27-MW', 'Moyuan Brown Hair, Vintage Handle Brush Medium', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 999.00, 10.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(48, 82, 'TT27-LS', 'Moyuan White Hair, Vintage Handle Brush Large', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1099.00, 10.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(49, 84, 'TT53', 'Generic black colored watercolor Artist brush - size 2', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 7.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(50, 85, 'TT54', 'Generic black colored watercolor Artist brush - size 8', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 299.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(51, 86, 'TT64', 'Artsecret Black Flat Art Brushe (40mm)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 3299.00, 3.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(52, 87, 'TT63', 'W&N -18 watercolor Artist paint set box', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 3699.00, 5.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(53, 88, 'TT62', 'W&N -24 watercolor Artist paint set box', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 4629.00, 5.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(54, 89, 'TT65', 'Generic chinese Long Handle Artist watercolor brush', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 449.00, 39.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(55, 90, 'TT66', 'Generic chinese Short Handle Artist Travel watercolor brush - surface minor damaged', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 659.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(56, 91, 'TT67', 'Plastic Foldable Artist Water Cups', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 549.00, 16.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(57, 92, 'TT61', 'Watercolor Palette Scraper', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 999.00, 1.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(58, 93, 'TT60', 'Mali Flat sheep Brush (Size 3)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 659.00, 4.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(59, 94, 'TT59', 'Mali Flat sheep Brush (Size 5)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 899.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(60, 95, 'TT58', 'Xiangfei Chinese Calligraphy Brush (Size S)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1149.00, 5.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(61, 96, 'TT57', 'Xiangfei Chinese Calligraphy Brush (Size M)', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1269.00, 5.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(62, 97, 'TT56', 'Plastic Water spray bottles 100 ml', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 499.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(63, 98, 'TT55', 'Generic Water-fillng watercolor Artist brush kits', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1199.00, 15.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(64, 99, 'TT68', 'Titanium Buff 291', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(65, 100, 'TT69', 'Light Oxide Red 339', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(66, 101, 'TT70', 'Phthalo Blue 570', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(67, 102, 'TT71', 'Payne\'s gray 708', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(68, 103, 'TT72', 'Permanent Orange 266', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(69, 104, 'TT73', 'Permanent blue violet 568', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(70, 105, 'TT74', 'Lavender 525', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(71, 106, 'TT75', 'Sepia 416', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(72, 107, 'TT76', 'Naples Yellow Red 224', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(73, 108, 'TT77', 'Vandyke Brown 403', '', '', 'Van Gogh', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1350.00, 0.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(74, 109, 'PC06', 'Potentate A5 Mixed Media Book', '', '', 'Potentate', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1499.00, 38.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(75, 110, 'PC07', 'Potentate A4 Mixed Media Book', '', '', 'Potentate', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2199.00, 20.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(76, 111, 'TP01', 'Paul Rubens Professional Watercolor Metal 24 Color Kit', '', '', 'Paul Rubens', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 14999.00, 3.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(77, 112, 'TT78', '12 Well Watercolor Palette', '', '', '', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 499.00, 22.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(78, 113, 'TT79', 'Chinese W&N Sap green [428]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 10.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(79, 114, 'TT80', 'Chinese W&N medium yellow [120]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 9.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(80, 115, 'TT81', 'Chinese W&N orange [150]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 9.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(81, 117, 'TT83', 'Chinese W&N ultramarine [340]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(82, 118, 'TT84', 'Chinese W&N yellow orchre [500]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 10.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(83, 120, 'TT86', 'Chinese W&N fresh tint [210]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 10.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(84, 121, 'TT87', 'Chinese W&N permanet red [240]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 8.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(85, 122, 'TT88', 'Chinese W&N violet [300]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 10.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(86, 119, 'TT85', 'Chinese W&N burnt sienne [520]', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 249.00, 9.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(87, 123, 'TS01', 'SiconArt Chi Ling T6 Travel Watercolor Brush', '', '', 'SiconArt', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 899.00, 25.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(88, 124, 'TS02', 'SiconArt HQ Goat Hair Mix Flat Brush - Size 3', '', '', 'SiconArt', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1799.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(89, 125, 'TS03', 'SiconArt HQ Goat Hair Mix Flat Brush - Size 5', '', '', 'SiconArt', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2999.00, 2.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(90, 126, 'TS04', 'SiconArt S3 Dark Green Short Watercolor Brush', '', '', 'SiconArt', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2499.00, 25.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(91, 127, 'TS05', 'SiconArt Goat Hair Mix LK Watercolor Brush', '', '', 'SiconArt', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 1499.00, 25.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30'),
(92, 128, 'TT89', 'W&N -12 watercolor Artist paint set box', '', '', 'W&N', 'Pc(s)', 'https://erppro.lk/public/img/default.png', 2899.00, 5.000, 1, '2026-04-25 12:35:30', '2026-04-25 12:35:30');

-- --------------------------------------------------------

--
-- Table structure for table `product_overrides`
--

CREATE TABLE `product_overrides` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `override_slug` varchar(255) DEFAULT NULL,
  `override_title` varchar(255) DEFAULT NULL,
  `override_description` text DEFAULT NULL,
  `override_image_url` varchar(500) DEFAULT NULL,
  `override_price` decimal(12,2) DEFAULT NULL,
  `override_badge` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `google_reviews`
--
ALTER TABLE `google_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `review_id` (`review_id`),
  ADD KEY `idx_place_id` (`place_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_review_date` (`review_date`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_sync` (`erp_sync_status`),
  ADD KEY `idx_orders_status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `idx_order_items_erp_product` (`erp_product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `erp_product_id` (`erp_product_id`),
  ADD KEY `idx_products_name` (`name`),
  ADD KEY `idx_products_sku` (`sku`),
  ADD KEY `idx_products_category` (`category_name`);

--
-- Indexes for table `product_overrides`
--
ALTER TABLE `product_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`),
  ADD KEY `idx_override_slug` (`override_slug`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `google_reviews`
--
ALTER TABLE `google_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `product_overrides`
--
ALTER TABLE `product_overrides`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_overrides`
--
ALTER TABLE `product_overrides`
  ADD CONSTRAINT `fk_product_overrides_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
