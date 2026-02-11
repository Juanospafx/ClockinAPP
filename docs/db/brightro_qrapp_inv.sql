-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 10-02-2026 a las 23:27:17
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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `location` varchar(100) NOT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lng` decimal(9,6) DEFAULT NULL,
  `type` enum('entrada','salida','check') NOT NULL,
  `rawTime` datetime NOT NULL,
  `roundedTime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `type` enum('entry','exit','pause','resume','start_lunch','end_lunch') NOT NULL,
  `original_time` datetime NOT NULL,
  `rounded_time` datetime NOT NULL,
  `total_duration` int(11) DEFAULT NULL COMMENT 'Total work duration in minutes for the day',
  `lunch_duration` int(11) DEFAULT NULL COMMENT 'Duration of lunch break in minutes',
  `project_qr_id` int(11) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1 = active, 2 = paused, 3 = exited',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(295, 10, 18.50404335, -69.88588975, '2025-10-07 00:34:11'),
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
(312, 1, 18.48569325, -69.90439797, '2026-02-09 11:57:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `projects`
--

INSERT INTO `projects` (`id`, `name`, `created_at`) VALUES
(2, 'Work stages ', '2025-07-17 15:15:31');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `project_qrs`
--

INSERT INTO `project_qrs` (`id`, `project_id`, `action_type`, `location`, `qr_content`, `created_at`) VALUES
(17, 2, '', '', '{\"project_id\":\"2\"}', '2025-09-29 05:12:38');

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
  `profile_pic_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role_id`, `profile_pic_url`) VALUES
(1, 'admin', '$2y$10$.kBYZxtnN1N1eopFm6EDse40zVeSS0FhUHPG1l3gf/ooXIWSCt4FW', 1, NULL),
(9, 'pedro', '$2y$10$rrmXsyuyztBpzuabHi8vVeVxGjuwUXHZRkV49zVebC4SjdowBLo9y', 2, NULL),
(10, 'juan', '$2y$10$w/BUQnw7S8vl3ptUS7zFleZHDYO7zsd91Rq9JlzgGXEQGqOqMS11K', 4, NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`);

--
-- Indices de la tabla `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attendance_project_qr` (`project_qr_id`),
  ADD KEY `idx_user_time` (`user_id`,`original_time`),
  ADD KEY `idx_status` (`status`);

--
-- Indices de la tabla `location_history`
--
ALTER TABLE `location_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `fk_users_role` (`role_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `location_history`
--
ALTER TABLE `location_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=313;

--
-- AUTO_INCREMENT de la tabla `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `project_qrs`
--
ALTER TABLE `project_qrs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`);

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
