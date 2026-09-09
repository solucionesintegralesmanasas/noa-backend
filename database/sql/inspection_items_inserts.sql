-- Documentos
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'DOCUMENTOS', 'Licencia de tránsito'),
(UUID(), 'DOCUMENTOS', 'Revisión técnico mecánica'),
(UUID(), 'DOCUMENTOS', 'Seguro obligatorio'),
(UUID(), 'DOCUMENTOS', 'Seguro terceros'),
(UUID(), 'DOCUMENTOS', 'Licencia de conducción'),
(UUID(), 'DOCUMENTOS', 'Planos, mapas rutas, GPS'),
(UUID(), 'DOCUMENTOS', 'Contactos de Emergencia');

-- Dotación
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'DOTACION', 'Botas'),
(UUID(), 'DOTACION', 'Chaleco fotoluminiscente'),
(UUID(), 'DOTACION', 'Guantes'),
(UUID(), 'DOTACION', 'Protección auditiva'),
(UUID(), 'DOTACION', 'Monogafas'),
(UUID(), 'DOTACION', 'Radio teléfono / celular'),
(UUID(), 'DOTACION', 'Hidratación');

-- Vidrios/Espejos
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'VIDRIOS_ESPEJOS', 'Parabrisas'),
(UUID(), 'VIDRIOS_ESPEJOS', 'Limpiaparabrisas'),
(UUID(), 'VIDRIOS_ESPEJOS', 'Laterales'),
(UUID(), 'VIDRIOS_ESPEJOS', 'Vidrio Trasero'),
(UUID(), 'VIDRIOS_ESPEJOS', 'Lavaparabrisas trasero'),
(UUID(), 'VIDRIOS_ESPEJOS', 'Espejo retrovisor'),
(UUID(), 'VIDRIOS_ESPEJOS', 'Espejos laterales');

-- Emergencias
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'EMERGENCIAS', 'Kit de Accidentes'),
(UUID(), 'EMERGENCIAS', 'Cámara fotográfica'),
(UUID(), 'EMERGENCIAS', 'Linterna con pilas'),
(UUID(), 'EMERGENCIAS', 'Bolígrafo');

-- Extintor
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'EXTINTOR', 'Pin de Seguridad'),
(UUID(), 'EXTINTOR', 'Cargado'),
(UUID(), 'EXTINTOR', 'Vigente');

-- Herramientas
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'HERRAMIENTAS', 'Triángulos Reflectores (2)'),
(UUID(), 'HERRAMIENTAS', 'Gato'),
(UUID(), 'HERRAMIENTAS', 'Caja de herramientas'),
(UUID(), 'HERRAMIENTAS', 'Cruceta'),
(UUID(), 'HERRAMIENTAS', 'Tacos'),
(UUID(), 'HERRAMIENTAS', 'Llanta de repuesto'),
(UUID(), 'HERRAMIENTAS', 'Cables de Arranque');

-- Luces
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'LUCES', 'Bajas'),
(UUID(), 'LUCES', 'Plenas'),
(UUID(), 'LUCES', 'Direccionales'),
(UUID(), 'LUCES', 'Cocuyos'),
(UUID(), 'LUCES', 'Reversa'),
(UUID(), 'LUCES', 'Anti-niebla'),
(UUID(), 'LUCES', 'Luces de cabina'),
(UUID(), 'LUCES', 'Emergencia'),
(UUID(), 'LUCES', 'Tercer Stop');

-- Fluidos
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'FLUIDOS', 'Aceite motor'),
(UUID(), 'FLUIDOS', 'Último cambio'),
(UUID(), 'FLUIDOS', 'Dirección'),
(UUID(), 'FLUIDOS', 'Líquido de frenos'),
(UUID(), 'FLUIDOS', 'Refrigerante'),
(UUID(), 'FLUIDOS', 'Agua parabrisas'),
(UUID(), 'FLUIDOS', 'Nivel combustible'),
(UUID(), 'FLUIDOS', 'Fugas de lubricantes'),
(UUID(), 'FLUIDOS', 'Fugas de Agua');

-- Neumáticos
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'NEUMATICOS', 'Delantero Derecho'),
(UUID(), 'NEUMATICOS', 'Delantero Izquierdo'),
(UUID(), 'NEUMATICOS', 'Trasero Derecho'),
(UUID(), 'NEUMATICOS', 'Trasero Izquierdo'),
(UUID(), 'NEUMATICOS', 'Llanta repuesto');

-- Presión
INSERT INTO inspection_items (uuid, category, item_name) VALUES
(UUID(), 'PRESION', 'Delantero Derecho'),
(UUID(), 'PRESION', 'Delantero Izquierdo'),
(UUID(), 'PRESION', 'Trasero Derecho'),
(UUID(), 'PRESION', 'Trasero Izquierdo'),
(UUID(), 'PRESION', 'Llanta repuesto');
