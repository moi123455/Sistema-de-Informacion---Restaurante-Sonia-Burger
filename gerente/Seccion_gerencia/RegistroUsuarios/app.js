// app.js - Gestión de usuarios y utilidades
const API_BASE = '/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_gerencia/RegistroUsuarios';
const LOGOUT_PATH = '/Restaurante_Sonia_Burger/Restaurante_SB/logout.php';
const API_PERSONAS = `${API_BASE}/api_get_personas.php`;
const API_USUARIOS = `${API_BASE}/usuarios.php`;
const API_DELETE_USUARIO = `${API_BASE}/delete_usuario.php`;
const API_DELETE_CLIENTE = `${API_BASE}/delete_cliente.php`;
const API_CLIENTES = `${API_BASE}/update_cliente.php`; // si prefieres endpoint separado
const PING_PATH = `${API_BASE}/ping.php`;

const ACTIVE_THRESHOLD = 120; // segundos
const HEARTBEAT_INTERVAL = 60000; // 60s
let heartbeatTimer = null;

function escapeHtml(s) {
  return s ? s.toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) : '';
}

function isActive(lastActivityAt) {
  if (!lastActivityAt) return false;
  const t = new Date(lastActivityAt).getTime();
  return (Date.now() - t) <= (ACTIVE_THRESHOLD * 1000);
}

// Heartbeat & Logout
function startHeartbeat() {
  fetch(PING_PATH, { method: 'POST', credentials: 'include' }).catch(()=>{});
  if (heartbeatTimer) clearInterval(heartbeatTimer);
  heartbeatTimer = setInterval(() => {
    fetch(PING_PATH, { method: 'POST', credentials: 'include' }).catch(()=>{});
  }, HEARTBEAT_INTERVAL);
}

async function doLogout() {
  try { await fetch(LOGOUT_PATH, { method: 'POST', credentials: 'include' }); } catch (e) {}
  localStorage.removeItem("usuarioActivo");
  localStorage.removeItem("rolActivo");
  localStorage.removeItem("usuarioActivoId");
  window.location.href = "/Restaurante_Sonia_Burger/Restaurante_SB/gerente/index.html";
}

// Cargar y renderizar usuarios/clientes
async function cargarUsuarios() {
  try {
    const res = await fetch(API_PERSONAS, { credentials: 'include' });
    if (!res.ok) throw new Error('Error en la respuesta del servidor');
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Respuesta inválida');

    const tabla = document.getElementById("tablaUsuarios");
    if (!tabla) return;
    tabla.innerHTML = "";

    const usuarioActivoName = localStorage.getItem("usuarioActivo");
    const usuarioActivoId = localStorage.getItem("usuarioActivoId");

    json.data.forEach(u => {
      const fila = document.createElement("tr");

      let badgeClass = 'role-cliente';
      if (u.rol === 'gerente') badgeClass = 'role-gerente';
      if (u.rol === 'cajero') badgeClass = 'role-cajero';

      let estadoConexion = '-';
      if (u.tipo === 'usuario') {
        if (isActive(u.last_activity_at)) {
          estadoConexion = '<span class="badge bg-success">Conectado</span>';
        } else {
          estadoConexion = u.last_login_at ? escapeHtml(new Date(u.last_login_at).toLocaleString()) : '-';
        }
      } else {
        estadoConexion = '-';
      }

      let metrics = '';
      if (u.tipo === 'cliente' || u.rol === 'cliente') {
        metrics = `Pedidos: <strong>${u.pedidos_count || 0}</strong>`;
      } else if (u.rol === 'gerente') {
        metrics = `Cierres: <strong>${u.cash_closes_count || 0}</strong>`;
      } else if (u.rol === 'cajero') {
        metrics = `Entregados: <strong>${u.orders_delivered_count || 0}</strong><br>Cierres: <strong>${u.cash_closes_count || 0}</strong>`;
      } else {
        metrics = `Entregados: <strong>${u.orders_delivered_count || 0}</strong>`;
      }

      const telefono = u.telefono ? escapeHtml(u.telefono) : '-';
      const ultimaPedido = (u.tipo === 'cliente') ? (u.ultima_compra ? escapeHtml(new Date(u.ultima_compra).toLocaleString()) : '-') : (u.last_order_at ? escapeHtml(new Date(u.last_order_at).toLocaleString()) : '-');

      let acciones = `<button class="btn btn-sm btn-primary" onclick="abrirModalEditar('${escapeHtml(u.id)}','${escapeHtml(u.nombre)}','${escapeHtml(u.correo||'')}','${escapeHtml(telefono)}')">Editar</button>`;

      const isSelf = (String(u.id) === String(usuarioActivoId)) || (u.nombre && usuarioActivoName && u.nombre === usuarioActivoName && u.tipo === 'usuario');
      if (isSelf) {
        acciones += ` <span class="badge bg-secondary ms-2">No puedes eliminarte a ti mismo</span>`;
      } else {
        if (String(u.id).startsWith('c_')) {
          acciones += ` <button class="btn btn-sm btn-danger ms-2" onclick="confirmarEliminarCliente('${escapeHtml(u.id)}')">Eliminar</button>`;
        } else {
          acciones += ` <button class="btn btn-sm btn-danger ms-2" onclick="confirmarEliminarUsuario('${escapeHtml(u.id)}')">Eliminar</button>`;
        }
      }

      fila.innerHTML = `
        <td>${escapeHtml(u.id)}</td>
        <td>${escapeHtml(u.nombre)}</td>
        <td>${escapeHtml(u.correo || '-')}</td>
        <td><span class="role-badge ${badgeClass}">${escapeHtml(u.rol)}</span></td>
        <td>${metrics}</td>
        <td>${telefono}</td>
        <td>${ultimaPedido}</td>
        <td>${estadoConexion}</td>
        <td>${acciones}</td>
      `;
      tabla.appendChild(fila);
    });

  } catch (err) {
    console.error("Error al cargar usuarios:", err);
    const tabla = document.getElementById("tablaUsuarios");
    if (tabla) tabla.innerHTML = `<tr><td colspan="9">Error al cargar datos</td></tr>`;
  }
}

// Registrar nuevo usuario
document.addEventListener("DOMContentLoaded", function() {
  const formRegistro = document.getElementById("formRegistro");
  if (formRegistro) {
    formRegistro.addEventListener("submit", async function(e) {
      e.preventDefault();
      const nombre = document.getElementById("nombreCompleto").value.trim();
      const correo = document.getElementById("correo").value.trim();
      const telefono = document.getElementById("telefono").value.trim();
      const password = document.getElementById("clave").value;
      const rol = document.getElementById("rol").value;

      if (!nombre || !correo || !password || !rol) { alert('Completa todos los campos'); return; }

      const formData = new FormData();
      formData.append("nombre", nombre);
      formData.append("correo", correo);
      formData.append("telefono", telefono);
      formData.append("password", password);
      formData.append("rol", rol);

      try {
        const res = await fetch(API_USUARIOS, { method: 'POST', body: formData, credentials: 'include' });
        const json = await res.json();
        if (json.success) {
          const modal = bootstrap.Modal.getInstance(document.getElementById("modalRegistro"));
          if (modal) modal.hide();
          cargarUsuarios();
          alert('Usuario registrado con éxito');
          formRegistro.reset();
        } else {
          alert('Error: ' + (json.error || json.message || 'No se pudo registrar'));
        }
      } catch (err) {
        console.error('Error al registrar usuario:', err);
        alert('Error al registrar usuario');
      }
    });
  }

  // Único handler para el formulario de edición (formEditar)
  const formEditar = document.getElementById("formEditar");
  if (formEditar) {
    formEditar.addEventListener("submit", async function(e) {
      e.preventDefault();

      const idUsuario = document.getElementById("idUsuarioEditar")?.value || '';
      const nombre = document.getElementById("editarNombre")?.value.trim() || '';
      const telefono = document.getElementById("editarTelefono")?.value.trim() || '';
      const correoEl = document.getElementById("editarCorreo");
      const correo = correoEl ? correoEl.value.trim() : '';
      const claveEl = document.getElementById("editarClave");
      const clave = claveEl ? claveEl.value : '';

      const isCliente = String(idUsuario).startsWith('c_');

      // Validaciones
      if (!idUsuario || !nombre) { alert('Completa los campos requeridos'); return; }
      if (!isCliente && !correo) { alert('Completa los campos requeridos (correo)'); return; }

      // Preparar FormData
      const fd = new FormData();
      fd.append('_method', 'PUT');
      fd.append('id', idUsuario);
      fd.append('nombre', nombre);
      fd.append('telefono', telefono);
      if (!isCliente) {
        fd.append('correo', correo);
        if (clave) fd.append('password', clave);
      }

      // Logging para depuración (quítalo en producción)
      console.log('Editar payload:', Array.from(fd.entries()), 'isCliente:', isCliente);

      const endpoint = (isCliente && typeof API_CLIENTES !== 'undefined') ? API_CLIENTES : API_USUARIOS;

      try {
        const res = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'include' });
        const json = await res.json();

        if (!res.ok || !json.success) {
          console.error('Error servidor:', json);
          alert(json.message || json.error || 'No se pudo actualizar. Revisa la consola.');
          return;
        }

        const modalEl = document.getElementById("modalEditar");
        bootstrap.Modal.getInstance(modalEl)?.hide();

        if (typeof cargarUsuarios === 'function') cargarUsuarios();
        if (typeof cargarClientes === 'function') cargarClientes();

        alert((isCliente ? 'Cliente' : 'Usuario') + ' actualizado con éxito');
      } catch (err) {
        console.error('Error al actualizar:', err);
        alert('Error de red al actualizar. Revisa la consola.');
      }
    });
  }

  // Confirm delete button in modal
  const btnConfirmarEliminar = document.getElementById("btnConfirmarEliminar");
  if (btnConfirmarEliminar) {
    btnConfirmarEliminar.addEventListener("click", async function() {
      const id = document.getElementById("idUsuarioEliminar").value;
      if (!id) return;
      if (String(id).startsWith('c_')) {
        await confirmarEliminarCliente(id);
      } else {
        await confirmarEliminarUsuario(id);
      }
      const modal = bootstrap.Modal.getInstance(document.getElementById("modalEliminar"));
      if (modal) modal.hide();
    });
  }
});

// Abrir modal editar
function abrirModalEditar(id, nombre, correo, telefono) {
  const isCliente = String(id).startsWith('c_');

  const idEl = document.getElementById("idUsuarioEditar");
  const nombreEl = document.getElementById("editarNombre");
  const correoEl = document.getElementById("editarCorreo");
  const telefonoEl = document.getElementById("editarTelefono");
  const claveEl = document.getElementById("editarClave");

  if (idEl) idEl.value = id || '';
  if (nombreEl) nombreEl.value = nombre || '';
  if (correoEl) correoEl.value = correo || '';
  if (telefonoEl) telefonoEl.value = telefono || '';
  if (claveEl) claveEl.value = '';

  const hideWrapper = (el) => { if (!el) return; const w = el.closest('.mb-3'); if (w) w.classList.add('d-none'); el.disabled = true; };
  const showWrapper = (el) => { if (!el) return; const w = el.closest('.mb-3'); if (w) w.classList.remove('d-none'); el.disabled = false; };

  if (isCliente) {
    showWrapper(nombreEl);
    showWrapper(telefonoEl);
    hideWrapper(correoEl);
    hideWrapper(claveEl);
  } else {
    showWrapper(nombreEl);
    showWrapper(telefonoEl);
    showWrapper(correoEl);
    showWrapper(claveEl);
  }

  const modal = new bootstrap.Modal(document.getElementById("modalEditar"));
  modal.show();
}

// Eliminar usuario / cliente
async function confirmarEliminarUsuario(id) {
  if (!confirm('¿Confirmar eliminación del usuario?')) return;
  try {
    const res = await fetch(API_DELETE_USUARIO, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id })
    });
    const json = await res.json();
    if (json.success) cargarUsuarios();
    else alert('Error: ' + (json.error || json.message || 'No se pudo eliminar'));
  } catch (err) {
    console.error(err);
    alert('Error al eliminar');
  }
}

async function confirmarEliminarCliente(prefixedId) {
  const realId = prefixedId.replace(/^c_/, '');
  if (!confirm('¿Confirmar eliminación del cliente?')) return;
  try {
    const res = await fetch(API_DELETE_CLIENTE, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ id: realId })
    });
    const json = await res.json();
    if (json.success) cargarUsuarios();
    else alert('Error: ' + (json.error || json.message || 'No se pudo eliminar'));
  } catch (err) {
    console.error(err);
    alert('Error al eliminar cliente');
  }
}

// Confirmar entrega de pedido y registrar actividad del cajero
async function confirmarEntregaPedido(orderId) {
  if (!orderId) return;
  try {
    let userId = localStorage.getItem('usuarioActivoId') || null;
    if (!userId) {
      const resp = await fetch('/Restaurante_Sonia_Burger/Restaurante_SB/verificar_sesion.php', { credentials: 'include' });
      const info = await resp.json();
      userId = info?.id || null;
    }

    const payload = new URLSearchParams();
    payload.append('order_id', orderId);
    if (userId) payload.append('delivered_by', userId);

    const res = await fetch('/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero/api_confirm_delivery.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload.toString()
    });

    const json = await res.json();
    if (!res.ok || !json.success) {
      console.error('Error confirmando entrega:', json);
      alert(json.message || json.error || 'No se pudo confirmar la entrega');
      return;
    }

    if (typeof refreshPedidos === 'function') refreshPedidos();
    if (typeof cargarUsuarios === 'function') cargarUsuarios();

    alert('Entrega confirmada y actividad registrada.');
  } catch (err) {
    console.error('Error en confirmarEntregaPedido:', err);
    alert('Error de red al confirmar la entrega.');
  }
}

// Inicialización
document.addEventListener("DOMContentLoaded", function() {
  cargarUsuarios();
  if (localStorage.getItem("usuarioActivoId")) startHeartbeat();

  const btnLogout = document.getElementById("btnLogout");
  if (btnLogout) btnLogout.addEventListener("click", function() { doLogout(); });
});
