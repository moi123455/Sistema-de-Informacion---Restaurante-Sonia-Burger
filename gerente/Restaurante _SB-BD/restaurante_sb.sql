

-- Tabla de usuarios
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  correo VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  rol ENUM('gerente','cajero') NOT NULL
);

-- Tabla de menú
CREATE TABLE menu (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  precio_usd DECIMAL(10,2) NOT NULL,
  imagen VARCHAR(255)
);

-- Tabla de pedidos
CREATE TABLE pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente VARCHAR(100) NOT NULL,
  telefono VARCHAR(20),
  metodo_pago VARCHAR(50) NOT NULL,
  plato_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  estado ENUM('pendiente','entregado','cancelado') DEFAULT 'pendiente',
  fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plato_id) REFERENCES menu(id)
);

-- Tabla de reportes (cada cierre de caja)
CREATE TABLE reportes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fecha_reporte DATETIME DEFAULT CURRENT_TIMESTAMP,
  tasa_bcv DECIMAL(10,2) NOT NULL,
  pedidos_json TEXT -- aquí guardamos los pedidos confirmados del cierre
);

-- Tabla de gastos (para pérdidas)
CREATE TABLE gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  concepto VARCHAR(100) NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ===============================
-- Datos iniciales
-- ===============================

-- Usuario Gerente
INSERT INTO usuarios (nombre, correo, password, rol)
VALUES ('Administrador', 'gerente@sb.com', '1234', 'gerente');

-- Usuario Cajero
INSERT INTO usuarios (nombre, correo, password, rol)
VALUES ('Cajero Principal', 'cajero@sb.com', '1234', 'cajero');

-- Menú de prueba
INSERT INTO menu (nombre, precio_usd, imagen) VALUES
('Hamburguesa Clásica', 5.00, 'hamburguesa.jpg'),
('Perro Caliente', 3.50, 'perro.jpg'),
('Refresco', 1.50, 'refresco.jpg');

-- Gastos de prueba
INSERT INTO gastos (concepto, monto) VALUES
('Compra de insumos', 20.00),
('Reposición de refrescos', 10.00);
