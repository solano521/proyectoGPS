-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 17-11-2026 a las 23:05:24
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `alerta_campus`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas_atencion`
--

CREATE TABLE `areas_atencion` (
  `id_area` int(11) NOT NULL,
  `nombre_area` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `areas_atencion`
--

INSERT INTO `areas_atencion` (`id_area`, `nombre_area`) VALUES
(1, 'Centro de Cómputo / Redes'),
(2, 'Mantenimiento e Infraestructura'),
(4, 'Recursos Materiales y Mobiliario'),
(3, 'Seguridad y Vigilancia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `nombre_categoria` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `id_area`, `nombre_categoria`) VALUES
(1, 1, 'Falla de Internet'),
(2, 1, 'Equipo de Cómputo Dañado'),
(3, 4, 'Mobiliario Dañado'),
(4, 2, 'Sanitarios y Agua'),
(5, 2, 'Falla Eléctrica'),
(6, 3, 'Enjambre'),
(7, 3, 'Fuga de Gas'),
(8, 3, 'Fuga de Químicos'),
(9, 3, 'Incendio'),
(10, 3, 'Amenaza de Bomba');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes`
--

CREATE TABLE `reportes` (
  `id_reporte` int(11) NOT NULL,
  `folio` varchar(25) NOT NULL,
  `id_usuario_reporta` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_ubicacion` int(11) NOT NULL,
  `id_area_asignada` int(11) NOT NULL,
  `id_tecnico_asignado` int(11) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `prioridad` enum('Baja','Media','Alta','Critica') DEFAULT 'Media',
  `estatus` enum('Pendiente','En Proceso','Resuelto','Cerrado') DEFAULT 'Pendiente',
  `evidencia_cierre` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reportes`
--

INSERT INTO `reportes` (`id_reporte`, `folio`, `id_usuario_reporta`, `id_categoria`, `id_ubicacion`, `id_area_asignada`, `id_tecnico_asignado`, `titulo`, `descripcion`, `prioridad`, `estatus`, `evidencia_cierre`, `fecha_creacion`) VALUES
(1, 'ITZ-2026-5134', 1, 1, 1, 1, 2, 'Falla de Internet / Wi-Fi', 'no hay internet en todo el edificio', 'Media', 'Resuelto', 'Incidencia atendida y finalizada por el departamento.', '2026-11-16 04:32:52'),
(2, 'ITZ-2026-3371', 1, 4, 3, 2, NULL, 'Falla Eléctrica / Luminarias', 'esta fallando la luz que esta al lado derecho al fondo', 'Media', 'Resuelto', 'Falla de mantenimiento/sanitarios reparada y finalizada.', '2026-11-16 04:33:45'),
(3, 'ITZ-2026-5932', 1, 1, 3, 1, NULL, 'Falla de Internet / Wi-Fi', 'no cntamos con internet', 'Media', 'Resuelto', 'Incidencia atendida y finalizada por el departamento.', '2026-11-16 05:24:16'),
(4, 'ITZ-2026-3910', 3, 5, 2, 3, NULL, 'Objeto Extraviado / Seguridad', 'enjambre de abejas', 'Critica', 'Pendiente', NULL, '2026-11-16 05:24:48'),
(5, 'ITZ-2026-7656', 3, 5, 2, 3, NULL, 'Objeto Extraviado / Seguridad', 'enjambre de abejas', 'Critica', 'Pendiente', NULL, '2026-11-16 05:33:27'),
(6, 'ITZ-2026-4903', 3, 1, 1, 1, NULL, 'Falla de Internet / Wi-Fi', 'no hay unternwet ára nada', 'Media', 'Resuelto', 'Falla de red/cómputo reparada y finalizada.', '2026-11-16 05:39:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(5, 'Administrador'),
(2, 'Docente'),
(1, 'Estudiante'),
(4, 'Jefe de Departamento'),
(3, 'Operativo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubicaciones`
--

CREATE TABLE `ubicaciones` (
  `id_ubicacion` int(11) NOT NULL,
  `edificio` varchar(100) NOT NULL,
  `detalle_lugar` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ubicaciones`
--

INSERT INTO `ubicaciones` (`id_ubicacion`, `edificio`, `detalle_lugar`) VALUES
(1, 'Edificio 1', ''),
(2, 'Edificio 2', ''),
(3, 'Edificio 3', ''),
(4, 'Edificio 4', ''),
(5, 'Edificio 5', ''),
(6, 'Edificio 6', ''),
(7, 'Edificio 7', ''),
(8, 'Edificio 8', ''),
(9, 'Edificio 9', ''),
(10, 'Edificio 10', ''),
(11, 'Edificio 11', ''),
(12, 'Laboratorio de Cómputo', ''),
(13, 'Laboratorio de Bioquímica', ''),
(14, 'Biblioteca', ''),
(15, 'Recursos Materiales', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `id_area` int(11) DEFAULT NULL,
  `identificador_institucional` varchar(50) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `id_rol`, `id_area`, `identificador_institucional`, `nombre_completo`, `correo`, `password_hash`, `activo`) VALUES
(1, 1, NULL, '21090001', 'Iván Solano Bernal', 'ivan@zacatepec.tecnm.mx', '123456', 1),
(2, 3, 1, 'EMP-001', 'Técnico Sistemas', 'sistemas@zacatepec.tecnm.mx', '123456', 1),
(3, 4, 1, 'JEF-001', 'Jefe Sistemas', 'jefe@zacatepec.tecnm.mx', '123456', 1),
(4, 5, NULL, 'ADM-001', 'Administrador General', 'admin@zacatepec.tecnm.mx', '123456', 1),
(5, 4, NULL, 'DEP-MANT-01', 'Depto. Mantenimiento', 'mantenimiento@zacatepec.tecnm.mx', '123456', 1),
(7, 2, NULL, 'DOC-ITZ-01', 'Prof. Carlos Mendoza', 'carlos.mendoza@zacatepec.tecnm.mx', '123456', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `areas_atencion`
--
ALTER TABLE `areas_atencion`
  ADD PRIMARY KEY (`id_area`),
  ADD UNIQUE KEY `nombre_area` (`nombre_area`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD PRIMARY KEY (`id_reporte`),
  ADD UNIQUE KEY `folio` (`folio`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  ADD PRIMARY KEY (`id_ubicacion`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `areas_atencion`
--
ALTER TABLE `areas_atencion`
  MODIFY `id_area` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `reportes`
--
ALTER TABLE `reportes`
  MODIFY `id_reporte` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `ubicaciones`
--
ALTER TABLE `ubicaciones`
  MODIFY `id_ubicacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
