// app_reportes_cajero.js - versión completa y robusta para la administración de pedidos (cajero)
const API_BASE = "/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero";
let pedidos = [];
let pollInterval = null;
let consecutiveFetchErrors = 0;
const MAX_FETCH_ERRORS = 5;
const FETCH_RETRY_AFTER_MS = 30000;
let currentFilters = { estado: "", desde: "", hasta: "", q: "" };

// Helpers
function qs(id) { return document.getElementById(id); }
function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str).replace(/[&<>"'`=\/]/g, s => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
  })[s]);
}
function showToast(msg, type = "info") {
  console[type === "error" ? "error" : "log"](msg);
  const containerId = "toastContainer";
  let container = qs(containerId);
  if (!container) {
    container = document.createElement("div");
    container.id = containerId;
    container.style.position = "fixed";
    container.style.right = "20px";
    container.style.top = "20px";
    container.style.zIndex = 2000;
    document.body.appendChild(container);
  }
  const el = document.createElement("div");
  el.className = `toast align-items-center text-bg-${type === "error" ? "danger" : "primary"} border-0 show`;
  el.style.minWidth = "220px";
  el.style.marginTop = "8px";
  el.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(msg)}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button></div>`;
  container.appendChild(el);
  el.querySelector(".btn-close").addEventListener("click", () => el.remove());
  setTimeout(() => { try { el.remove(); } catch(e){} }, 5000);
}

// Fetch pedidos con manejo de errores y pausa de polling
async function fetchPedidos(filters = {}) {
  try {
    currentFilters = Object.assign({}, currentFilters, filters);
    const params = new URLSearchParams();
    if (currentFilters.estado) params.append("estado", currentFilters.estado);
    if (currentFilters.desde) params.append("desde", currentFilters.desde);
    if (currentFilters.hasta) params.append("hasta", currentFilters.hasta);
    if (currentFilters.q) params.append("q", currentFilters.q);
    params.append("limit", 500);

    const res = await fetch(`${API_BASE}/get_pedidos.php?${params.toString()}`, { credentials: 'include' });
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || "Error al obtener pedidos");

    consecutiveFetchErrors = 0;
    pedidos = data.pedidos || [];
    // intentar auto-borrar cancelados viejos (no bloqueante)
    autoBorrarCancelados(pedidos).catch(e => console.error("autoBorrar error:", e));
    renderPedidos();
    renderStats(data.stats);
  } catch (err) {
    console.error("Error fetchPedidos:", err);
    consecutiveFetchErrors++;
    showToast("Error de conexión al obtener pedidos", "error");
    if (consecutiveFetchErrors >= MAX_FETCH_ERRORS) {
      if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
        console.warn("Polling pausado por errores. Reintentando en " + (FETCH_RETRY_AFTER_MS/1000) + "s");
        setTimeout(() => {
          consecutiveFetchErrors = 0;
          startPolling();
          fetchPedidos();
        }, FETCH_RETRY_AFTER_MS);
      }
    }
  }
}

// Render pedidos (seguro contra classList.add("") y con delegación)
function renderPedidos() {
  const contenedor = qs("contenedorReportesCajero");
  if (!contenedor) return;
  contenedor.innerHTML = "";
  if (!pedidos || pedidos.length === 0) {
    contenedor.innerHTML = `<div class="alert alert-secondary">No hay pedidos.</div>`;
    return;
  }

  pedidos.forEach(p => {
    const card = document.createElement("div");
    card.className = "card mb-3";
    card.style.width = "18rem";

    let estadoClass = "";
    const estadoLower = String(p.estado || "").toLowerCase();
    if (estadoLower === "pendiente") estadoClass = "pedido-pendiente";
    if (estadoLower === "entregado") estadoClass = "pedido-entregado";
    if (estadoLower === "cancelado") estadoClass = "pedido-cancelado";
    if (estadoLower === "archivado") estadoClass = "pedido-archivado";

    try {
      if (estadoClass) card.classList.add(estadoClass);
    } catch (err) {
      console.error("Error al añadir clase de estado:", err, "estadoClass:", estadoClass);
    }

    const precioBs = p.plato_precio_bs !== null && p.plato_precio_bs !== undefined ? Number(p.plato_precio_bs).toFixed(2) : "N/A";
    const fecha = p.fecha || "";

    let accionesHtml = "";
    if (estadoLower === "pendiente") {
      accionesHtml = `
        <div class="d-flex">
          <button class="btn btn-warning btn-sm me-2" data-id="${p.id}" data-action="editar">Editar</button>
          <button class="btn btn-success btn-sm me-2" data-id="${p.id}" data-action="confirmar">Confirmar</button>
          <button class="btn btn-danger btn-sm" data-id="${p.id}" data-action="cancelar">Cancelar</button>
        </div>`;
    } else if (estadoLower === "cancelado") {
      accionesHtml = `<div class="d-flex">
          <button class="btn btn-outline-danger btn-sm" data-id="${p.id}" data-action="borrar">Borrar</button>
        </div>`;
    } else {
      accionesHtml = "";
    }

    card.innerHTML = `
      <div class="card-body">
        <h5 class="card-title">Cliente: ${escapeHtml(p.cliente)}</h5>
        <p><strong>Teléfono:</strong> ${escapeHtml(p.telefono)}</p>
        <p><strong>Plato:</strong> ${escapeHtml(p.plato_nombre)}</p>
        <p><strong>Método de pago:</strong> ${escapeHtml(p.metodo_pago)}</p>
        <p><strong>Fecha:</strong> ${escapeHtml(fecha)}</p>
        <p><strong>Total:</strong> $${Number(p.total || 0).toFixed(2)} / Bs ${precioBs}</p>
        <p><strong>Estado:</strong> ${escapeHtml(p.estado)}</p>
        ${accionesHtml}
      </div>
    `;

    // Delegación de eventos por botón
    card.querySelectorAll("button[data-action]").forEach(btn => {
      btn.addEventListener("click", () => {
        const id = parseInt(btn.getAttribute("data-id"), 10);
        const action = btn.getAttribute("data-action");
        if (action === "editar") abrirEditarPedido(id);
        if (action === "confirmar") confirmarPedido(id);
        if (action === "cancelar") cancelarPedido(id);
        if (action === "borrar") borrarPedido(id);
      });
    });

    contenedor.appendChild(card);
  });
}

// Render estadísticas
function renderStats(stats) {
  const statsEl = qs("statsResumen");
  if (statsEl && stats) {
    statsEl.innerHTML = `
      <div><strong>Pedidos hoy:</strong> ${stats.total_pedidos_hoy || 0}</div>
      <div><strong>Ventas USD hoy:</strong> $${Number(stats.ventas_usd_hoy || 0).toFixed(2)}</div>
      <div><strong>Ventas Bs hoy:</strong> Bs ${Number(stats.ventas_bs_hoy || 0).toFixed(2)}</div>
    `;
  } else {
    console.log("Estadísticas:", stats);
  }
}

// ===================== Acciones sobre pedidos =====================
// Abrir edición (implementa según tu modal/flujo)
function abrirEditarPedido(id) {
  const p = pedidos.find(x => x.id === id);
  if (!p || String(p.estado).toLowerCase() !== "pendiente") return;
  qs("editarIdPedido").value = p.id;
  qs("editarCliente").value = p.cliente || "";
  qs("editarTelefono").value = p.telefono || "";
  qs("editarMetodoPago").value = p.metodo_pago || "";
  qs("editarPlato").value = p.plato_nombre || "";
  const modal = new bootstrap.Modal(qs("editarPedidoModal"));
  modal.show();
}

qs("formEditarPedido")?.addEventListener("submit", async function(e) {
  e.preventDefault();
  const id = parseInt(qs("editarIdPedido").value, 10);
  if (!id) return;
  const payload = {
    id: id,
    cliente: qs("editarCliente").value.trim(),
    telefono: qs("editarTelefono").value.trim(),
    metodo_pago: qs("editarMetodoPago").value,
    plato_nombre: qs("editarPlato").value.trim(),
    usuario: localStorage.getItem("usuarioActivo") || "Gerente"
  };
  try {
    const res = await fetch(`${API_BASE}/update_pedido.php`, {
      method: "POST",
      credentials: 'include',
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(payload)
    });
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    if (data.success) {
      await fetchPedidos();
      const modal = bootstrap.Modal.getInstance(qs("editarPedidoModal"));
      modal.hide();
      showToast("Pedido actualizado", "info");
    } else {
      showToast("Error al actualizar: " + (data.error || ""), "error");
    }
  } catch (err) {
    console.error("update_pedido error:", err);
    showToast("Error al actualizar pedido", "error");
  }
});

// Cambiar estado (con manejo seguro de respuestas)
async function cambiarEstado(id, nuevo_estado) {
  try {
    const res = await fetch(`${API_BASE}/update_pedido_estado.php`, {
      method: "POST",
      credentials: 'include',
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({ id, nuevo_estado, usuario: localStorage.getItem("usuarioActivo") || "Gerente" })
    });

    if (!res.ok) {
      const text = await res.text().catch(()=>"");
      console.error("Server error response:", res.status, text);
      showToast("Error al actualizar estado (servidor).", "error");
      return;
    }

    const data = await res.json().catch(err => {
      console.error("JSON parse error:", err);
      showToast("Respuesta inválida del servidor.", "error");
      return null;
    });
    if (!data) return;

    if (data.success) {
      await fetchPedidos();
      showToast("Estado actualizado", "info");
    } else {
      showToast("Error: " + (data.error || "No se pudo actualizar"), "error");
    }
  } catch (err) {
    console.error("cambiarEstado error:", err);
    showToast("Error al actualizar estado", "error");
  }
}
async function confirmarPedido(id) {
  if (!confirm("Confirmar entrega de este pedido?")) return;
  await cambiarEstado(id, "entregado");
}
async function cancelarPedido(id) {
  if (!confirm("Cancelar este pedido?")) return;
  await cambiarEstado(id, "cancelado");
}

// Borrar pedido
async function borrarPedido(id) {
  if (!confirm("¿Borrar este pedido cancelado? Esta acción es irreversible.")) return;
  try {
    const res = await fetch(`${API_BASE}/delete_pedido.php`, {
      method: 'POST',
      credentials: 'include',
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({ id, usuario: localStorage.getItem("usuarioActivo") || "Sistema" })
    });
    if (!res.ok) {
      const text = await res.text().catch(()=>"");
      console.error("delete_pedido server error:", res.status, text);
      showToast("Error al borrar pedido (servidor).", "error");
      return;
    }
    const data = await res.json().catch(()=>null);
    if (!data) { showToast("Respuesta inválida al borrar pedido.", "error"); return; }
    if (data.success) {
      await fetchPedidos();
      showToast("Pedido borrado", "info");
    } else {
      showToast("No se pudo borrar: " + (data.error || ""), "error");
    }
  } catch (err) {
    console.error("borrarPedido error:", err);
    showToast("Error al borrar pedido", "error");
  }
}

// Auto-borrar cancelados viejos (10 minutos)
async function autoBorrarCancelados(pedidosList) {
  const now = Date.now();
  const TEN_MIN = 10 * 60 * 1000;
  for (const p of pedidosList) {
    if (String(p.estado).toLowerCase() === "cancelado") {
      const fechaPedido = new Date(p.fecha).getTime();
      if (!isNaN(fechaPedido) && (now - fechaPedido) >= TEN_MIN) {
        try {
          await fetch(`${API_BASE}/delete_pedido.php`, {
            method: 'POST',
            credentials: 'include',
            headers: {"Content-Type":"application/json"},
            body: JSON.stringify({ id: p.id, usuario: localStorage.getItem("usuarioActivo") || "Sistema" })
          });
        } catch (e) {
          console.error("autoBorrar error for id", p.id, e);
        }
      }
    }
  }
}

// Cierre de caja (robusto) - usa credentials: 'include' y muestra cuerpo de error si hay 500
async function cierreCaja() {
  if (!confirm("¿Deseas cerrar la caja y archivar los pedidos entregados del día?")) return;
  try {
    const payload = { usuario: localStorage.getItem("usuarioActivo") || "Cajero" };
    const res = await fetch(`${API_BASE}/cierre_caja.php`, {
      method: "POST",
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!res.ok) {
      // intentar leer cuerpo JSON o texto para depuración
      let text = await res.text().catch(()=>null);
      try {
        const maybeJson = text ? JSON.parse(text) : null;
        console.error("cierre_caja server error:", res.status, maybeJson || text);
        const msg = maybeJson && maybeJson.error ? maybeJson.error : `Error al cerrar caja (servidor). Código: ${res.status}`;
        showToast(msg, "error");
      } catch (e) {
        console.error("cierre_caja server error (non-json):", res.status, text);
        showToast("Error al cerrar caja (servidor). Revisa logs.", "error");
      }
      return;
    }

    const j = await res.json().catch(()=>null);
    if (!j) {
      showToast("Respuesta inválida del servidor al cerrar caja.", "error");
      return;
    }
    if (j.success) {
      showToast(`Caja cerrada. Caja #${j.caja_num || j.caja_numero || ''} — ${j.count || 0} pedidos archivados`, "info");
      await fetchPedidos();
      // opcional: refrescar lista de reportes si existe función global fetchReportes
      if (typeof fetchReportes === 'function') fetchReportes();
    } else {
      showToast("No se pudo cerrar caja: " + (j.error || "Sin pedidos entregados"), "error");
    }
  } catch (err) {
    console.error("cierre caja error:", err);
    showToast("Error al cerrar caja (cliente).", "error");
  }
}

// Export CSV
function exportCSV() {
  const params = new URLSearchParams();
  if (currentFilters.estado) params.append("estado", currentFilters.estado);
  if (currentFilters.desde) params.append("desde", currentFilters.desde);
  if (currentFilters.hasta) params.append("hasta", currentFilters.hasta);
  if (currentFilters.q) params.append("q", currentFilters.q);
  const url = `${API_BASE}/export_pedidos.php?${params.toString()}`;
  window.location.href = url;
}

// Polling
function startPolling() {
  if (pollInterval) clearInterval(pollInterval);
  pollInterval = setInterval(() => fetchPedidos(), 12000);
}

// Inicialización
document.addEventListener("DOMContentLoaded", () => {
  fetchPedidos();
  startPolling();

  // filtros
  const filtroEstado = qs("filtroEstado");
  if (filtroEstado) {
    filtroEstado.addEventListener("change", () => {
      const estado = filtroEstado.value || "";
      fetchPedidos({ estado });
    });
  }

  // logout / cerrar sesión (si existe)
  const btnCerrar = qs("btnCerrarSesion") || qs("btnCerrarCaja");
  if (btnCerrar) btnCerrar.addEventListener("click", () => {
    localStorage.removeItem("usuarioActivo");
    localStorage.removeItem("rolActivo");
    window.location.href = "cajero.html";
  });

  // botón de cierre de caja (soporta varios ids posibles)
  const btnCierre1 = qs("btnCierreCaja");
  const btnCierre2 = qs("btnCerrarCaja");
  const btnCierre3 = qs("btnCerrarCajaPrincipal");
  [btnCierre1, btnCierre2, btnCierre3].forEach(b => {
    if (b) b.addEventListener("click", cierreCaja);
  });

  const btnExport = qs("btnExportCSV");
  if (btnExport) btnExport.addEventListener("click", exportCSV);

  const btnAplicarFiltros = qs("btnAplicarFiltros");
  if (btnAplicarFiltros) {
    btnAplicarFiltros.addEventListener("click", () => {
      const f = {
        estado: qs("filtroEstado") ? qs("filtroEstado").value : "",
        desde: qs("filtroDesde") ? qs("filtroDesde").value : "",
        hasta: qs("filtroHasta") ? qs("filtroHasta").value : "",
        q: qs("filtroQ") ? qs("filterQ").value.trim() : ""
      };
      fetchPedidos(f);
    });
  }
});
