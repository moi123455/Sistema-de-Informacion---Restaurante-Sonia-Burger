// app.js - Base inicial para login y roles

let currentRole = null; // 'gerente' o 'cajero'

// Abrir modal de selección de rol
document.getElementById('btnOpenLogin').addEventListener('click', () => {
  new bootstrap.Modal(document.getElementById('modalRol')).show();
});

// Selección de rol
document.getElementById('btnGerente').addEventListener('click', () => {
  currentRole = 'gerente';
  bootstrap.Modal.getInstance(document.getElementById('modalRol')).hide();
  new bootstrap.Modal(document.getElementById('modalLogin')).show();
});

document.getElementById('btnCajero').addEventListener('click', () => {
  currentRole = 'cajero';
  bootstrap.Modal.getInstance(document.getElementById('modalRol')).hide();
  new bootstrap.Modal(document.getElementById('modalLogin')).show();
});

// LOGIN simulado (más adelante se conecta a MySQL)
document.getElementById('formLogin').addEventListener('submit', (e) => {
  e.preventDefault();
  const usuario = document.getElementById('loginUser').value;
  const password = document.getElementById('loginPassword').value;

  // ⚠️ Simulación: aquí luego se validará contra la BD
  if (usuario && password) {
    bootstrap.Modal.getInstance(document.getElementById('modalLogin')).hide();

    // Mostrar info en header
    document.getElementById('loginItem').style.display = 'none';
    document.getElementById('userItem').style.display = 'inline-block';
    document.getElementById('userName').textContent = usuario;
    document.getElementById('roleBadge').style.display = 'inline-block';
    document.getElementById('roleBadge').textContent = currentRole.toUpperCase();

    // Redirección según rol
    if (currentRole === 'gerente') {
      window.location.href = "gerente.html"; // página del gerente
    } else {
      window.location.href = "cajero.html"; // página del cajero
    }
  } else {
    alert("Debes ingresar usuario y contraseña.");
  }
});

// REGISTRO simulado (solo Gerente puede registrar)
document.getElementById('formRegistro').addEventListener('submit', (e) => {
  e.preventDefault();
  const nombre = document.getElementById('regNombre').value;
  const usuario = document.getElementById('regUsuario').value;
  const password = document.getElementById('regPassword').value;
  const rol = document.getElementById('regRol').value;

  // ⚠️ Simulación: aquí luego se insertará en la BD
  alert(`Usuario registrado:\nNombre: ${nombre}\nUsuario: ${usuario}\nRol: ${rol}`);
  bootstrap.Modal.getInstance(document.getElementById('modalRegistro')).hide();
});

// LOGOUT simulado
document.getElementById('btnLogout').addEventListener('click', () => {
  alert("Sesión cerrada.");
  location.reload();
});
