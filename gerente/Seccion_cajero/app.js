// app.js - Cajero (adaptado para horario activo del gerente)
let clienteActivo = null;
let platoSeleccionado = null;
let tasaBCV = 0;
const BASE_SERVER_URL = "http://192.168.1.139/Restaurante_Sonia_Burger/Restaurante_SB/";

// Endpoints (ajusta rutas si tu estructura difiere)
const CAJERO_MENU_ENDPOINT = "/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_gerencia/Menu/menu_cajero.php";
const MENU_BY_HORARIO = "/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_gerencia/Menu/menu.php";

document.addEventListener("DOMContentLoaded", () => {
  cargarSesion();
  cargarTasaBCV().then(() => cargarMenuCajero());
  inicializarEventos();

  const btn = document.querySelector("#formRegistroPedido button[type='submit']");
  if (btn) btn.disabled = true;

  if (!localStorage.getItem("usuarioActivo")) mostrarModalLogin();
});

function inicializarEventos() {
  const btnBuscar = document.getElementById("btnBuscarCliente");
  if (btnBuscar) btnBuscar.addEventListener("click", buscarCliente);

  const formCliente = document.getElementById("formRegistroCliente");
  if (formCliente) formCliente.addEventListener("submit", registrarCliente);

  const formPedido = document.getElementById("formRegistroPedido");
  if (formPedido) formPedido.addEventListener("submit", registrarPedido);

  const btnCerrar = document.getElementById("btnCerrarSesion");
  if (btnCerrar) btnCerrar.addEventListener("click", cerrarSesion);

  const metodoPagoEl = document.getElementById("metodoPago");
  if (metodoPagoEl) metodoPagoEl.addEventListener("change", actualizarEstadoConfirmar);

  const selLocal = document.getElementById("selectorHorarioCajero");
  if (selLocal) {
    selLocal.addEventListener("change", function() {
      const v = this.value;
      if (!v) {
        cargarMenuCajero(); // vuelve al horario activo
      } else {
        cargarMenuPorHorarioLocal(v);
      }
    });
  }
}

// Sesión / Header
function cargarSesion() {
  const usuario = localStorage.getItem("usuarioActivo") || "CajeroDemo";
  const rol = localStorage.getItem("rolActivo") || "cajero";
  const userName = document.getElementById("userName");
  if (userName) userName.textContent = usuario;
  const roleBadge = document.getElementById("roleBadge");
  if (roleBadge) {
    roleBadge.textContent = rol.charAt(0).toUpperCase() + rol.slice(1);
    roleBadge.className = rol === "cajero" ? "badge bg-danger text-white me-3" : "badge bg-warning text-dark me-3";
  }
}

function cerrarSesion() {
  localStorage.removeItem("usuarioActivo");
  localStorage.removeItem("rolActivo");
  window.location.href = "/Restaurante_Sonia_Burger/Restaurante_SB/index.html";
}

function mostrarModalLogin() {
  const modalEl = document.getElementById("loginModal");
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }
}

// Tasa BCV
async function cargarTasaBCV() {
  try {
    const res = await fetch("/Restaurante_Sonia_Burger/Restaurante_SB/get_tasa.php");
    const data = await res.json();
    if (data.exists && data.monto_bs) {
      tasaBCV = parseFloat(data.monto_bs);
      const el = document.getElementById("tasaBCV");
      if (el) el.textContent = tasaBCV.toFixed(2);
    } else {
      const el = document.getElementById("tasaBCV");
      if (el) el.textContent = "No establecida";
    }
  } catch (err) {
    console.error("Error cargando tasa BCV:", err);
    const el = document.getElementById("tasaBCV");
    if (el) el.textContent = "Error";
  }
}

// Clientes
function buscarCliente() {
  const cedula = (document.getElementById("cedulaCliente") || {}).value || "";
  if (!cedula.trim()) {
    mostrarInfoCliente("Debe ingresar una cédula", "danger");
    return;
  }

  fetch(`/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero/clientes.php?cedula=${encodeURIComponent(cedula.trim())}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        clienteActivo = data.cliente;
        mostrarInfoCliente(`Cliente: ${clienteActivo.nombre} | Cédula: ${clienteActivo.cedula} | Teléfono: ${clienteActivo.telefono}`, "success");
      } else {
        clienteActivo = null;
        mostrarInfoCliente("Cédula no encontrada. Registre al cliente.", "danger");
      }
      actualizarEstadoConfirmar();
    })
    .catch(err => {
      console.error("Error buscando cliente:", err);
      mostrarInfoCliente("Error buscando cliente", "danger");
      actualizarEstadoConfirmar();
    });
}

function registrarCliente(e) {
  e.preventDefault();
  const form = document.getElementById("formRegistroCliente");
  if (!form) return;
  const formData = new FormData(form);

  fetch("/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero/clientes.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.json())
    .then(data => {
      const msg = document.getElementById("mensajeRegistroCliente");
      if (data.success) {
        if (msg) { msg.textContent = "Cliente registrado con éxito"; msg.style.color = "green"; }
        form.reset();
        const modalEl = document.getElementById("modalRegistroCliente");
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.hide();
        const cedulaReg = document.getElementById("cedulaClienteReg");
        if (cedulaReg && cedulaReg.value) {
          document.getElementById("cedulaCliente").value = cedulaReg.value;
          setTimeout(buscarCliente, 300);
        }
      } else {
        if (msg) { msg.textContent = data.error || "Error al registrar cliente"; msg.style.color = "red"; }
      }
    })
    .catch(err => {
      console.error("Error registrando cliente:", err);
      const msg = document.getElementById("mensajeRegistroCliente");
      if (msg) { msg.textContent = "Error al registrar cliente"; msg.style.color = "red"; }
    });
}

function mostrarInfoCliente(texto, tipo) {
  const info = document.getElementById("infoCliente");
  if (!info) return;
  info.textContent = texto;
  info.className = `alert alert-${tipo}`;
  info.style.display = "block";
}

// Menú: carga según horario activo (menu_cajero.php) o por horario local (menu.php?horario=...)
async function cargarMenuCajero() {
  try {
    const res = await fetch(CAJERO_MENU_ENDPOINT, { credentials: 'include' });
    const json = await res.json();
    // menu_cajero.php devuelve { success:true, horario:'dia', data:[...] } o un array; soportamos ambos
    const horario = (json && json.horario) ? json.horario : 'dia';
    const productos = json.data || (Array.isArray(json) ? json : []);
    actualizarVistaCajero(productos, horario);
  } catch (e) {
    console.error('Error cargando menú cajero:', e);
  }
}

async function cargarMenuPorHorarioLocal(horario) {
  try {
    const res = await fetch(`${MENU_BY_HORARIO}?horario=${encodeURIComponent(horario)}&role=cajero`, { credentials: 'include' });
    const json = await res.json();
    const productos = json.data || (Array.isArray(json) ? json : []);
    actualizarVistaCajero(productos, horario);
  } catch (e) {
    console.error('Error cargando menú por horario local:', e);
  }
}

function actualizarVistaCajero(productos, horario) {
  const etiqueta = document.getElementById('etiquetaHorarioActivo');
  if (etiqueta) etiqueta.textContent = 'Menú activo: ' + (horario === 'dia' ? 'Día' : horario === 'tarde' ? 'Tarde' : 'Noche');

  const contenedor = document.getElementById("contenedorMenuCajero");
  if (!contenedor) return;
  contenedor.innerHTML = "";

  // Agrupar por categoría (mantener tu layout original)
  const grupos = {};
  productos.forEach(p => {
    const cat = p.categoria || "Sin categoría";
    if (!grupos[cat]) grupos[cat] = [];
    grupos[cat].push(p);
  });

  Object.keys(grupos).forEach(cat => {
    const header = document.createElement('div');
    header.className = 'col-12';
    header.innerHTML = `<h5 class="mt-3">${cat}</h5>`;
    contenedor.appendChild(header);

    grupos[cat].forEach(p => {
      const col = document.createElement('div');
      col.className = 'col-12 col-md-6';
      const imgSrc = p.imagen_url || (p.imagen ? (BASE_SERVER_URL + p.imagen.replace(/^\/+/, '')) : (BASE_SERVER_URL + "gerente/Seccion_gerencia/Menu/default.jpg"));
      const precioUsd = parseFloat(p.precio_usd || 0).toFixed(2);
      const precioBs = (p.precio_bs !== undefined && p.precio_bs !== null) ? parseFloat(p.precio_bs).toFixed(2) : 'No establecido';

      col.innerHTML = `
        <div class="card h-100 mb-3" data-id="${p.id}">
          <div class="row g-0">
            <div class="col-auto">
              <img src="${imgSrc}" class="img-fluid" style="max-width:140px; max-height:120px; object-fit:cover;" onerror="this.src='${BASE_SERVER_URL}gerente/Seccion_gerencia/Menu/default.jpg'">
            </div>
            <div class="col">
              <div class="card-body">
                <h5 class="card-title mb-1">${escapeHtml(p.nombre)}</h5>
                <p class="card-text descripcion mb-2">${escapeHtml(p.descripcion || '')}</p>
                <p class="card-text mb-1"><small>USD $${precioUsd} — Bs ${precioBs}</small></p>
              </div>
            </div>
          </div>
        </div>
      `;
      // click para seleccionar plato
      col.querySelector('.card').addEventListener('click', function() {
        document.querySelectorAll("#contenedorMenuCajero .card").forEach(c => c.classList.remove("plato-seleccionado"));
        this.classList.add("plato-seleccionado");
        platoSeleccionado = p;
        actualizarEstadoConfirmar();
      });

      contenedor.appendChild(col);
    });
  });
}

// Pedidos
function registrarPedido(e) {
  e.preventDefault();

  if (!clienteActivo) { alert("Debe buscar y seleccionar un cliente"); return; }
  if (!platoSeleccionado) { alert("Debe seleccionar un plato del menú"); return; }
  const metodoPagoEl = document.getElementById("metodoPago");
  const metodoPago = metodoPagoEl ? metodoPagoEl.value : "";
  if (!metodoPago) { alert("Debe seleccionar un método de pago"); return; }

  const cantidadEl = document.getElementById("cantidad");
  const cantidad = cantidadEl ? Math.max(1, parseInt(cantidadEl.value || "1", 10)) : 1;

  const pedido = {
    cliente_id: clienteActivo.id,
    plato_id: platoSeleccionado.id,
    metodo_pago: metodoPago,
    cantidad: cantidad
  };

  const btn = document.querySelector("#formRegistroPedido button[type='submit']");
  if (btn) { btn.disabled = true; btn.textContent = "Registrando..."; }

  fetch("/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero/cajero.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(pedido)
  })
    .then(res => res.json())
    .then(data => {
      if (btn) { btn.disabled = false; btn.textContent = "Confirmar pedido"; }
      if (data.success) {
        alert("Pedido registrado con éxito");
        const formPedido = document.getElementById("formRegistroPedido");
        if (formPedido) formPedido.reset();
        platoSeleccionado = null;
        document.querySelectorAll("#contenedorMenuCajero .card").forEach(c => c.classList.remove("plato-seleccionado"));
        clienteActivo = null;
        const info = document.getElementById("infoCliente");
        if (info) info.style.display = "none";
        actualizarEstadoConfirmar();
      } else {
        alert("Error al registrar pedido: " + (data.error || ""));
      }
    })
    .catch(err => {
      console.error("Error registrando pedido:", err);
      if (btn) { btn.disabled = false; btn.textContent = "Confirmar pedido"; }
      alert("Error registrando pedido");
    });
}

// UX: habilitar/deshabilitar Confirmar
function actualizarEstadoConfirmar() {
  const btn = document.querySelector("#formRegistroPedido button[type='submit']");
  const metodoPago = (document.getElementById("metodoPago") || {}).value || "";
  if (!btn) return;
  if (clienteActivo && platoSeleccionado && metodoPago) btn.disabled = false;
  else btn.disabled = true;
}

// Helper para escapar
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
