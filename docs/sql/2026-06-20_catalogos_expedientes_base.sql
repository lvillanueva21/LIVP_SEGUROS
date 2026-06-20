-- LIVP_SEGUROS - Catalogos base para expedientes
-- Ejecucion manual en phpMyAdmin.
-- No contiene DROP, TRUNCATE, DELETE ni datos semilla.

CREATE TABLE `seg_tipos_seguro` (
  `id` int(11) NOT NULL,
  `ramo_id` int(11) NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(160) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ejemplo_uso` varchar(255) DEFAULT NULL,
  `orden_visual` int(11) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_usuario_externo_id` int(11) DEFAULT NULL,
  `actualizado_por_usuario_externo_id` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL,
  `actualizado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `seg_estados_expediente` (
  `id` int(11) NOT NULL,
  `codigo` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ejemplo_uso` varchar(255) DEFAULT NULL,
  `color_etiqueta` char(7) NOT NULL DEFAULT '#6c757d',
  `orden_visual` int(11) NOT NULL DEFAULT 0,
  `es_inicial` tinyint(1) NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_usuario_externo_id` int(11) DEFAULT NULL,
  `actualizado_por_usuario_externo_id` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL,
  `actualizado_en` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `seg_tipos_seguro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seg_tipos_seguro_codigo` (`codigo`),
  ADD UNIQUE KEY `uq_seg_tipos_seguro_ramo_nombre` (`ramo_id`,`nombre`),
  ADD KEY `idx_seg_tipos_seguro_ramo` (`ramo_id`),
  ADD KEY `idx_seg_tipos_seguro_estado` (`estado`),
  ADD KEY `idx_seg_tipos_seguro_orden` (`orden_visual`);

ALTER TABLE `seg_estados_expediente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seg_estados_expediente_codigo` (`codigo`),
  ADD UNIQUE KEY `uq_seg_estados_expediente_nombre` (`nombre`),
  ADD KEY `idx_seg_estados_expediente_estado` (`estado`),
  ADD KEY `idx_seg_estados_expediente_inicial` (`es_inicial`,`estado`),
  ADD KEY `idx_seg_estados_expediente_orden` (`orden_visual`);

ALTER TABLE `seg_tipos_seguro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `seg_estados_expediente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `seg_tipos_seguro`
  ADD CONSTRAINT `fk_seg_tipos_seguro_ramo` FOREIGN KEY (`ramo_id`) REFERENCES `seg_ramos` (`id`);
