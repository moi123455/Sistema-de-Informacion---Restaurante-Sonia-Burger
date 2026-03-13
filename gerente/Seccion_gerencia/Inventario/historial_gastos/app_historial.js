// app_historial.js
// Rutas relativas: coloca este archivo en la misma carpeta que get_reportes_gastos.php y get_reportes_gastos_items.php
const REPORTES_ENDPOINT = 'get_reportes_gastos.php';
const REPORTES_ITEMS_ENDPOINT = 'get_reportes_gastos_items.php';

function escapeHtml(text) {
  if (text === null || text === undefined) return '';
  return String(text)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function formatCurrencyUSD(v) {
  return '$' + (isNaN(v) ? '0.00' : parseFloat(v).toFixed(2));
}
function formatCurrencyBs(v) {
  return 'Bs ' + (isNaN(v) ? '0.00' : parseFloat(v).toFixed(2));
}
function formatTasa(v) {
  if (v === null || v === undefined || v === '') return '—';
  const n = Number(v);
  if (isNaN(n)) return escapeHtml(String(v));
  return Number(n) % 1 === 0 ? n.toFixed(0) + ' Bs' : n.toFixed(2) + ' Bs';
}

// Devuelve el primer campo de tasa disponible en el item
function detectTasa(item) {
  const tasaCandidates = ['tasa', 'tasa_cobrada', 'tasa_cobrado', 'tasa_usd', 'tasa_cobrada_usd', 'tasa_cobrada_bs'];
  for (const c of tasaCandidates) {
    if (Object.prototype.hasOwnProperty.call(item, c) && item[c] !== null && item[c] !== undefined && item[c] !== '') {
      return item[c];
    }
  }
  // fallback: si el item tiene total_bs y total_usd, intentar derivar tasa (total_bs / total_usd) si tiene sentido
  if (item.total_usd && item.total_bs && Number(item.total_usd) > 0) {
    const derived = Number(item.total_bs) / Number(item.total_usd);
    if (isFinite(derived) && derived > 0) return derived;
  }
  return null;
}

async function cargarReportes(query = '') {
  const cont = document.getElementById('listaReportes');
  const sin = document.getElementById('sinReportes');
  cont.innerHTML = '<div class="p-3 text-center text-muted">Cargando...</div>';
  try {
    const res = await fetch(REPORTES_ENDPOINT, { credentials: 'include' });
    if (!res.ok) throw new Error('Error al obtener reportes');
    const list = await res.json();
    const filtered = list.filter(r => {
      if (!query) return true;
      const q = query.toLowerCase();
      return (r.titulo && r.titulo.toLowerCase().includes(q)) || (r.created_at && r.created_at.toLowerCase().includes(q));
    });

    cont.innerHTML = '';
    if (!filtered.length) {
      sin.classList.remove('d-none');
      return;
    }
    sin.classList.add('d-none');

    filtered.forEach(r => {
      const el = document.createElement('div');
      el.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
      el.innerHTML = `
        <div>
          <div class="fw-bold">${escapeHtml(r.titulo)}</div>
          <div class="small text-muted">${escapeHtml(r.created_at)}</div>
        </div>
        <div class="text-end">
          <div class="fw-bold">${formatCurrencyUSD(r.total_usd)}</div>
          <div class="small text-muted">${formatCurrencyBs(r.total_bs)}</div>
          <div class="mt-2">
            <button class="btn btn-sm btn-outline-primary" data-id="${r.id}" onclick="abrirReporte(${r.id}, '${escapeHtml(r.titulo)}')">Ver</button>
          </div>
        </div>
      `;
      cont.appendChild(el);
    });
  } catch (e) {
    console.error(e);
    cont.innerHTML = `<div class="p-3 text-danger">No se pudieron cargar los reportes. Revisa la consola.</div>`;
  }
}

async function abrirReporte(id, titulo = '') {
  try {
    const res = await fetch(`${REPORTES_ITEMS_ENDPOINT}?id=${encodeURIComponent(id)}`, { credentials: 'include' });
    if (!res.ok) throw new Error('Error al obtener items');
    const items = await res.json();

    const tbody = document.getElementById('modalReporteItems');
    tbody.innerHTML = '';
    let totalUsd = 0;
    let totalBs = 0;

    // Construir filas con columna adicional "Tasa"
    items.forEach(it => {
      const tasaVal = detectTasa(it);
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(it.nombre)}</td>
        <td class="text-end">${parseFloat(it.cantidad || 0).toFixed(2)}</td>
        <td>${escapeHtml(it.unidad)}</td>
        <td class="text-end">${formatCurrencyUSD(it.costo_unitario_usd)}</td>
        <td class="text-end">${formatCurrencyUSD(it.total_usd)}</td>
        <td class="text-end">${formatCurrencyBs(it.total_bs)}</td>
        <td class="text-end">${escapeHtml(formatTasa(tasaVal))}</td>
        <td>${escapeHtml(it.proveedor || '')}</td>
        <td>${escapeHtml(it.fecha_hora || '')}</td>
      `;
      tbody.appendChild(tr);
      totalUsd += parseFloat(it.total_usd || 0);
      totalBs += parseFloat(it.total_bs || 0);
    });

    document.getElementById('modalReporteTitulo').textContent = titulo || `Reporte #${id}`;
    document.getElementById('modalReporteTotalUsd').textContent = formatCurrencyUSD(totalUsd);
    document.getElementById('modalReporteTotalBs').textContent = formatCurrencyBs(totalBs);
    document.getElementById('modalReporteNotas').textContent = ''; // si hay notas en cabecera, mostrarlas aquí

    // Attach download handler for this report (CSV includes tasa column)
    const btnDownload = document.getElementById('btnDescargarReporte');
    btnDownload.onclick = () => descargarCSVReporte(items, titulo || `reporte_${id}`);

    // Print handler
    document.getElementById('btnImprimirReporte').onclick = () => {
      window.print();
    };

    const modal = new bootstrap.Modal(document.getElementById('modalReporte'));
    modal.show();
  } catch (e) {
    console.error(e);
    alert('No se pudo cargar el reporte. Revisa la consola.');
  }
}

function descargarCSVReporte(items, filename = 'reporte') {
  // Añadimos 'tasa' al encabezado CSV
  const headers = ['nombre','cantidad','unidad','costo_unitario_usd','total_usd','total_bs','tasa','proveedor','fecha_hora'];
  const rows = items.map(it => {
    const tasaVal = detectTasa(it);
    const rowObj = {
      nombre: it.nombre ?? '',
      cantidad: it.cantidad ?? '',
      unidad: it.unidad ?? '',
      costo_unitario_usd: it.costo_unitario_usd ?? '',
      total_usd: it.total_usd ?? '',
      total_bs: it.total_bs ?? '',
      tasa: tasaVal ?? '',
      proveedor: it.proveedor ?? '',
      fecha_hora: it.fecha_hora ?? ''
    };
    return headers.map(h => `"${String(rowObj[h] ?? '').replace(/"/g, '""')}"`).join(',');
  });
  const csv = [headers.join(','), ...rows].join('\r\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${filename}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

async function descargarTodosCSV() {
  try {
    const res = await fetch(REPORTES_ENDPOINT, { credentials: 'include' });
    if (!res.ok) throw new Error('Error al obtener reportes');
    const list = await res.json();
    // For each report, fetch items and append to CSV with a header row per report
    let csvParts = [];
    for (const r of list) {
      const resItems = await fetch(`${REPORTES_ITEMS_ENDPOINT}?id=${encodeURIComponent(r.id)}`, { credentials: 'include' });
      if (!resItems.ok) continue;
      const items = await resItems.json();
      csvParts.push(`"Reporte","${String(r.titulo).replace(/"/g,'""')}"`);
      csvParts.push(`"Fecha","${String(r.created_at).replace(/"/g,'""')}"`);
      csvParts.push('"nombre","cantidad","unidad","costo_unitario_usd","total_usd","total_bs","tasa","proveedor","fecha_hora"');
      items.forEach(it => {
        const tasaVal = detectTasa(it);
        const row = [
          it.nombre, it.cantidad, it.unidad, it.costo_unitario_usd, it.total_usd, it.total_bs, tasaVal, it.proveedor, it.fecha_hora
        ].map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(',');
        csvParts.push(row);
      });
      csvParts.push(''); // blank line between reports
    }
    const blob = new Blob([csvParts.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `historial_reportes_gastos.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    console.error(e);
    alert('No se pudo descargar el CSV completo. Revisa la consola.');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  cargarReportes();

  document.getElementById('btnRefrescarReportes').addEventListener('click', () => cargarReportes(document.getElementById('filtroBusqueda').value.trim()));
  document.getElementById('btnBuscar').addEventListener('click', () => cargarReportes(document.getElementById('filtroBusqueda').value.trim()));
  document.getElementById('filtroBusqueda').addEventListener('keyup', (e) => { if (e.key === 'Enter') cargarReportes(e.target.value.trim()); });

  document.getElementById('btnDescargarTodos').addEventListener('click', descargarTodosCSV);
});
