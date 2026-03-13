// app.js - Lógica para gerente.html

let tasaDolar = null; // Variable global para la tasa del dólar

// Mostrar modal de tasa al cargar la página
document.addEventListener('DOMContentLoaded', () => {
  const modalTasa = new bootstrap.Modal(document.getElementById('modalTasa'));
  modalTasa.show();
});

// Guardar tasa inicial
document.getElementById('formTasa').addEventListener('submit', (e) => {
  e.preventDefault();
  const valor = document.getElementById('inputTasa').value;

  if (valor && parseFloat(valor) > 0) {
    tasaDolar = parseFloat(valor).toFixed(2);
    document.getElementById('tasaDolar').textContent = `${tasaDolar} Bs`;
    bootstrap.Modal.getInstance(document.getElementById('modalTasa')).hide();
  } else {
    alert("Por favor ingresa un valor válido para la tasa.");
  }
});

// Cambiar tasa desde el botón
document.getElementById('btnCambiarTasa').addEventListener('click', () => {
  const modalTasa = new bootstrap.Modal(document.getElementById('modalTasa'));
  modalTasa.show();
});

// Botones principales (placeholders)
document.getElementById('btnUsuarios').addEventListener('click', () => {
  alert("Sección de Registro de Usuarios (pendiente de implementación).");
  // Aquí luego redirigirá a usuarios.html
});

document.getElementById('btnMenu').addEventListener('click', () => {
  alert("Sección de Gestión del Menú (pendiente de implementación).");
  // Aquí luego redirigirá a menu.html
});

document.getElementById('btnInventario').addEventListener('click', () => {
  alert("Sección de Inventario (pendiente de implementación).");
  // Aquí luego redirigirá a inventario.html
});

document.getElementById('btnReportes').addEventListener('click', () => {
  alert("Sección de Reportes (pendiente de implementación).");
  // Aquí luego redirigirá a reportes.html
});

// Logout
document.getElementById('btnLogout').addEventListener('click', () => {
  alert("Sesión cerrada.");
  window.location.href = "../index.html"; // Regresa al login
});
