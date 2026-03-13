// app.js - Gestión de Inventario (final, muestra total_bs del registro y cierre que marca inventario)
let insumos = [];
let tasaBCV = 350;

// -------------------------------
// Util: parsear JSON o devolver texto crudo (útil para depuración)
async function parseJsonOrText(response) {
  const text = await response.text();
  try { return JSON.parse(text); } catch (err) {
    console.error("Respuesta no JSON recibida del servidor:", text);
    return { __raw: text, __parseError: err.message };
  }
}

// -------------------------------
// Formateo de números (miles y decimales)
function formatNumber(n, decimals = 2, decPoint = ',', thousandsSep = '.') {
  if (n === null || n === undefined || isNaN(Number(n))) {
    const zero = (0).toFixed(decimals);
    const partsZero = zero.split('.');
    partsZero[0] = partsZero[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
    return partsZero.join(decPoint);
  }
  const fixed = Number(n).toFixed(decimals);
  const parts = fixed.split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
  return parts.join(decPoint);
}

// -------------------------------
// Escape HTML
function escapeHtml(text) {
  if (text === null || text === undefined) return "";
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// -------------------------------
// Imagen por unidad
function obtenerImagenPorUnidad(unidad) {
  if (!unidad) return "uploads/default.jpg";
  switch (unidad.toString().toLowerCase()) {
    case "kg": return "uploads/kg.jpg";
    case "litros": return "uploads/litros.jpg";
    case "unidades": return "uploads/unidades.jpg";
    case "gramos": return "uploads/gramos.jpg";
    default: return "uploads/default.jpg";
  }
}

// -------------------------------
// Render desde array local (evita fetch extra cuando ya tenemos la fila insertada)
function renderInsumosDesdeArray() {
  const contenedor = document.getElementById("contenedorInsumos");
  if (!contenedor) return;
  contenedor.innerHTML = "";

  const grupos = {};
  insumos.forEach(i => {
    const fechaDia = (i.fecha || "").split(" ")[0] || obtenerFechaActualSimple();
    if (!grupos[fechaDia]) grupos[fechaDia] = [];
    grupos[fechaDia].push(i);
  });

  Object.keys(grupos).sort().forEach(fechaDia => {
    const section = document.createElement("div");
    section.className = "fecha-section mb-4";

    const titulo = document.createElement("h4");
    titulo.textContent = formatearFecha(fechaDia);
    section.appendChild(titulo);

    const fila = document.createElement("div");
    fila.className = "d-flex flex-wrap gap-3";

    let totalDia = 0;
    let totalDiaBs = 0;

    grupos[fechaDia].forEach(i => {
      // mostrar solo activos
      if (Number(i.activo) !== 1) return;

      const costoUsd = parseFloat(i.costo_usd) || 0;
      const cantidad = parseFloat(i.cantidad) || 0;
      const totalCompra = cantidad * costoUsd;
      totalDia += totalCompra;

      let totalBsMostrar = null;
      if (i.total_bs !== undefined && i.total_bs !== null && i.total_bs !== '') {
        totalBsMostrar = parseFloat(i.total_bs) || 0;
      } else if (i.tasa !== undefined && i.tasa !== null && i.tasa !== '') {
        totalBsMostrar = totalCompra * (parseFloat(i.tasa) || tasaBCV);
      } else {
        totalBsMostrar = totalCompra * tasaBCV;
      }
      totalDiaBs += totalBsMostrar;

      const imgSrc = (i.imagen && i.imagen.trim() !== "") ? i.imagen : obtenerImagenPorUnidad(i.unidad);

      const card = document.createElement("div");
      card.className = "card";
      card.style.width = "18rem";

      card.innerHTML = `
  <img src="${escapeHtml(imgSrc)}" class="card-img-top" alt="${escapeHtml(i.nombre || 'Insumo')}">
  <div class="card-body">
    <h5 class="card-title">${escapeHtml(i.nombre || '')}</h5>
    <p><strong>Cantidad:</strong> ${formatNumber(cantidad,2)} ${escapeHtml(i.unidad || '')}</p>
    <p><strong>Costo unitario:</strong> $${formatNumber(costoUsd,2,'.',',')}</p>
    <p><strong>Total compra:</strong> $${formatNumber(totalCompra,2,'.',',')}</p>
    <p><strong>Costo en Bs:</strong> ${formatNumber(totalBsMostrar,2)}</p>
    <p><strong>Tasa aplicada (Bs/USD):</strong> ${ (i.tasa !== undefined && i.tasa !== null && i.tasa !== '') ? formatNumber(parseFloat(i.tasa),2) : formatNumber(tasaBCV,2) }</p>
    <p><strong>Proveedor:</strong> ${escapeHtml(i.proveedor || "N/A")}</p>
    <p><strong>Fecha/Hora:</strong> ${escapeHtml(i.fecha || '')}</p>
    <p><strong>Estado:</strong> ${i.activo == 1 ? "Activo" : "Inactivo"}</p>
    <button class="btn btn-sm btn-primary" onclick="abrirModalEditar(${i.id})">Editar</button>
    <button class="btn btn-sm btn-danger" onclick="abrirModalEliminar(${i.id})">Eliminar</button>
  </div>
`;

      fila.appendChild(card);
    });

    const totalEl = document.createElement("p");
    totalEl.className = "fw-bold mt-2";
    totalEl.textContent = `Total gastado el ${formatearFecha(fechaDia)}: $${formatNumber(totalDia,2,'.',',')} — Bs ${formatNumber(totalDiaBs,2)}`;
    section.appendChild(fila);
    section.appendChild(totalEl);

    contenedor.appendChild(section);
  });
}

// -------------------------------
// Cargar desde servidor
function cargarInsumos() {
  fetch("inventario.php", { credentials: 'include' })
    .then(async res => {
      if (!res.ok) {
        const parsed = await parseJsonOrText(res);
        throw new Error("Error en GET inventario: " + (parsed.error || parsed.__raw || res.status));
      }
      return res.json();
    })
    .then(data => {
      insumos = Array.isArray(data) ? data : [];
      renderInsumosDesdeArray();
    })
    .catch(err => {
      console.error(err);
      alert("Error cargando insumos. Revisa la consola para más detalles.");
    });
}

// -------------------------------
// Registrar nuevo insumo
async function registrarInsumoFetch(e) {
  e.preventDefault();

  const nombre = document.getElementById("nombreInsumo").value.trim();
  const cantidad = parseFloat(document.getElementById("cantidadInsumo").value) || 0;
  const unidad = document.getElementById("unidadInsumo").value;
  const costo_usd = parseFloat(document.getElementById("costoUsd").value) || 0;
  const proveedor = document.getElementById("proveedorInsumo").value.trim();

  const tasaInput = document.getElementById("tasaInsumo");
  const tasa = tasaInput ? parseFloat(tasaInput.value) || 0 : 0;
  const total_bs = tasa > 0 ? (cantidad * costo_usd * tasa) : (cantidad * costo_usd * tasaBCV);

  const formData = new FormData();
  formData.append("nombre", nombre);
  formData.append("cantidad", cantidad);
  formData.append("unidad", unidad);
  formData.append("costo_usd", costo_usd);
  formData.append("proveedor", proveedor);
  formData.append("tasa", tasa);
  formData.append("total_bs", total_bs.toFixed(2));

  const fileInput = document.getElementById("imagenInsumo");
  if (fileInput && fileInput.files && fileInput.files[0]) formData.append("imagen", fileInput.files[0]);

  try {
    const res = await fetch("inventario.php", {
      method: "POST",
      credentials: 'include',
      body: formData
    });
    const parsed = await parseJsonOrText(res);
    if (!res.ok) throw new Error("Error en POST inventario: " + (parsed.error || parsed.__raw || res.status));
    const respuesta = parsed;

    if (respuesta.success) {
      // Si backend devolvió la fila insertada, usarla para mostrar inmediatamente
      if (respuesta.row) {
        const row = respuesta.row;
        row.cantidad = parseFloat(row.cantidad) || 0;
        row.costo_usd = parseFloat(row.costo_usd) || 0;
        if (row.total_bs !== undefined && row.total_bs !== null && row.total_bs !== '') row.total_bs = parseFloat(row.total_bs);
        else row.total_bs = parseFloat(total_bs);
        if (row.tasa !== undefined && row.tasa !== null && row.tasa !== '') row.tasa = parseFloat(row.tasa);
        else row.tasa = parseFloat(tasa) || null;
        // asegurar activo
        row.activo = (row.activo === undefined || row.activo === null) ? 1 : Number(row.activo);
        insumos.unshift(row);
        renderInsumosDesdeArray();
      } else {
        // fallback: recargar
        await cargarInsumos();
      }

      alert("Insumo registrado con éxito");
      const form = document.getElementById("formRegistroInsumo");
      if (form) form.reset();
      const previewInsumoEl = document.getElementById("previewInsumo");
      if (previewInsumoEl) previewInsumoEl.textContent = "Completa el formulario para ver un resumen aquí...";
      const previewTotalBsEl = document.getElementById("previewTotalBs");
      if (previewTotalBsEl) previewTotalBsEl.textContent = "Total Bs: 0.00";
      const modalEl = document.getElementById("modalRegistroInsumo");
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    } else {
      console.error("Respuesta POST inesperada:", respuesta);
      alert("Error al registrar insumo. Revisa la consola para más detalles.");
    }
  } catch (err) {
    console.error("Error en POST inventario:", err);
    alert("Error al registrar insumo. Revisa la consola para ver la respuesta cruda del servidor.");
  }
}

// -------------------------------
// Preview dinámico
function actualizarPreview() {
  const nombre = document.getElementById("nombreInsumo").value.trim();
  const unidad = document.getElementById("unidadInsumo").value;
  const cantidad = parseFloat(document.getElementById("cantidadInsumo").value) || 0;
  const precio = parseFloat(document.getElementById("costoUsd").value) || 0;
  const proveedor = document.getElementById("proveedorInsumo").value.trim();
  const tasa = parseFloat((document.getElementById("tasaInsumo") || { value: '' }).value) || 0;

  const previewEl = document.getElementById("previewInsumo");
  const previewTotalBsEl = document.getElementById("previewTotalBs");
  if (!previewEl) return;

  if (!nombre) {
    previewEl.textContent = "Introduce el nombre del insumo...";
    if (previewTotalBsEl) previewTotalBsEl.textContent = `Total Bs: ${formatNumber(0,2)}`;
    return;
  }
  const totalUsd = (cantidad || 0) * (precio || 0);
  const totalBs = tasa > 0 ? totalUsd * tasa : totalUsd * tasaBCV;
  previewEl.textContent = `Se está ingresando en ${nombre} un total de: ${formatNumber(cantidad,2)} ${unidad} — Total estimado: $${formatNumber(totalUsd,2,'.',',')}${proveedor ? " — Proveedor: " + proveedor : ""}`;
  if (previewTotalBsEl) previewTotalBsEl.textContent = `Total Bs: ${formatNumber(totalBs,2)}`;
}

// -------------------------------
// Editar / Enviar edición
function abrirModalEditar(idInsumo) {
  const insumo = insumos.find(i => parseInt(i.id) === parseInt(idInsumo));
  if (!insumo) {
    fetch(`inventario.php?id=${idInsumo}`, { credentials: 'include' })
      .then(async res => {
        if (!res.ok) {
          const parsed = await parseJsonOrText(res);
          throw new Error("Error obteniendo insumo: " + (parsed.error || parsed.__raw || res.status));
        }
        return res.json();
      })
      .then(data => {
        const registro = Array.isArray(data) ? data[0] : data;
        if (registro) rellenarFormularioEditar(registro);
        const modal = new bootstrap.Modal(document.getElementById("modalEditarInsumo"));
        modal.show();
      })
      .catch(err => {
        console.error("Error obteniendo insumo:", err);
        alert("No se pudo obtener el insumo. Revisa la consola.");
      });
    return;
  }

  rellenarFormularioEditar(insumo);
  const modal = new bootstrap.Modal(document.getElementById("modalEditarInsumo"));
  modal.show();
}

function rellenarFormularioEditar(insumo) {
  document.getElementById("idInsumoEditar").value = insumo.id;
  document.getElementById("nombreInsumoEditar").value = insumo.nombre || "";
  document.getElementById("cantidadInsumoEditar").value = insumo.cantidad || "";
  document.getElementById("unidadInsumoEditar").value = insumo.unidad || "kg";
  document.getElementById("costoUsdEditar").value = insumo.costo_usd || "";
  document.getElementById("proveedorInsumoEditar").value = insumo.proveedor || "";

  const previewEditar = document.getElementById("previewEditarImagen");
  const imgSrc = (insumo.imagen && insumo.imagen.trim() !== "") ? insumo.imagen : obtenerImagenPorUnidad(insumo.unidad);
  if (previewEditar) previewEditar.src = imgSrc;
}

function enviarEdicion(e) {
  e.preventDefault();
  const id = document.getElementById("idInsumoEditar").value;
  const payload = {
    id: id,
    nombre: document.getElementById("nombreInsumoEditar").value.trim(),
    cantidad: parseFloat(document.getElementById("cantidadInsumoEditar").value) || 0,
    unidad: document.getElementById("unidadInsumoEditar").value,
    costo_usd: parseFloat(document.getElementById("costoUsdEditar").value) || 0,
    proveedor: document.getElementById("proveedorInsumoEditar").value.trim()
  };

  const quitarImagenEl = document.getElementById("quitarImagenEditar");
  if (quitarImagenEl && quitarImagenEl.checked) payload.remove_image = 1;

  fetch("inventario.php", {
    method: "PUT",
    credentials: 'include',
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
  .then(async res => {
    if (!res.ok) {
      const parsed = await parseJsonOrText(res);
      throw new Error("Error en PUT inventario: " + (parsed.error || parsed.__raw || res.status));
    }
    return parseJsonOrText(res);
  })
  .then(respuesta => {
    if (respuesta.success) {
      alert("Insumo actualizado con éxito");
      cargarInsumos();
      const modalEl = document.getElementById("modalEditarInsumo");
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    } else {
      console.error("Respuesta PUT inesperada:", respuesta);
      alert("Error al actualizar insumo. Revisa la consola.");
    }
  })
  .catch(err => {
    console.error("Error en PUT inventario:", err);
    alert("Error al actualizar insumo. Revisa la consola para ver la respuesta cruda.");
  });
}

// -------------------------------
// Eliminar insumo
function abrirModalEliminar(idInsumo) {
  document.getElementById("idInsumoEliminar").value = idInsumo;
  const modal = new bootstrap.Modal(document.getElementById("modalEliminarInsumo"));
  modal.show();
}

function confirmarEliminar() {
  const idInsumo = document.getElementById("idInsumoEliminar").value;
  if (!idInsumo) return;

  fetch(`inventario.php?id=${idInsumo}`, { method: "DELETE", credentials: 'include' })
    .then(async res => {
      if (!res.ok) {
        const parsed = await parseJsonOrText(res);
        throw new Error("Error en DELETE inventario: " + (parsed.error || parsed.__raw || res.status));
      }
      return parseJsonOrText(res);
    })
    .then(respuesta => {
      if (respuesta.success) {
        alert("Insumo eliminado con éxito");
        cargarInsumos();
        const modalEl = document.getElementById("modalEliminarInsumo");
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      } else {
        console.error("Respuesta DELETE inesperada:", respuesta);
        alert("Error al eliminar insumo. Revisa la consola.");
      }
    })
    .catch(err => {
      console.error("Error en DELETE inventario:", err);
      alert("Error al eliminar insumo. Revisa la consola para ver la respuesta cruda del servidor.");
    });
}

// -------------------------------
// CIERRE DE GASTOS (usa inventario)
function parseJsonSafe(text) {
  try { return JSON.parse(text); } catch (e) { return null; }
}

async function ejecutarCierreGastos(desde = null, hasta = null) {
  const btnConfirm = document.getElementById('btnConfirmarCierreGastos');
  if (btnConfirm) btnConfirm.disabled = true;
  try {
    const body = (desde && hasta) ? new URLSearchParams({ desde, hasta }) : null;
    const res = await fetch('cierre_gastos.php', {
      method: 'POST',
      credentials: 'include',
      body
    });

    const text = await res.text();
    const j = parseJsonSafe(text) || { error: text };

    if (res.ok && j && j.success) {
      const modalEl = document.getElementById('modalConfirmCierreGastos');
      if (modalEl) {
        const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        inst.hide();
      }

      if (Array.isArray(j.processed_ids) && j.processed_ids.length > 0) {
        // eliminar localmente los procesados (marcados como inactivos)
        insumos = insumos.filter(i => !j.processed_ids.includes(Number(i.id)));
        renderInsumosDesdeArray();
      } else {
        await cargarInsumos();
      }

      if (typeof cargarReportesList === 'function') await cargarReportesList();

      alert('Cierre de gastos generado correctamente. Items procesados: ' + (j.items_count || 0));
    } else {
      console.error('Error cierre gastos:', j);
      alert('Error al generar cierre: ' + (j.error || j.message || 'Error desconocido'));
    }
  } catch (err) {
    console.error('Error en ejecución de cierre_gastos:', err);
    alert('Error al generar cierre. Revisa la consola para más detalles.');
  } finally {
    if (btnConfirm) btnConfirm.disabled = false;
  }
}

function inicializarCierreGastos() {
  const btnCerrar = document.getElementById('btnCerrarGastosDia');
  if (btnCerrar) {
    btnCerrar.addEventListener('click', () => {
      const modal = new bootstrap.Modal(document.getElementById('modalConfirmCierreGastos'));
      modal.show();
    });
  }

  const btnConfirm = document.getElementById('btnConfirmarCierreGastos');
  if (btnConfirm) {
    btnConfirm.addEventListener('click', function (e) {
      e.preventDefault();
      ejecutarCierreGastos();
    });
  }
}

// -------------------------------
// Utilidades de fecha
function obtenerFechaHoraActual() {
  const ahora = new Date();
  const yyyy = ahora.getFullYear();
  const mm = String(ahora.getMonth() + 1).padStart(2, '0');
  const dd = String(ahora.getDate()).padStart(2, '0');
  const hh = String(ahora.getHours()).padStart(2, '0');
  const min = String(ahora.getMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
}

function obtenerFechaActualSimple() {
  const ahora = new Date();
  const yyyy = ahora.getFullYear();
  const mm = String(ahora.getMonth() + 1).padStart(2, '0');
  const dd = String(ahora.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
}

function formatearFecha(fechaStr) {
  const partes = fechaStr.split("-");
  const yyyy = partes[0];
  const mm = parseInt(partes[1], 10);
  const dd = partes[2];
  const meses = ["enero","febrero","marzo","abril","mayo","junio","julio","agosto","septiembre","octubre","noviembre","diciembre"];
  return `${dd} de ${meses[mm-1]} de ${yyyy}`;
}

// -------------------------------
// Inicialización
document.addEventListener("DOMContentLoaded", function() {
  cargarInsumos();

  const formRegistro = document.getElementById("formRegistroInsumo");
  if (formRegistro) formRegistro.addEventListener("submit", registrarInsumoFetch);

  const formEditar = document.getElementById("formEditarInsumo");
  if (formEditar) formEditar.addEventListener("submit", enviarEdicion);

  const btnConfirmarEliminar = document.getElementById("btnConfirmarEliminarInsumo");
  if (btnConfirmarEliminar) btnConfirmarEliminar.addEventListener("click", confirmarEliminar);

  ["nombreInsumo","unidadInsumo","cantidadInsumo","costoUsd","proveedorInsumo","tasaInsumo"].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', actualizarPreview);
      el.addEventListener('blur', actualizarPreview);
    }
  });

  const previewEl = document.getElementById("previewInsumo");
  if (previewEl && previewEl.textContent.trim() === "") previewEl.textContent = "Completa el formulario para ver un resumen aquí...";

  inicializarCierreGastos();
});
