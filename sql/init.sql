-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 02, 2026 at 06:42 AM
-- Server version: 8.0.45-0ubuntu0.24.04.1
-- PHP Version: 8.4.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ept`
--
CREATE DATABASE IF NOT EXISTS `ept` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `ept`;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE IF NOT EXISTS `announcements` (
  `announcement_id` int NOT NULL AUTO_INCREMENT,
  `announcement_msg` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements_notification`
--

DROP TABLE IF EXISTS `announcements_notification`;
CREATE TABLE IF NOT EXISTS `announcements_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `message` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `participants` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_on` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE IF NOT EXISTS `audit_log` (
  `audit_log_id` int NOT NULL AUTO_INCREMENT,
  `statement` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`audit_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificate_batches`
--

DROP TABLE IF EXISTS `certificate_batches`;
CREATE TABLE IF NOT EXISTS `certificate_batches` (
  `batch_id` int NOT NULL AUTO_INCREMENT,
  `batch_name` varchar(100) NOT NULL,
  `shipment_ids` text NOT NULL,
  `status` enum('pending','generating','generated','approved','distributed','failed') DEFAULT 'pending',
  `download_url` varchar(500) DEFAULT NULL,
  `folder_path` varchar(500) DEFAULT NULL,
  `excellence_count` int DEFAULT '0',
  `participation_count` int DEFAULT '0',
  `skipped_count` int DEFAULT '0',
  `error_message` text,
  `created_by` int NOT NULL,
  `created_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `approved_by` int DEFAULT NULL,
  `approved_on` datetime DEFAULT NULL,
  `distributed_on` datetime DEFAULT NULL,
  PRIMARY KEY (`batch_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificate_templates`
--

DROP TABLE IF EXISTS `certificate_templates`;
CREATE TABLE IF NOT EXISTS `certificate_templates` (
  `ct_id` int NOT NULL AUTO_INCREMENT,
  `scheme_type` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `participation_certificate` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `excellence_certificate` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ct_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_us`
--

DROP TABLE IF EXISTS `contact_us`;
CREATE TABLE IF NOT EXISTS `contact_us` (
  `contact_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lab` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `additional_info` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `participant_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `subject` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `country` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `contacted_on` datetime DEFAULT NULL,
  `ip_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
CREATE TABLE IF NOT EXISTS `countries` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `iso_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `iso2` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `iso3` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `numeric_code` smallint NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=250 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `iso_name`, `iso2`, `iso3`, `numeric_code`) VALUES
(1, 'Afghanistan', 'AF', 'AFG', 4),
(2, 'Aland Islands', 'AX', 'ALA', 248),
(3, 'Albania', 'AL', 'ALB', 8),
(4, 'Algeria', 'DZ', 'DZA', 12),
(5, 'American Samoa', 'AS', 'ASM', 16),
(6, 'Andorra', 'AD', 'AND', 20),
(7, 'Angola', 'AO', 'AGO', 24),
(8, 'Anguilla', 'AI', 'AIA', 660),
(9, 'Antarctica', 'AQ', 'ATA', 10),
(10, 'Antigua and Barbuda', 'AG', 'ATG', 28),
(11, 'Argentina', 'AR', 'ARG', 32),
(12, 'Armenia', 'AM', 'ARM', 51),
(13, 'Aruba', 'AW', 'ABW', 533),
(14, 'Australia', 'AU', 'AUS', 36),
(15, 'Austria', 'AT', 'AUT', 40),
(16, 'Azerbaijan', 'AZ', 'AZE', 31),
(17, 'Bahamas', 'BS', 'BHS', 44),
(18, 'Bahrain', 'BH', 'BHR', 48),
(19, 'Bangladesh', 'BD', 'BGD', 50),
(20, 'Barbados', 'BB', 'BRB', 52),
(21, 'Belarus', 'BY', 'BLR', 112),
(22, 'Belgium', 'BE', 'BEL', 56),
(23, 'Belize', 'BZ', 'BLZ', 84),
(24, 'Benin', 'BJ', 'BEN', 204),
(25, 'Bermuda', 'BM', 'BMU', 60),
(26, 'Bhutan', 'BT', 'BTN', 64),
(27, 'Bolivia, Plurinational State of', 'BO', 'BOL', 68),
(28, 'Bonaire, Sint Eustatius and Saba', 'BQ', 'BES', 535),
(29, 'Bosnia and Herzegovina', 'BA', 'BIH', 70),
(30, 'Botswana', 'BW', 'BWA', 72),
(31, 'Bouvet Island', 'BV', 'BVT', 74),
(32, 'Brazil', 'BR', 'BRA', 76),
(33, 'British Indian Ocean Territory', 'IO', 'IOT', 86),
(34, 'Brunei Darussalam', 'BN', 'BRN', 96),
(35, 'Bulgaria', 'BG', 'BGR', 100),
(36, 'Burkina Faso', 'BF', 'BFA', 854),
(37, 'Burundi', 'BI', 'BDI', 108),
(38, 'Cambodia', 'KH', 'KHM', 116),
(39, 'Cameroon', 'CM', 'CMR', 120),
(40, 'Canada', 'CA', 'CAN', 124),
(41, 'Cape Verde', 'CV', 'CPV', 132),
(42, 'Cayman Islands', 'KY', 'CYM', 136),
(43, 'Central African Republic', 'CF', 'CAF', 140),
(44, 'Chad', 'TD', 'TCD', 148),
(45, 'Chile', 'CL', 'CHL', 152),
(46, 'China', 'CN', 'CHN', 156),
(47, 'Christmas Island', 'CX', 'CXR', 162),
(48, 'Cocos (Keeling) Islands', 'CC', 'CCK', 166),
(49, 'Colombia', 'CO', 'COL', 170),
(50, 'Comoros', 'KM', 'COM', 174),
(51, 'Congo', 'CG', 'COG', 178),
(52, 'Congo, the Democratic Republic of the', 'CD', 'COD', 180),
(53, 'Cook Islands', 'CK', 'COK', 184),
(54, 'Costa Rica', 'CR', 'CRI', 188),
(55, 'Cote d\'Ivoire', 'CI', 'CIV', 384),
(56, 'Croatia', 'HR', 'HRV', 191),
(57, 'Cuba', 'CU', 'CUB', 192),
(58, 'Cura', 'CW', 'CUW', 531),
(59, 'Cyprus', 'CY', 'CYP', 196),
(60, 'Czech Republic', 'CZ', 'CZE', 203),
(61, 'Denmark', 'DK', 'DNK', 208),
(62, 'Djibouti', 'DJ', 'DJI', 262),
(63, 'Dominica', 'DM', 'DMA', 212),
(64, 'Dominican Republic', 'DO', 'DOM', 214),
(65, 'Ecuador', 'EC', 'ECU', 218),
(66, 'Egypt', 'EG', 'EGY', 818),
(67, 'El Salvador', 'SV', 'SLV', 222),
(68, 'Equatorial Guinea', 'GQ', 'GNQ', 226),
(69, 'Eritrea', 'ER', 'ERI', 232),
(70, 'Estonia', 'EE', 'EST', 233),
(71, 'Ethiopia', 'ET', 'ETH', 231),
(72, 'Falkland Islands (Malvinas)', 'FK', 'FLK', 238),
(73, 'Faroe Islands', 'FO', 'FRO', 234),
(74, 'Fiji', 'FJ', 'FJI', 242),
(75, 'Finland', 'FI', 'FIN', 246),
(76, 'France', 'FR', 'FRA', 250),
(77, 'French Guiana', 'GF', 'GUF', 254),
(78, 'French Polynesia', 'PF', 'PYF', 258),
(79, 'French Southern Territories', 'TF', 'ATF', 260),
(80, 'Gabon', 'GA', 'GAB', 266),
(81, 'Gambia', 'GM', 'GMB', 270),
(82, 'Georgia', 'GE', 'GEO', 268),
(83, 'Germany', 'DE', 'DEU', 276),
(84, 'Ghana', 'GH', 'GHA', 288),
(85, 'Gibraltar', 'GI', 'GIB', 292),
(86, 'Greece', 'GR', 'GRC', 300),
(87, 'Greenland', 'GL', 'GRL', 304),
(88, 'Grenada', 'GD', 'GRD', 308),
(89, 'Guadeloupe', 'GP', 'GLP', 312),
(90, 'Guam', 'GU', 'GUM', 316),
(91, 'Guatemala', 'GT', 'GTM', 320),
(92, 'Guernsey', 'GG', 'GGY', 831),
(93, 'Guinea', 'GN', 'GIN', 324),
(94, 'Guinea-Bissau', 'GW', 'GNB', 624),
(95, 'Guyana', 'GY', 'GUY', 328),
(96, 'Haiti', 'HT', 'HTI', 332),
(97, 'Heard Island and McDonald Islands', 'HM', 'HMD', 334),
(98, 'Holy See (Vatican City State)', 'VA', 'VAT', 336),
(99, 'Honduras', 'HN', 'HND', 340),
(100, 'Hong Kong', 'HK', 'HKG', 344),
(101, 'Hungary', 'HU', 'HUN', 348),
(102, 'Iceland', 'IS', 'ISL', 352),
(103, 'India', 'IN', 'IND', 356),
(104, 'Indonesia', 'ID', 'IDN', 360),
(105, 'Iran, Islamic Republic of', 'IR', 'IRN', 364),
(106, 'Iraq', 'IQ', 'IRQ', 368),
(107, 'Ireland', 'IE', 'IRL', 372),
(108, 'Isle of Man', 'IM', 'IMN', 833),
(109, 'Israel', 'IL', 'ISR', 376),
(110, 'Italy', 'IT', 'ITA', 380),
(111, 'Jamaica', 'JM', 'JAM', 388),
(112, 'Japan', 'JP', 'JPN', 392),
(113, 'Jersey', 'JE', 'JEY', 832),
(114, 'Jordan', 'JO', 'JOR', 400),
(115, 'Kazakhstan', 'KZ', 'KAZ', 398),
(116, 'Kenya', 'KE', 'KEN', 404),
(117, 'Kiribati', 'KI', 'KIR', 296),
(118, 'Korea, Democratic People\'s Republic of', 'KP', 'PRK', 408),
(119, 'Korea, Republic of', 'KR', 'KOR', 410),
(120, 'Kuwait', 'KW', 'KWT', 414),
(121, 'Kyrgyzstan', 'KG', 'KGZ', 417),
(122, 'Lao People\'s Democratic Republic', 'LA', 'LAO', 418),
(123, 'Latvia', 'LV', 'LVA', 428),
(124, 'Lebanon', 'LB', 'LBN', 422),
(125, 'Lesotho', 'LS', 'LSO', 426),
(126, 'Liberia', 'LR', 'LBR', 430),
(127, 'Libya', 'LY', 'LBY', 434),
(128, 'Liechtenstein', 'LI', 'LIE', 438),
(129, 'Lithuania', 'LT', 'LTU', 440),
(130, 'Luxembourg', 'LU', 'LUX', 442),
(131, 'Macao', 'MO', 'MAC', 446),
(132, 'Macedonia, the former Yugoslav Republic of', 'MK', 'MKD', 807),
(133, 'Madagascar', 'MG', 'MDG', 450),
(134, 'Malawi', 'MW', 'MWI', 454),
(135, 'Malaysia', 'MY', 'MYS', 458),
(136, 'Maldives', 'MV', 'MDV', 462),
(137, 'Mali', 'ML', 'MLI', 466),
(138, 'Malta', 'MT', 'MLT', 470),
(139, 'Marshall Islands', 'MH', 'MHL', 584),
(140, 'Martinique', 'MQ', 'MTQ', 474),
(141, 'Mauritania', 'MR', 'MRT', 478),
(142, 'Mauritius', 'MU', 'MUS', 480),
(143, 'Mayotte', 'YT', 'MYT', 175),
(144, 'Mexico', 'MX', 'MEX', 484),
(145, 'Micronesia, Federated States of', 'FM', 'FSM', 583),
(146, 'Moldova, Republic of', 'MD', 'MDA', 498),
(147, 'Monaco', 'MC', 'MCO', 492),
(148, 'Mongolia', 'MN', 'MNG', 496),
(149, 'Montenegro', 'ME', 'MNE', 499),
(150, 'Montserrat', 'MS', 'MSR', 500),
(151, 'Morocco', 'MA', 'MAR', 504),
(152, 'Mozambique', 'MZ', 'MOZ', 508),
(153, 'Myanmar', 'MM', 'MMR', 104),
(154, 'Namibia', 'NA', 'NAM', 516),
(155, 'Nauru', 'NR', 'NRU', 520),
(156, 'Nepal', 'NP', 'NPL', 524),
(157, 'Netherlands', 'NL', 'NLD', 528),
(158, 'New Caledonia', 'NC', 'NCL', 540),
(159, 'New Zealand', 'NZ', 'NZL', 554),
(160, 'Nicaragua', 'NI', 'NIC', 558),
(161, 'Niger', 'NE', 'NER', 562),
(162, 'Nigeria', 'NG', 'NGA', 566),
(163, 'Niue', 'NU', 'NIU', 570),
(164, 'Norfolk Island', 'NF', 'NFK', 574),
(165, 'Northern Mariana Islands', 'MP', 'MNP', 580),
(166, 'Norway', 'NO', 'NOR', 578),
(167, 'Oman', 'OM', 'OMN', 512),
(168, 'Pakistan', 'PK', 'PAK', 586),
(169, 'Palau', 'PW', 'PLW', 585),
(170, 'Palestine, State of', 'PS', 'PSE', 275),
(171, 'Panama', 'PA', 'PAN', 591),
(172, 'Papua New Guinea', 'PG', 'PNG', 598),
(173, 'Paraguay', 'PY', 'PRY', 600),
(174, 'Peru', 'PE', 'PER', 604),
(175, 'Philippines', 'PH', 'PHL', 608),
(176, 'Pitcairn', 'PN', 'PCN', 612),
(177, 'Poland', 'PL', 'POL', 616),
(178, 'Portugal', 'PT', 'PRT', 620),
(179, 'Puerto Rico', 'PR', 'PRI', 630),
(180, 'Qatar', 'QA', 'QAT', 634),
(181, 'Reunion', 'RE', 'REU', 638),
(182, 'Romania', 'RO', 'ROU', 642),
(183, 'Russian Federation', 'RU', 'RUS', 643),
(184, 'Rwanda', 'RW', 'RWA', 646),
(185, 'Saint Barthelemy', 'BL', 'BLM', 652),
(186, 'Saint Helena, Ascension and Tristan da Cunha', 'SH', 'SHN', 654),
(187, 'Saint Kitts and Nevis', 'KN', 'KNA', 659),
(188, 'Saint Lucia', 'LC', 'LCA', 662),
(189, 'Saint Martin (French part)', 'MF', 'MAF', 663),
(190, 'Saint Pierre and Miquelon', 'PM', 'SPM', 666),
(191, 'Saint Vincent and the Grenadines', 'VC', 'VCT', 670),
(192, 'Samoa', 'WS', 'WSM', 882),
(193, 'San Marino', 'SM', 'SMR', 674),
(194, 'Sao Tome and Principe', 'ST', 'STP', 678),
(195, 'Saudi Arabia', 'SA', 'SAU', 682),
(196, 'Senegal', 'SN', 'SEN', 686),
(197, 'Serbia', 'RS', 'SRB', 688),
(198, 'Seychelles', 'SC', 'SYC', 690),
(199, 'Sierra Leone', 'SL', 'SLE', 694),
(200, 'Singapore', 'SG', 'SGP', 702),
(201, 'Sint Maarten (Dutch part)', 'SX', 'SXM', 534),
(202, 'Slovakia', 'SK', 'SVK', 703),
(203, 'Slovenia', 'SI', 'SVN', 705),
(204, 'Solomon Islands', 'SB', 'SLB', 90),
(205, 'Somalia', 'SO', 'SOM', 706),
(206, 'South Africa', 'ZA', 'ZAF', 710),
(207, 'South Georgia and the South Sandwich Islands', 'GS', 'SGS', 239),
(208, 'South Sudan', 'SS', 'SSD', 728),
(209, 'Spain', 'ES', 'ESP', 724),
(210, 'Sri Lanka', 'LK', 'LKA', 144),
(211, 'Sudan', 'SD', 'SDN', 729),
(212, 'Suriname', 'SR', 'SUR', 740),
(213, 'Svalbard and Jan Mayen', 'SJ', 'SJM', 744),
(214, 'Eswatini', 'SZ', 'SWZ', 748),
(215, 'Sweden', 'SE', 'SWE', 752),
(216, 'Switzerland', 'CH', 'CHE', 756),
(217, 'Syrian Arab Republic', 'SY', 'SYR', 760),
(218, 'Taiwan, Province of China', 'TW', 'TWN', 158),
(219, 'Tajikistan', 'TJ', 'TJK', 762),
(220, 'Tanzania, United Republic of', 'TZ', 'TZA', 834),
(221, 'Thailand', 'TH', 'THA', 764),
(222, 'Timor-Leste', 'TL', 'TLS', 626),
(223, 'Togo', 'TG', 'TGO', 768),
(224, 'Tokelau', 'TK', 'TKL', 772),
(225, 'Tonga', 'TO', 'TON', 776),
(226, 'Trinidad and Tobago', 'TT', 'TTO', 780),
(227, 'Tunisia', 'TN', 'TUN', 788),
(228, 'Turkey', 'TR', 'TUR', 792),
(229, 'Turkmenistan', 'TM', 'TKM', 795),
(230, 'Turks and Caicos Islands', 'TC', 'TCA', 796),
(231, 'Tuvalu', 'TV', 'TUV', 798),
(232, 'Uganda', 'UG', 'UGA', 800),
(233, 'Ukraine', 'UA', 'UKR', 804),
(234, 'United Arab Emirates', 'AE', 'ARE', 784),
(235, 'United Kingdom', 'GB', 'GBR', 826),
(236, 'United States', 'US', 'USA', 840),
(237, 'US Pacific Islands', 'UM', 'UMI', 581),
(238, 'Uruguay', 'UY', 'URY', 858),
(239, 'Uzbekistan', 'UZ', 'UZB', 860),
(240, 'Vanuatu', 'VU', 'VUT', 548),
(241, 'Venezuela, Bolivarian Republic of', 'VE', 'VEN', 862),
(242, 'Vietnam', 'VN', 'VNM', 704),
(243, 'Virgin Islands, British', 'VG', 'VGB', 92),
(244, 'Virgin Islands, U.S.', 'VI', 'VIR', 850),
(245, 'Wallis and Futuna', 'WF', 'WLF', 876),
(246, 'Western Sahara', 'EH', 'ESH', 732),
(247, 'Yemen', 'YE', 'YEM', 887),
(248, 'Zambia', 'ZM', 'ZMB', 894),
(249, 'Zimbabwe', 'ZW', 'ZWE', 716);

-- --------------------------------------------------------

--
-- Table structure for table `covid19_identified_genes`
--

DROP TABLE IF EXISTS `covid19_identified_genes`;
CREATE TABLE IF NOT EXISTS `covid19_identified_genes` (
  `gene_map_id` int NOT NULL AUTO_INCREMENT,
  `map_id` int NOT NULL,
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `gene_id` int DEFAULT NULL,
  `ct_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `remarks` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`gene_map_id`),
  KEY `map_id` (`map_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `gene_id` (`gene_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `covid19_recommended_test_types`
--

DROP TABLE IF EXISTS `covid19_recommended_test_types`;
CREATE TABLE IF NOT EXISTS `covid19_recommended_test_types` (
  `test_no` int NOT NULL,
  `test_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`test_no`,`test_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_page_content`
--

DROP TABLE IF EXISTS `custom_page_content`;
CREATE TABLE IF NOT EXISTS `custom_page_content` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `modified_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `modified_date_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `data_manager`
--

DROP TABLE IF EXISTS `data_manager`;
CREATE TABLE IF NOT EXISTS `data_manager` (
  `dm_id` int NOT NULL AUTO_INCREMENT,
  `participant_ulid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `primary_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `institute` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `data_manager_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'manager',
  `ptcc` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `secondary_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `country_id` int DEFAULT NULL,
  `UserFld1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `UserFld2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `UserFld3` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mobile` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `force_password_reset` int NOT NULL DEFAULT '0',
  `force_profile_check` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'no',
  `qc_access` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `enable_adding_test_response_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `enable_choosing_mode_of_receipt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `view_only_access` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'inactive',
  `created_on` datetime DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_ban` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `auth_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `api_token_generated_datetime` datetime DEFAULT NULL,
  `download_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `new_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_date_for_email_reset` date DEFAULT NULL,
  `language` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'en_US',
  PRIMARY KEY (`dm_id`),
  UNIQUE KEY `primary_email` (`primary_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='A PT user Table for Data entry or report printing';

-- --------------------------------------------------------

--
-- Table structure for table `distributions`
--

DROP TABLE IF EXISTS `distributions`;
CREATE TABLE IF NOT EXISTS `distributions` (
  `distribution_id` int NOT NULL AUTO_INCREMENT,
  `distribution_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `distribution_date` date NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`distribution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dts_recommended_testkits`
--

DROP TABLE IF EXISTS `dts_recommended_testkits`;
CREATE TABLE IF NOT EXISTS `dts_recommended_testkits` (
  `test_no` int NOT NULL,
  `testkit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `dts_test_mode` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'dts',
  PRIMARY KEY (`test_no`,`testkit`,`dts_test_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dts_shipment_corrective_action_map`
--

DROP TABLE IF EXISTS `dts_shipment_corrective_action_map`;
CREATE TABLE IF NOT EXISTS `dts_shipment_corrective_action_map` (
  `shipment_map_id` int NOT NULL,
  `corrective_action_id` int NOT NULL,
  `action_taken` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `action_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_participants`
--

DROP TABLE IF EXISTS `email_participants`;
CREATE TABLE IF NOT EXISTS `email_participants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subject` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `receivers` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `shipment_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `date_initiated` datetime DEFAULT CURRENT_TIMESTAMP,
  `initiated_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
CREATE TABLE IF NOT EXISTS `enrollments` (
  `enrollment_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `list_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'default',
  `scheme_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `participant_id` int NOT NULL,
  `enrolled_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`list_name`,`participant_id`),
  KEY `participant_id` (`participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generic_recommended_test_types`
--

DROP TABLE IF EXISTS `generic_recommended_test_types`;
CREATE TABLE IF NOT EXISTS `generic_recommended_test_types` (
  `scheme_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `testkit` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `global_config`
--

DROP TABLE IF EXISTS `global_config`;
CREATE TABLE IF NOT EXISTS `global_config` (
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `global_config`
--

INSERT INTO `global_config` (`name`, `value`) VALUES
('additional_institute_details', NULL),
('admin_email', 'eptmanager@gmail.com'),
('aggregate_insights_url', NULL),
('auto_generate_pt_survey_code', 'yes'),
('custom_field_1', NULL),
('custom_field_2', NULL),
('custom_field_needed', 'no'),
('date_format', NULL),
('direct_participant_login', 'no'),
('disable_push_notification', 'yes'),
('domain', 'https://ept.example.org'),
('dts_configuration', NULL),
('dts_enforce_algorithm_check', 'yes'),
('enable_admin_email_notification', 'yes'),
('enable_capa', 'no'),
('enable_login_attempt_ban', 'no'),
('evaluate_before_generating_reports', 'yes'),
('faq_configurations', NULL),
('faqs', NULL),
('fcm_api_key', NULL),
('fcm_auth_domain', NULL),
('fcm_database_url', NULL),
('fcm_messaging_sender_id', NULL),
('fcm_project_id', NULL),
('fcm_serverkey', NULL),
('fcm_storage_bucket', NULL),
('fcm_url', NULL),
('feed_back_option', 'no'),
('footer_text', NULL),
('generic_test_config', 'yes'),
('home_left_logo', NULL),
('home_right_logo', NULL),
('instance', NULL),
('institute_address', NULL),
('job_completion_alert_mails', NULL),
('job_completion_alert_status', 'no'),
('locale', 'en_US'),
('mail', '{\"domain\":\"https:\\/\\/ept.labsinformatics.com\",\"host\":\"\",\"port\":\"\",\"fromName\":\"\",\"fromEmail\":\"\",\"cc\":\"\",\"bcc\":\"\",\"ssl\":\"\",\"username\":\"eptmanager@gmail.com\",\"password\":\"123\",\"auth\":\"\"}'),
('map_center', NULL),
('map_zoom', NULL),
('max_attempts_for_perm_ban', '5'),
('max_attempts_for_temp_ban', '3'),
('participant_dateformat', 'dd-M-yy'),
('participant_feedback', 'no'),
('participant_login_password_length', '8'),
('participant_login_prefix', 'PTID'),
('participants_can_edit_name', 'yes'),
('pass_percentage', '95'),
('pt_program_name', 'EQA Proficiency Testing'),
('pt_program_short_name', 'EQA PT'),
('qc_access', 'yes'),
('response_after_evaluate', 'yes'),
('temporary_login_ban_time', NULL),
('theme_color', 'blue'),
('training_instance', 'no'),
('training_instance_text', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `home_banner`
--

DROP TABLE IF EXISTS `home_banner`;
CREATE TABLE IF NOT EXISTS `home_banner` (
  `banner_id` int NOT NULL AUTO_INCREMENT,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`banner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `home_banner`
--

INSERT INTO `home_banner` (`banner_id`, `image`) VALUES
(1, 'home_banner.gif');

-- --------------------------------------------------------

--
-- Table structure for table `home_sections`
--

DROP TABLE IF EXISTS `home_sections`;
CREATE TABLE IF NOT EXISTS `home_sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `link` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `section_file` varchar(255) DEFAULT NULL,
  `text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `icon` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `display_order` int DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `modified_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `modified_date_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mail_template`
--

DROP TABLE IF EXISTS `mail_template`;
CREATE TABLE IF NOT EXISTS `mail_template` (
  `mail_temp_id` int NOT NULL AUTO_INCREMENT,
  `mail_purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `from_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mail_from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mail_cc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mail_bcc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mail_subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mail_content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `mail_footer` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`mail_temp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notify`
--

DROP TABLE IF EXISTS `notify`;
CREATE TABLE IF NOT EXISTS `notify` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'auto id',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'notify title',
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT 'notify description',
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'link for corresponding page',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'unread' COMMENT 'read, readed for notify status',
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'current insertion date time',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant`
--

DROP TABLE IF EXISTS `participant`;
CREATE TABLE IF NOT EXISTS `participant` (
  `participant_id` int NOT NULL AUTO_INCREMENT,
  `ulid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `unique_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `individual` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lab_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `institute_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `department_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lab_director_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lab_director_email` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `contact_person_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `contact_person_email` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `contact_person_telephone` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `district` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `country` int NOT NULL,
  `zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `long` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lat` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `shipping_address` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `funding_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `testing_volume` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `enrolled_programs` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `site_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `anc` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `pepfar_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `region` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `first_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mobile` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `additional_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `affiliation` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `network_tier` int DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `force_profile_updation` int NOT NULL DEFAULT '0',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'inactive',
  PRIMARY KEY (`participant_id`),
  UNIQUE KEY `unique_identifier` (`unique_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participants_not_uploaded`
--

DROP TABLE IF EXISTS `participants_not_uploaded`;
CREATE TABLE IF NOT EXISTS `participants_not_uploaded` (
  `id` int NOT NULL AUTO_INCREMENT,
  `s_no` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `participant_id` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `individual` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `participant_lab_name` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `participant_last_name` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `institute_name` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `department` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `address` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `district` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `province` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `country` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `zip` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `longitude` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `latitude` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `mobile_number` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `participant_email` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `participant_password` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `additional_email` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `filename` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `error` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_enrolled_programs_map`
--

DROP TABLE IF EXISTS `participant_enrolled_programs_map`;
CREATE TABLE IF NOT EXISTS `participant_enrolled_programs_map` (
  `participant_id` int NOT NULL,
  `ep_id` int NOT NULL,
  PRIMARY KEY (`participant_id`,`ep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_feedback_answer`
--

DROP TABLE IF EXISTS `participant_feedback_answer`;
CREATE TABLE IF NOT EXISTS `participant_feedback_answer` (
  `answer_id` int NOT NULL,
  `shipment_id` int NOT NULL,
  `participant_id` int DEFAULT NULL,
  `question_id` int NOT NULL,
  `map_id` int NOT NULL,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `updated_datetime` datetime DEFAULT NULL,
  `modified_by` int DEFAULT NULL,
  PRIMARY KEY (`answer_id`),
  KEY `map_id` (`map_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `participant_id` (`participant_id`),
  KEY `question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_manager_map`
--

DROP TABLE IF EXISTS `participant_manager_map`;
CREATE TABLE IF NOT EXISTS `participant_manager_map` (
  `participant_id` int NOT NULL,
  `dm_id` int NOT NULL,
  PRIMARY KEY (`participant_id`,`dm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_messages`
--

DROP TABLE IF EXISTS `participant_messages`;
CREATE TABLE IF NOT EXISTS `participant_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `attached_file` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `participant_testkit_map`
--

DROP TABLE IF EXISTS `participant_testkit_map`;
CREATE TABLE IF NOT EXISTS `participant_testkit_map` (
  `participant_id` int NOT NULL,
  `shipment_id` int NOT NULL,
  `testkit_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  KEY `participant_id` (`participant_id`),
  KEY `testkit_id` (`testkit_id`),
  KEY `shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

DROP TABLE IF EXISTS `partners`;
CREATE TABLE IF NOT EXISTS `partners` (
  `partner_id` int NOT NULL AUTO_INCREMENT,
  `partner_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `sort_order` int DEFAULT NULL,
  `added_by` int NOT NULL,
  `added_on` datetime NOT NULL,
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `logo_image` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`partner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `partners`
--

INSERT INTO `partners` (`partner_id`, `partner_name`, `link`, `sort_order`, `added_by`, `added_on`, `status`, `logo_image`) VALUES
(1, 'CDC-Centers for Disease Control and Prevention', '', NULL, 1, '2016-07-04 17:58:43', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ptcc_countries_map`
--

DROP TABLE IF EXISTS `ptcc_countries_map`;
CREATE TABLE IF NOT EXISTS `ptcc_countries_map` (
  `ptcc_id` int NOT NULL,
  `country_id` int NOT NULL,
  `state` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `district` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mapped_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `ptcc_id_2` (`ptcc_id`,`country_id`,`state`,`district`),
  KEY `ptcc_id` (`ptcc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_notification`
--

DROP TABLE IF EXISTS `push_notification`;
CREATE TABLE IF NOT EXISTS `push_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notification_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT 'create notify message (title body and icon) and convert into json and store here',
  `data_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT 'create notify data message and convert into Json then store here',
  `push_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'refuse, pending, send, not-send',
  `created_on` datetime DEFAULT NULL,
  `token_identify_id` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci COMMENT 'Set which mobile to send push notify. Here id come either shipment or DM',
  `identify_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Type of identify id either shipment, people(DM), General and not-responded people.',
  `notification_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Reports, Shipment, General',
  `announcement_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_notification_template`
--

DROP TABLE IF EXISTS `push_notification_template`;
CREATE TABLE IF NOT EXISTS `push_notification_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `purpose` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `notify_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `notify_body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `data_msg` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `push_notification_template`
--

INSERT INTO `push_notification_template` (`id`, `purpose`, `notify_title`, `notify_body`, `data_msg`, `icon`) VALUES
(1, 'announcement', 'Announcement', 'Announcement Body', 'Announcement message', 'ic_launcher'),
(2, 'report', 'Report', 'Report Body', 'Report Data Message', 'ic_launcher'),
(3, 'not_participated', 'Not Participated', 'Not Participated Body', 'Not Participated Data Message', 'ic_launcher'),
(4, 'new_shipment', 'New Shipment', 'New Shipment Body', 'New Shipment Data Message', 'ic_launcher');

-- --------------------------------------------------------

--
-- Table structure for table `queue_report_generation`
--

DROP TABLE IF EXISTS `queue_report_generation`;
CREATE TABLE IF NOT EXISTS `queue_report_generation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `report_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `requested_by` int NOT NULL,
  `requested_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_finalised` datetime DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `previous_status` varchar(256) DEFAULT NULL,
  `processing_started_at` datetime DEFAULT NULL,
  `last_heartbeat` datetime DEFAULT NULL,
  `initated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_covid19_test_type`
--

DROP TABLE IF EXISTS `reference_covid19_test_type`;
CREATE TABLE IF NOT EXISTS `reference_covid19_test_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `sample_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `test_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `lot_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `expiry_date` date NOT NULL,
  `result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_dbs_eia`
--

DROP TABLE IF EXISTS `reference_dbs_eia`;
CREATE TABLE IF NOT EXISTS `reference_dbs_eia` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `eia` int NOT NULL,
  `lot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `od` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cutoff` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_dbs_wb`
--

DROP TABLE IF EXISTS `reference_dbs_wb`;
CREATE TABLE IF NOT EXISTS `reference_dbs_wb` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `wb` int NOT NULL,
  `lot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `160` int DEFAULT NULL,
  `120` int DEFAULT NULL,
  `66` int DEFAULT NULL,
  `55` int DEFAULT NULL,
  `51` int DEFAULT NULL,
  `41` int DEFAULT NULL,
  `31` int DEFAULT NULL,
  `24` int DEFAULT NULL,
  `17` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_dts_eia`
--

DROP TABLE IF EXISTS `reference_dts_eia`;
CREATE TABLE IF NOT EXISTS `reference_dts_eia` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `eia` int NOT NULL,
  `lot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `od` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cutoff` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `result` varchar(556) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_dts_geenius`
--

DROP TABLE IF EXISTS `reference_dts_geenius`;
CREATE TABLE IF NOT EXISTS `reference_dts_geenius` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int DEFAULT NULL,
  `sample_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lot_no` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_dts_rapid_hiv`
--

DROP TABLE IF EXISTS `reference_dts_rapid_hiv`;
CREATE TABLE IF NOT EXISTS `reference_dts_rapid_hiv` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `sample_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `testkit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `lot_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `expiry_date` date NOT NULL,
  `result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_dts_wb`
--

DROP TABLE IF EXISTS `reference_dts_wb`;
CREATE TABLE IF NOT EXISTS `reference_dts_wb` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `wb` int NOT NULL,
  `lot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `160` int DEFAULT NULL,
  `120` int DEFAULT NULL,
  `66` int DEFAULT NULL,
  `55` int DEFAULT NULL,
  `51` int DEFAULT NULL,
  `41` int DEFAULT NULL,
  `31` int DEFAULT NULL,
  `24` int DEFAULT NULL,
  `17` int DEFAULT NULL,
  `result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_generic_test_calculations`
--

DROP TABLE IF EXISTS `reference_generic_test_calculations`;
CREATE TABLE IF NOT EXISTS `reference_generic_test_calculations` (
  `shipment_id` int NOT NULL,
  `testkit_id` varchar(256) DEFAULT NULL,
  `sample_id` int NOT NULL,
  `no_of_responses` int DEFAULT NULL,
  `q1` double(20,10) DEFAULT NULL,
  `q3` double(20,10) DEFAULT NULL,
  `iqr` double(20,10) DEFAULT NULL,
  `quartile_low` double(20,10) DEFAULT NULL,
  `quartile_high` double(20,10) DEFAULT NULL,
  `mean` double(20,10) DEFAULT NULL,
  `median` double(20,10) DEFAULT NULL,
  `sd` double(20,10) DEFAULT NULL,
  `standard_uncertainty` double(20,10) DEFAULT NULL,
  `is_uncertainty_acceptable` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cv` double(20,10) DEFAULT NULL,
  `low_limit` double(20,10) DEFAULT NULL,
  `high_limit` double(20,10) DEFAULT NULL,
  `calculated_on` datetime DEFAULT NULL,
  `manual_mean` double(20,10) DEFAULT NULL,
  `manual_median` double(20,10) DEFAULT NULL,
  `manual_sd` double(20,10) DEFAULT NULL,
  `manual_standard_uncertainty` double(20,10) DEFAULT NULL,
  `manual_is_uncertainty_acceptable` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `manual_cv` double(20,10) DEFAULT NULL,
  `manual_q1` double(20,10) DEFAULT NULL,
  `manual_q3` double(20,10) DEFAULT NULL,
  `manual_iqr` double(20,10) DEFAULT NULL,
  `manual_quartile_low` double(20,10) DEFAULT NULL,
  `manual_quartile_high` double(20,10) DEFAULT NULL,
  `manual_low_limit` double(20,10) DEFAULT NULL,
  `manual_high_limit` double(20,10) DEFAULT NULL,
  `z_score` double(20,10) NOT NULL,
  `is_result_invalid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `error_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `comment` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `use_range` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'calculated',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_recency_assay`
--

DROP TABLE IF EXISTS `reference_recency_assay`;
CREATE TABLE IF NOT EXISTS `reference_recency_assay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int DEFAULT NULL,
  `sample_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `assay` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lot_no` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_covid19`
--

DROP TABLE IF EXISTS `reference_result_covid19`;
CREATE TABLE IF NOT EXISTS `reference_result_covid19` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_preparation_date` date DEFAULT NULL,
  `reference_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` decimal(10,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Referance Result for Covid19 Shipment';

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_dbs`
--

DROP TABLE IF EXISTS `reference_result_dbs`;
CREATE TABLE IF NOT EXISTS `reference_result_dbs` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_preparation_date` date DEFAULT NULL,
  `reference_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Referance Result for DBS Shipment';

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_dts`
--

DROP TABLE IF EXISTS `reference_result_dts`;
CREATE TABLE IF NOT EXISTS `reference_result_dts` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_preparation_date` date DEFAULT NULL,
  `reference_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `syphilis_reference_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dts_rtri_reference_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` decimal(10,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Referance Result for DTS Shipment';

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_eid`
--

DROP TABLE IF EXISTS `reference_result_eid`;
CREATE TABLE IF NOT EXISTS `reference_result_eid` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_preparation_date` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reference_result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `reference_hiv_ct_od` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reference_ic_qs` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` decimal(10,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_generic_test`
--

DROP TABLE IF EXISTS `reference_result_generic_test`;
CREATE TABLE IF NOT EXISTS `reference_result_generic_test` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_preparation_date` date DEFAULT NULL,
  `reference_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` decimal(10,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_recency`
--

DROP TABLE IF EXISTS `reference_result_recency`;
CREATE TABLE IF NOT EXISTS `reference_result_recency` (
  `shipment_id` int NOT NULL,
  `dts_id` int DEFAULT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_preparation_date` date DEFAULT NULL,
  `reference_result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `reference_control_line` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reference_diagnosis_line` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reference_longterm_line` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_tb`
--

DROP TABLE IF EXISTS `reference_result_tb`;
CREATE TABLE IF NOT EXISTS `reference_result_tb` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_preparation_date` date DEFAULT NULL,
  `request_attributes` json DEFAULT NULL,
  `sample_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `tb_isolate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mtb_detected` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mtb_detected_ultra` varchar(256) DEFAULT NULL,
  `rif_resistance` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `rif_resistance_ultra` varchar(256) DEFAULT NULL,
  `probe_d` decimal(10,4) DEFAULT NULL,
  `probe_c` decimal(10,4) DEFAULT NULL,
  `probe_e` decimal(10,4) DEFAULT NULL,
  `probe_b` decimal(10,4) DEFAULT NULL,
  `spc_xpert` decimal(10,4) DEFAULT NULL,
  `spc_xpert_ultra` decimal(10,4) DEFAULT NULL,
  `probe_a` decimal(10,4) DEFAULT NULL,
  `mtbrif_probe_a_mean_stability_ct` decimal(10,4) DEFAULT NULL,
  `mtbultra_lowest_rpo_b_probe_mean_stability_ct` decimal(10,4) DEFAULT NULL,
  `is1081_is6110` decimal(10,4) DEFAULT NULL,
  `rpo_b1` decimal(10,4) DEFAULT NULL,
  `rpo_b2` decimal(10,4) DEFAULT NULL,
  `rpo_b3` decimal(10,4) DEFAULT NULL,
  `rpo_b4` decimal(10,4) DEFAULT NULL,
  `control` int DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `mtb_detection_consensus` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `rif_resistance_consensus` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `mtb_ultra_detection_consensus` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `rif_ultra_resistance_consensus` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_score` decimal(10,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`shipment_id`,`sample_id`),
  KEY `idx_reference_result_tb_ship_sample` (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_vl`
--

DROP TABLE IF EXISTS `reference_result_vl`;
CREATE TABLE IF NOT EXISTS `reference_result_vl` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sample_preparation_date` date DEFAULT NULL,
  `reference_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` decimal(10,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_vl_calculation`
--

DROP TABLE IF EXISTS `reference_vl_calculation`;
CREATE TABLE IF NOT EXISTS `reference_vl_calculation` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `vl_assay` int NOT NULL,
  `no_of_responses` int DEFAULT NULL,
  `q1` double(10,2) NOT NULL,
  `q3` double(10,2) NOT NULL,
  `iqr` double(10,2) NOT NULL,
  `quartile_low` double(10,2) NOT NULL,
  `quartile_high` double(10,2) NOT NULL,
  `mean` double(10,2) NOT NULL,
  `median` double(20,10) DEFAULT NULL,
  `sd` double(10,2) NOT NULL,
  `standard_uncertainty` double(20,10) DEFAULT NULL,
  `is_uncertainty_acceptable` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cv` double(10,2) NOT NULL,
  `low_limit` double(10,2) NOT NULL,
  `high_limit` double(10,2) NOT NULL,
  `calculated_on` datetime DEFAULT NULL,
  `manual_q1` double(20,10) DEFAULT NULL,
  `manual_q3` double(20,10) DEFAULT NULL,
  `manual_iqr` double(20,10) DEFAULT NULL,
  `manual_quartile_low` double(20,10) DEFAULT NULL,
  `manual_quartile_high` double(20,10) DEFAULT NULL,
  `manual_mean` double(20,10) NOT NULL,
  `manual_median` double(20,10) DEFAULT NULL,
  `manual_sd` double(20,10) NOT NULL,
  `manual_standard_uncertainty` double(20,10) DEFAULT NULL,
  `manual_is_uncertainty_acceptable` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `manual_cv` double(20,10) NOT NULL,
  `manual_low_limit` double(10,2) NOT NULL DEFAULT '0.00',
  `manual_high_limit` double(10,2) NOT NULL DEFAULT '0.00',
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `use_range` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'calculated',
  PRIMARY KEY (`shipment_id`,`sample_id`,`vl_assay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reference_vl_methods`
--

DROP TABLE IF EXISTS `reference_vl_methods`;
CREATE TABLE IF NOT EXISTS `reference_vl_methods` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `assay` int NOT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`shipment_id`,`sample_id`,`assay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_config`
--

DROP TABLE IF EXISTS `report_config`;
CREATE TABLE IF NOT EXISTS `report_config` (
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `report_config`
--

INSERT INTO `report_config` (`name`, `value`) VALUES
('institute-address-postition', 'header'),
('logo', 'logo_example.png'),
('logo-right', NULL),
('report-comment', ''),
('report-format', NULL),
('report-header', 'HEALTHLAND MINISTRY OF HEALTH'),
('report-layout', 'default'),
('template-top-margin', '55');

-- --------------------------------------------------------

--
-- Table structure for table `response_covid19_not_tested_reason`
--

DROP TABLE IF EXISTS `response_covid19_not_tested_reason`;
CREATE TABLE IF NOT EXISTS `response_covid19_not_tested_reason` (
  `covid19_not_tested_reason_id` int NOT NULL AUTO_INCREMENT,
  `covid19_not_tested_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`covid19_not_tested_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_covid19`
--

DROP TABLE IF EXISTS `response_result_covid19`;
CREATE TABLE IF NOT EXISTS `response_result_covid19` (
  `shipment_map_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `test_type_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `name_of_pcr_reagent_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcr_reagent_lot_no_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcr_reagent_exp_date_1` date DEFAULT NULL,
  `lot_no_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_1` date DEFAULT NULL,
  `test_result_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `test_type_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `name_of_pcr_reagent_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcr_reagent_lot_no_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcr_reagent_exp_date_2` date DEFAULT NULL,
  `lot_no_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_2` date DEFAULT NULL,
  `test_result_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `test_type_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `name_of_pcr_reagent_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcr_reagent_lot_no_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pcr_reagent_exp_date_3` date DEFAULT NULL,
  `lot_no_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_3` date DEFAULT NULL,
  `test_result_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reported_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_dbs`
--

DROP TABLE IF EXISTS `response_result_dbs`;
CREATE TABLE IF NOT EXISTS `response_result_dbs` (
  `shipment_map_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `eia_1` int DEFAULT NULL,
  `lot_no_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_1` date DEFAULT NULL,
  `od_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cutoff_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `eia_2` int DEFAULT NULL,
  `lot_no_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_2` date DEFAULT NULL,
  `od_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cutoff_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `eia_3` int DEFAULT NULL,
  `lot_no_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_3` date DEFAULT NULL,
  `od_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `cutoff_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb` int DEFAULT NULL,
  `wb_lot` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_exp_date` date DEFAULT NULL,
  `wb_160` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_120` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_66` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_55` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_51` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_41` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_31` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_24` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `wb_17` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reported_result` int DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_dts`
--

DROP TABLE IF EXISTS `response_result_dts`;
CREATE TABLE IF NOT EXISTS `response_result_dts` (
  `shipment_map_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `test_kit_name_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_test_kit_name_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lot_no_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_lot_no_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_1` date DEFAULT NULL,
  `repeat_exp_date_1` date DEFAULT NULL,
  `test_result_1` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `syphilis_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_test_result_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qc_done_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_qc_done_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qc_date_1` date DEFAULT NULL,
  `repeat_qc_date_1` date DEFAULT NULL,
  `test_kit_name_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_test_kit_name_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lot_no_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_lot_no_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_2` date DEFAULT NULL,
  `repeat_exp_date_2` date DEFAULT NULL,
  `test_result_2` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_test_result_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qc_done_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_qc_done_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qc_date_2` date DEFAULT NULL,
  `repeat_qc_date_2` date DEFAULT NULL,
  `test_kit_name_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_test_kit_name_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lot_no_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_lot_no_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `exp_date_3` date DEFAULT NULL,
  `repeat_exp_date_3` date DEFAULT NULL,
  `test_result_3` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_test_result_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qc_done_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `repeat_qc_done_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qc_date_3` date DEFAULT NULL,
  `repeat_qc_date_3` date DEFAULT NULL,
  `reported_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `syphilis_final` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_this_retest` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dts_rtri_control_line` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dts_rtri_diagnosis_line` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dts_rtri_longterm_line` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dts_rtri_reported_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `algorithm_result` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `interpretation_result` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `dts_rtri_is_editable` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'no',
  `kit_additional_info` json DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_eid`
--

DROP TABLE IF EXISTS `response_result_eid`;
CREATE TABLE IF NOT EXISTS `response_result_eid` (
  `shipment_map_id` int NOT NULL,
  `sample_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `reported_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `hiv_ct_od` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ic_qs` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_generic_test`
--

DROP TABLE IF EXISTS `response_result_generic_test`;
CREATE TABLE IF NOT EXISTS `response_result_generic_test` (
  `shipment_map_id` int NOT NULL,
  `sample_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `result_1` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `result_2` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `result_3` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reported_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `additional_detail` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `z_score` double(20,10) DEFAULT NULL,
  `is_result_invalid` varchar(256) DEFAULT NULL,
  `error_code` varchar(256) DEFAULT NULL,
  `comments` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_recency`
--

DROP TABLE IF EXISTS `response_result_recency`;
CREATE TABLE IF NOT EXISTS `response_result_recency` (
  `shipment_map_id` int NOT NULL,
  `dts_id` int DEFAULT NULL,
  `sample_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `reported_result` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `control_line` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `diagnosis_line` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `longterm_line` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_tb`
--

DROP TABLE IF EXISTS `response_result_tb`;
CREATE TABLE IF NOT EXISTS `response_result_tb` (
  `shipment_map_id` int NOT NULL,
  `sample_id` varchar(45) NOT NULL,
  `response_attributes` json DEFAULT NULL,
  `assay_id` int NOT NULL,
  `mtb_detected` varchar(255) DEFAULT NULL,
  `rif_resistance` varchar(255) DEFAULT NULL,
  `probe_d` decimal(10,4) DEFAULT NULL,
  `probe_c` decimal(10,4) DEFAULT NULL,
  `probe_e` decimal(10,4) DEFAULT NULL,
  `probe_b` decimal(10,4) DEFAULT NULL,
  `spc_xpert` decimal(10,4) DEFAULT NULL,
  `spc_xpert_ultra` decimal(10,4) DEFAULT NULL,
  `probe_a` decimal(10,4) DEFAULT NULL,
  `test_date` date DEFAULT NULL,
  `is1081_is6110` decimal(10,4) DEFAULT NULL,
  `rpo_b1` decimal(10,4) DEFAULT NULL,
  `rpo_b2` decimal(10,4) DEFAULT NULL,
  `rpo_b3` decimal(10,4) DEFAULT NULL,
  `rpo_b4` decimal(10,4) DEFAULT NULL,
  `instrument_serial_no` varchar(256) DEFAULT NULL,
  `gene_xpert_module_no` varchar(256) DEFAULT NULL,
  `tester_name` varchar(256) DEFAULT NULL,
  `error_code` varchar(256) DEFAULT NULL,
  `calculated_score` varchar(45) DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`,`assay_id`),
  KEY `idx_response_result_tb_map_sample` (`shipment_map_id`,`sample_id`),
  KEY `idx_response_result_tb_sample_assay` (`sample_id`,`assay_id`),
  KEY `idx_response_result_tb_assay` (`assay_id`),
  KEY `idx_response_result_tb_rif` (`rif_resistance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_result_vl`
--

DROP TABLE IF EXISTS `response_result_vl`;
CREATE TABLE IF NOT EXISTS `response_result_vl` (
  `shipment_map_id` int NOT NULL,
  `sample_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `reported_viral_load` double(10,2) DEFAULT NULL,
  `z_score` double(20,10) DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `vl_assay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_tnd` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_result_invalid` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `error_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `module_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `comment` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `response_vl_not_tested_reason`
--

DROP TABLE IF EXISTS `response_vl_not_tested_reason`;
CREATE TABLE IF NOT EXISTS `response_vl_not_tested_reason` (
  `vl_not_tested_reason_id` int NOT NULL AUTO_INCREMENT,
  `vl_not_tested_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`vl_not_tested_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `run_once_scripts`
--

DROP TABLE IF EXISTS `run_once_scripts`;
CREATE TABLE IF NOT EXISTS `run_once_scripts` (
  `script_name` varchar(255) NOT NULL,
  `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`script_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `r_control`
--

DROP TABLE IF EXISTS `r_control`;
CREATE TABLE IF NOT EXISTS `r_control` (
  `control_id` int NOT NULL AUTO_INCREMENT,
  `control_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `for_scheme` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_active` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`control_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_control`
--

INSERT INTO `r_control` (`control_id`, `control_name`, `for_scheme`, `is_active`) VALUES
(1, 'Kit Negative Control', 'eid', 'active'),
(2, 'Kit Positive Control', 'eid', 'active'),
(3, 'PT Provider Negative Control', 'eid', 'active'),
(4, 'PT Provider Positive Control', 'eid', 'active'),
(5, 'In-House Negative Control', 'eid', 'active'),
(6, 'In-House Positive Control	', 'eid', 'active'),
(7, 'Negative Control', 'vl', 'active'),
(8, 'Low Positive Control', 'vl', 'active'),
(9, 'High Positive Control', 'vl', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_corrective_actions`
--

DROP TABLE IF EXISTS `r_covid19_corrective_actions`;
CREATE TABLE IF NOT EXISTS `r_covid19_corrective_actions` (
  `action_id` int NOT NULL AUTO_INCREMENT,
  `corrective_action` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`action_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_covid19_corrective_actions`
--

INSERT INTO `r_covid19_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES
(1, 'Please submit response before last date', 'Late response, response not evaluated. Your response received after last date. Expected result for PT panel will be available for your reference. '),
(2, 'Review and refer to SOP for testing. Sample should be tested per National Covid-19 Testing lab.', 'For sample (1/2/3?) National Covid-19 Testing lab was not followed.'),
(3, 'Review all testing procedures prior to performing client testing as reported result does not match expected result.', 'Sample (1/2/3?) reported result does not match with expected result.'),
(4, 'You are required to test all samples in PT panel', 'Sample (1/2/3) was not reported '),
(5, 'Ensure expired test type are not be used for testing. If test types are not available, please contact your superior.', 'Test type XYZ expired M days before the test date DD-MON-YYY.'),
(6, 'Ensure expiry date information is submitted for all performed tests.', 'Result not evaluated Ð test type expiry date (first/second/third) is not reported with PT response.'),
(7, 'Ensure test type name is reported for all performed tests.', 'Result not evaluated Ð name of test type not reported.'),
(8, 'Please use the approved test types according to the SOP/National Covid-19 Testing lab for confirmatory and tie-breaker.', 'Testtype XYZ repeated for all 3 test types'),
(9, 'Please use the approved test types according to the SOP/National Covid-19 Testing lab for confirmatory and tie-breaker.', 'Test type repeated for confirmatory or tiebreaker test (T1/T2/T3).'),
(10, 'Ensure test type lot number is reported for all performed tests. ', 'Result not evaluated Ð Test Type lot number (first/second/third) is not reported.'),
(11, 'Ensure to provide supervisor approval along with his name.', 'Missing supervisor approval for reported result.'),
(12, 'Ensure to provide sample rehydration date', 'Re-hydration date missing in PT report form.'),
(13, 'Ensure to provide to provide panel testing date.', 'Testing date missing in PT report form.'),
(14, 'Covid19 Testing should be done within specified hours of rehydration as per SOP.', 'Testing is not performed within X to Y hours of rehydration.'),
(15, 'Review all testing procedures prior to performing client testing and contact your supervisor for improvement.', 'Participant did not meet the score criteria (Participant Score is 80 and Required Score is 95)'),
(16, 'Ensure to provide to provide panel receipt date. ', 'Panel receipt date missing in PT report form.'),
(17, 'Please test Covid19 sample as per National Covid-19 Testing lab. Review and refer to SOP for testing.', 'For Test (1/2/3) testing is not performed with country approved test type.');

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_gene_types`
--

DROP TABLE IF EXISTS `r_covid19_gene_types`;
CREATE TABLE IF NOT EXISTS `r_covid19_gene_types` (
  `gene_id` int NOT NULL AUTO_INCREMENT,
  `gene_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `scheme_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `gene_status` varchar(55) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  PRIMARY KEY (`gene_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_covid19_gene_types`
--

INSERT INTO `r_covid19_gene_types` (`gene_id`, `gene_name`, `scheme_type`, `gene_status`, `created_by`, `created_on`) VALUES
(1, 'E', 'covid19', 'active', NULL, '2021-03-09 00:10:39'),
(2, 'N', 'covid19', 'active', NULL, '2021-03-09 00:10:50'),
(3, 'RdRp', 'covid19', 'active', NULL, '2021-03-09 00:10:59'),
(4, 'ORF1ab', 'covid19', 'active', NULL, '2021-03-09 00:11:16'),
(5, 'S', 'covid19', 'active', NULL, '2021-03-18 21:21:06'),
(6, 'ORF1', 'covid19', 'active', NULL, '2021-03-18 21:21:33'),
(7, 'ORF3a', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(8, 'M', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(9, 'ORF6', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(10, 'ORF7a', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(11, 'ORF7b', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(12, 'ORF8', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(13, 'ORF10', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(14, 'ORF1b-nsp14', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(15, 'ORF1b', 'covid19', 'active', NULL, '2021-03-18 21:29:57'),
(16, 'Internal control (EAV, MS2, RNAseP)', 'covid19', 'active', NULL, '2021-03-18 21:29:57');

-- --------------------------------------------------------

--
-- Table structure for table `r_dbs_eia`
--

DROP TABLE IF EXISTS `r_dbs_eia`;
CREATE TABLE IF NOT EXISTS `r_dbs_eia` (
  `eia_id` int NOT NULL AUTO_INCREMENT,
  `eia_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`eia_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_dbs_eia`
--

INSERT INTO `r_dbs_eia` (`eia_id`, `eia_name`) VALUES
(1, 'EIA-01 BioRad Genetic Systems HIV 1/2 plus O'),
(2, 'EIA-02 bioMerieux Vironostika Uniform II plus O (3rd gen)'),
(3, 'EIA-03 bioMerieux Vironostika HIV Ag/Ab (4th gen)'),
(4, 'EIA-04 Murex HIV 1.2.0 (3rd gen)');

-- --------------------------------------------------------

--
-- Table structure for table `r_dbs_wb`
--

DROP TABLE IF EXISTS `r_dbs_wb`;
CREATE TABLE IF NOT EXISTS `r_dbs_wb` (
  `wb_id` int NOT NULL AUTO_INCREMENT,
  `wb_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`wb_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_dbs_wb`
--

INSERT INTO `r_dbs_wb` (`wb_id`, `wb_name`) VALUES
(1, 'WB-01 BioRad GS HIV- 1 Western Blot'),
(2, 'WB-02 Cambridge Biotech HIV-1 Western Blot'),
(3, 'WB-03 BioRad LAV Blot I '),
(4, 'WB-04 Genelab Diagnostics HIV Blot kit');

-- --------------------------------------------------------

--
-- Table structure for table `r_dts_corrective_actions`
--

DROP TABLE IF EXISTS `r_dts_corrective_actions`;
CREATE TABLE IF NOT EXISTS `r_dts_corrective_actions` (
  `action_id` int NOT NULL AUTO_INCREMENT,
  `corrective_action` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`action_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_dts_corrective_actions`
--

INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES
(1, 'Please submit response before last date', 'Late response, response not evaluated. Your response received after last date. Expected result for PT panel will be available for your reference. '),
(2, 'Review and refer to SOP for testing. Sample should be tested per National HIV Testing algorithm. ', 'For sample (1/2/3?) National HIV Testing algorithm was not followed.'),
(3, 'Review all testing procedures prior to performing client testing as reported result does not match expected result.', 'Sample (1/2/3?) reported result does not match with expected result.'),
(4, 'You are required to test all samples in PT panel', 'Sample (1/2/3) was not reported '),
(5, 'Ensure expired test kit are not be used for testing. If test kits are not available, please contact your superior.', 'Test kit XYZ expired M days before the test date DD-MON-YYY.'),
(6, 'Ensure expiry date information is submitted for all performed tests.', 'Result not evaluated Ð test kit expiry date (first/second/third) is not reported with PT response.'),
(7, 'Ensure test kit name is reported for all performed tests.', 'Result not evaluated Ð name of test kit not reported.'),
(8, 'Please use the approved test kits according to the SOP/National HIV Testing algorithm for confirmatory and tie-breaker.', 'Testkit XYZ repeated for all 3 test kits'),
(9, 'Please use the approved test kits according to the SOP/National HIV Testing algorithm for confirmatory and tie-breaker.', 'Test kit repeated for confirmatory or tiebreaker test (T1/T2/T3).'),
(10, 'Ensure test kit lot number is reported for all performed tests. ', 'Result not evaluated Ð Test Kit lot number (first/second/third) is not reported.'),
(11, 'Ensure to provide supervisor approval along with his name.', 'Missing supervisor approval for reported result.'),
(12, 'Ensure to provide sample rehydration date', 'Re-hydration date missing in PT report form.'),
(13, 'Ensure to provide to provide panel testing date.', 'Testing date missing in PT report form.'),
(14, 'DTS Testing should be done within specified hours of rehydration as per SOP.', 'Testing is not performed within X to Y hours of rehydration.'),
(15, 'Review all testing procedures prior to performing client testing and contact your supervisor for improvement.', 'Participant did not meet the score criteria (Participant Score is 80 and Required Score is 95)'),
(16, 'Ensure to provide to provide panel receipt date. ', 'Panel receipt date missing in PT report form.'),
(17, 'Please test DTS sample as per National HIV Testing algorithm. Review and refer to SOP for testing.', 'For Test (1/2/3) testing is not performed with country approved test kit.'),
(18, 'Please ensure condition of PT Samples is reported', 'Please ensure condition of PT Samples is reported'),
(19, 'Please ensure Refridgerator availability is reported', 'Please ensure Refridgerator availability is reported'),
(20, 'Please ensure Room Temperature is reported', 'Please ensure Room Temperature is reported'),
(21, 'Please ensure Stop Watch availability is reported', 'Please ensure Stop Watch availability is reported');

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_detection_assay`
--

DROP TABLE IF EXISTS `r_eid_detection_assay`;
CREATE TABLE IF NOT EXISTS `r_eid_detection_assay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `sort_order` int DEFAULT '0',
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_eid_detection_assay`
--

INSERT INTO `r_eid_detection_assay` (`id`, `name`, `sort_order`, `status`) VALUES
(1, 'COBAS Ampliprep/Taqman HIV-1 Qual Test', 1, 'active'),
(2, 'Roche - Amplicor HIV-1 Monitor Test', 2, 'active'),
(3, 'QIAamp Viral Mini Kit (DNA or RNA)', 4, 'active'),
(4, 'Biocentric - Generic', 5, 'active'),
(5, 'Chelex', 6, 'active'),
(6, 'In House', 7, 'active'),
(7, 'Abbott RealTime HIV-1 Qualitative Assay', 3, 'active'),
(8, 'Other', 8, 'active'),
(9, 'Other', 8, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_extraction_assay`
--

DROP TABLE IF EXISTS `r_eid_extraction_assay`;
CREATE TABLE IF NOT EXISTS `r_eid_extraction_assay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `sort_order` int DEFAULT '0',
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_eid_extraction_assay`
--

INSERT INTO `r_eid_extraction_assay` (`id`, `name`, `sort_order`, `status`) VALUES
(1, 'COBAS Ampliprep/Taqman HIV-1 Qual Test', 1, 'active'),
(2, 'Roche - Amplicor HIV-1 Monitor Test', 2, 'active'),
(3, 'QIAamp Viral Mini Kit (DNA or RNA)', 4, 'active'),
(4, 'Biocentric - Generic', 5, 'active'),
(5, 'Chelex', 6, 'active'),
(6, 'In House', 7, 'active'),
(7, 'Abbott RealTime HIV-1 Qualitative Assay', 3, 'active'),
(8, 'Other', 8, 'active'),
(9, 'Other', 8, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_enrolled_programs`
--

DROP TABLE IF EXISTS `r_enrolled_programs`;
CREATE TABLE IF NOT EXISTS `r_enrolled_programs` (
  `r_epid` int NOT NULL AUTO_INCREMENT,
  `enrolled_programs` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`r_epid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_enrolled_programs`
--

INSERT INTO `r_enrolled_programs` (`r_epid`, `enrolled_programs`) VALUES
(1, 'PEPFAR RTQI Program'),
(2, 'PEPFAR');

-- --------------------------------------------------------

--
-- Table structure for table `r_evaluation_comments`
--

DROP TABLE IF EXISTS `r_evaluation_comments`;
CREATE TABLE IF NOT EXISTS `r_evaluation_comments` (
  `comment_id` int NOT NULL AUTO_INCREMENT,
  `scheme` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `comment` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_evaluation_comments`
--

INSERT INTO `r_evaluation_comments` (`comment_id`, `scheme`, `comment`) VALUES
(1, 'dts', 'Mandatory Samples not reported'),
(2, 'dts', 'Minimum score not reached'),
(3, 'eid', 'Controls were not reported'),
(4, 'eid', 'Unsatisfactory score'),
(5, 'vl', 'There were not enough responses for the VL Assay chosen'),
(6, 'vl', 'Some mandatory samples were not reported'),
(7, 'dbs', 'Some Mandatory samples were not reported'),
(8, '', 'Did not meet the minimum score required');

-- --------------------------------------------------------

--
-- Table structure for table `r_feedback_questions`
--

DROP TABLE IF EXISTS `r_feedback_questions`;
CREATE TABLE IF NOT EXISTS `r_feedback_questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `question_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `question_type` enum('text','datetime','dropdown','numeric') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `question_show_to` varchar(256) DEFAULT NULL,
  `question_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `response_attributes` json DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `modified_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `r_modes_of_receipt`
--

DROP TABLE IF EXISTS `r_modes_of_receipt`;
CREATE TABLE IF NOT EXISTS `r_modes_of_receipt` (
  `mode_id` int NOT NULL AUTO_INCREMENT,
  `mode_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`mode_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_modes_of_receipt`
--

INSERT INTO `r_modes_of_receipt` (`mode_id`, `mode_name`) VALUES
(1, 'Online Response'),
(2, 'Courier'),
(3, 'Email'),
(4, 'Scan'),
(5, 'SMS');

-- --------------------------------------------------------

--
-- Table structure for table `r_network_tiers`
--

DROP TABLE IF EXISTS `r_network_tiers`;
CREATE TABLE IF NOT EXISTS `r_network_tiers` (
  `network_id` int NOT NULL AUTO_INCREMENT,
  `network_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`network_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_network_tiers`
--

INSERT INTO `r_network_tiers` (`network_id`, `network_name`) VALUES
(1, 'Primary care laboratory service tier'),
(2, 'Secondary and tertiary laboratory service tiers'),
(3, 'Public Health Reference Laboratories');

-- --------------------------------------------------------

--
-- Table structure for table `r_participant_affiliates`
--

DROP TABLE IF EXISTS `r_participant_affiliates`;
CREATE TABLE IF NOT EXISTS `r_participant_affiliates` (
  `aff_id` int NOT NULL AUTO_INCREMENT,
  `affiliate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`aff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_participant_affiliates`
--

INSERT INTO `r_participant_affiliates` (`aff_id`, `affiliate`) VALUES
(1, 'PMTCT'),
(2, 'VCT'),
(3, 'Mobile VCT'),
(4, 'Hospital');

-- --------------------------------------------------------

--
-- Table structure for table `r_participant_feedback_form`
--

DROP TABLE IF EXISTS `r_participant_feedback_form`;
CREATE TABLE IF NOT EXISTS `r_participant_feedback_form` (
  `rpff_id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `scheme_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `form_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`rpff_id`),
  KEY `shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `r_participant_feedback_form_files_map`
--

DROP TABLE IF EXISTS `r_participant_feedback_form_files_map`;
CREATE TABLE IF NOT EXISTS `r_participant_feedback_form_files_map` (
  `rpf_id` int NOT NULL AUTO_INCREMENT,
  `rpff_id` int DEFAULT NULL,
  `shipment_id` int NOT NULL,
  `scheme_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `feedback_file` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `file_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `files_show_to` varchar(255) DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`rpf_id`),
  KEY `shipment_id` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `r_participant_feedback_form_question_map`
--

DROP TABLE IF EXISTS `r_participant_feedback_form_question_map`;
CREATE TABLE IF NOT EXISTS `r_participant_feedback_form_question_map` (
  `fqm_id` int NOT NULL AUTO_INCREMENT,
  `rpff_id` int NOT NULL,
  `shipment_id` int NOT NULL,
  `scheme_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `question_id` int NOT NULL,
  `is_response_mandatory` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`fqm_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `question_id` (`question_id`),
  KEY `scheme_type` (`scheme_type`),
  KEY `rpff_id` (`rpff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `r_possibleresult`
--

DROP TABLE IF EXISTS `r_possibleresult`;
CREATE TABLE IF NOT EXISTS `r_possibleresult` (
  `id` int NOT NULL AUTO_INCREMENT,
  `scheme_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `scheme_sub_group` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sub_scheme` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `result_type` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `response` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `result_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `display_context` enum('participant','admin','all','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'all',
  `high_range` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `threshold_range` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `low_range` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `sd_scaling_factor` varchar(256) DEFAULT NULL,
  `uncertainy_scaling_factor` varchar(256) DEFAULT NULL,
  `uncertainy_threshold` varchar(256) DEFAULT NULL,
  `minimum_number_of_responses` int DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scheme_sub_group` (`scheme_sub_group`,`result_code`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_possibleresult`
--

INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `sub_scheme`, `result_type`, `response`, `result_code`, `display_context`, `high_range`, `threshold_range`, `low_range`, `sd_scaling_factor`, `uncertainy_scaling_factor`, `uncertainy_threshold`, `minimum_number_of_responses`, `sort_order`) VALUES
(1, 'dts', 'DTS_TEST', NULL, NULL, 'REACTIVE', 'R', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'dts', 'DTS_TEST', NULL, NULL, 'NONREACTIVE', 'NR', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'dts', 'DTS_TEST', NULL, NULL, 'INVALID', 'I', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'dts', 'DTS_FINAL', NULL, NULL, 'POSITIVE', 'P', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'dts', 'DTS_FINAL', NULL, NULL, 'NEGATIVE', 'N', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'dts', 'DTS_FINAL', NULL, NULL, 'INDETERMINATE', 'IND', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'eid', 'EID_FINAL', NULL, NULL, 'Positive (HIV Detected)', NULL, 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'eid', 'EID_FINAL', NULL, NULL, 'Negative (HIV Not Detected)', NULL, 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'eid', 'EID_FINAL', NULL, NULL, 'Equivocal', 'E', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'dbs', 'DBS_FINAL', NULL, NULL, 'P', 'P', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'dbs', 'DBS_FINAL', NULL, NULL, 'N', 'N', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'dts', 'DTS_FINAL', NULL, NULL, 'Not Tested', 'NT', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'recency', 'RECENCY_FINAL', NULL, NULL, 'Recent', 'R', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'recency', 'RECENCY_FINAL', NULL, NULL, 'Long Term', 'LT', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'recency', 'RECENCY_FINAL', NULL, NULL, 'Invalid', 'I', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'recency', 'RECENCY_FINAL', NULL, NULL, 'Negative', 'N', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'covid19', 'COVID19_FINAL', NULL, NULL, 'Postive', 'P', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'covid19', 'COVID19_FINAL', NULL, NULL, 'Negative', 'N', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'covid19', 'COVID19_FINAL', NULL, NULL, 'Invalid', 'I', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'covid19', 'COVID19_TEST', NULL, NULL, 'Postive', 'P', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'covid19', 'COVID19_TEST', NULL, NULL, 'Negative', 'N', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'covid19', 'COVID19_TEST', NULL, NULL, 'Invalid', 'I', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'dts', 'DTS_SYP_TEST', NULL, NULL, 'REACTIVE', 'R', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 'dts', 'DTS_SYP_TEST', NULL, NULL, 'NONREACTIVE', 'NR', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'dts', 'DTS_SYP_TEST', NULL, NULL, 'INVALID', 'INV', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'dts', 'DTS_SYP_FINAL', NULL, NULL, 'POSITIVE', 'P', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'dts', 'DTS_SYP_FINAL', NULL, NULL, 'NEGATIVE', 'N', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'dts', 'DTS_SYP_FINAL', NULL, NULL, 'INDETERMINATE', 'IND', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'dts', 'DTS_FINAL', NULL, NULL, 'Not Reported', 'NOTREPORTED', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'DETECTED', 'detected', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(33, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'NOT DETECTED', 'not-detected', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(34, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'ERROR', 'error', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8),
(35, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'INVALID', 'invalid', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9),
(36, 'tb', 'TB_MICROSCOPY_FINAL', NULL, NULL, 'NEGATIVE', 'negative', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'tb', 'TB_MICROSCOPY_FINAL', NULL, NULL, 'SCANTY', 'scanty', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'tb', 'TB_MICROSCOPY_FINAL', NULL, NULL, '1+', '1+', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'tb', 'TB_MICROSCOPY_FINAL', NULL, NULL, '2+', '2+', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'tb', 'TB_MICROSCOPY_FINAL', NULL, NULL, '3+', '3+', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'LOW', 'low', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5),
(42, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'VERY LOW', 'very-low', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4),
(43, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'MEDIUM', 'medium', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6),
(45, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'TRACE', 'trace', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3),
(46, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'HIGH', 'high', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 7),
(47, 'tb', 'TB_MOLECULAR_FINAL', NULL, NULL, 'NO RESULT', 'no-result', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10),
(48, 'dts', 'DTS_FINAL', NULL, NULL, 'NONREACTIVE', 'NR', 'all', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `r_recency_assay`
--

DROP TABLE IF EXISTS `r_recency_assay`;
CREATE TABLE IF NOT EXISTS `r_recency_assay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `r_response_not_tested_reasons`
--

DROP TABLE IF EXISTS `r_response_not_tested_reasons`;
CREATE TABLE IF NOT EXISTS `r_response_not_tested_reasons` (
  `ntr_id` int NOT NULL AUTO_INCREMENT,
  `ntr_reason` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ntr_test_type` json DEFAULT NULL,
  `collect_panel_receipt_date` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `reason_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ntr_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`ntr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_response_not_tested_reasons`
--

INSERT INTO `r_response_not_tested_reasons` (`ntr_id`, `ntr_reason`, `ntr_test_type`, `collect_panel_receipt_date`, `reason_code`, `ntr_status`) VALUES
(1, 'No reagents for testing of PT panel', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'A', 'active'),
(2, 'No lab personal for testing of PT panel', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'B', 'active'),
(3, ' Instrument down', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'C', 'active'),
(4, 'Laboratory facility under renovation', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'D', 'active'),
(5, 'Laboratory facility no longer perform testing', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'E', 'active'),
(6, 'The results were invalid for the entire run', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'F', 'active'),
(7, 'The PT panel testing failed during sample processing', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'G', 'active'),
(8, 'The PT panel shipment was lost/damage', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'H', 'active'),
(9, 'Not received PT panel shipment due to country custom clearance issue', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'I', 'active'),
(10, 'Not received PT panel shipment due to incorrect contact info on the shipment package', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'J', 'active'),
(11, 'Issue with Sample', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'K', 'active'),
(12, 'Machine not working', '[\"vl\", \"eid\", \"dts\", \"covid19\", \"recency\"]', 'no', 'L', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_response_vl_not_tested_reason`
--

DROP TABLE IF EXISTS `r_response_vl_not_tested_reason`;
CREATE TABLE IF NOT EXISTS `r_response_vl_not_tested_reason` (
  `vl_not_tested_reason_id` int NOT NULL AUTO_INCREMENT,
  `vl_not_tested_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `collect_panel_receipt_date` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'yes',
  `status` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`vl_not_tested_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_response_vl_not_tested_reason`
--

INSERT INTO `r_response_vl_not_tested_reason` (`vl_not_tested_reason_id`, `vl_not_tested_reason`, `collect_panel_receipt_date`, `status`) VALUES
(1, 'No reagents for testing of PT panel', 'yes', 'active'),
(2, 'No lab personal for testing of PT panel', 'yes', 'active'),
(3, ' Instrument down', 'yes', 'active'),
(4, 'Laboratory facility under renovation', 'yes', 'active'),
(5, 'Laboratory facility no longer perform testing', 'yes', 'active'),
(6, 'The results were invalid for the entire run', 'yes', 'active'),
(7, 'The PT panel testing failed during sample processing', 'yes', 'active'),
(8, 'The PT panel shipment was lost/damage', 'no', 'active'),
(9, 'Not received PT panel shipment due to country custom clearance issue', 'no', 'active'),
(10, 'Not received PT panel shipment due to incorrect contact info on the shipment package', 'no', 'active'),
(11, 'Other (please explain)', 'no', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_results`
--

DROP TABLE IF EXISTS `r_results`;
CREATE TABLE IF NOT EXISTS `r_results` (
  `result_id` int NOT NULL AUTO_INCREMENT,
  `result_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_results`
--

INSERT INTO `r_results` (`result_id`, `result_name`) VALUES
(1, 'Pass'),
(2, 'Fail'),
(3, 'Excluded'),
(4, 'Not Evaluated');

-- --------------------------------------------------------

--
-- Table structure for table `r_site_type`
--

DROP TABLE IF EXISTS `r_site_type`;
CREATE TABLE IF NOT EXISTS `r_site_type` (
  `r_stid` int NOT NULL AUTO_INCREMENT,
  `site_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`r_stid`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_site_type`
--

INSERT INTO `r_site_type` (`r_stid`, `site_type`) VALUES
(1, 'VCT'),
(2, 'Mobile VCT'),
(3, 'TB Center'),
(4, 'Antenatal Clinic (PMTCT)'),
(5, 'Outpatient Clinic'),
(6, 'Hospital'),
(7, 'Laboratory'),
(8, 'District'),
(9, 'Province'),
(10, 'Region'),
(11, 'Department'),
(12, 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `r_tb_assay`
--

DROP TABLE IF EXISTS `r_tb_assay`;
CREATE TABLE IF NOT EXISTS `r_tb_assay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(255) NOT NULL,
  `assay_type` varchar(255) NOT NULL DEFAULT 'specific',
  `drug_resistance_test` varchar(255) NOT NULL DEFAULT 'yes',
  `status` varchar(256) DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_tb_assay`
--

INSERT INTO `r_tb_assay` (`id`, `name`, `short_name`, `assay_type`, `drug_resistance_test`, `status`) VALUES
(1, 'Xpert MTB RIF', 'xpert-mtb-rif', 'specific', 'yes', 'active'),
(2, 'Xpert MTB RIF Ultra', 'xpert-mtb-rif-ultra', 'specific', 'yes', 'active'),
(3, 'Molbio Truenat TB', 'molbio-truenat-tb', 'specific', 'yes', 'active'),
(4, 'Molbio Truenat Plus', 'molbio-truenat-plus', 'specific', 'yes', 'active'),
(5, 'Ref-Molbio TB-RIF Dx', 'ref-molbio-tb-rif-dx', 'specific', 'yes', 'active'),
(6, 'Other Assay', 'other', 'generic', 'yes', 'active'),
(7, 'Microscopy', 'microscopy', 'specific', 'yes', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_testkitnames`
--

DROP TABLE IF EXISTS `r_testkitnames`;
CREATE TABLE IF NOT EXISTS `r_testkitnames` (
  `TestKitName_ID` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `TestKit_Name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `TestKit_Name_Short` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `TestKit_Comments` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `Updated_On` datetime DEFAULT NULL,
  `Updated_By` int DEFAULT NULL,
  `Installation_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `TestKit_Manufacturer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `Created_On` datetime DEFAULT NULL,
  `Created_By` int DEFAULT NULL,
  `Approval` int DEFAULT '1' COMMENT '1 = Approved , 0 not approved.',
  `TestKit_ApprovalAgency` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'USAID, FDA, LOCAL',
  `source_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `CountryAdapted` int DEFAULT NULL COMMENT '0= Not allowed in the country 1 = approved in country ',
  `attributes` json DEFAULT NULL,
  `testkit_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`TestKitName_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_testkitnames`
--

INSERT INTO `r_testkitnames` (`TestKitName_ID`, `TestKit_Name`, `TestKit_Name_Short`, `TestKit_Comments`, `Updated_On`, `Updated_By`, `Installation_id`, `TestKit_Manufacturer`, `Created_On`, `Created_By`, `Approval`, `TestKit_ApprovalAgency`, `source_reference`, `CountryAdapted`, `attributes`, `testkit_status`) VALUES
('tk1G3JtOqAxJxts', 'DETERMINE', NULL, NULL, NULL, NULL, NULL, NULL, '2017-01-10 03:13:49', NULL, 0, NULL, NULL, NULL, NULL, NULL),
('tk3umfy04vTVydu', 'NOMDOS1', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-15 23:34:26', NULL, 0, NULL, NULL, 1, NULL, NULL),
('tk50f41f66a2388', 'ACON HIV 1/2/0 Tri-line', 'ACON HIV 1/2/0 Tri', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a238f', 'Alere Determine HIV-1/2', 'Alere Determine HIV-1/2', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/Abbott Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2399', 'Aware HIV-1/2 BSP', 'Aware HIV-1/2 BSP', NULL, '2013-01-14 10:09:21', 0, '0', ' Calypte Biomedical ', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a239e', 'Bionor HIV-1&2', 'Bionor HIV-1&2', NULL, '2013-01-14 10:09:21', 0, '0', ' Bionor A/S ', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23a7', 'Calypte Aware HIV-1/2 OMT ', 'Calypte Aware HIV-', NULL, '2013-01-14 10:09:21', 0, '0', ' Calypte Biomedical Corp.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23b1', 'Care Start HIV 1-2-O', 'Care Start HIV 1-2', NULL, '2013-01-14 10:09:21', 0, '0', ' Access Bio, Inc.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23b5', 'ClearviewÃ‚Â® COMPLETE HIV1/2 (formerly SURE) CHECKÃ‚Â® HIV1/2)', 'ClearviewÃ‚Â® COMPLETE HIV1/2 Non - US Labeling', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23ba', 'ClearviewÃ‚Â® COMPLETE HIV1/2 - US labeling** (formerly SURE CHECKÃ‚Â® HIV1/2)', 'ClearviewÃ‚Â® COMPLETE HIV1/2 - US labeling ', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23bf', 'Clearview  HIV 1/2 STAT-PAK Assay', 'Clearview  HIV 1/2', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23c4', 'Combaids RS Advantage', 'Combaids RS Advant', NULL, '2013-01-14 10:09:21', 0, '0', ' Span Diagnostics Ltd.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23c8', 'DPP HIV 1/2 Screen ', 'DPP HIV 1/2 Screen', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23cd', 'DPP HIV 1 / 2 Screen Assay  Oral Fluid, Whole Blood,Serum & Plasma', 'DPP HIV 1 / 2 Scre', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23d1', 'Double Check HIV 1&2', 'Double Check HIV 1', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/ Orgenics, Ltd', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23d6', 'Double Check Gold HIV1&2', 'Double Check Gold ', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/ Orgenics, Ltd', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23db', 'EZ-TRUST Rapid Anti-HIV (1&2) Test', 'EZ-TRUST Rapid Ant', NULL, '2013-01-14 10:09:21', 0, '0', ' CS Innovation', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23df', 'First Response HIV 1-2.0', 'First Response HIV', NULL, '2013-01-14 10:09:21', 0, '0', ' Premier Medical Corporation', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23e3', 'Genie Fast HIV 1/2 ', 'Genie Fast HIV 1/2', NULL, '2013-01-14 10:09:21', 0, '0', ' Bio-Rad Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23e8', 'HIV 1/2 Gold Rapid Screen Test ', 'HIV 1/2 Gold Rapid', NULL, '2013-01-14 10:09:21', 0, '0', ' Medinostics IntÃ¢â‚¬â„¢l', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23ed', 'HIV 1/2 Rapid Test Kit', 'HIV 1/2 Rapid Test', NULL, '2013-01-14 10:09:21', 0, '0', ' Medinostics IntÃ¢â‚¬â„¢l', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23f1', 'HIV 1/ 2 STAT-PAK Assay', 'HIV 1/ 2 STAT-PAK ', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23f6', 'HIV 1/2 STAT-PAK Dipstick Assay', 'HIV 1/2 STAT-PAK D', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23fa', 'HIV(1+2) Rapid Test Strip', 'HIV(1+2) Rapid Tes', NULL, '2013-01-14 10:09:21', 0, '0', ' Shanghai Kehua Bio-engineering Co., Ltd (KHB)', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a23ff', 'HIVSav 1&2 Rapid SeroTest', 'HIVSav 1&2 Rapid S', NULL, '2013-01-14 10:09:21', 0, '0', ' Savyvon Diagnostics Ltd.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2404', 'iCARE Rapid Anti-HIV (1&2) ', 'iCARE Rapid Anti-H', NULL, '2013-01-14 10:09:21', 0, '0', ' JAL Innovation', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2408', 'ImmunoComb HIV 1&2', 'ImmunoComb HIV 1&2', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/ Orgenics, Ltd', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a240d', 'InstantCHEK HIV1+2', 'InstantCHEK HIV1+2', NULL, '2013-01-14 10:09:21', 0, '0', ' EY Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2411', 'KSII  HIV 1/2 Rapid Diagnostic Test Kit ', 'KSII  HIV 1/2 Rapi', NULL, '2013-01-14 10:09:21', 0, '0', ' K. Shorehill Int\'l, Inc.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2415', 'MPI Diagnostics Anti-HIV (1&2) Test ', 'MPI Diagnostics An', NULL, '2013-01-14 10:09:21', 0, '0', ' MPI Diagnostics', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a241a', 'INSTI HIV Antibody', 'INSTI HIV Antibody', NULL, '2013-01-14 10:09:21', 0, '0', ' Biolytical Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a241f', 'Multispot HIV-1/HIV-2', 'Multispot HIV-1/HI', NULL, '2013-01-14 10:09:21', 0, '0', ' Bio-Rad laboratories', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2423', 'OraQuick ADVANCE Rapid HIV-1/2', 'OraQuick ADVANCE R', NULL, '2013-01-14 10:09:21', 0, '0', ' OraSure Technologies', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2428', 'OraQuick HIV-1/2 Rapid Antibody Test', 'OraQuick HIV-1/2 R', NULL, '2013-01-14 10:09:21', 0, '0', ' OraSure Technologies', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a242c', 'RAPID 1-2-3 HEMA Dipstick', 'RAPID 1-2-3 HEMA D', NULL, '2013-01-14 10:09:21', 0, '0', ' Hema Diagnostics Systems', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2430', 'RAPID 1-2-3 HEMA EZ ', 'RAPID 1-2-3 HEMA E', NULL, '2013-01-14 10:09:21', 0, '0', ' Hema Diagnostics Systems', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2435', 'RAPID 1-2-3 HEMA EXPRESS', 'RAPID 1-2-3 HEMA E', NULL, '2013-01-14 10:09:21', 0, '0', ' Hema Diagnostics Systems', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2439', 'Reveal Rapid HIV Test', 'Reveal Rapid HIV T', NULL, '2013-01-14 10:09:21', 0, '0', ' MedMira', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a243e', 'Reveal G3 Rapid HIV-1 Antibody Test', 'Reveal G3 Rapid HI', NULL, '2013-01-14 10:09:21', 0, '0', ' MedMira', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2443', 'SD Bioline HIV 1/2 3.0', 'SD Bioline', '', '2013-01-14 10:09:21', 0, '0', 'Abbott', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a2447', 'Uni-Gold HIV - USAID', 'Uni-Gold HIV -USAID', NULL, '2013-01-14 10:09:21', 0, '0', ' Trinity Biotech', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk50f41f66a244b', 'Uni-Gold Recombigen HIV - FDA', 'Uni-Gold Recombige - FDA', NULL, '2013-01-14 10:09:21', 0, '0', ' Trinity Biotech', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1, NULL, NULL),
('tk5136b425387a4', 'First Response HIV 1+2/Syphilis Combo', 'Frist Response HIV 1+2/Syphilis Combo', 'Comments', NULL, NULL, 'LOG4fabc8babf6eb', 'Premier Medical Cooporation', '2013-03-06 04:12:37', 0, 1, 'WHO and National', 'Yes', 1, NULL, NULL),
('tk5137b608ac1d9', 'Hexagon HIVI II', 'Hexagon', 'rwer', NULL, NULL, 'LOG4fabc8babf6eb', 'rewr', '2013-03-06 22:32:56', 0, 0, 'NA', 'Yes', 1, NULL, NULL),
('tk51435b69f3b7e', 'gdfg', 'gfdg', 'gfdg', NULL, NULL, '5132ceba8fafa', 'gfdg', '2013-03-15 18:33:29', 0, 1, 'NA', 'NA', 1, NULL, NULL),
('tk514b50a81832c', 'Test Kit New ', 'New ', 'dasd', NULL, NULL, '5132ceba8fafa', 'dsad', '2013-03-21 19:25:44', 0, 1, 'Other', 'Yes', 1, NULL, NULL),
('tk5IedjgZ4X1Bbw', 'ADVANCED QUALITY', NULL, NULL, NULL, NULL, NULL, NULL, '2015-12-16 08:29:33', NULL, 0, NULL, NULL, NULL, NULL, NULL),
('tkAXhaWjcYQRLDK', 'ALERE DETERMINE', NULL, NULL, NULL, NULL, NULL, NULL, '2017-01-10 07:00:16', NULL, 0, NULL, NULL, NULL, NULL, NULL),
('tkfdutAep5J1oio', 'WOND FOREPID ONE STEP TEST', NULL, NULL, NULL, NULL, NULL, NULL, '2018-07-27 07:10:56', NULL, 0, NULL, NULL, NULL, NULL, NULL),
('tkftR0U24gQULr5', 'RAPID TEST', NULL, NULL, NULL, NULL, NULL, NULL, '2015-12-16 08:41:06', NULL, 0, NULL, NULL, NULL, NULL, NULL),
('tkKwqRgnnMO4wkb', 'Wantai HIV Antibody Rapid Test (Colloidal Gold)', 'Colloidal Gold', '', NULL, NULL, NULL, '', '2025-04-29 14:24:50', NULL, 1, 'WHO', '', 1, '{\"additional_info\": \"\", \"additional_info_label\": \"\", \"additional_info_mandatory\": \"\"}', NULL),
('tkrlglmlFO8n27E', 'DETERMINE ALERE', NULL, NULL, NULL, NULL, NULL, NULL, '2018-08-15 06:40:45', NULL, 0, NULL, NULL, NULL, NULL, NULL),
('tkRqEZsgulUtwC6', 'DETERMINE HIV -1/2', NULL, NULL, NULL, NULL, NULL, NULL, '2017-01-10 05:07:55', NULL, 0, NULL, NULL, NULL, NULL, NULL),
('tkYH06BNjJRZXXl', 'HIVC0-7316', NULL, NULL, NULL, NULL, NULL, NULL, '2019-07-26 14:27:42', NULL, 0, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `r_test_type_covid19`
--

DROP TABLE IF EXISTS `r_test_type_covid19`;
CREATE TABLE IF NOT EXISTS `r_test_type_covid19` (
  `test_type_id` int NOT NULL AUTO_INCREMENT,
  `scheme_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `test_type_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `test_type_short_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `test_type_comments` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `installation_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `test_type_manufacturer` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `approval` int DEFAULT '1' COMMENT '1 = Approved , 0 not approved.',
  `test_type_approval_agency` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'USAID, FDA, LOCAL',
  `source_reference` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `country_adapted` int DEFAULT NULL COMMENT '0= Not allowed in the country 1 = approved in country ',
  `test_type_1` int NOT NULL DEFAULT '0',
  `test_type_2` int NOT NULL DEFAULT '0',
  `test_type_3` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`test_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_test_type_covid19`
--

INSERT INTO `r_test_type_covid19` (`test_type_id`, `scheme_type`, `test_type_name`, `test_type_short_name`, `test_type_comments`, `updated_on`, `updated_by`, `installation_id`, `test_type_manufacturer`, `created_on`, `created_by`, `approval`, `test_type_approval_agency`, `source_reference`, `country_adapted`, `test_type_1`, `test_type_2`, `test_type_3`) VALUES
(1, 'covid19', '54 gene', '54 gene', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(2, 'covid19', 'Abbott M2000', 'Abbott M2000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(3, 'covid19', 'ABI 7300', 'ABI 7300', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(4, 'covid19', 'ABI 7500 fast', 'ABI 7500 fast', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(5, 'covid19', 'ABI StepOne/ StepOnePlus', 'ABI StepOne/ StepOnePlus', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(6, 'covid19', 'ABI7500', 'ABI7500', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(7, 'covid19', 'Agilent MxMP3000', 'Agilent MxMP3000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(8, 'covid19', 'AriaMx', 'AriaMx', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(9, 'covid19', 'Biorad CFX 96', 'Biorad CFX 96', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(10, 'covid19', 'BioRad iQ5', 'BioRad iQ5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(11, 'covid19', 'BMS - MIC', 'BMS - MIC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(12, 'covid19', 'Cobas 6800', 'Cobas 6800', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(13, 'covid19', 'Cobas 8800', 'Cobas 8800', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(14, 'covid19', 'GeneXpert', 'GeneXpert', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(15, 'covid19', 'Liferiver - MIC', 'Liferiver - MIC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(16, 'covid19', 'LightCycler 96', 'LightCycler 96', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(17, 'covid19', 'LightCycler4800', 'LightCycler4800', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(18, 'covid19', 'LightCycler96', 'LightCycler96', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(19, 'covid19', 'Quant Studio12K Flex', 'Quant Studio12K Flex', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(20, 'covid19', 'Quant Studio5', 'Quant Studio5', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(21, 'covid19', 'Quant Studio7 pro', 'Quant Studio7 pro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(22, 'covid19', 'Ridacycler - MIC', 'Ridacycler - MIC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(23, 'covid19', 'Roche LC 2.0', 'Roche LC 2.0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(24, 'covid19', 'Roche LC 480', 'Roche LC 480', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(25, 'covid19', 'Rotor Gene RG-3000', 'Rotor Gene RG-3000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(26, 'covid19', 'Rotorgene 6000', 'Rotorgene 6000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(27, 'covid19', 'Rotorgene Q', 'Rotorgene Q', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(28, 'covid19', 'Stratagene MX3005P', 'Stratagene MX3005P', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'WHO', NULL, 1, 1, 0, 0),
(29, 'covid19', 'GENTIER 48E', NULL, NULL, NULL, NULL, NULL, NULL, '2021-11-17 11:32:54', NULL, 0, NULL, NULL, NULL, 0, 0, 0),
(30, 'covid19', 'GENTIER 48E/48R', NULL, NULL, NULL, NULL, NULL, NULL, '2021-11-17 11:19:23', NULL, 0, NULL, NULL, NULL, 0, 0, 0),
(31, 'covid19', 'Genefinder COVID-19 Plus Real AMP Kit', NULL, NULL, NULL, NULL, NULL, NULL, '2021-11-19 15:42:35', NULL, 0, NULL, NULL, NULL, 0, 0, 0),
(32, 'covid19', 'MYGO PRO', NULL, NULL, NULL, NULL, NULL, NULL, '2021-11-11 16:58:49', NULL, 0, NULL, NULL, NULL, 0, 0, 0),
(33, 'covid19', 'Sansure Biotech', NULL, NULL, NULL, NULL, NULL, NULL, '2021-11-03 12:29:37', NULL, 0, NULL, NULL, NULL, 0, 0, 0),
(34, 'covid19', 'BIOER LINEGENE 9600', NULL, NULL, NULL, NULL, NULL, NULL, '2021-12-05 10:07:52', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_vl_assay`
--

DROP TABLE IF EXISTS `r_vl_assay`;
CREATE TABLE IF NOT EXISTS `r_vl_assay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `short_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `allow_invalid` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `r_vl_assay`
--

INSERT INTO `r_vl_assay` (`id`, `name`, `short_name`, `allow_invalid`, `status`) VALUES
(1, 'Abbott - RealTime ', '', 'no', 'active'),
(2, 'Roche - COBAS Ampliprep/TaqMan', '', 'no', 'active'),
(3, 'Biocentric - Generic HIV Charge Virale', '', 'no', 'active'),
(4, 'Biomerieux - NucliSENS', '', 'no', 'active'),
(5, 'Roche - Amplicor', '', 'no', 'active'),
(6, 'Other', 'Other', 'no', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_jobs`
--

DROP TABLE IF EXISTS `scheduled_jobs`;
CREATE TABLE IF NOT EXISTS `scheduled_jobs` (
  `job_id` int NOT NULL AUTO_INCREMENT,
  `job` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `requested_on` datetime DEFAULT NULL,
  `requested_by` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `completed_on` datetime DEFAULT NULL,
  `status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `initated_by` int DEFAULT NULL,
  PRIMARY KEY (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheme_config`
--

DROP TABLE IF EXISTS `scheme_config`;
CREATE TABLE IF NOT EXISTS `scheme_config` (
  `scheme_config_name` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `scheme_config_value` json DEFAULT NULL,
  PRIMARY KEY (`scheme_config_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `scheme_config`
--

INSERT INTO `scheme_config` (`scheme_config_name`, `scheme_config_value`) VALUES
('covid19', '{\"passPercentage\": \"90\", \"allowedAlgorithms\": \"serial,parallel,myanmarNationalDtsAlgo\", \"documentationScore\": \"\", \"sampleRehydrateDays\": \"1\", \"dtsEnforceAlgorithmCheck\": \"yes\", \"covid19MaximumTestAllowed\": \"2\", \"covid19EnforceAlgorithmCheck\": \"\"}'),
('dts', '{\"panelScore\": \"90\", \"rtriEnabled\": \"no\", \"dtsSchemeType\": \"updated-3-tests\", \"passPercentage\": \"95\", \"allowRepeatTests\": \" yes \", \"dtsAlgorithmScore\": \"0\", \"documentationScore\": \"10\", \"disableOtherTestkit\": \"no\", \"sampleRehydrateDays\": \"1\", \"collectAdditionalTestkits\": \"no\", \"displaySampleConditionFields\": \"no\"}'),
('recency', '{\"panelScore\": \"90\", \"passPercentage\": \"95\", \"documentationScore\": \"10\", \"sampleRehydrateDays\": \"1\"}'),
('tb', '{\"contactInfo\": \"&lt;h4&gt;&lt;b&gt;Contact Information&lt;/b&gt;&lt;/h4&gt;\\n&lt;p&gt;Molecular TB Proficiency Test Program&lt;/p&gt;\\n&lt;p&gt;&lt;a href=&quot;mailto:xtpt@cdc.gov&quot; target=&quot;_blank&quot;&gt;xtpt@cdc.gov&lt;/a&gt;&lt;/p&gt;\\n&lt;table class=&quot;table table-bordered&quot;&gt;\\n    &lt;tbody&gt;\\n        &lt;tr&gt;\\n            &lt;td&gt;\\n                &lt;u&gt;Kyle DeGruy&lt;/u&gt;&lt;br&gt;\\n                Program Coordinator, TB and Clinical Monitoring Team&lt;br&gt;\\n                International Laboratory Branch&lt;br&gt;\\n                Division of Global HIV and TB&lt;br&gt;\\n                Global Health Center&lt;br&gt;\\n                US Centers for Disease Control and Prevention&lt;br&gt;\\n                &lt;a href=&quot;mailto:gsz4@cdc.gov&quot; target=&quot;_blank&quot;&gt;gsz4@cdc.gov&lt;/a&gt;\\n            &lt;/td&gt;\\n            &lt;td&gt;\\n                &lt;u&gt;Subhadra Nandakumar, PhD&lt;/u&gt;&lt;br&gt;\\n                Acting Team Lead, TB and Clinical Monitoring International Laboratory Branch&lt;br&gt;\\n                International Laboratory Branch&lt;br&gt;\\n                Division of Global HIV and TB&lt;br&gt;\\n                Global Health Center&lt;br&gt;\\n                US Centers for Disease Control and Prevention&lt;br&gt;\\n                &lt;a href=&quot;mailto:ifd0@cdc.gov&quot; target=&quot;_blank&quot;&gt;ifd0@cdc.gov&lt;/a&gt;\\n            &lt;/td&gt;\\n        &lt;/tr&gt;\\n    &lt;/tbody&gt;\\n&lt;/table&gt;\", \"passPercentage\": \"80\"}'),
('vl', '{\"passPercentage\": \"80\", \"documentationScore\": \"80\", \"contentForIndividualVlReports\": \"<p><br></p>\"}');

-- --------------------------------------------------------

--
-- Table structure for table `scheme_list`
--

DROP TABLE IF EXISTS `scheme_list`;
CREATE TABLE IF NOT EXISTS `scheme_list` (
  `scheme_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `scheme_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `response_table` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reference_result_table` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_user_configured` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `user_test_config` json DEFAULT NULL,
  `attribute_list` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`scheme_id`),
  UNIQUE KEY `scheme_name` (`scheme_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `scheme_list`
--

INSERT INTO `scheme_list` (`scheme_id`, `scheme_name`, `response_table`, `reference_result_table`, `is_user_configured`, `user_test_config`, `attribute_list`, `status`) VALUES
('covid19', 'SARS-CoV-2', 'response_result_covid19', 'reference_result_covid19', 'no', NULL, NULL, 'active'),
('dbs', 'Dried Blood Spot - HIV Serology', 'response_result_dbs', 'reference_result_dbs', 'no', NULL, NULL, 'active'),
('dts', 'Dried Tube Specimen - HIV Serology', 'response_result_dts', 'reference_result_dts', 'no', NULL, NULL, 'active'),
('eid', 'Dried Blood Spot - Early Infant Diagnosis', 'response_result_eid', 'reference_result_eid', 'no', NULL, NULL, 'active'),
('recency', 'Rapid Test for Recent Infection (RTRI)', 'response_result_recency', 'reference_result_recency', 'no', NULL, NULL, 'active'),
('tb', 'Dried Tube Specimen - Tuberculosis', 'response_result_tb', 'reference_result_tb', 'no', NULL, NULL, 'active'),
('vl', 'Dried Tube Specimen - HIV Viral Load', 'response_result_vl', 'reference_result_vl', 'no', NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `scheme_testkit_map`
--

DROP TABLE IF EXISTS `scheme_testkit_map`;
CREATE TABLE IF NOT EXISTS `scheme_testkit_map` (
  `scheme_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `testkit_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `testkit_1` int NOT NULL DEFAULT '0',
  `testkit_2` int NOT NULL DEFAULT '0',
  `testkit_3` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`scheme_type`,`testkit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipment`
--

DROP TABLE IF EXISTS `shipment`;
CREATE TABLE IF NOT EXISTS `shipment` (
  `shipment_id` int NOT NULL AUTO_INCREMENT,
  `shipment_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `scheme_type` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `shipment_date` date DEFAULT NULL,
  `lastdate_response` date DEFAULT NULL,
  `distribution_id` int NOT NULL,
  `number_of_samples` int DEFAULT NULL,
  `number_of_controls` int NOT NULL,
  `response_switch` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'off',
  `allow_editing_response` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'yes',
  `max_score` int DEFAULT NULL,
  `average_score` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT '0',
  `shipment_attributes` json DEFAULT NULL,
  `shipment_comment` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `issuing_authority` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pt_co_ordinator_name` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `pt_co_ordinator_email` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pt_co_ordinator_phone` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by_admin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on_admin` datetime DEFAULT NULL,
  `updated_by_admin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on_admin` datetime DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending',
  `evaluated_at` datetime DEFAULT NULL,
  `reports_generated_at` datetime DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `report_in_queue` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `corrective_action_file` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `tb_form_generated` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'no',
  `collect_feedback` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `feedback_expiry_date` date DEFAULT NULL,
  `previous_status` varchar(256) DEFAULT NULL,
  `processing_started_at` datetime DEFAULT NULL,
  `last_heartbeat` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_id`),
  KEY `scheme_type` (`scheme_type`),
  KEY `distribution_id` (`distribution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipment_participant_map`
--

DROP TABLE IF EXISTS `shipment_participant_map`;
CREATE TABLE IF NOT EXISTS `shipment_participant_map` (
  `map_id` int NOT NULL AUTO_INCREMENT,
  `shipment_id` int NOT NULL,
  `participant_id` int NOT NULL,
  `lab_director_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lab_director_email` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `contact_person_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `contact_person_email` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `contact_person_telephone` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `evaluation_status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Shipment Status					\nUse this to flag - 					\nABCDEFG					',
  `shipment_score` decimal(5,2) DEFAULT NULL,
  `documentation_score` decimal(5,2) DEFAULT '0.00',
  `shipment_test_date` date DEFAULT NULL,
  `number_of_tests` int DEFAULT NULL,
  `specimen_volume` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_pt_test_not_performed` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `vl_not_tested_reason` int DEFAULT NULL,
  `received_pt_panel` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pt_test_not_performed_comments` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `pt_support_comments` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `shipment_receipt_date` date DEFAULT NULL,
  `shipment_test_report_date` datetime DEFAULT NULL,
  `is_response_late` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `participant_supervisor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `supervisor_approval` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `final_result` int DEFAULT '0',
  `failure_reason` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `evaluation_comment` int DEFAULT '0',
  `optional_eval_comment` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `is_followup` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'no',
  `is_excluded` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `user_comment` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `custom_field_1` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `custom_field_2` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `created_on_admin` datetime DEFAULT NULL,
  `updated_on_admin` datetime DEFAULT NULL,
  `updated_by_admin` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on_user` datetime DEFAULT NULL,
  `updated_by_user` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_by_admin` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on_user` datetime DEFAULT NULL,
  `report_generated` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_new_shipment_mailed_on` datetime DEFAULT NULL,
  `new_shipment_mail_count` int DEFAULT '0',
  `last_not_participated_mailed_on` datetime DEFAULT NULL,
  `last_not_participated_mail_count` int NOT NULL DEFAULT '0',
  `qc_done` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `qc_date` date DEFAULT NULL,
  `qc_done_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `qc_created_on` datetime DEFAULT NULL,
  `mode_id` int DEFAULT NULL,
  `manual_override` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `show_announcement` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'yes',
  `synced` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'no',
  `synced_on` datetime DEFAULT NULL,
  `mode_of_response` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'web,app,api',
  `user_client_info` json DEFAULT NULL,
  `response_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'noresponse',
  `report_download_metadata` json DEFAULT NULL,
  `individual_report_downloaded_on` datetime DEFAULT NULL,
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `shipment_id_2` (`shipment_id`,`participant_id`),
  KEY `shipment_id` (`shipment_id`),
  KEY `participant_id` (`participant_id`),
  KEY `idx_spm_ship_resp_excl_map` (`shipment_id`,`response_status`,`is_excluded`,`map_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Shipment for DTS Samples';

-- --------------------------------------------------------

--
-- Table structure for table `system_admin`
--

DROP TABLE IF EXISTS `system_admin`;
CREATE TABLE IF NOT EXISTS `system_admin` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `primary_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `secondary_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `force_password_reset` int DEFAULT NULL,
  `scheme` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'inactive',
  `privileges` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `created_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

DROP TABLE IF EXISTS `system_config`;
CREATE TABLE IF NOT EXISTS `system_config` (
  `config` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `display_name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`config`, `value`, `display_name`) VALUES
('api_version', '2.0', 'API Version'),
('app_version', '7.3.5', 'App Version');

-- --------------------------------------------------------

--
-- Table structure for table `system_metadata`
--

DROP TABLE IF EXISTS `system_metadata`;
CREATE TABLE IF NOT EXISTS `system_metadata` (
  `metadata_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `metadata_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  PRIMARY KEY (`metadata_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `system_metadata`
--

INSERT INTO `system_metadata` (`metadata_id`, `metadata_value`) VALUES
('instance-id', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tb_instruments`
--

DROP TABLE IF EXISTS `tb_instruments`;
CREATE TABLE IF NOT EXISTS `tb_instruments` (
  `instrument_id` int NOT NULL AUTO_INCREMENT,
  `map_id` int DEFAULT NULL,
  `participant_id` int NOT NULL,
  `instrument_serial` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `instrument_installed_on` date DEFAULT NULL,
  `instrument_last_calibrated_on` date DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`instrument_id`),
  KEY `participant_id` (`participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_mail`
--

DROP TABLE IF EXISTS `temp_mail`;
CREATE TABLE IF NOT EXISTS `temp_mail` (
  `temp_id` int NOT NULL AUTO_INCREMENT,
  `message` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `from_mail` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `reply_to` varchar(128) DEFAULT NULL,
  `to_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `bcc` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `cc` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `subject` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `from_full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `queued_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `failure_reason` text,
  `failure_type` varchar(64) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`temp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `track_api_requests`
--

DROP TABLE IF EXISTS `track_api_requests`;
CREATE TABLE IF NOT EXISTS `track_api_requests` (
  `api_track_id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `requested_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `requested_on` datetime DEFAULT NULL,
  `number_of_records` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `request_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `test_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `api_url` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `api_params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `request_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `response_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `data_format` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`api_track_id`),
  KEY `requested_on` (`requested_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_login_history`
--

DROP TABLE IF EXISTS `user_login_history`;
CREATE TABLE IF NOT EXISTS `user_login_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `login_context` enum('participant','admin','','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'participant',
  `user_id` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `login_id` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `login_attempted_datetime` datetime DEFAULT NULL,
  `login_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `ip_address` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `browser` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `operating_system` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `login_status_attempted_datetime_idx` (`login_status`,`login_attempted_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `covid19_identified_genes`
--
ALTER TABLE `covid19_identified_genes`
  ADD CONSTRAINT `covid19_identified_genes_ibfk_1` FOREIGN KEY (`map_id`) REFERENCES `shipment_participant_map` (`map_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `covid19_identified_genes_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `covid19_identified_genes_ibfk_3` FOREIGN KEY (`gene_id`) REFERENCES `r_covid19_gene_types` (`gene_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `covid19_identified_genes_ibfk_4` FOREIGN KEY (`gene_id`) REFERENCES `r_covid19_gene_types` (`gene_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `participant_feedback_answer`
--
ALTER TABLE `participant_feedback_answer`
  ADD CONSTRAINT `participant_feedback_answer_ibfk_1` FOREIGN KEY (`map_id`) REFERENCES `shipment_participant_map` (`map_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `participant_feedback_answer_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `participant_feedback_answer_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `r_participant_feedback_form_question_map` (`question_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `participant_feedback_answer_ibfk_4` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `participant_feedback_answer_ibfk_5` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `participant_testkit_map`
--
ALTER TABLE `participant_testkit_map`
  ADD CONSTRAINT `participant_testkit_map_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `participant_testkit_map_ibfk_2` FOREIGN KEY (`testkit_id`) REFERENCES `r_testkitnames` (`TestKitName_ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `participant_testkit_map_ibfk_3` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `ptcc_countries_map`
--
ALTER TABLE `ptcc_countries_map`
  ADD CONSTRAINT `ptcc_countries_map_ibfk_1` FOREIGN KEY (`ptcc_id`) REFERENCES `data_manager` (`dm_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `queue_report_generation`
--
ALTER TABLE `queue_report_generation`
  ADD CONSTRAINT `queue_report_generation_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `reference_result_covid19`
--
ALTER TABLE `reference_result_covid19`
  ADD CONSTRAINT `reference_result_covid19_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`);

--
-- Constraints for table `reference_result_dts`
--
ALTER TABLE `reference_result_dts`
  ADD CONSTRAINT `reference_result_dts_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`);

--
-- Constraints for table `reference_result_eid`
--
ALTER TABLE `reference_result_eid`
  ADD CONSTRAINT `reference_result_eid_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`);

--
-- Constraints for table `reference_result_vl`
--
ALTER TABLE `reference_result_vl`
  ADD CONSTRAINT `reference_result_vl_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`);

--
-- Constraints for table `response_result_covid19`
--
ALTER TABLE `response_result_covid19`
  ADD CONSTRAINT `response_result_covid19_ibfk_1` FOREIGN KEY (`shipment_map_id`) REFERENCES `shipment_participant_map` (`map_id`);

--
-- Constraints for table `response_result_dts`
--
ALTER TABLE `response_result_dts`
  ADD CONSTRAINT `response_result_dts_ibfk_1` FOREIGN KEY (`shipment_map_id`) REFERENCES `shipment_participant_map` (`map_id`);

--
-- Constraints for table `response_result_eid`
--
ALTER TABLE `response_result_eid`
  ADD CONSTRAINT `response_result_eid_ibfk_1` FOREIGN KEY (`shipment_map_id`) REFERENCES `shipment_participant_map` (`map_id`);

--
-- Constraints for table `response_result_vl`
--
ALTER TABLE `response_result_vl`
  ADD CONSTRAINT `response_result_vl_ibfk_1` FOREIGN KEY (`shipment_map_id`) REFERENCES `shipment_participant_map` (`map_id`);

--
-- Constraints for table `r_participant_feedback_form`
--
ALTER TABLE `r_participant_feedback_form`
  ADD CONSTRAINT `r_participant_feedback_form_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `r_participant_feedback_form_files_map`
--
ALTER TABLE `r_participant_feedback_form_files_map`
  ADD CONSTRAINT `r_participant_feedback_form_files_map_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `r_participant_feedback_form_question_map`
--
ALTER TABLE `r_participant_feedback_form_question_map`
  ADD CONSTRAINT `r_participant_feedback_form_question_map_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`),
  ADD CONSTRAINT `r_participant_feedback_form_question_map_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `r_feedback_questions` (`question_id`),
  ADD CONSTRAINT `r_participant_feedback_form_question_map_ibfk_3` FOREIGN KEY (`rpff_id`) REFERENCES `r_participant_feedback_form` (`rpff_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `shipment`
--
ALTER TABLE `shipment`
  ADD CONSTRAINT `shipment_ibfk_2` FOREIGN KEY (`distribution_id`) REFERENCES `distributions` (`distribution_id`);

--
-- Constraints for table `shipment_participant_map`
--
ALTER TABLE `shipment_participant_map`
  ADD CONSTRAINT `shipment_participant_map_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`),
  ADD CONSTRAINT `shipment_participant_map_ibfk_2` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`);

--
-- Constraints for table `tb_instruments`
--
ALTER TABLE `tb_instruments`
  ADD CONSTRAINT `tb_instruments_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
