// app_reportes_gerente.js - Reportes del Gerente (final: fecha única en tarjetas + total = neto del día)
const API_BASE = '/Restaurante_Sonia_Burger/Restaurante_SB/gerente/Seccion_gerencia/Reportes/api';

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

function formatUSD(n) {
  const num = Number(n) || 0;
  const abs = Math.abs(num);
  const formatted = `$${formatNumber(abs, 2, '.', ',')}`;
  return (num < 0) ? `-${formatted}` : formatted;
}

async function cargarSessionInfo() {
  try {
    const res = await fetch(`${API_BASE}/session_info.php`, { credentials: 'include' });
    if (!res.ok) return;
    const j = await res.json();
    if (!j.success) return;
    const userEl = document.getElementById('userName');
    if (userEl) userEl.textContent = j.user_name || '';
    const roleBadge = document.getElementById('roleBadge');
    if (roleBadge) {
      const role = (j.user_role || '').toLowerCase();
      roleBadge.textContent = j.user_role || '';
      if (role === 'gerente') roleBadge.className = 'badge bg-warning text-dark me-3';
      else if (role === 'cajero') roleBadge.className = 'badge bg-danger me-3';
      else roleBadge.className = 'badge bg-secondary me-3';
    }
  } catch (err) {
    console.error('No se pudo cargar session info', err);
  }
}

async function cargarResumenDefault() {
  await cargarSessionInfo();
  const desde = new Date(); desde.setHours(0,0,0,0);
  const hasta = new Date(); hasta.setHours(23,59,59,999);
  await cargarResumen(desde.toISOString().slice(0,19).replace('T',' '), hasta.toISOString().slice(0,19).replace('T',' '));
  await cargarTodosLosCierres();
}

async function cargarResumen(desde, hasta) {
  try {
    const q = `?desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`;
    const res = await fetch(`${API_BASE}/summary.php${q}`, { credentials: 'include' });
    if (!res.ok) {
      const text = await res.text();
      console.error('summary.php response not ok', res.status, text);
      throw new Error('Error al obtener resumen');
    }
    const data = await res.json();
    if (!data.success) throw new Error('Error al obtener resumen');

    const periods = data.periods || {};

    // Hoy: ganancias y pérdidas (solo del día)
    const hoyG = periods.today?.ganancias || {usd:0,bs:0, list: []};
    const hoyP = periods.today?.perdidas || {usd:0,bs:0, list: []};
    setTextSafe('gananciasHoy', `USD: ${formatUSD(hoyG.usd)} — Bs: ${formatNumber(hoyG.bs,2)}`);
    setTextSafe('perdidasHoy', `USD: ${formatUSD(hoyP.usd)} — Bs: ${formatNumber(hoyP.bs,2)}`);

    // Semana
    const semG = periods.week?.ganancias || {usd:0,bs:0};
    const semP = periods.week?.perdidas || {usd:0,bs:0};
    setTextSafe('gananciasSemana', `USD: ${formatUSD(semG.usd)} — Bs: ${formatNumber(semG.bs,2)}`);
    setTextSafe('perdidasSemana', `USD: ${formatUSD(semP.usd)} — Bs: ${formatNumber(semP.bs,2)}`);

    // Mes
    const mesG = periods.month?.ganancias || {usd:0,bs:0};
    const mesP = periods.month?.perdidas || {usd:0,bs:0};
    setTextSafe('gananciasMes', `USD: ${formatUSD(mesG.usd)} — Bs: ${formatNumber(mesG.bs,2)}`);
    setTextSafe('perdidasMes', `USD: ${formatUSD(mesP.usd)} — Bs: ${formatNumber(mesP.bs,2)}`);

    // ----------------------------
    // NETO DEL DÍA (esto es lo que se mostrará en el centro)
    // Solo toma en cuenta cierres realizados hoy; si no hay cierres hoy, queda en 0.
    const netUsdToday = (hoyG.usd || 0) - (hoyP.usd || 0);
    const netBsToday  = (hoyG.bs  || 0) - (hoyP.bs  || 0);

    const netoAmountEl = document.getElementById('netoAmount');
    if (netoAmountEl) {
      // Si no hay cierres hoy (ambos arrays vacíos o ambos 0), mostrar 0
      const hayCierresHoy = ((hoyG.usd || 0) !== 0) || ((hoyP.usd || 0) !== 0) || ((hoyG.bs || 0) !== 0) || ((hoyP.bs || 0) !== 0);
      if (!hayCierresHoy) {
        netoAmountEl.textContent = `USD: $0.00 — Bs: ${formatNumber(0,2)}`;
        netoAmountEl.classList.remove('text-danger'); netoAmountEl.classList.add('text-muted');
      } else {
        netoAmountEl.textContent = `USD: ${formatUSD(netUsdToday)} — Bs: ${formatNumber(netBsToday,2)}`;
        if (netUsdToday >= 0) {
          netoAmountEl.classList.remove('text-danger'); netoAmountEl.classList.add('text-success');
        } else {
          netoAmountEl.classList.remove('text-success'); netoAmountEl.classList.add('text-danger');
        }
      }
    }

    // Mensaje descriptivo (usa neto del día)
    const msgEl = document.getElementById('netoMessage');
    if (msgEl) {
      const mainLine = (netUsdToday >= 0) ? 'Se han generado en el día' : 'Se han perdido en el día';
      const detailLine = `USD: ${formatUSD(netUsdToday)} — Bs: ${formatNumber(netBsToday,2)}`;
      const compareLine = (netUsdToday >= 0) ? 'Las ganancias superan a las pérdidas' : 'Las pérdidas superan a las ganancias';
      const pctText = getPctText(semG.usd, semP.usd);
      // Si no hay cierres hoy, mostrar mensaje claro
      if (!((hoyG.usd || 0) || (hoyP.usd || 0) || (hoyG.bs || 0) || (hoyP.bs || 0))) {
        msgEl.innerHTML = `<div class="fw-semibold">No hay cierres registrados hoy</div>
                           <div class="mt-1 small text-muted">Realiza cierres de caja o gastos para que aparezcan en Total</div>`;
      } else {
        msgEl.innerHTML = `<div class="fw-semibold">${mainLine}</div>
                           <div class="mt-1">${detailLine}</div>
                           <div class="mt-2 small text-muted">${compareLine} — <strong>${pctText}</strong></div>`;
      }
    }

    // Estado (porcentaje) - basado en semana (como referencia)
    const statusEl = document.getElementById('netoStatus');
    if (statusEl) {
      let pct = 0;
      if (semG.usd > 0) pct = Math.round(((semG.usd || 0) - (semP.usd || 0)) / semG.usd * 100);
      else if ((semG.usd || 0) === 0 && (semP.usd || 0) > 0) pct = -100;
      if ((semG.usd || 0) - (semP.usd || 0) >= 0) {
        statusEl.textContent = `Ganancia ${pct}%`;
        statusEl.className = 'mb-3 text-success';
      } else {
        statusEl.textContent = `Pérdida ${Math.abs(pct)}%`;
        statusEl.className = 'mb-3 text-danger';
      }
    }

    // Actualizar netos por periodo (pequeños)
    setTextSafe('netoSemana', `${formatUSD((semG.usd||0)-(semP.usd||0))}`);
    setTextSafe('netoMes', `${formatUSD((mesG.usd||0)-(mesP.usd||0))}`);

    // Mostrar netoHoySmall con neto del día (o 0 si no hay cierres)
    setTextSafe('netoHoySmall', `${formatUSD(netUsdToday)} — Bs: ${formatNumber(netBsToday,2)}`);

    // Totales por periodo (llamadas adicionales si tu API las necesita)
    await cargarTotalesPeriodo('week');
    await cargarTotalesPeriodo('month');

  } catch (err) {
    console.error(err);
    alert('Error cargando resumen');
  }
}

function getPctText(ganUsd, perUsd) {
  const gan = Number(ganUsd || 0);
  const per = Number(perUsd || 0);
  const net = gan - per;
  if (gan === 0 && per === 0) return '0%';
  if (gan === 0) return '-100%';
  return `${Math.round((net / (gan || 1)) * 100)}%`;
}

async function cargarTodosLosCierres() {
  try {
    const desde = '1970-01-01 00:00:00';
    const hasta = new Date().toISOString().slice(0,19).replace('T',' ');
    const q = `?desde=${encodeURIComponent(desde)}&hasta=${encodeURIComponent(hasta)}`;
    const res = await fetch(`${API_BASE}/summary.php${q}`, { credentials: 'include' });
    if (!res.ok) {
      const text = await res.text();
      console.error('summary.php (todos) response not ok', res.status, text);
      return;
    }
    const data = await res.json();
    if (!data.success) return;
    renderList('gananciasList', data.ganancias, 'ganancia');
    renderList('perdidasList', data.perdidas, 'perdida');
  } catch (err) {
    console.error('Error cargando todos los cierres', err);
  }
}

function setTextSafe(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

function renderList(containerId, items, tipo) {
  const cont = document.getElementById(containerId);
  if (!cont) return;
  cont.innerHTML = '';
  if (!items || items.length === 0) {
    cont.innerHTML = `<div class="text-muted">No hay registros</div>`;
    return;
  }

  // helper: extrae la última ocurrencia de "YYYY-MM-DD HH:MM:SS" o devuelve el texto limpio
  function normalizeCreated(raw) {
    if (!raw) return '';
    const s = String(raw);
    const matches = s.match(/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/g);
    if (matches && matches.length) return matches[matches.length - 1];
    const dateOnly = s.match(/\d{4}-\d{2}-\d{2}/);
    const timeOnly = s.match(/\d{2}:\d{2}:\d{2}/);
    if (dateOnly && timeOnly) return `${dateOnly[0]} ${timeOnly[0]}`;
    if (dateOnly) return dateOnly[0];
    return s.trim();
  }

  // helper: quitar una fecha final tipo " - YYYY-MM-DD" del título si existe
  function stripTrailingDateFromTitle(t) {
    if (!t) return '';
    return String(t).replace(/\s*-\s*\d{4}-\d{2}-\d{2}\s*$/, '').trim();
  }

  items.forEach(it => {
    const card = document.createElement('div');
    card.className = 'card p-2 mb-2';

    // Obtener una sola fecha/hora normalizada
    const rawCreated = it.created_at || it.fecha || '';
    const created = normalizeCreated(rawCreated);

    // Construir título base (sin fecha al final)
    let titleBase;
    if (tipo === 'ganancia') {
      const caja = it.caja_num || it.id;
      titleBase = `Caja ${caja}`;
    } else {
      const t = it.titulo || `Gasto ${it.id}`;
      titleBase = stripTrailingDateFromTitle(t);
    }

    const usd = (it.total_usd !== undefined && it.total_usd !== null) ? formatUSD(it.total_usd) : '-';
    const bsNum = (it.total_bs !== undefined && it.total_bs !== null) ? formatNumber(it.total_bs,2) : '-';

    let tasa = it.tasa || it.tasa_usd_bs || null;
    if ((tasa === null || tasa === '' || Number(tasa) === 0) && it.total_usd && Number(it.total_usd) != 0) {
      tasa = Number(it.total_bs) / Number(it.total_usd);
    }
    const tasaLabel = tipo === 'ganancia' ? 'Última tasa registrada' : 'Tasa cobrada';

    // Título final: solo una vez "Título - YYYY-MM-DD HH:MM:SS"
    const titleLine = created ? `${titleBase} - ${created}` : titleBase;

    card.innerHTML = `
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="fw-bold">${titleLine}</div>
          <div class="small">USD: ${usd}</div>
          <div class="small">Bs: ${bsNum}</div>
          <div class="small">${tasaLabel}: ${tasa !== null && tasa !== '' ? formatNumber(tasa,2) : '-'}</div>
        </div>
      </div>
    `;
    if (tipo === 'ganancia') card.style.borderLeft = '4px solid #28a745';
    else card.style.borderLeft = '4px solid #dc3545';
    cont.appendChild(card);
  });
}



async function cargarTotalesPeriodo(periodo) {
  try {
    const res = await fetch(`${API_BASE}/totales_periodo.php?periodo=${periodo}`, { credentials:'include' });
    if (!res.ok) {
      const text = await res.text();
      console.error('totales_periodo.php response not ok', res.status, text);
      return;
    }
    const j = await res.json();
    if (!j.success) return;
    const g = j.ganancias, p = j.perdidas;
    if (periodo === 'week') {
      setTextSafe('gananciasSemana', `USD: ${formatUSD(g.usd)} — Bs: ${formatNumber(g.bs,2)}`);
      setTextSafe('perdidasSemana', `USD: ${formatUSD(p.usd)} — Bs: ${formatNumber(p.bs,2)}`);
      setTextSafe('netoSemana', `${formatUSD((g.usd||0)-(p.usd||0))}`);
    } else {
      setTextSafe('gananciasMes', `USD: ${formatUSD(g.usd)} — Bs: ${formatNumber(g.bs,2)}`);
      setTextSafe('perdidasMes', `USD: ${formatUSD(p.usd)} — Bs: ${formatNumber(p.bs,2)}`);
      setTextSafe('netoMes', `${formatUSD((g.usd||0)-(p.usd||0))}`);
    }
  } catch (err) {
    console.error(err);
  }
}

async function descargarReporteImagen() {
  // Ajustes (modifica si quieres más/menos oscuridad)
  const SCALE = 1.5;
  const BLUR_PX = 8;
  const BRIGHTNESS = 0.45;
  const IMG_NAME = 'fondo.jpg'; // nombre del archivo en la misma carpeta del HTML

  const hideButtons = () => document.querySelectorAll('#btnDescargarReporte, #btnLimpiarReportes').forEach(b => b.style.visibility = 'hidden');
  const showButtons = () => document.querySelectorAll('#btnDescargarReporte, #btnLimpiarReportes').forEach(b => b.style.visibility = 'visible');

  // Resuelve la URL absoluta de fondo.jpg respecto a la página actual
  const resolveBackgroundUrl = (name) => {
    try {
      return new URL(name, window.location.href).href;
    } catch (e) {
      return name;
    }
  };

  const BACKGROUND_SRC = resolveBackgroundUrl(IMG_NAME);

  try {
    hideButtons();

    // Intento de cargar la imagen (misma origen: no necesita crossOrigin)
    const img = new Image();
    img.crossOrigin = ''; // dejar vacío para evitar forzar CORS si es mismo origen
    const imgLoad = new Promise((res, rej) => {
      img.onload = () => res();
      img.onerror = () => rej(new Error('No se pudo cargar la imagen de fondo desde: ' + BACKGROUND_SRC));
    });
    img.src = BACKGROUND_SRC;

    // Si la imagen no carga, haremos fallback con overlay oscuro
    let imgLoaded = true;
    try { await imgLoad; } catch (err) { console.warn(err); imgLoaded = false; }

    // Crear overlays reales (si la imagen carga, usaremos canvas procesado; si no, solo overlay oscuro)
    let overlayBg = null;
    let overlayShade = null;

    if (imgLoaded && typeof OffscreenCanvas !== 'undefined' || true) {
      // Intentar procesar la imagen en canvas con ctx.filter (si falla, fallback más abajo)
      try {
        // Canvas con tamaño del viewport (para que cubra igual que en pantalla)
        const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        const vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(vw * SCALE);
        canvas.height = Math.round(vh * SCALE);
        const ctx = canvas.getContext('2d');

        // Aplicar filtro si está soportado
        if (ctx && 'filter' in ctx) {
          ctx.filter = `blur(${BLUR_PX}px) brightness(${BRIGHTNESS})`;

          // Dibujar la imagen escalada para cubrir el canvas (cover)
          const imgRatio = img.width / img.height;
          const canvasRatio = canvas.width / canvas.height;
          let dw, dh, dx, dy;
          if (imgRatio > canvasRatio) {
            dh = canvas.height;
            dw = Math.round(dh * imgRatio);
            dx = Math.round((canvas.width - dw) / 2);
            dy = 0;
          } else {
            dw = canvas.width;
            dh = Math.round(dw / imgRatio);
            dx = 0;
            dy = Math.round((canvas.height - dh) / 2);
          }
          ctx.drawImage(img, dx, dy, dw, dh);

          // Crear overlay con la imagen procesada (dataURL)
          const dataUrl = canvas.toDataURL('image/png');
          overlayBg = document.createElement('div');
          Object.assign(overlayBg.style, {
            position: 'fixed',
            inset: '0',
            backgroundImage: `url("${dataUrl}")`,
            backgroundSize: 'cover',
            backgroundPosition: 'center center',
            zIndex: '0',
            pointerEvents: 'none'
          });
          document.body.appendChild(overlayBg);

          // Capa semitransparente encima para igualar tu gradiente
          overlayShade = document.createElement('div');
          Object.assign(overlayShade.style, {
            position: 'fixed',
            inset: '0',
            background: `linear-gradient(180deg, rgba(11,11,11,0.45), rgba(11,11,11,0.65))`,
            zIndex: '1',
            pointerEvents: 'none'
          });
          document.body.appendChild(overlayShade);
        } else {
          // ctx.filter no soportado: fallback a overlay simple (sin procesado)
          imgLoaded = false;
        }
      } catch (err) {
        console.warn('Error procesando imagen en canvas:', err);
        imgLoaded = false;
      }
    }

    // Si no pudimos procesar la imagen, usar overlay con la imagen sin filtros + capa oscura encima
    if (!imgLoaded) {
      overlayBg = document.createElement('div');
      Object.assign(overlayBg.style, {
        position: 'fixed',
        inset: '0',
        backgroundImage: `url("${BACKGROUND_SRC}")`,
        backgroundSize: 'cover',
        backgroundPosition: 'center center',
        zIndex: '0',
        pointerEvents: 'none'
      });
      overlayShade = document.createElement('div');
      Object.assign(overlayShade.style, {
        position: 'fixed',
        inset: '0',
        background: `linear-gradient(180deg, rgba(11,11,11,0.62), rgba(11,11,11,0.67))`,
        zIndex: '1',
        pointerEvents: 'none'
      });
      document.body.appendChild(overlayBg);
      document.body.appendChild(overlayShade);
    }

    // Asegurar que el contenido quede por encima
    const contentEls = Array.from(document.querySelectorAll('header, main, .container, .header-container'));
    contentEls.forEach(el => {
      el.__oldPosition = el.style.position || '';
      el.__oldZ = el.style.zIndex || '';
      el.style.position = el.style.position || 'relative';
      el.style.zIndex = '2';
    });

    // Esperar a que el navegador pinte
    await new Promise(r => setTimeout(r, 180));

    // Capturar el contenedor deseado
    const target = document.querySelector('main') || document.body;
    const canvasCapture = await html2canvas(target, {
      scale: SCALE,
      useCORS: true,
      logging: false,
      backgroundColor: null,
      allowTaint: false
    });

    // Restaurar
    showButtons();
    overlayBg?.remove();
    overlayShade?.remove();
    contentEls.forEach(el => {
      if (el.__oldPosition !== undefined) el.style.position = el.__oldPosition;
      if (el.__oldZ !== undefined) el.style.zIndex = el.__oldZ;
      delete el.__oldPosition; delete el.__oldZ;
    });

    // Descargar
    const finalDataUrl = canvasCapture.toDataURL('image/png');
    const a = document.createElement('a');
    a.href = finalDataUrl;
    const now = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
    a.download = `reporte-gerente-${now}.png`;
    document.body.appendChild(a);
    a.click();
    a.remove();

  } catch (err) {
    // Restaurar en caso de error
    showButtons();
    document.getElementById('captureBgReal')?.remove();
    document.getElementById('captureShadeReal')?.remove();
    document.querySelectorAll('header, main, .container, .header-container').forEach(el => {
      if (el.__oldPosition !== undefined) el.style.position = el.__oldPosition;
      if (el.__oldZ !== undefined) el.style.zIndex = el.__oldZ;
      delete el.__oldPosition; delete el.__oldZ;
    });
    console.error('Error generando captura:', err);
    alert('No se pudo generar la imagen del reporte. Revisa la consola para más detalles.');
  }
}



async function limpiarReportes() {
  if (!confirm('¿Estás seguro? Esto eliminará TODOS los reportes y gastos.')) return;
  if (!confirm('CONFIRMACIÓN FINAL: ¿Deseas eliminar permanentemente todos los reportes? Esta acción no se puede deshacer.')) return;

  try {
    const res = await fetch(`${API_BASE}/clear_all.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ confirm: true })
    });
    if (!res.ok) {
      const text = await res.text();
      console.error('clear_all.php response not ok', res.status, text);
      alert('Error al limpiar reportes');
      return;
    }
    const j = await res.json();
    if (!j.success) {
      alert('No se pudo limpiar reportes: ' + (j.error || 'Error'));
      return;
    }
    alert('Reportes eliminados correctamente.');
    cargarResumenDefault();
  } catch (err) {
    console.error(err);
    alert('Error al limpiar reportes');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarResumenDefault();
  document.getElementById('btnDescargarReporte')?.addEventListener('click', descargarReporteImagen);
  document.getElementById('btnLimpiarReportes')?.addEventListener('click', limpiarReportes);
 // Logout robusto: llama al logout del servidor, borra localStorage, elimina cookie PHPSESSID y redirige
document.getElementById('btnLogout')?.addEventListener('click', async function(e){
  e.preventDefault();

  try {
    // Llamada al servidor para destruir sesión
    const res = await fetch('/Restaurante_Sonia_Burger/Restaurante_SB/logout.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' }
    });

    // Intentar parsear respuesta JSON (si el servidor devuelve JSON)
    let ok = false;
    if (res && res.ok) {
      try { const j = await res.json(); ok = !!j.success; } catch(_) { ok = true; }
    }

    // Limpiar estado local siempre
    localStorage.removeItem("usuarioActivo");
    localStorage.removeItem("rolActivo");
    localStorage.removeItem("usuarioActivoId");

    // Eliminar cookie de sesión PHP (intentar borrar PHPSESSID)
    // Nota: la cookie debe borrarse con el mismo path/domain que se creó.
    document.cookie = 'PHPSESSID=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';

    // Redirigir al index del proyecto (ruta relativa en el servidor)
    window.location.href = '/Restaurante_Sonia_Burger/Restaurante_SB/gerente/index.html';
    return;
  } catch (err) {
    console.warn('Logout request failed', err);
    // Aun así limpiar y redirigir
    localStorage.removeItem("usuarioActivo");
    localStorage.removeItem("rolActivo");
    localStorage.removeItem("usuarioActivoId");
    document.cookie = 'PHPSESSID=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
    window.location.href = '/Restaurante_Sonia_Burger/Restaurante_SB/gerente/index.html';
  }
});



});
// Aplica clases de estado a los elementos de totales para mantener color
function applyTotalsStateClasses(netUsdToday, netBsToday, hoyG, hoyP) {
  // neto central
  const netoEl = document.getElementById('netoAmount');
  if (netoEl) {
    netoEl.classList.remove('text-success','text-danger','text-muted');
    if ((hoyG?.usd || 0) === 0 && (hoyP?.usd || 0) === 0) {
      netoEl.classList.add('text-muted');
    } else if (netUsdToday >= 0) {
      netoEl.classList.add('text-success');
    } else {
      netoEl.classList.add('text-danger');
    }
  }

  // totales pequeños (hoy/semana/mes) — ganancias
  ['gananciasHoy','gananciasSemana','gananciasMes'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('text-success','text-danger');
    // si el texto contiene USD negativo, marcar como danger
    const txt = (el.textContent||'').trim();
    if (txt.includes('-$')) el.classList.add('text-danger'); else el.classList.add('text-success');
  });

  // totales pequeños — perdidas (mantener rojo si hay valor)
  ['perdidasHoy','perdidasSemana','perdidasMes'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('text-success','text-danger');
    const txt = (el.textContent||'').trim();
    // si hay monto distinto de 0, marcar como danger; si 0, quitar
    if (!/0+(\.0+)?/.test(txt.replace(/[^0-9.,-]/g,''))) el.classList.add('text-danger');
  });

  // valores laterales (si usas spans con clase .totales-valor)
  document.querySelectorAll('#gananciasCol .totales-valor').forEach(el => {
    el.classList.remove('text-success','text-danger');
    el.classList.add('text-success');
  });
  document.querySelectorAll('#perdidasCol .totales-valor').forEach(el => {
    el.classList.remove('text-success','text-danger');
    el.classList.add('text-danger');
  });
}
