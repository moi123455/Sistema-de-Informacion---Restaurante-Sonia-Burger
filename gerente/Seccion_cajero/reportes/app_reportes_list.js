const API_BASE = "/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_cajero";

function qs(id){ return document.getElementById(id); }
function escapeHtml(s){ if(s==null) return ""; return String(s).replace(/[&<>"'`=\/]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'})[c]); }

// Formateo de bolívares con separadores (ej: 11.480,00)
function formatBs(value){
  try {
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
  } catch(e){
    return Number(value || 0).toFixed(2);
  }
}

// Calcula plato favorito (más vendido) a partir de array de pedidos
function calcularPlatoFavorito(pedidos){
  const counts = {};
  pedidos.forEach(p=>{
    const nombre = (p.plato_nombre || "").trim();
    if (!nombre) return;
    counts[nombre] = (counts[nombre] || 0) + (parseInt(p.cantidad || 1, 10) || 1);
  });
  let favorito = null;
  let max = 0;
  for (const k in counts){
    if (counts[k] > max){ max = counts[k]; favorito = k; }
  }
  return favorito || "—";
}

async function fetchReportes(){
  try {
    const res = await fetch(`${API_BASE}/get_reportes.php`);
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    if (!data.success) throw new Error(data.error || "Error al obtener reportes");
    renderReportes(data.reportes || []);
  } catch (e) {
    console.error("fetchReportes error:", e);
    qs("listaReportes").innerHTML = `<div class="alert alert-danger">No se pudieron cargar los reportes.</div>`;
  }
}

function renderReportes(list){
  const cont = qs("listaReportes");
  cont.innerHTML = "";
  if (!list.length) { cont.innerHTML = `<div class="alert alert-secondary">No hay reportes archivados.</div>`; return; }
  list.forEach(r => {
    const cajaLabel = `Caja ${r.caja_num || r.id}`;
    const item = document.createElement("a");
    item.className = "list-group-item list-group-item-action d-flex justify-content-between align-items-start";
    item.href = "#";
    item.innerHTML = `<div>
        <div class="fw-bold">${escapeHtml(cajaLabel)}</div>
        <div class="small text-muted">${escapeHtml(r.fecha_reporte)}</div>
      </div>
      <div class="text-end">
        <div>$${Number(r.total_usd||0).toFixed(2)} / Bs ${formatBs(r.total_bs||0)}</div>
        <button class="btn btn-sm btn-outline-primary mt-2" data-id="${r.id}" data-action="ver">Ver</button>
      </div>`;
    item.querySelector("button[data-action='ver']").addEventListener("click", (ev)=>{
      ev.preventDefault();
      verDetalle(r);
    });
    cont.appendChild(item);
  });
}

// intenta obtener la tasa desde varias rutas conocidas
async function obtenerUltimaTasa() {
  const candidates = [
    `${API_BASE}/get_tasa.php`,
    '/Restaurante_Sonia_Burger/Restaurante_SB/get_tasa.php',
    '/Restaurante_Sonia_Burger/Restaurante_SB/gerente/get_tasa.php'
  ];
  for (const url of candidates) {
    try {
      const res = await fetch(url, { credentials: 'include' });
      if (!res.ok) continue;
      const j = await res.json();
      // tu get_tasa.php devuelve { exists: true, monto_bs: ... }
      if (j && (j.monto_bs !== undefined || j.monto !== undefined)) {
        return Number(j.monto_bs ?? j.monto ?? 0);
      }
      // si devuelve exists:false, seguir probando
      if (j && j.exists === false) return null;
    } catch (e) {
      // intentar siguiente candidate
      continue;
    }
  }
  return null;
}

async function verDetalle(r){
  const titulo = qs("detalleTitulo");
  const body = qs("detalleBody");
  const cajaLabel = `Caja ${r.caja_num || r.id}`;

  // 1) Preferir la tasa guardada en el reporte (campo tasa_usd_bs o tasa)
  let tasaVal = null;
  if (r.tasa_usd_bs !== undefined && r.tasa_usd_bs !== null) {
    tasaVal = Number(r.tasa_usd_bs);
  } else if (r.tasa !== undefined && r.tasa !== null) {
    tasaVal = Number(r.tasa);
  } else {
    // 2) Fallback: obtener la última tasa registrada en el sistema (no bloqueante)
    try { tasaVal = await obtenerUltimaTasa(); } catch(e){ tasaVal = null; }
  }

  // construir título incluyendo tasa si existe
  let tituloText = `${cajaLabel} — ${r.fecha_reporte || ''}`;
  if (tasaVal !== null && !isNaN(tasaVal)) {
    const tasaStr = Number(tasaVal) % 1 === 0 ? Number(tasaVal).toFixed(0) : Number(tasaVal).toFixed(2);
    tituloText += ` — Tasa: ${tasaStr} Bs`;
  }
  titulo.textContent = tituloText;

  let pedidos = [];
  try { pedidos = JSON.parse(r.pedidos_json); } catch(e){ pedidos = []; }
  if (!Array.isArray(pedidos)) pedidos = [pedidos];

  const favorito = calcularPlatoFavorito(pedidos);
  let html = `<div class="mb-2"><strong>Total USD:</strong> $${Number(r.total_usd||0).toFixed(2)} — <strong>Total Bs:</strong> Bs ${formatBs(r.total_bs||0)}</div>`;

  // mostrar tasa también en el cuerpo para referencia (usar la misma tasaVal)
  if (tasaVal !== null && !isNaN(tasaVal)) {
    const tasaStr = Number(tasaVal) % 1 === 0 ? Number(tasaVal).toFixed(0) : Number(tasaVal).toFixed(2);
    html += `<div class="mb-2"><strong>Última tasa registrada al momento del cierre:</strong> ${tasaStr} Bs</div>`;
  }

  html += `<div class="mb-3"><strong>Plato preferido de la clientela:</strong> ${escapeHtml(favorito)}</div><hr>`;

  pedidos.forEach(p=>{
    html += `<div class="card mb-2"><div class="card-body">
      <h6 class="card-title">Cliente: ${escapeHtml(p.cliente)}</h6>
      <p class="mb-1"><strong>Tel:</strong> ${escapeHtml(p.telefono)} — <strong>Plato:</strong> ${escapeHtml(p.plato_nombre)}</p>
      <p class="mb-1"><strong>Método:</strong> ${escapeHtml(p.metodo_pago)} — <strong>Total:</strong> $${Number(p.total||0).toFixed(2)} / Bs ${formatBs(p.plato_precio_bs||0)}</p>
      <p class="mb-0 small text-muted">Fecha: ${escapeHtml(p.fecha)}</p>
    </div></div>`;
  });
  body.innerHTML = html;
  const modal = new bootstrap.Modal(qs("detalleModal"));
  modal.show();
}


document.addEventListener("DOMContentLoaded", ()=>{ fetchReportes(); });
