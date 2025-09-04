-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 04, 2025 at 08:07 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `project-app`
--

-- --------------------------------------------------------

--
-- Table structure for table `berkas`
--

CREATE TABLE `berkas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `kode_berkas` varchar(255) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`keywords`)),
  `dokumen` varchar(255) DEFAULT NULL,
  `dokumen_versions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dokumen_versions`)),
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `berkas`
--

INSERT INTO `berkas` (`id`, `customer_name`, `model`, `kode_berkas`, `nama`, `thumbnail`, `detail`, `keywords`, `dokumen`, `dokumen_versions`, `is_public`, `created_at`, `updated_at`) VALUES
(12, 'Suzuki', 'YTB', '65112-74T00', 'BRACKET, ROOF GARNISH', 'berkas/thumbnails/PT.-Indomatsumoto-Press-Dies-Industries-v1.png', 'Document SiMPiCA', '\"PPAP,SiMPiCA,SIS P,Spec List,PIDS,Sampel Product,Design Record,ECN Record,QCPC,Work Instruction,WI,Inspection Standard,PFMEA,Pokayoke System,Capability Process,SDS,System Check,Supply Chain\"', 'berkas/20250901_073210-bvRIQO-SiMPiCA.xlsx', NULL, 1, '2025-08-27 23:11:02', '2025-09-02 19:08:20'),
(15, 'Astemo', 'HAAA', 'H31A1-762-0B-02', 'LOWER SPRING SIT', NULL, 'Document PPAP', '\"PPAP\"', 'berkas/20250904_010418-7v3bHI-Drawing H31A1-762-0A-02.pdf', NULL, 1, '2025-09-03 18:04:18', '2025-09-03 18:46:40');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lampirans`
--

CREATE TABLE `lampirans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `berkas_id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(255) DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `file_versions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`file_versions`)),
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`keywords`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lampirans`
--

INSERT INTO `lampirans` (`id`, `berkas_id`, `nama`, `file`, `file_versions`, `keywords`, `created_at`, `updated_at`, `parent_id`) VALUES
(48, 12, '1. Material Performace Test Summarize', 'lampiran/1. 65112-74T00 Material Performance Test Summarize.xlsx', NULL, '[\"YTB\",\"BRACKET,ROOF GARNISH\",\"Distribution flow\",\"Part Inspection\"]', '2025-08-27 23:12:35', '2025-08-28 00:54:16', NULL),
(49, 12, '2. Test and Durability Summarize', 'lampiran/2. 65112-74T00 Test and Durability Summarize.xlsx', NULL, '[\"TEST & DURABILITY SUMMARIZE\",\"YTB\",\"BRACKET,ROOF GARNISH\",\"Durability\",\"Welding Performance\",\"Distribution flow\",\"Part Inspection\"]', '2025-08-27 23:13:54', '2025-08-28 00:54:28', NULL),
(50, 12, '3. Inspection Report', 'lampiran/3. 65112-74T00 Inspection Report.pdf', NULL, '[\"BRACKET ROOF GARNISH\",\"quality check sheet\",\"65112-74T00-000\"]', '2025-08-27 23:15:53', '2025-08-27 23:15:53', NULL),
(52, 12, '4. Surface Finishing Check', NULL, NULL, '[]', '2025-08-27 23:25:23', '2025-08-27 23:25:23', NULL),
(53, 12, '5. SIS P', 'lampiran/5. 65112-74T00 _SIS-P.pdf', NULL, '[\"YTB\",\"BRACKET ROOF GARNISH\",\"BOLT ROOF GARNISH\",\"JSC270CC\",\"09119-06315\",\"SIS\",\"MASTER CONTROLLED DOCUMENT\",\"General parts\"]', '2025-08-27 23:42:27', '2025-08-27 23:42:27', NULL),
(54, 12, '6. Part Inspection Data Sheet (PIDS)', NULL, NULL, '[\"PIDS\"]', '2025-08-27 23:43:23', '2025-08-27 23:45:48', NULL),
(55, 12, '6. PIDS-MPP', 'lampiran/6. 65112-74T00_PIDS - MPP.pdf', NULL, '[\"PIDS\",\"PP\",\"BRACKET ROOF GARNISH\",\"CONFIDENTIAL\",\"QUAKLITY CHECK SHEET\",\"OK\",\"JSC270CC\",\"SHIMADZU\",\"SUZUKI\",\"SOC Free\"]', '2025-08-27 23:48:26', '2025-08-27 23:48:26', 54),
(56, 12, '6. PIDS - PILOT', 'lampiran/6. 65112-74T00_PIDS - PILOT.pdf', NULL, '[\"PIDS\",\"PP\",\"PILOT\",\"QUALITY CHECK SHEET\",\"BRACKET ROOF GARNISH\"]', '2025-08-27 23:49:44', '2025-08-27 23:49:44', 54),
(57, 12, '6. PIDS- PP', 'lampiran/6. 65112-74T00_PIDS - PP.pdf', NULL, '[\"PIDS PP\",\"PP\",\"62\\/DS\\/PI\\/CKRG\\/VII\\/24\",\"BRACKET ROOF GARNISH\",\"mill test certficate\",\"JSC270CC\",\"COLD ROLLED COIL\"]', '2025-08-27 23:51:40', '2025-08-27 23:51:40', 54),
(58, 12, '7. Specification List', 'lampiran/7. 65112-74T00 Specification List.xlsx', NULL, '[\"BRACKET,ROOF GARNISH\",\"YTB\",\"Welding Performance\",\"SOC FREE\",\"welded bolt tensile\",\"millsheet\"]', '2025-08-27 23:56:15', '2025-08-27 23:56:15', NULL),
(59, 12, '8. Sampel Product', 'lampiran/8. 657112-74T00 Sample Produk.xlsx', NULL, '[\"sampel\"]', '2025-08-27 23:57:25', '2025-08-28 18:53:10', NULL),
(60, 12, '9. Limit Sample Sheet', 'lampiran/9. 657112-74T00 Limit Sample sheet.xlsx', NULL, '[]', '2025-08-27 23:57:55', '2025-08-27 23:57:55', NULL),
(61, 12, '10. Design Record', 'lampiran/10. 657112-74T00 Design Record.xlsx', NULL, '[\"BRACKET ROOF GARNISH\",\"SMC DWG\",\"Distribution flow\"]', '2025-08-27 23:58:48', '2025-08-27 23:58:48', NULL),
(62, 12, '11. ECN Record', 'lampiran/11. 657112-74T00 ECN Record.xlsx', NULL, '[\"ENGINEERING\",\"CHANGE\",\"NOTICE\",\"RECORD\",\"BRACKET ROOF GARNISH\",\"ISSUED\",\"distribution flow\",\"Design Engineering\",\"DDPP\"]', '2025-08-28 00:00:06', '2025-08-28 00:00:06', NULL),
(63, 12, '12. Control Plan (QCPC)', 'lampiran/12. 657112-74T00 PCS-QCPC.XLSX', NULL, '[\"Process Control Standard\",\"BRACKET ROOF GARNISH\",\"BOLT ROOF GARNISH\",\"09119-06135\",\"65112-74T00\",\"JSC270CC\"]', '2025-08-28 00:01:31', '2025-08-28 00:01:31', NULL),
(64, 12, '13. Checking Aids (Inspection Tool) Verification Result', 'lampiran/13. 657112-74T00 Checking Aids (Inspection Tool) Verification Result (NA).xlsx', NULL, '[]', '2025-08-28 00:02:03', '2025-08-28 00:02:03', NULL),
(65, 12, '14. Work Instruction', NULL, NULL, '[]', '2025-08-28 00:05:23', '2025-08-28 00:05:23', NULL),
(66, 12, '14. WI-QC-INSPEKSI', 'lampiran/14. 65112-74T00 WI-QC-INPEKSI.pdf', NULL, '[\"WI\",\"QC\",\"INSPEKSI\",\"WORK INSTUCTION\",\"QUALITY FINISHED GOODS\",\"CHECK FIXTURE\",\"LHQ\",\"LABEL\",\"BRACKET ROOF GARNISH\",\"INSPECTION\"]', '2025-08-28 00:07:06', '2025-08-28 00:07:06', 65),
(67, 12, '14. WI-PR-PRESS', 'lampiran/14. 657112-74T00 WI-PR-PRESS.xlsx', NULL, '[\"WI\",\"PR\",\"PRESS\",\"JSC270CC 1.2 X 48 X COIL\",\"PROGRESSIVE\",\"WI-PRP-ISI4TB-89\",\"PRESSING\"]', '2025-08-28 00:08:24', '2025-08-28 00:08:24', 65),
(68, 12, '14. WI-PR-WELDING', 'lampiran/14. 657112-74T00 WI-PR-WELDING.xlsx', NULL, '[\"WI\",\"PR\",\"WELDING\",\"WI-PRSW-ISI4TB-89-1B\"]', '2025-08-28 00:08:55', '2025-08-28 00:09:48', 65),
(69, 12, '15. Skill matrix + Training Man Power', NULL, NULL, '[]', '2025-08-28 00:12:38', '2025-08-28 00:12:38', NULL),
(70, 12, '15. Training Materi YTB', 'lampiran/15. 657112-74T00 Training Materi YTB.pdf', NULL, '[\"PROJECT YTB\",\"SUZUKI CIKARANG\",\"XE-619\"]', '2025-08-28 00:26:34', '2025-08-28 00:26:34', 69),
(71, 12, '15. Training YTB 1', 'lampiran/15. 657112-74T00 Training YTB 1.pdf', NULL, '[\"daftar hadir\",\"FM-HR-07\\/REV 0\\/03-09-2007\",\"TRAINING PART YTB\"]', '2025-08-28 00:28:22', '2025-08-28 00:28:22', 69),
(72, 12, '15. Training YTB 2', 'lampiran/15. 657112-74T00 Training YTB 2.pdf', NULL, '[\"training new model ytb\",\"daftar hadir\",\"FM-HR-07\\/REV 0\\/03-09-2007\"]', '2025-08-28 00:28:55', '2025-08-28 00:30:29', 69),
(73, 12, '15. Training YTB Press', 'lampiran/15. 657112-74T00 Training YTB_Press.pdf', NULL, '[\"daftar hadir\",\"training new model ytb\"]', '2025-08-28 00:31:39', '2025-08-28 00:31:39', 69),
(74, 12, '15. Training YTB Press (foto)', 'lampiran/15. 657112-74T00 Training YTB_Press_foto.jpeg', NULL, '[\"training ytb\",\"bukti\",\"foto\"]', '2025-08-28 00:32:44', '2025-08-28 00:32:44', 73),
(75, 12, '15. Training YTB QC', 'lampiran/15. 657112-74T00 Training YTB_QC.pdf', NULL, '[\"training new model\",\"daftar hadir\",\"Bracket RR Back Hinge Inside R\",\"FM-HR-07\\/REV 0\\/03-09-2007\"]', '2025-08-28 00:34:48', '2025-08-28 00:34:48', 69),
(76, 12, '15. Training YTB QC (foto)', 'lampiran/15. 657112-74T00 Training YTB_QC_foto.jpeg', NULL, '[\"foto\",\"bukti\",\"training ytb\"]', '2025-08-28 00:35:46', '2025-08-28 00:35:46', 75),
(77, 12, '15. Training YTB Welding', 'lampiran/15. 657112-74T00 Training YTB_Weld.pdf', NULL, '[\"daftar hadir\",\"training new model YTB\",\"bracket\"]', '2025-08-28 00:36:52', '2025-08-28 00:36:52', 69),
(78, 12, '15. Training YTB Welding (Foto)', 'lampiran/15. 657112-74T00 Training YTB_Weld_foto.jpeg', NULL, '[\"foto\",\"bukti\",\"training\"]', '2025-08-28 00:37:28', '2025-08-28 00:37:28', 77),
(79, 12, '16. Inspection Standard', NULL, NULL, '[]', '2025-08-28 00:37:49', '2025-08-28 00:37:49', NULL),
(80, 12, '17. PROCESS FMEA & A											 														', 'lampiran/17. 65112-74T00 FMEA final Brkt Roof Garnish.xlsx', NULL, '[\"PROCESS FMEA & A\",\"PFMEAA\",\"Process Failure Mode and Effect Analysis and Action\",\"BRACKET ROOF GARNISH\"]', '2025-08-28 00:39:24', '2025-08-28 00:39:24', NULL),
(81, 12, '18. Parameter setting machine', 'lampiran/18. 657112-74T00 Parameter Setting Mesin.xlsx', NULL, '[]', '2025-08-28 00:39:57', '2025-08-28 00:39:57', NULL),
(82, 12, '19. Pokayoke System', NULL, NULL, '[]', '2025-08-28 00:40:11', '2025-08-28 00:40:11', NULL),
(83, 12, '20. Capacity Production', NULL, NULL, '[]', '2025-08-28 00:40:27', '2025-08-28 00:40:27', NULL),
(84, 12, '21. Capability Process', NULL, NULL, '[]', '2025-08-28 00:40:42', '2025-08-28 00:40:42', NULL),
(85, 12, '22. Standard Packaging', 'lampiran/22. 657122-74T00 Standar Packing.pdf', NULL, '[\"standard packing\",\"lembar registrasi\",\"BRACJET FR RENDER LWR\",\"YTB\",\"CHECKED\"]', '2025-08-28 00:42:28', '2025-08-28 00:42:28', NULL),
(86, 12, '23. Supplier Development Schedule (SDS)', NULL, NULL, '[]', '2025-08-28 00:42:56', '2025-08-28 00:42:56', NULL),
(87, 12, '23. PRE SDS YTB', 'lampiran/23. 657122-74T00 PRE SDS YTB.xlsx', NULL, '[\"MATING SURFACE\",\"SDS\",\"YTB\",\"supplier development schedule\"]', '2025-08-28 00:44:24', '2025-08-28 00:44:24', 86),
(88, 12, '23. UPDATE SDS YTB', 'lampiran/23. 657122-74T00 UPDATE SDS YTB.xlsx', NULL, '[\"Supplier General Schedule\",\"MATING SURFACE\"]', '2025-08-28 00:45:15', '2025-08-28 00:45:15', 86),
(89, 12, '24. Counterpartner', 'lampiran/24. 657112-74T00 Counterpartner.xlsx', NULL, '[\"counterpartner\",\"YTB\",\"Suzuki\"]', '2025-08-28 00:47:00', '2025-08-28 00:47:00', NULL),
(90, 12, '25. Identification List Tooling and Process', 'lampiran/25. 65112-74T00 Identification List Tooling & Process.xlsx', NULL, '[\"IDENTIFICATION LIST TOOLING & PROCESS\",\"PP\",\"BRACKET ROOF GARNISH\"]', '2025-08-28 00:48:59', '2025-08-28 00:48:59', NULL),
(91, 12, '26. Supply Chain', 'lampiran/26. 65112-74T00  Form Supply Chain Part.xlsx', NULL, '[\"Supply Chain\",\"BRACKET ROOF GARNISH\",\"YTB\"]', '2025-08-28 00:50:11', '2025-08-28 00:50:11', NULL),
(92, 12, '27. System Check', NULL, NULL, '[]', '2025-08-28 00:50:22', '2025-08-28 00:50:22', NULL),
(93, 12, '28. Part Submission Warranty', NULL, NULL, '[]', '2025-08-28 00:50:50', '2025-08-28 00:50:50', NULL),
(94, 12, '29. TOL & LOI', 'lampiran/29. 657112-74T00 LOI-SIM-YTB.pdf', NULL, '[\"SUZUKI\",\"LETTER OF INTENT\",\"LOI\",\"BRACKET ROOF GARNISH\",\"YTB\",\"TOOLING ORDER LETTER\",\"TOL\",\"65112-74T00\"]', '2025-08-28 00:52:08', '2025-08-28 00:52:08', NULL),
(95, 12, '30. Drawing', NULL, NULL, '[]', '2025-08-28 00:52:34', '2025-08-28 00:52:34', NULL),
(96, 12, '30. 2D Drawing', 'lampiran/30.  65112-74T00 2D_Drawing.TIF', NULL, '[\"Drawing 2D\",\"2D\",\"drawing\"]', '2025-08-28 00:53:10', '2025-08-28 00:53:10', 95),
(97, 12, '30. 3D Drawing', 'lampiran/30. 65112-74T00 3D_DRAWING.SLDPRT', NULL, '[\"Drawing 3D\",\"3D\",\"drawing\"]', '2025-08-28 00:54:03', '2025-08-28 00:54:03', 95),
(109, 15, '1. Dokumen desain produk', NULL, NULL, '[]', '2025-09-03 18:56:45', '2025-09-03 18:56:58', NULL),
(110, 15, '2. Design change approval record', NULL, NULL, '[]', '2025-09-03 18:57:45', '2025-09-03 18:57:55', NULL),
(111, 15, '3. Process Flow Design', NULL, NULL, '[]', '2025-09-03 18:58:12', '2025-09-03 18:58:31', NULL),
(112, 15, '4. Process Failure Mode & Effect Analysis (PFMEA)', NULL, NULL, '[]', '2025-09-03 18:59:07', '2025-09-03 18:59:07', NULL),
(113, 15, '5. Process Quality Control Sheet (PQCS)', NULL, NULL, '[]', '2025-09-03 18:59:25', '2025-09-03 18:59:35', NULL),
(114, 15, '6. Measurement System Analysis (MSA)', NULL, NULL, '[]', '2025-09-03 19:00:07', '2025-09-03 19:00:07', NULL),
(115, 15, '7. Pengecekan dimensi', NULL, NULL, '[]', '2025-09-03 19:00:22', '2025-09-03 19:00:22', NULL),
(116, 15, 'Pengujian Material dan Performa', NULL, NULL, '[]', '2025-09-03 19:00:38', '2025-09-03 19:00:38', NULL),
(117, 15, 'Kapabilitas proses', NULL, NULL, '[]', '2025-09-03 19:00:49', '2025-09-03 19:00:49', NULL),
(118, 15, '10. Dokumen Laboratorium', NULL, NULL, '[]', '2025-09-03 19:01:03', '2025-09-03 19:01:03', NULL),
(119, 15, '11. Appearance Approval Report', NULL, NULL, '[]', '2025-09-03 19:01:20', '2025-09-03 19:01:20', NULL),
(120, 15, '12. Sample part', NULL, NULL, '[]', '2025-09-03 19:01:29', '2025-09-03 19:01:29', NULL),
(121, 15, '13. Master Sample', NULL, NULL, '[]', '2025-09-03 19:01:40', '2025-09-03 19:01:40', NULL),
(122, 15, '14. Alat ukur & alat bantu ukur', NULL, NULL, '[]', '2025-09-03 19:01:57', '2025-09-03 19:01:57', NULL),
(123, 15, '15. Kapasitas vs Loading Produksi', NULL, NULL, '[]', '2025-09-03 19:02:18', '2025-09-03 19:02:18', NULL),
(124, 15, '16. Specification Agreement', NULL, NULL, '[]', '2025-09-03 19:02:33', '2025-09-03 19:02:33', NULL),
(125, 15, '17. Training Karyawan', NULL, NULL, '[]', '2025-09-03 19:02:47', '2025-09-03 19:02:47', NULL),
(126, 15, '18. Sarana delivery', NULL, NULL, '[]', '2025-09-03 19:02:59', '2025-09-03 19:02:59', NULL),
(127, 15, '19. Packaging standard (SDIS)', NULL, NULL, '[]', '2025-09-03 19:03:18', '2025-09-03 19:03:18', NULL),
(128, 15, 'Part Submission Warant (PSW)', NULL, NULL, '[]', '2025-09-03 19:03:40', '2025-09-03 19:03:40', NULL),
(129, 15, '21. Component Supply Chain List', NULL, NULL, '[]', '2025-09-03 19:03:58', '2025-09-03 19:03:58', NULL),
(130, 15, '22. Chemical Substance in Material', NULL, NULL, '[]', '2025-09-03 19:04:16', '2025-09-03 19:04:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2025_08_20_023312_create_berkas_table', 1),
(6, '2025_08_20_024637_rename_kodeberkas_column_on_berkas_table', 1),
(7, '2025_08_20_031832_create_lampirans_table', 2),
(8, '2025_08_20_083008_rename_berkas_to_dokumens', 3),
(9, '2025_08_20_092031_add_keyword_to_berkas_table', 4),
(10, '2025_08_20_092723_add_keywords_json_to_berkas_table', 5),
(11, '2025_08_20_092907_add_keywords_to_berkas_table', 6),
(12, '2025_08_21_010707_add_keywords_to_lampirans_table', 6),
(13, '2025_08_22_082159_add_thumbnail_to_berkas_table', 7),
(14, '2025_08_26_013231_add_parent_id_to_lampirans_table', 8),
(15, '2025_08_27_041202_add_indexes_and_cascade_to_lampirans_table', 9),
(16, '2025_09_01_010348_create_permission_tables', 10),
(17, '2025_09_01_044752_add_is_public_to_berkas_table', 11),
(18, '2025_09_03_015400_add_customer_name_and_model_to_berkas_table', 12),
(19, '2025_09_04_024957_add_versions_to_berkas_and_lampirans_tables', 13);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 1),
(2, 'App\\Models\\User', 2);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'berkas.view', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(2, 'berkas.create', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(3, 'berkas.update', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(4, 'berkas.delete', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(6, 'lampiran.view', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(7, 'lampiran.create', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(8, 'lampiran.update', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(9, 'lampiran.delete', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'Admin', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(2, 'Editor', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54'),
(3, 'Viewer', 'web', '2025-08-31 18:39:54', '2025-08-31 18:39:54');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(2, 1),
(2, 2),
(3, 1),
(3, 2),
(4, 1),
(4, 2),
(6, 1),
(6, 2),
(7, 1),
(7, 2),
(8, 1),
(8, 2),
(9, 1),
(9, 2);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'athifa', 'a@gmail.com', NULL, '$2y$12$eMi7322DtH/azReHtI9h7OZPyzgf6.D52uj66gzDVCeLXkuYuefyq', NULL, '2025-08-19 19:51:28', '2025-08-19 19:51:28'),
(2, 'nathania', 'n@gmail.com', NULL, '$2y$12$hQ4HPD4IRJd9xtvBXKayau3Il8ig2a92gWePUWIUR9gWIzqZRlSii', NULL, '2025-08-28 18:56:02', '2025-08-28 18:56:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `berkas`
--
ALTER TABLE `berkas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `lampirans`
--
ALTER TABLE `lampirans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lampirans_parent_id_foreign` (`parent_id`),
  ADD KEY `lampirans_berkas_parent_idx` (`berkas_id`,`parent_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `berkas`
--
ALTER TABLE `berkas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lampirans`
--
ALTER TABLE `lampirans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lampirans`
--
ALTER TABLE `lampirans`
  ADD CONSTRAINT `lampirans_berkas_id_foreign` FOREIGN KEY (`berkas_id`) REFERENCES `berkas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lampirans_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `lampirans` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
