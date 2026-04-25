-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 16-04-2026 a las 08:05:56
-- Versión del servidor: 10.6.24-MariaDB-cll-lve
-- Versión de PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `brightro_qrapp_inv`
--
CREATE DATABASE IF NOT EXISTS `brightro_qrapp_inv` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `brightro_qrapp_inv`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `absences`
--

CREATE TABLE `absences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  `reason` enum('familiar','enfermedad','vacaciones','sin_justificacion') NOT NULL,
  `notes` text DEFAULT NULL,
  `evidence_path` varchar(500) DEFAULT NULL,
  `status` enum('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `absences`
--

INSERT INTO `absences` (`id`, `user_id`, `project_id`, `date_start`, `date_end`, `reason`, `notes`, `evidence_path`, `status`, `reviewed_by`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(49, 17, NULL, '2026-03-05', '2026-03-05', 'sin_justificacion', 'No hay registro', NULL, 'aprobado', 1, '2026-04-02 12:37:20', '2026-04-01 15:51:56', '2026-04-02 16:37:20'),
(50, 9, NULL, '2026-04-06', '2026-04-06', 'sin_justificacion', 'Ausencia registrada automáticamente por falta de asistencia y sin justificación.', NULL, 'aprobado', 1, '2026-04-06 14:20:41', '2026-04-06 18:20:41', '2026-04-06 18:20:41'),
(52, 14, NULL, '2026-04-06', '2026-04-06', 'sin_justificacion', 'Ausencia registrada automáticamente por falta de asistencia y sin justificación.', NULL, 'aprobado', 1, '2026-04-06 14:20:42', '2026-04-06 18:20:42', '2026-04-06 18:20:42'),
(53, 15, NULL, '2026-04-06', '2026-04-06', 'sin_justificacion', 'Ausencia registrada automáticamente por falta de asistencia y sin justificación.', NULL, 'aprobado', 1, '2026-04-06 14:20:42', '2026-04-06 18:20:42', '2026-04-06 18:20:42'),
(54, 16, NULL, '2026-04-06', '2026-04-06', 'sin_justificacion', 'Ausencia registrada automáticamente por falta de asistencia y sin justificación.', NULL, 'aprobado', 1, '2026-04-06 14:20:42', '2026-04-06 18:20:42', '2026-04-06 18:20:42'),
(56, 9, NULL, '2026-04-03', '2026-04-03', 'sin_justificacion', 'Ausencia registrada automáticamente por falta de asistencia y sin justificación.', NULL, 'aprobado', 1, '2026-04-06 14:21:09', '2026-04-06 18:21:09', '2026-04-06 18:21:09'),
(58, 15, NULL, '2026-04-03', '2026-04-03', 'sin_justificacion', 'Ausencia registrada automáticamente por falta de asistencia y sin justificación.', NULL, 'aprobado', 1, '2026-04-06 14:21:09', '2026-04-06 18:21:09', '2026-04-06 18:21:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `type` enum('entry','exit','pause','resume','start_lunch','end_lunch') NOT NULL,
  `entry_mode` enum('auto','manual') NOT NULL DEFAULT 'auto',
  `manual_reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_by_admin_id` int(11) DEFAULT NULL,
  `created_at_admin` datetime DEFAULT NULL,
  `original_time` datetime NOT NULL,
  `rounded_time` datetime NOT NULL,
  `total_duration` int(11) DEFAULT NULL COMMENT 'Total work duration in minutes for the day',
  `lunch_duration` int(11) DEFAULT NULL COMMENT 'Duration of lunch break in minutes',
  `project_qr_id` int(11) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 = active, 2 = paused, 3 = exited',
  `entry_source` enum('qr','manual') NOT NULL DEFAULT 'qr',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `late_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `user_id`, `location`, `type`, `entry_mode`, `manual_reason`, `created_by`, `created_by_admin_id`, `created_at_admin`, `original_time`, `rounded_time`, `total_duration`, `lunch_duration`, `project_qr_id`, `status`, `entry_source`, `created_at`, `late_reason`) VALUES
(60, 12, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-02 08:00:00', '2026-03-02 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 14:25:21', NULL),
(61, 13, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-02 08:00:00', '2026-03-02 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 14:25:21', NULL),
(62, 14, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-02 08:00:00', '2026-03-02 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 14:25:21', NULL),
(63, 15, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-02 08:00:00', '2026-03-02 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 14:25:21', NULL),
(64, 16, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-02 08:00:00', '2026-03-02 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 14:25:21', NULL),
(65, 18, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-02 08:00:00', '2026-03-02 16:00:00', 420, 60, 30, 1, 'manual', '2026-04-01 14:26:59', NULL),
(66, 12, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-03 08:00:00', '2026-03-03 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:04:35', NULL),
(67, 12, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-04 08:00:00', '2026-03-04 16:00:00', 420, 60, NULL, 1, 'manual', '2026-04-01 15:04:36', NULL),
(68, 12, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-05 08:00:00', '2026-03-05 16:00:00', 420, 60, NULL, 1, 'manual', '2026-04-01 15:04:36', NULL),
(69, 12, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-06 08:00:00', '2026-03-06 16:00:00', 420, 60, NULL, 1, 'manual', '2026-04-01 15:04:36', NULL),
(70, 18, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-04 08:00:00', '2026-03-04 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:06:14', NULL),
(71, 18, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-05 08:00:00', '2026-03-05 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:06:14', NULL),
(72, 18, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-06 08:00:00', '2026-03-06 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:06:14', NULL),
(73, 14, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-03 08:00:00', '2026-03-03 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:33:40', NULL),
(74, 14, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-04 08:00:00', '2026-03-04 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:33:40', NULL),
(75, 14, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-05 08:00:00', '2026-03-05 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:33:40', NULL),
(76, 14, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-06 08:00:00', '2026-03-06 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:33:41', NULL),
(77, 15, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-03 08:00:00', '2026-03-03 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:42:20', NULL),
(78, 15, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-04 08:00:00', '2026-03-04 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:42:20', NULL),
(79, 15, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-05 08:00:00', '2026-03-05 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:42:20', NULL),
(80, 15, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-06 08:00:00', '2026-03-06 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:42:21', NULL),
(81, 18, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-03 08:00:00', '2026-03-03 16:00:00', 420, 60, 30, 1, 'manual', '2026-04-01 15:43:40', NULL),
(82, 13, 'Manual Entry', 'exit', 'auto', 'Entry time!', 1, NULL, NULL, '2026-03-03 08:00:00', '2026-03-03 16:00:00', 420, 60, 27, 1, 'manual', '2026-04-01 15:44:39', NULL),
(83, 13, 'Manual Entry', 'exit', 'auto', 'Entry time!', 1, NULL, NULL, '2026-03-04 08:00:00', '2026-03-04 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:44:40', NULL),
(84, 13, 'Manual Entry', 'exit', 'auto', 'Entry time!', 1, NULL, NULL, '2026-03-05 08:00:00', '2026-03-05 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:44:40', NULL),
(85, 13, 'Manual Entry', 'exit', 'auto', 'Entry time!', 1, NULL, NULL, '2026-03-06 08:00:00', '2026-03-06 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:44:40', NULL),
(86, 16, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-03 08:00:00', '2026-03-03 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:47:29', NULL),
(87, 16, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-04 08:00:00', '2026-03-04 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:47:29', NULL),
(88, 16, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-05 08:00:00', '2026-03-05 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:47:29', NULL),
(89, 16, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-06 08:00:00', '2026-03-06 16:00:00', 420, 60, 27, 1, 'manual', '2026-04-01 15:48:57', NULL),
(90, 17, 'Manual Entry', 'exit', 'auto', 'Manual Entry', 1, NULL, NULL, '2026-03-02 08:00:00', '2026-03-02 16:00:00', 420, 60, 30, 1, 'manual', '2026-04-01 15:50:30', NULL),
(91, 17, 'Manual Entry', 'exit', 'auto', 'Manual Entry', 1, NULL, NULL, '2026-03-03 08:00:00', '2026-03-03 16:00:00', 420, 60, 30, 1, 'manual', '2026-04-01 15:50:30', NULL),
(92, 17, 'Manual Entry', 'exit', 'auto', 'Manual Entry!', 1, NULL, NULL, '2026-03-04 08:00:00', '2026-03-04 16:00:00', 420, 60, 29, 1, 'manual', '2026-04-01 15:51:22', NULL),
(93, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-09 07:00:00', '2026-03-09 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(94, 13, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-09 07:00:00', '2026-03-09 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(95, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-09 07:00:00', '2026-03-09 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(96, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-09 07:00:00', '2026-03-09 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(97, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-09 07:00:00', '2026-03-09 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(98, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-09 07:00:00', '2026-03-09 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(99, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-10 07:00:00', '2026-03-10 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(100, 13, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-10 07:00:00', '2026-03-10 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(101, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-10 07:00:00', '2026-03-10 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(102, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-10 07:00:00', '2026-03-10 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(103, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-10 07:00:00', '2026-03-10 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(104, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-10 07:00:00', '2026-03-10 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(105, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-11 07:00:00', '2026-03-11 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(107, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-11 07:00:00', '2026-03-11 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(108, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-11 07:00:00', '2026-03-11 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(109, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-11 07:00:00', '2026-03-11 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(110, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-11 07:00:00', '2026-03-11 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(111, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-12 07:00:00', '2026-03-12 16:00:00', 480, 60, 27, 1, 'manual', '2026-04-06 15:58:28', NULL),
(113, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-12 07:00:00', '2026-03-12 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(114, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-12 07:00:00', '2026-03-12 16:00:00', 480, 60, 27, 1, 'manual', '2026-04-06 15:58:28', NULL),
(115, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-12 07:00:00', '2026-03-12 16:00:00', 480, 60, 27, 1, 'manual', '2026-04-06 15:58:28', NULL),
(116, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-12 07:00:00', '2026-03-12 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(118, 13, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-13 07:00:00', '2026-03-13 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(120, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-13 07:00:00', '2026-03-13 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(121, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-13 07:00:00', '2026-03-13 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:28', NULL),
(135, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-16 07:00:00', '2026-03-16 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(137, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-16 07:00:00', '2026-03-16 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(138, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-16 07:00:00', '2026-03-16 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(140, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-16 07:00:00', '2026-03-16 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(141, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-17 07:00:00', '2026-03-17 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(143, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-17 07:00:00', '2026-03-17 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(144, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-17 07:00:00', '2026-03-17 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(145, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-17 07:00:00', '2026-03-17 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(146, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-17 07:00:00', '2026-03-17 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(149, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-18 07:00:00', '2026-03-18 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(150, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-18 07:00:00', '2026-03-18 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(152, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-18 07:00:00', '2026-03-18 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(153, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-19 07:00:00', '2026-03-19 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(154, 13, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-19 07:00:00', '2026-03-19 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(155, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-19 07:00:00', '2026-03-19 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(156, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-19 07:00:00', '2026-03-19 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(157, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-19 07:00:00', '2026-03-19 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(158, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-19 07:00:00', '2026-03-19 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(159, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-20 07:00:00', '2026-03-20 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(160, 13, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-20 07:00:00', '2026-03-20 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(161, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-20 07:00:00', '2026-03-20 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(162, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-20 07:00:00', '2026-03-20 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(163, 16, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-20 07:00:00', '2026-03-20 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(164, 18, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-20 07:00:00', '2026-03-20 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(179, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-23 07:00:00', '2026-03-23 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(180, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-23 07:00:00', '2026-03-23 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(185, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-24 07:00:00', '2026-03-24 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(186, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-24 07:00:00', '2026-03-24 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:29', NULL),
(219, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-30 07:00:00', '2026-03-30 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:30', NULL),
(221, 14, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-30 07:00:00', '2026-03-30 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:30', NULL),
(222, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-30 07:00:00', '2026-03-30 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:30', NULL),
(225, 12, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-31 07:00:00', '2026-03-31 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:30', NULL),
(228, 15, 'Manual Entry', 'exit', 'auto', 'GM MAnual', 1, NULL, NULL, '2026-03-31 07:00:00', '2026-03-31 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 15:58:30', NULL),
(267, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-13 07:00:00', '2026-03-13 16:00:00', 480, 60, 27, 1, 'manual', '2026-04-06 16:18:27', NULL),
(268, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-16 07:00:00', '2026-03-16 16:00:00', 480, 60, 31, 1, 'manual', '2026-04-06 16:49:28', NULL),
(269, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-17 07:00:00', '2026-03-17 16:00:00', 480, 60, 31, 1, 'manual', '2026-04-06 16:49:28', NULL),
(271, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-18 07:00:00', '2026-03-18 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 16:53:59', NULL),
(272, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-18 07:00:00', '2026-03-18 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 16:53:59', NULL),
(273, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-18 07:00:00', '2026-03-18 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 16:53:59', NULL),
(274, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-18 07:00:00', '2026-03-18 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 16:53:59', NULL),
(275, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-16 07:00:00', '2026-03-16 16:00:00', 540, 0, NULL, 1, 'manual', '2026-04-06 16:54:49', NULL),
(276, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-17 07:00:00', '2026-03-17 16:00:00', 540, 0, NULL, 1, 'manual', '2026-04-06 16:54:49', NULL),
(277, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-19 07:00:00', '2026-03-19 16:00:00', 540, 0, NULL, 1, 'manual', '2026-04-06 16:54:50', NULL),
(278, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-20 07:00:00', '2026-03-20 16:00:00', 540, 0, NULL, 1, 'manual', '2026-04-06 16:54:50', NULL),
(279, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-06 07:00:00', '2026-03-06 16:00:00', 540, 0, 27, 1, 'manual', '2026-04-06 16:55:59', NULL),
(280, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-23 07:00:00', '2026-03-23 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(281, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-23 07:00:00', '2026-03-23 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(282, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-24 07:00:00', '2026-03-24 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(283, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-24 07:00:00', '2026-03-24 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(284, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-25 07:00:00', '2026-03-25 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(285, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-25 07:00:00', '2026-03-25 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(286, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-26 07:00:00', '2026-03-26 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(287, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-26 07:00:00', '2026-03-26 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(288, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-27 07:00:00', '2026-03-27 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(289, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-27 07:00:00', '2026-03-27 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:58:31', NULL),
(290, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-25 07:00:00', '2026-03-25 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:59:34', NULL),
(291, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-25 07:00:00', '2026-03-25 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:59:34', NULL),
(292, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-26 07:00:00', '2026-03-26 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:59:34', NULL),
(293, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-26 07:00:00', '2026-03-26 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:59:34', NULL),
(294, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-27 07:00:00', '2026-03-27 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:59:34', NULL),
(295, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-27 07:00:00', '2026-03-27 16:00:00', 540, 0, 34, 1, 'manual', '2026-04-06 16:59:34', NULL),
(296, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-23 07:00:00', '2026-03-23 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 17:00:40', NULL),
(297, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-23 07:00:00', '2026-03-23 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 17:00:40', NULL),
(298, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-23 07:00:00', '2026-03-23 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 17:00:40', NULL),
(299, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-24 07:00:00', '2026-03-24 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 17:00:40', NULL),
(300, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-24 07:00:00', '2026-03-24 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 17:00:40', NULL),
(301, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-24 07:00:00', '2026-03-24 16:00:00', 540, 0, 33, 1, 'manual', '2026-04-06 17:00:40', NULL),
(302, 15, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-25 07:00:00', '2026-03-25 16:00:00', 480, 60, 35, 1, 'manual', '2026-04-06 17:45:20', NULL),
(303, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-25 07:00:00', '2026-03-25 16:00:00', 480, 60, 33, 1, 'manual', '2026-04-06 17:46:15', NULL),
(304, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-26 07:00:00', '2026-03-26 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 17:47:25', NULL),
(305, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-25 07:00:00', '2026-03-25 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 17:48:25', NULL),
(308, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-30 07:00:00', '2026-03-30 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 17:55:08', NULL),
(309, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-30 07:00:00', '2026-03-30 16:00:00', 480, 60, NULL, 1, 'manual', '2026-04-06 17:55:55', NULL),
(310, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-30 07:00:00', '2026-03-30 16:00:00', 480, 60, NULL, 1, 'manual', '2026-04-06 17:55:55', NULL),
(311, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-30 07:00:00', '2026-03-30 16:00:00', 480, 60, NULL, 1, 'manual', '2026-04-06 17:55:55', NULL),
(312, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-31 07:00:00', '2026-03-31 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 17:57:11', NULL),
(313, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-31 07:00:00', '2026-03-31 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 17:57:11', NULL),
(314, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-31 07:00:00', '2026-03-31 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 17:57:11', NULL),
(315, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-31 07:00:00', '2026-03-31 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(316, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-03-31 07:00:00', '2026-03-31 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(317, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-01 07:00:00', '2026-04-01 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(318, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-01 07:00:00', '2026-04-01 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(319, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-02 07:00:00', '2026-04-02 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(320, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-02 07:00:00', '2026-04-02 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(321, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-03 07:00:00', '2026-04-03 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(322, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-03 07:00:00', '2026-04-03 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 17:59:55', NULL),
(323, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-02 07:00:00', '2026-04-02 16:00:00', 480, 60, 37, 1, 'manual', '2026-04-06 18:04:16', NULL),
(324, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-02 07:00:00', '2026-04-02 16:00:00', 480, 60, 37, 1, 'manual', '2026-04-06 18:04:16', NULL),
(325, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-01 07:00:00', '2026-04-01 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 18:05:11', NULL),
(326, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-01 07:00:00', '2026-04-01 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 18:05:11', NULL),
(327, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-01 07:00:00', '2026-04-01 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 18:05:11', NULL),
(328, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-02 07:00:00', '2026-04-02 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 18:07:27', NULL),
(330, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-01 07:00:00', '2026-04-01 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 18:12:03', NULL),
(331, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-02 07:00:00', '2026-04-02 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-06 18:12:38', NULL),
(332, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-03 07:00:00', '2026-04-03 16:00:00', 480, 60, 38, 1, 'manual', '2026-04-06 18:13:31', NULL),
(333, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-03 07:00:00', '2026-04-03 16:00:00', 480, 60, 38, 1, 'manual', '2026-04-06 18:13:31', NULL),
(334, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-03 07:00:00', '2026-04-03 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 18:14:24', NULL),
(335, 16, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-03 07:00:00', '2026-04-03 16:00:00', 480, 60, 34, 1, 'manual', '2026-04-06 18:14:24', NULL),
(336, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-06 08:00:00', '2026-04-06 16:00:00', 420, 60, 38, 1, 'manual', '2026-04-06 18:15:41', NULL),
(338, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-06 07:00:00', '2026-04-06 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 18:17:36', NULL),
(339, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-06 07:00:00', '2026-04-06 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-06 18:17:36', NULL),
(340, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-06 08:00:00', '2026-04-06 16:00:00', 420, 60, 38, 1, 'manual', '2026-04-06 18:18:16', NULL),
(342, 20, '28.5041807,-81.3790335', 'exit', 'auto', NULL, NULL, NULL, NULL, '2026-04-06 08:00:00', '2026-04-06 17:00:00', 480, 60, 35, 1, 'qr', '2026-04-07 13:18:53', NULL),
(343, 20, '28.5041598,-81.379085', 'entry', 'auto', NULL, NULL, NULL, NULL, '2026-04-07 13:43:43', '2026-04-07 13:45:00', 439, NULL, 35, 3, 'qr', '2026-04-07 13:43:43', NULL),
(344, 12, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-07 07:00:00', '2026-04-07 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-07 20:21:05', NULL),
(345, 13, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-07 07:00:00', '2026-04-07 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-07 20:21:05', NULL),
(346, 14, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-07 07:00:00', '2026-04-07 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-07 20:21:05', NULL),
(347, 15, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-07 07:00:00', '2026-04-07 16:00:00', 480, 60, 29, 1, 'manual', '2026-04-07 20:21:05', NULL),
(348, 17, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-07 07:00:00', '2026-04-07 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-07 20:23:34', NULL),
(349, 18, 'Manual Entry', 'exit', 'auto', 'GM', 1, NULL, NULL, '2026-04-07 07:00:00', '2026-04-07 16:00:00', 480, 60, 36, 1, 'manual', '2026-04-07 20:23:34', NULL),
(350, 20, '28.5040394,-81.3790294', 'exit', 'auto', NULL, NULL, NULL, NULL, '2026-04-07 21:02:22', '2026-04-07 21:15:00', 439, NULL, 35, 1, 'qr', '2026-04-07 21:02:22', NULL),
(351, 20, '28.5041948,-81.3790401', 'entry', 'auto', NULL, NULL, NULL, NULL, '2026-04-08 14:56:49', '2026-04-08 15:00:00', 1345, NULL, 35, 3, 'qr', '2026-04-08 14:56:50', NULL),
(352, 20, '28.5042234,-81.379115', 'exit', 'auto', NULL, NULL, NULL, NULL, '2026-04-09 13:21:48', '2026-04-09 13:30:00', 1345, NULL, 35, 1, 'qr', '2026-04-09 13:21:49', NULL),
(353, 20, '28.5042497,-81.3790647', 'entry', 'auto', NULL, NULL, NULL, NULL, '2026-04-09 13:22:02', '2026-04-09 13:30:00', 1922, NULL, 35, 3, 'qr', '2026-04-09 13:22:02', NULL),
(354, 20, '28.5041707,-81.3790088', 'exit', 'auto', NULL, NULL, NULL, NULL, '2026-04-10 21:24:13', '2026-04-10 21:30:00', 1922, NULL, 35, 1, 'qr', '2026-04-10 21:24:14', NULL),
(355, 20, '28.5043659,-81.379029', 'entry', 'auto', NULL, NULL, NULL, NULL, '2026-04-15 14:50:08', '2026-04-15 15:00:00', NULL, NULL, 35, 1, 'qr', '2026-04-15 14:50:08', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `location_history`
--

CREATE TABLE `location_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `location_history`
--

INSERT INTO `location_history` (`id`, `user_id`, `latitude`, `longitude`, `timestamp`) VALUES
(282, 1, 18.50409001, -69.88608590, '2025-09-29 23:21:30'),
(283, 1, 18.50409001, -69.88608590, '2025-09-29 23:21:58'),
(284, 1, 18.50408761, -69.88607677, '2025-09-29 23:24:44'),
(285, 1, 18.50421656, -69.88587237, '2025-09-29 23:30:51'),
(286, 1, 18.50403385, -69.88608927, '2025-10-06 23:25:59'),
(287, 1, 18.50403716, -69.88601005, '2025-10-06 23:35:18'),
(288, 1, 18.50403716, -69.88600802, '2025-10-06 23:44:03'),
(289, 1, 18.50419041, -69.88619917, '2025-10-06 23:53:28'),
(290, 1, 18.50403385, -69.88608927, '2025-10-07 00:05:38'),
(291, 1, 18.50403385, -69.88608927, '2025-10-07 00:15:48'),
(292, 1, 18.50403716, -69.88601005, '2025-10-07 00:24:34'),
(293, 9, 18.50412222, -69.88605895, '2025-10-07 00:25:09'),
(294, 9, 18.50404335, -69.88589400, '2025-10-07 00:26:31'),
(296, 1, 18.48765816, -69.96218937, '2025-11-20 14:43:22'),
(297, 1, 18.50409296, -69.88584689, '2026-02-06 12:14:25'),
(298, 1, 18.48570522, -69.90439736, '2026-02-09 09:41:55'),
(299, 1, 18.48570655, -69.90439663, '2026-02-09 09:46:42'),
(300, 1, 18.48570702, -69.90439874, '2026-02-09 09:56:42'),
(301, 1, 18.48571475, -69.90439809, '2026-02-09 10:06:42'),
(302, 1, 18.48570616, -69.90439680, '2026-02-09 10:17:28'),
(303, 1, 18.48571126, -69.90439967, '2026-02-09 10:27:29'),
(304, 1, 18.48571602, -69.90439671, '2026-02-09 10:37:28'),
(305, 1, 18.48570905, -69.90439791, '2026-02-09 10:47:28'),
(306, 1, 18.48571157, -69.90439733, '2026-02-09 10:57:28'),
(307, 1, 18.48570342, -69.90439696, '2026-02-09 11:07:28'),
(308, 1, 18.48571655, -69.90439906, '2026-02-09 11:17:28'),
(309, 1, 18.48571207, -69.90439738, '2026-02-09 11:27:28'),
(310, 1, 18.48571719, -69.90439928, '2026-02-09 11:37:28'),
(311, 1, 18.48563367, -69.90438385, '2026-02-09 11:47:28'),
(312, 1, 18.48569325, -69.90439797, '2026-02-09 11:57:28'),
(313, 1, 18.48762900, -69.96289100, '2026-02-23 17:17:08'),
(314, 1, 18.48764946, -69.96289811, '2026-02-23 17:26:56'),
(315, 1, 18.48763539, -69.96290365, '2026-02-23 17:36:57'),
(316, 1, 18.48764029, -69.96289960, '2026-02-23 17:46:56'),
(317, 1, 18.48768060, -69.96289225, '2026-02-23 18:13:37'),
(318, 1, 18.50379359, -69.88607187, '2026-02-26 17:50:19'),
(319, 1, 18.50407000, -69.88586325, '2026-02-26 17:51:42'),
(320, 11, 18.50407564, -69.88595487, '2026-02-26 17:52:13'),
(321, 11, 18.50379359, -69.88607187, '2026-02-26 17:53:19'),
(322, 1, 18.50412269, -69.88587000, '2026-02-26 17:53:55'),
(323, 1, 28.50380000, -81.37920000, '2026-02-27 15:47:47'),
(324, 1, 28.50380000, -81.37920000, '2026-02-27 15:56:53'),
(325, 1, 28.50380000, -81.37920000, '2026-02-27 16:06:52'),
(326, 1, 28.50380000, -81.37920000, '2026-02-27 16:16:07'),
(327, 1, 19.46145200, -70.67747500, '2026-02-27 18:32:36'),
(328, 1, 19.46145200, -70.67747500, '2026-02-27 18:37:51'),
(329, 1, 19.46145200, -70.67747500, '2026-02-27 18:43:53'),
(330, 1, 19.46145200, -70.67747500, '2026-02-28 08:53:58'),
(331, 1, 19.46145200, -70.67747500, '2026-02-28 08:53:58'),
(332, 1, 19.46145200, -70.67747500, '2026-02-28 09:03:51'),
(333, 1, 19.46145200, -70.67747500, '2026-02-28 09:08:59'),
(334, 1, 19.46145200, -70.67747500, '2026-02-28 09:19:03'),
(335, 1, 19.46145200, -70.67747500, '2026-02-28 09:29:02'),
(336, 1, 19.46145200, -70.67747500, '2026-02-28 09:29:02'),
(337, 1, 19.46145200, -70.67747500, '2026-02-28 09:39:03'),
(338, 1, 19.46145200, -70.67747500, '2026-02-28 09:49:02'),
(339, 1, 19.45609340, -70.66699098, '2026-02-28 11:58:30'),
(340, 1, 19.45610653, -70.66698327, '2026-02-28 12:08:37'),
(341, 11, 19.45610680, -70.66698446, '2026-02-28 12:10:54'),
(342, 1, 19.45610680, -70.66698446, '2026-02-28 12:11:20'),
(343, 1, 19.45609340, -70.66699098, '2026-02-28 12:23:04'),
(344, 1, 19.45610083, -70.66697899, '2026-02-28 12:28:58'),
(345, 1, 19.45617520, -70.66692466, '2026-02-28 12:49:24'),
(346, 1, 19.45614486, -70.66696383, '2026-02-28 12:56:13'),
(347, 1, 19.45614464, -70.66692801, '2026-02-28 13:06:06'),
(348, 1, 19.45616635, -70.66695065, '2026-02-28 13:12:49'),
(349, 1, 19.45617043, -70.66689445, '2026-02-28 13:15:37'),
(350, 11, 19.45617043, -70.66689445, '2026-02-28 13:15:55'),
(351, 1, 19.45610761, -70.66697775, '2026-02-28 13:16:17'),
(352, 11, 19.45625971, -70.66686544, '2026-02-28 13:17:02'),
(353, 11, 19.45611319, -70.66697117, '2026-02-28 13:20:34'),
(354, 1, 19.45610680, -70.66698446, '2026-02-28 13:48:34'),
(355, 11, 19.45592458, -70.66749268, '2026-02-28 13:50:06'),
(356, 11, 19.45597232, -70.66747943, '2026-02-28 13:50:37'),
(357, 11, 19.45612568, -70.66697542, '2026-02-28 13:53:12'),
(358, 11, 19.45612568, -70.66697542, '2026-02-28 13:54:07'),
(359, 11, 19.45600624, -70.66745257, '2026-02-28 13:56:00'),
(360, 1, 19.45610653, -70.66698327, '2026-02-28 13:56:54'),
(361, 11, 19.45592458, -70.66749268, '2026-02-28 13:59:41'),
(362, 1, 19.45623518, -70.66688191, '2026-02-28 14:02:51'),
(363, 11, 19.45601939, -70.66742197, '2026-02-28 14:05:21'),
(364, 1, 19.45613017, -70.66697060, '2026-02-28 14:08:37'),
(365, 1, 19.45610653, -70.66698327, '2026-02-28 14:18:29'),
(366, 1, 19.45611318, -70.66697904, '2026-02-28 14:26:10'),
(367, 1, 19.45609025, -70.66699916, '2026-02-28 14:37:55'),
(368, 1, 18.47469680, -69.90308554, '2026-03-02 15:53:44'),
(369, 1, 18.50409042, -69.88583341, '2026-03-02 16:38:36'),
(370, 1, 18.48771282, -69.96346482, '2026-03-02 17:51:41'),
(371, 1, 18.48769321, -69.96349148, '2026-03-02 17:57:22'),
(372, 1, 18.48769828, -69.96347800, '2026-03-02 18:07:15'),
(373, 1, 18.47832770, -69.88801495, '2026-03-06 11:35:30'),
(374, 1, 18.48768544, -69.96251841, '2026-03-07 09:44:30'),
(375, 1, 18.48760268, -69.96249568, '2026-03-07 09:53:19'),
(376, 1, 18.48759000, -69.96251000, '2026-03-07 10:03:19'),
(377, 1, 18.48776135, -69.96255321, '2026-03-07 10:13:20'),
(378, 1, 18.48766482, -69.96251633, '2026-03-07 10:18:56'),
(379, 1, 18.48793183, -69.96254464, '2026-03-07 10:28:55'),
(380, 1, 18.48782244, -69.96250315, '2026-03-07 10:38:56'),
(381, 1, 18.50854802, -69.99333999, '2026-03-07 15:49:55'),
(382, 1, 18.50840400, -69.99330900, '2026-03-07 15:57:31'),
(383, 1, 18.50840400, -69.99330900, '2026-03-07 16:03:07'),
(384, 1, 18.50840400, -69.99330900, '2026-03-07 16:08:21'),
(385, 1, 18.50853779, -69.99334413, '2026-03-07 16:14:13'),
(386, 1, 18.50843346, -69.99332171, '2026-03-07 16:24:05'),
(387, 1, 18.50854127, -69.99333999, '2026-03-07 16:29:26'),
(388, 1, 18.50840400, -69.99330900, '2026-03-07 16:34:29'),
(389, 1, 18.50840400, -69.99330900, '2026-03-07 16:39:35'),
(390, 1, 18.50840400, -69.99330900, '2026-03-07 16:46:58'),
(391, 1, 18.50840400, -69.99330900, '2026-03-07 16:56:58'),
(392, 1, 18.50840400, -69.99330900, '2026-03-07 17:02:28'),
(393, 1, 18.50840400, -69.99330900, '2026-03-07 17:10:39'),
(394, 1, 18.50840400, -69.99330900, '2026-03-07 17:17:39'),
(395, 1, 18.50840400, -69.99330900, '2026-03-07 17:24:55'),
(396, 1, 18.50840400, -69.99330900, '2026-03-07 17:30:34'),
(397, 1, 18.50840400, -69.99331300, '2026-03-07 17:37:39'),
(398, 1, 18.50840400, -69.99330900, '2026-03-07 17:44:26'),
(399, 1, 18.50840400, -69.99331300, '2026-03-07 17:53:48'),
(400, 1, 18.50840400, -69.99331300, '2026-03-07 18:01:45'),
(401, 1, 18.47476366, -69.90312812, '2026-03-10 14:22:14'),
(402, 1, 18.47476556, -69.90310063, '2026-03-10 14:30:28'),
(403, 1, 18.47474816, -69.90310691, '2026-03-10 16:01:53'),
(404, 9, 18.47477153, -69.90314337, '2026-03-31 14:22:44'),
(405, 9, 18.47476533, -69.90314538, '2026-03-31 14:29:17'),
(406, 9, 18.47477285, -69.90312908, '2026-03-31 14:35:50'),
(407, 9, 18.47477830, -69.90310161, '2026-03-31 14:42:23'),
(408, 9, 18.47476769, -69.90309569, '2026-03-31 14:51:33'),
(409, 9, 18.47477649, -69.90314800, '2026-03-31 14:57:43'),
(410, 9, 18.47476736, -69.90311348, '2026-03-31 15:06:43'),
(411, 9, 18.47477090, -69.90311583, '2026-03-31 15:15:10'),
(412, 9, 18.47476173, -69.90313099, '2026-03-31 15:24:33'),
(413, 9, 18.47476600, -69.90311800, '2026-03-31 15:31:13'),
(414, 9, 18.47477650, -69.90309824, '2026-03-31 15:41:13'),
(415, 9, 18.47479525, -69.90311225, '2026-03-31 16:24:37'),
(416, 1, 18.47477463, -69.90309215, '2026-03-31 16:27:43'),
(417, 1, 18.47478470, -69.90311536, '2026-03-31 16:37:37'),
(418, 1, 18.47478015, -69.90311849, '2026-03-31 16:43:04'),
(419, 1, 18.47478343, -69.90311431, '2026-03-31 16:53:04'),
(420, 1, 18.47478930, -69.90310709, '2026-03-31 16:59:13'),
(421, 1, 18.47477622, -69.90309945, '2026-03-31 17:05:00'),
(422, 1, 18.47477846, -69.90308443, '2026-03-31 17:16:58'),
(423, 1, 18.47478248, -69.90307613, '2026-03-31 17:24:55'),
(424, 1, 18.47477651, -69.90310530, '2026-03-31 17:34:12'),
(425, 1, 18.50411808, -69.88589099, '2026-03-31 18:10:44'),
(426, 1, 18.50411287, -69.88590042, '2026-03-31 18:16:11'),
(427, 1, 18.50408647, -69.88587383, '2026-03-31 18:24:23'),
(428, 1, 18.50411808, -69.88589099, '2026-03-31 18:30:41'),
(429, 1, 18.50413729, -69.88603246, '2026-03-31 18:40:34'),
(430, 1, 18.50411762, -69.88602853, '2026-03-31 18:50:34'),
(431, 1, 18.50411762, -69.88602336, '2026-03-31 19:00:34'),
(432, 1, 18.50411424, -69.88602340, '2026-03-31 19:08:54'),
(433, 1, 18.47477715, -69.90311434, '2026-04-01 07:30:18'),
(434, 1, 18.47478495, -69.90306359, '2026-04-01 07:38:41'),
(435, 1, 18.47478750, -69.90306238, '2026-04-01 07:44:37'),
(436, 1, 18.47478043, -69.90309370, '2026-04-01 07:50:31'),
(437, 1, 18.47478208, -69.90311777, '2026-04-01 08:00:23'),
(438, 1, 18.47478396, -69.90313121, '2026-04-01 08:10:24'),
(439, 1, 18.47478375, -69.90313216, '2026-04-01 08:20:05'),
(440, 1, 18.47478519, -69.90310416, '2026-04-01 08:29:38'),
(441, 1, 18.47477518, -69.90309005, '2026-04-01 09:32:10'),
(442, 1, 18.47478163, -69.90308985, '2026-04-01 09:40:03'),
(443, 1, 18.47478225, -69.90312611, '2026-04-01 09:46:15'),
(444, 1, 18.47476721, -69.90313560, '2026-04-01 09:53:17'),
(445, 1, 18.47477380, -69.90310192, '2026-04-01 10:01:30'),
(446, 1, 18.47478049, -69.90312093, '2026-04-01 10:07:19'),
(447, 1, 18.47478397, -69.90309235, '2026-04-01 10:16:59'),
(448, 1, 18.47477862, -69.90311320, '2026-04-01 10:25:37'),
(449, 1, 18.47477145, -69.90309983, '2026-04-01 10:35:38'),
(450, 1, 18.47478518, -69.90310745, '2026-04-01 10:45:37'),
(451, 1, 18.47477575, -69.90311740, '2026-04-01 10:50:47'),
(452, 1, 18.47477814, -69.90312961, '2026-04-01 10:57:50'),
(453, 1, 18.47478547, -69.90313010, '2026-04-01 11:07:16'),
(454, 1, 18.47477619, -69.90311378, '2026-04-01 11:17:15'),
(455, 1, 18.47478178, -69.90312466, '2026-04-01 11:27:15'),
(456, 1, 18.47477730, -69.90312765, '2026-04-01 11:34:25'),
(457, 1, 18.47477940, -69.90312636, '2026-04-01 11:41:17'),
(458, 1, 18.47476148, -69.90313027, '2026-04-01 11:51:09'),
(459, 1, 18.47477394, -69.90311804, '2026-04-01 12:01:10'),
(460, 1, 28.51470000, -81.36080000, '2026-04-02 11:57:39'),
(461, 1, 28.51470000, -81.36080000, '2026-04-02 11:57:39'),
(462, 9, 18.49160574, -69.88965383, '2026-04-02 12:00:06'),
(463, 9, 18.49158534, -69.88963635, '2026-04-02 12:09:08'),
(464, 9, 18.49160778, -69.88963741, '2026-04-02 12:14:20'),
(465, 9, 18.49160585, -69.88967559, '2026-04-02 12:20:45'),
(466, 9, 18.49159499, -69.88966093, '2026-04-02 12:36:23'),
(467, 9, 18.49159499, -69.88966093, '2026-04-02 12:36:23'),
(468, 9, 18.49159499, -69.88966093, '2026-04-02 12:36:23'),
(469, 1, 18.49158324, -69.88964482, '2026-04-02 12:37:19'),
(470, 1, 18.49159502, -69.88964412, '2026-04-02 12:47:12'),
(471, 1, 18.49209300, -69.88883600, '2026-04-02 13:57:08'),
(472, 1, 18.49209300, -69.88883600, '2026-04-02 13:57:08'),
(473, 1, 18.49217879, -69.88886900, '2026-04-02 14:08:00'),
(474, 1, 18.49209300, -69.88883600, '2026-04-02 14:28:41'),
(475, 1, 28.51470000, -81.36080000, '2026-04-02 14:38:02'),
(476, 1, 18.49353400, -69.88844300, '2026-04-02 14:48:01'),
(477, 9, 18.49213981, -69.88882033, '2026-04-02 15:00:22'),
(478, 9, 28.51470000, -81.36080000, '2026-04-02 15:10:20'),
(479, 9, 18.49247787, -69.88872883, '2026-04-02 15:20:19'),
(480, 1, 18.49242550, -69.89018200, '2026-04-03 13:52:38'),
(481, 1, 18.49242550, -69.89018200, '2026-04-03 14:01:12'),
(482, 1, 18.49233635, -69.89013801, '2026-04-03 14:11:12'),
(483, 1, 28.50353149, -81.37919650, '2026-04-06 15:42:49'),
(484, 1, 28.50353149, -81.37919650, '2026-04-06 15:42:49'),
(485, 20, 28.50418260, -81.37903440, '2026-04-06 15:44:47'),
(486, 20, 28.50417730, -81.37904810, '2026-04-06 15:46:39'),
(487, 1, 28.50349157, -81.37918986, '2026-04-06 15:52:39'),
(488, 20, 28.50419670, -81.37904250, '2026-04-06 15:52:40'),
(489, 1, 28.50355108, -81.37919817, '2026-04-06 16:02:38'),
(490, 20, 28.50417890, -81.37903380, '2026-04-06 16:03:54'),
(491, 1, 28.50362825, -81.37920874, '2026-04-06 16:12:38'),
(492, 20, 28.50417890, -81.37903380, '2026-04-06 16:13:53'),
(493, 1, 28.50355108, -81.37919817, '2026-04-06 16:22:38'),
(494, 20, 28.50417890, -81.37903380, '2026-04-06 16:27:03'),
(495, 1, 28.50355108, -81.37919817, '2026-04-06 16:32:38'),
(496, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(497, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(498, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(499, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(500, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(501, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(502, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(503, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(504, 1, 28.50351902, -81.37918700, '2026-04-06 17:18:22'),
(505, 20, 28.47179430, -81.37874130, '2026-04-06 20:37:32'),
(506, 20, 28.47179430, -81.37874130, '2026-04-06 20:37:32'),
(507, 20, 28.47179080, -81.37874790, '2026-04-06 20:46:21'),
(508, 1, 18.47478067, -69.90310175, '2026-04-07 07:31:59'),
(509, 1, 18.47478357, -69.90310947, '2026-04-07 07:38:30'),
(510, 1, 18.47478466, -69.90310153, '2026-04-07 08:22:05'),
(511, 1, 18.47478512, -69.90310590, '2026-04-07 08:31:54'),
(512, 1, 18.47477989, -69.90310119, '2026-04-07 08:41:54'),
(513, 1, 18.47477165, -69.90309393, '2026-04-07 08:47:39'),
(514, 20, 28.50418850, -81.37903160, '2026-04-07 09:18:28'),
(515, 20, 28.50418070, -81.37903350, '2026-04-07 09:18:47'),
(516, 20, 28.50414850, -81.37908470, '2026-04-07 09:28:40'),
(517, 20, 28.50415980, -81.37908500, '2026-04-07 09:43:53'),
(518, 20, 28.50404850, -81.37906910, '2026-04-07 10:10:20'),
(519, 20, 28.50404850, -81.37906910, '2026-04-07 10:10:20'),
(520, 20, 28.50416250, -81.37908430, '2026-04-07 13:02:34'),
(521, 20, 28.50416250, -81.37908430, '2026-04-07 13:02:34'),
(522, 1, 28.50373298, -81.37921040, '2026-04-07 16:19:46'),
(523, 20, 28.50418390, -81.37903460, '2026-04-07 16:25:33'),
(524, 1, 28.50344962, -81.37918393, '2026-04-07 16:29:38'),
(525, 20, 28.50418390, -81.37903460, '2026-04-07 16:31:45'),
(526, 1, 18.47476573, -69.90306997, '2026-04-07 16:43:10'),
(527, 1, 18.47476573, -69.90306997, '2026-04-07 16:43:10'),
(528, 1, 18.47476573, -69.90306997, '2026-04-07 16:43:10'),
(529, 1, 18.47478898, -69.90309451, '2026-04-07 16:52:50'),
(530, 20, 28.50403940, -81.37902940, '2026-04-07 17:02:22'),
(531, 1, 28.50371401, -81.37922206, '2026-04-07 17:02:55'),
(532, 1, 28.50371401, -81.37922206, '2026-04-07 17:02:55'),
(533, 1, 28.50371401, -81.37922206, '2026-04-07 17:02:55'),
(534, 1, 28.50371401, -81.37922206, '2026-04-07 17:02:55'),
(535, 1, 28.50371401, -81.37922206, '2026-04-07 17:02:55'),
(536, 1, 28.50351660, -81.37919535, '2026-04-07 17:09:38'),
(537, 1, 28.50350155, -81.37919010, '2026-04-07 17:19:38'),
(538, 1, 28.50371401, -81.37922206, '2026-04-07 17:29:38'),
(539, 1, 28.50362825, -81.37920874, '2026-04-07 17:39:38'),
(540, 1, 28.50371401, -81.37922206, '2026-04-07 17:49:39'),
(541, 1, 28.50360268, -81.37920642, '2026-04-07 17:59:38'),
(542, 1, 28.50371401, -81.37922206, '2026-04-07 18:09:37'),
(543, 1, 28.50371401, -81.37922206, '2026-04-07 18:19:38'),
(544, 1, 28.50360268, -81.37920642, '2026-04-07 18:29:39'),
(545, 1, 28.50360268, -81.37920642, '2026-04-07 18:39:38'),
(546, 1, 28.50357485, -81.37920544, '2026-04-07 18:49:38'),
(547, 1, 28.50342562, -81.37919108, '2026-04-07 18:59:38'),
(548, 1, 28.50372677, -81.37922464, '2026-04-07 19:09:40'),
(549, 20, 28.50420470, -81.37904450, '2026-04-07 20:11:29'),
(550, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(551, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(552, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(553, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(554, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(555, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(556, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(557, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(558, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(559, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(560, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(561, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(562, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(563, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:02'),
(564, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(565, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(566, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(567, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(568, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(569, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(570, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(571, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(572, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(573, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(574, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(575, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(576, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(577, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(578, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(579, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(580, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(581, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(582, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(583, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(584, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(585, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(586, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(587, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(588, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(589, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(590, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(591, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(592, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(593, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(594, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(595, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(596, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(597, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(598, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(599, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(600, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(601, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(602, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(603, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(604, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(605, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(606, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(607, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(608, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(609, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(610, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(611, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(612, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(613, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(614, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(615, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(616, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(617, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(618, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(619, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(620, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(621, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(622, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(623, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(624, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(625, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(626, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(627, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(628, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(629, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(630, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(631, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(632, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(633, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(634, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(635, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(636, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(637, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(638, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(639, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(640, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(641, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(642, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(643, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:03'),
(644, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(645, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(646, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(647, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(648, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(649, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(650, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(651, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(652, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(653, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(654, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(655, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(656, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(657, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(658, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(659, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(660, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(661, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(662, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(663, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(664, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(665, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(666, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(667, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(668, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(669, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(670, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(671, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(672, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(673, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(674, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(675, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(676, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(677, 1, 28.50362448, -81.37919908, '2026-04-08 09:10:04'),
(678, 1, 28.50377469, -81.37922974, '2026-04-08 09:19:04'),
(679, 20, 28.50424930, -81.37905150, '2026-04-08 10:56:38'),
(680, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(681, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(682, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(683, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(684, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(685, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(686, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(687, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(688, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(689, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(690, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(691, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(692, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(693, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(694, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(695, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(696, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(697, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(698, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(699, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(700, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(701, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(702, 1, 28.50369215, -81.37920666, '2026-04-08 11:17:32'),
(703, 20, 28.50422340, -81.37911500, '2026-04-09 09:21:48'),
(704, 1, 18.47478945, -69.90310919, '2026-04-09 10:52:42'),
(705, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:32'),
(706, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(707, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(708, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(709, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(710, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(711, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(712, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(713, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(714, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(715, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(716, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(717, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(718, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(719, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(720, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(721, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(722, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(723, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(724, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(725, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(726, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(727, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(728, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(729, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(730, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(731, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(732, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(733, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(734, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(735, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(736, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(737, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(738, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(739, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(740, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(741, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(742, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(743, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(744, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(745, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(746, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(747, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(748, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(749, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(750, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(751, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(752, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(753, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(754, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(755, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(756, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(757, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(758, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(759, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(760, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(761, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(762, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(763, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(764, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(765, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(766, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(767, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(768, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(769, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(770, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(771, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(772, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(773, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(774, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(775, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(776, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(777, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:33'),
(778, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(779, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(780, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(781, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(782, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(783, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(784, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(785, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(786, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(787, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(788, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(789, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(790, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(791, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(792, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(793, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(794, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(795, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(796, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(797, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(798, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(799, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(800, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(801, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(802, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(803, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(804, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(805, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(806, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(807, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(808, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(809, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(810, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(811, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(812, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(813, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(814, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(815, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(816, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(817, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(818, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(819, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(820, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(821, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(822, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(823, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(824, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(825, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(826, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(827, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(828, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(829, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(830, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(831, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(832, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(833, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(834, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(835, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(836, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:34'),
(837, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(838, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(839, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(840, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(841, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(842, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(843, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(844, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(845, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(846, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(847, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(848, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(849, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(850, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(851, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(852, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(853, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(854, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(855, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(856, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(857, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(858, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(859, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(860, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(861, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(862, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(863, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(864, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(865, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(866, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(867, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(868, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(869, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(870, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(871, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(872, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(873, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(874, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(875, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(876, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(877, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(878, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(879, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(880, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(881, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(882, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(883, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(884, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(885, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(886, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(887, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(888, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(889, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(890, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(891, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(892, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(893, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(894, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(895, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(896, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(897, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(898, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(899, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(900, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(901, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:35'),
(902, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(903, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(904, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(905, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(906, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(907, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(908, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(909, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(910, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(911, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(912, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(913, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(914, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(915, 1, 28.47157021, -81.37867414, '2026-04-09 19:00:36'),
(916, 1, 18.50399500, -69.88592300, '2026-04-09 22:12:22'),
(917, 1, 18.50399500, -69.88592300, '2026-04-09 22:12:22'),
(918, 1, 18.50399500, -69.88592300, '2026-04-09 22:12:22'),
(919, 1, 18.50399500, -69.88592300, '2026-04-09 22:12:22'),
(920, 1, 18.50399500, -69.88592300, '2026-04-09 22:12:22'),
(921, 1, 18.50399500, -69.88592300, '2026-04-09 22:12:22'),
(922, 1, 18.50399500, -69.88592300, '2026-04-09 22:12:22'),
(923, 1, 18.50406285, -69.88600380, '2026-04-09 22:26:22'),
(924, 1, 18.50406285, -69.88600380, '2026-04-09 22:31:28'),
(925, 20, 28.50436590, -81.37902900, '2026-04-15 10:50:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `message`, `related_id`, `is_read`, `read_at`, `created_at`) VALUES
(1, 9, 'absence_reviewed', 'Tu ausencia fue rechazado por admin.', 37, 1, '2026-03-31 14:22:44', '2026-03-10 16:02:17'),
(2, 1, 'absence_reported', 'pedro reportó una ausencia (2026-03-10).', 38, 1, '2026-03-31 17:02:29', '2026-03-10 16:07:35'),
(3, 1, 'absence_reported', 'Yormaikel reportó una ausencia (2026-03-05).', 49, 1, '2026-04-01 11:52:37', '2026-04-01 11:51:56'),
(4, 17, 'absence_reviewed', 'Tu ausencia fue aprobado por admin.', 49, 0, NULL, '2026-04-02 12:37:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `geofence_radius` int(10) UNSIGNED NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `projects`
--

INSERT INTO `projects` (`id`, `name`, `latitude`, `longitude`, `geofence_radius`, `created_at`) VALUES
(6, 'Work stages ', 19.45610761, -70.66697775, 100, '2026-02-28 18:16:40'),
(8, 'Lake Carter', 28.63875119, -81.53941221, 234, '2026-03-02 19:56:03'),
(9, 'Golden Eagle Storage', 28.37921280, -81.68714420, 331, '2026-03-02 19:56:52'),
(10, 'Oakland Exchange', 28.54631390, -81.64308170, 274, '2026-03-02 19:58:26'),
(14, 'Forest Lake Liquor Store', 28.15557476, -81.59193589, 2240, '2026-04-06 16:22:09'),
(15, '121  Towne Center Sanford FL', 28.15557476, -81.59193589, 100, '2026-04-06 16:23:41'),
(16, '728 Forest', 28.15557476, -81.59193589, 100, '2026-04-06 16:25:37'),
(17, '6232 Lynette St', 28.15557476, -81.59193589, 100, '2026-04-06 16:26:38'),
(18, '165 Drennen Warehouse', 28.50402706, -81.37872985, 100, '2026-04-06 17:12:42'),
(19, 'John Young Commercial', 28.41836969, -81.42179565, 100, '2026-04-06 17:58:23'),
(20, 'Florala', 28.50406820, -81.37895996, 100, '2026-04-06 18:03:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `project_qrs`
--

CREATE TABLE `project_qrs` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `location` varchar(255) NOT NULL,
  `qr_content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `entry_time_required` time DEFAULT NULL,
  `exit_time_optional` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `project_qrs`
--

INSERT INTO `project_qrs` (`id`, `project_id`, `action_type`, `location`, `qr_content`, `created_at`, `entry_time_required`, `exit_time_optional`) VALUES
(27, 10, 'manual', 'Manual Entry', '{\"project_id\":10,\"source\":\"manual_attendance\"}', '2026-03-10 20:06:52', NULL, NULL),
(28, 6, 'manual', 'Manual Entry', '{\"project_id\":6,\"source\":\"manual_attendance\"}', '2026-04-01 13:49:26', NULL, NULL),
(29, 9, 'manual', 'Manual Entry', '{\"project_id\":9,\"source\":\"manual_attendance\"}', '2026-04-01 14:25:21', NULL, NULL),
(30, 8, 'manual', 'Manual Entry', '{\"project_id\":8,\"source\":\"manual_attendance\"}', '2026-04-01 14:26:59', NULL, NULL),
(31, 17, 'manual', 'Manual Entry', '{\"project_id\":17,\"source\":\"manual_attendance\"}', '2026-04-06 16:49:28', NULL, NULL),
(33, 14, 'manual', 'Manual Entry', '{\"project_id\":14,\"source\":\"manual_attendance\"}', '2026-04-06 16:53:59', NULL, NULL),
(34, 15, 'manual', 'Manual Entry', '{\"project_id\":15,\"source\":\"manual_attendance\"}', '2026-04-06 16:58:31', NULL, NULL),
(35, 18, 'manual', 'Manual Entry', '{\"project_id\":18,\"source\":\"manual_attendance\"}', '2026-04-06 17:45:20', NULL, NULL),
(36, 19, 'manual', 'Manual Entry', '{\"project_id\":19,\"source\":\"manual_attendance\"}', '2026-04-06 17:59:55', NULL, NULL),
(37, 20, 'manual', 'Manual Entry', '{\"project_id\":20,\"source\":\"manual_attendance\"}', '2026-04-06 18:04:16', NULL, NULL),
(38, 16, 'manual', 'Manual Entry', '{\"project_id\":16,\"source\":\"manual_attendance\"}', '2026-04-06 18:13:31', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Tiene acceso total al sistema'),
(2, 'user', 'Usuario estándar con permisos básicos'),
(4, 'special', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `profile_pic_url` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role_id`, `profile_pic_url`, `deleted_at`) VALUES
(1, 'admin', '$2y$10$.kBYZxtnN1N1eopFm6EDse40zVeSS0FhUHPG1l3gf/ooXIWSCt4FW', 1, NULL, NULL),
(9, 'pedro', '$2y$10$rrmXsyuyztBpzuabHi8vVeVxGjuwUXHZRkV49zVebC4SjdowBLo9y', 2, NULL, '2026-04-06 14:28:48'),
(11, 'juan', '$2y$10$MyWffHnl4vpFCWc.n8t1uu4rmNjgKl4JySMpDckhmZjCVPgs5eymq', 1, NULL, NULL),
(12, 'Maikol', '$2y$10$GfHcETFVIghmAjIXk96GW.mzWeFNwjiY/0Wc8zWAp.w3MWpcPQN3u', 2, NULL, NULL),
(13, 'Henry', '$2y$10$yyz9aaRJskHPXaZ6V1ykJeacK/bFMrZRRM155vqLN/bASMdimfxwC', 2, NULL, NULL),
(14, 'JoseR', '$2y$10$gTVbz1xreu5p22JVr8qBZucniKV0dPgVVmvXPXLd3y3u6JjrhMGWq', 2, NULL, NULL),
(15, 'Sean', '$2y$10$o/XUC60ray06XTQnRTrn..teyazSCTCy/rnrr0ejU36w/Pl0VXmPy', 2, NULL, NULL),
(16, 'Kelvin', '$2y$10$WBHCjfVp.qauR2ROcoEEDuXZbqWYC2SSN2gUL5Xjp8xCbFv3NXI32', 2, NULL, NULL),
(17, 'Yormaikel', '$2y$10$O1TOiI0.98pjC4vqvUIj6eBrE1zGXZSKjRX.49iSd8q58z0blssT6', 2, NULL, NULL),
(18, 'DavidC', '$2y$10$LRkmaC.4oEEDwspTOQwxjOuWL89NGtHEwHymrSQRfCd11Exf2acvC', 2, NULL, NULL),
(19, 'isaac', '$2y$10$g9Bzz0fS2A5xZR3myd05w.pagowa8Hx.83f8qcPHPEe.vnJLNu1kO', 2, NULL, NULL),
(20, 'Guillermo', '$2y$10$fOFdccAtSHaSiCaVetJzLOwkRDXjTVMA30Qrs/DXkAA3161EdInRS', 2, NULL, NULL),
(21, 'benito', '$2y$10$62J0ouJ5aRZ0joUY0cbR..XhIC1Mx3WxEBo5x1fq7gyGBxWWLNxsi', 4, NULL, '2026-04-07 07:32:23');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `absences`
--
ALTER TABLE `absences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_absences_user` (`user_id`),
  ADD KEY `idx_absences_project` (`project_id`),
  ADD KEY `idx_absences_status` (`status`),
  ADD KEY `idx_absences_dates` (`date_start`,`date_end`),
  ADD KEY `fk_absences_reviewer` (`reviewed_by`),
  ADD KEY `idx_absences_user_id` (`user_id`);

--
-- Indices de la tabla `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_attendance_manual_dedupe` (`user_id`,`project_qr_id`,`type`,`rounded_time`),
  ADD KEY `fk_attendance_project_qr` (`project_qr_id`),
  ADD KEY `idx_user_time` (`user_id`,`original_time`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_manual_dedup` (`user_id`,`type`,`original_time`,`entry_source`);

--
-- Indices de la tabla `location_history`
--
ALTER TABLE `location_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indices de la tabla `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`),
  ADD KEY `idx_user_time` (`username`,`attempted_at`);

--
-- Indices de la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`);

--
-- Indices de la tabla `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `project_qrs`
--
ALTER TABLE `project_qrs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_role` (`role_id`),
  ADD KEY `idx_users_deleted_at` (`deleted_at`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `absences`
--
ALTER TABLE `absences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT de la tabla `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=356;

--
-- AUTO_INCREMENT de la tabla `location_history`
--
ALTER TABLE `location_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=926;

--
-- AUTO_INCREMENT de la tabla `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `project_qrs`
--
ALTER TABLE `project_qrs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `absences`
--
ALTER TABLE `absences`
  ADD CONSTRAINT `fk_absences_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_absences_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_absences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD CONSTRAINT `fk_attendance_project_qr` FOREIGN KEY (`project_qr_id`) REFERENCES `project_qrs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `location_history`
--
ALTER TABLE `location_history`
  ADD CONSTRAINT `location_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `project_qrs`
--
ALTER TABLE `project_qrs`
  ADD CONSTRAINT `project_qrs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
