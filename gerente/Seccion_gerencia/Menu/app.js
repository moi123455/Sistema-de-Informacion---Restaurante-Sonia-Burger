// app.js - Gestión del menú (gerente)
// Copia y pega este archivo completo en tu proyecto.

/* =========================
   Configuración y utilidades
   ========================= */
const ROLE = 'gerente'; // ajustar si se usa otra vista

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}
function parseJsonSafe(text) {
  try { return JSON.parse(text); } catch (e) { return null; }
}

/* =========================
   Helpers de layout (2 columnas)
   ========================= */
function obtenerContenedorPrincipal() { return document.getElementById("contenedorPlatos"); }

function obtenerFila(contenedor, filaIndex) {
  let fila = contenedor.querySelector(`.categoria-row[data-row='${filaIndex}']`);
  if (!fila) {
    fila = document.createElement('div');
    fila.className = 'row categoria-row gy-4 justify-content-center';
    fila.setAttribute('data-row', filaIndex);
    contenedor.appendChild(fila);
  }
  return fila;
}

function obtenerColumnaParaIndex(contenedor, index) {
  const filaIndex = Math.floor(index / 2);
  const fila = obtenerFila(contenedor, filaIndex);
  const colPos = index % 2;
  let cols = fila.querySelectorAll('.col-md-6.categoria-col');
  if (cols[colPos]) return cols[colPos];
  while (cols.length <= colPos) {
    const col = document.createElement('div');
    col.className = 'col-md-6 categoria-col d-flex';
    fila.appendChild(col);
    cols = fila.querySelectorAll('.col-md-6.categoria-col');
  }
  return cols[colPos];
}

/* =========================
   Normalización de estilos inline
   ========================= */
function normalizeMenuTextColor() {
  const root = document.getElementById('contenedorPlatos');
  if (!root) return;
  root.querySelectorAll('[style]').forEach(el => {
    try {
      if (el.style && el.style.color) el.style.color = '';
    } catch(e) { /* ignorar elementos inaccesibles */ }
  });
}

/* =========================
   Cargar menú (render)
   ========================= */
async function cargarMenu(horario = null) {
  try {
    const q = new URLSearchParams({ role: ROLE });
    if (horario) q.set('horario', horario);
    const res = await fetch(`menu.php?${q.toString()}`, { credentials: 'include' });
    const data = await res.json();

    const contenedor = obtenerContenedorPrincipal();
    contenedor.innerHTML = "";

    const categoriasOrden = [];
    const mapaCategorias = {};

    data.forEach(p => {
      const cat = p.categoria || 'Sin categoría';
      if (!mapaCategorias[cat]) {
        const idx = categoriasOrden.length;
        categoriasOrden.push(cat);
        mapaCategorias[cat] = { index: idx, items: [] };
      }
      mapaCategorias[cat].items.push(p);
    });

    categoriasOrden.forEach((catName, idx) => {
      const col = obtenerColumnaParaIndex(contenedor, idx);
      const section = document.createElement('section');
      section.className = 'categoria-section w-100';
      const titulo = document.createElement('h3');
      titulo.className = 'categoria-titulo text-center';
      titulo.textContent = catName.toUpperCase();
      section.appendChild(titulo);
      const grid = document.createElement('div');
      grid.className = 'categoria-grid';
      section.appendChild(grid);
      col.appendChild(section);

      mapaCategorias[catName].items.forEach(p => {
        const card = document.createElement('div');
        card.className = 'card producto-card mb-2';
        // Evitar estilos inline; controlar altura desde CSS (.producto-card)
        const imgSrc = p.imagen_url || p.imagen || 'placeholder.png';
        const precioBsHtml = (p.tasa_establecida && p.precio_bs !== undefined) ? parseFloat(p.precio_bs).toFixed(2) : '<span class="text-danger">No establecido</span>';
        const cajeroLabelHtml = p.cajero_label ? `<p class="mb-1 text-${p.cajero_label_color}"><strong>${escapeHtml(p.cajero_label)}</strong></p>` : '';
        const horarioLabel = p.horario ? `<p class="mb-1"><strong>Horario:</strong> ${escapeHtml(p.horario)}</p>` : '';

        card.innerHTML = `
          <img src="${imgSrc}" class="card-img-top producto-img" alt="${escapeHtml(p.nombre)}" onerror="this.src='placeholder.png'">
          <div class="card-body d-flex flex-column">
            <h5 class="card-title">${escapeHtml(p.nombre)}</h5>
            <p class="card-text">${escapeHtml(p.descripcion || '')}</p>
            <p class="mb-1"><strong>Precio:</strong> $${parseFloat(p.precio_usd || 0).toFixed(2)}</p>
            <p class="mb-1"><strong>Precio en Bs:</strong> ${precioBsHtml}</p>
            ${cajeroLabelHtml}
            ${horarioLabel}
            <p class="mb-2"><strong>Estado:</strong> ${escapeHtml(p.estado || 'Activo')}</p>
            <div class="mt-auto d-flex gap-2">
              <button class="btn btn-sm btn-warning" onclick="cambiarEstado(${p.id}, '${p.estado || 'Activo'}')">Estado</button>
              <button class="btn btn-sm btn-primary" onclick="abrirModalEditar(${p.id})">Editar</button>
              <button class="btn btn-sm btn-danger" onclick="abrirModalEliminar(${p.id})">Eliminar</button>
            </div>
          </div>
        `;
        grid.appendChild(card);
      });
    });

    // Normalizar estilos inline que puedan haber sido inyectados
    normalizeMenuTextColor();

  } catch (err) {
    console.error("Error al cargar menú:", err);
  }
}

/* =========================
   Modal de advertencia (helper)
   Devuelve Promise<boolean>
   ========================= */
function showAdvertencia(mensaje, imagenPath = null) {
  return new Promise((resolve) => {
    const modalEl = document.getElementById("modalAdvertencia");
    const mensajeEl = document.getElementById("mensajeAdvertencia");
    const contImg = document.getElementById("contenedorImagenAdvertencia");
    const imgEl = document.getElementById("imagenAdvertencia");
    const btnContinuar = document.getElementById("btnContinuarAdvertencia");

    mensajeEl.textContent = mensaje || "";
    if (imagenPath) {
      imgEl.src = imagenPath;
      contImg.classList.remove("d-none");
    } else {
      imgEl.src = "";
      contImg.classList.add("d-none");
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    function onContinue() {
      cleanup();
      resolve(true);
    }
    function onHide() {
      cleanup();
      resolve(false);
    }
    function cleanup() {
      btnContinuar.removeEventListener("click", onContinue);
      modalEl.removeEventListener("hidden.bs.modal", onHide);
      try { modal.hide(); } catch(e) {}
    }

    btnContinuar.addEventListener("click", onContinue);
    modalEl.addEventListener("hidden.bs.modal", onHide);
  });
}

/* =========================
   Registro y edición (listeners)
   ========================= */
document.addEventListener("DOMContentLoaded", () => {
  // Session / header
  const usuario = localStorage.getItem("usuarioActivo");
  const rol = localStorage.getItem("rolActivo");
  if (usuario && rol) {
    const userNameEl = document.getElementById("userName");
    if (userNameEl) userNameEl.textContent = usuario;
    const roleBadge = document.getElementById("roleBadge");
    if (roleBadge) {
      roleBadge.textContent = rol.charAt(0).toUpperCase() + rol.slice(1);
      if (rol === "gerente") roleBadge.className = "badge bg-warning text-dark me-3";
      else if (rol === "cajero") roleBadge.className = "badge bg-danger me-3";
      else roleBadge.className = "badge bg-success me-3";
    }
  }

  // Logout
  const btnLogout = document.getElementById("btnLogout");
  if (btnLogout) {
    btnLogout.addEventListener("click", async function(e) {
      e.preventDefault();
      try {
        await fetch('/Restaurante_Sonia_Burger/Restaurante_SB/logout.php', { method: 'POST', credentials: 'include' });
      } catch (err) { /* ignorar */ }
      localStorage.removeItem("usuarioActivo");
      localStorage.removeItem("rolActivo");
      localStorage.removeItem("usuarioActivoId");
      window.location.href = "/Restaurante_Sonia_Burger/Restaurante_SB/gerente/index.html";
    });
  }

  // Formulario de registro
  const formRegistro = document.getElementById("formRegistroMenu");
  if (formRegistro) {
    formRegistro.addEventListener("submit", function(e) {
      e.preventDefault();
      const form = this;
      const formData = new FormData(form);
      if (!formData.get("estado")) formData.append("estado", "Activo");

      fetch("menu.php", { method: "POST", body: formData })
        .then(r => r.text())
        .then(text => {
          const data = parseJsonSafe(text);
          if (!data) { console.error("Respuesta no JSON al registrar:", text); alert("Error del servidor al registrar. Revisa error_log.txt."); return; }
          if (data.warning) {
            showAdvertencia(data.warning, data.imagen).then(confirmed => {
              if (confirmed) {
                formData.append("forzar", "1");
                fetch("menu.php", { method: "POST", body: formData })
                  .then(r2 => r2.text())
                  .then(t2 => {
                    const d2 = parseJsonSafe(t2);
                    if (!d2) { alert("Error del servidor al forzar registro."); return; }
                    if (d2.success) { bootstrap.Modal.getInstance(document.getElementById("modalRegistroMenu")).hide(); form.reset(); cargarMenu(); }
                    else if (d2.error) alert("Error: " + d2.error);
                  });
              }
            });
          } else if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById("modalRegistroMenu")).hide();
            form.reset();
            cargarMenu();
          } else if (data.error) {
            alert("Error: " + data.error);
          }
        })
        .catch(err => console.error("Error al registrar producto:", err));
    });
  }

  // Limpiar form al cerrar modal registro
  const modalReg = document.getElementById("modalRegistroMenu");
  if (modalReg) modalReg.addEventListener("hidden.bs.modal", () => {
    const f = document.getElementById("formRegistroMenu");
    if (f) f.reset();
  });

  // Limpiar form al cerrar modal editar
  const modalEdit = document.getElementById("modalEditarMenu");
  if (modalEdit) modalEdit.addEventListener("hidden.bs.modal", () => {
    const f = document.getElementById("formEditarMenu");
    if (f) f.reset();
    const preview = document.getElementById("previewImagenActual");
    if (preview) preview.classList.add("d-none");
  });

  // Filtro horario
  const filtroHorario = document.getElementById('filtroHorario');
  if (filtroHorario) {
    filtroHorario.addEventListener('change', async function() {
      const horario = this.value;
      await cargarMenu(horario);
      try {
        await fetch('set_menu_horario.php', {
          method: 'POST',
          credentials: 'include',
          body: new URLSearchParams({ horario })
        });
      } catch (e) { console.warn('No se pudo persistir horario:', e); }
    });
  }

  // Formulario editar
  const formEditar = document.getElementById("formEditarMenu");
  if (formEditar) {
    formEditar.addEventListener("submit", function(e) {
      e.preventDefault();
      const form = this;
      const formData = new FormData(form);
      const id = document.getElementById("idProductoEditar").value;
      formData.append("id", id);
      if (!formData.get("estado")) formData.append("estado", "Activo");

      fetch("menu.php", { method: "POST", body: formData })
        .then(r => r.text())
        .then(text => {
          const data = parseJsonSafe(text);
          if (!data) { console.error("Respuesta no JSON al actualizar:", text); alert("Error del servidor al actualizar. Revisa error_log.txt."); return; }
          if (data.warning) {
            showAdvertencia(data.warning, data.imagen).then(confirmed => {
              if (confirmed) {
                formData.append("forzar", "1");
                fetch("menu.php", { method: "POST", body: formData })
                  .then(r2 => r2.text())
                  .then(t2 => {
                    const d2 = parseJsonSafe(t2);
                    if (!d2) { alert("Error del servidor al forzar actualización."); return; }
                    if (d2.success) { bootstrap.Modal.getInstance(document.getElementById("modalEditarMenu")).hide(); form.reset(); cargarMenu(); }
                    else if (d2.error) alert("Error: " + d2.error);
                  });
              }
            });
          } else if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById("modalEditarMenu")).hide();
            form.reset();
            cargarMenu();
          } else if (data.error) {
            alert("Error: " + data.error);
          }
        })
        .catch(err => console.error("Error al actualizar producto:", err));
    });
  }

  // Confirm delete
  const btnConfirmarEliminar = document.getElementById("btnConfirmarEliminarMenu");
  if (btnConfirmarEliminar) {
    btnConfirmarEliminar.addEventListener("click", function() {
      const idProducto = document.getElementById("idProductoEliminar").value;
      fetch("menu.php", { method: "DELETE", body: new URLSearchParams({ id: idProducto }) })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById("modalEliminarMenu")).hide();
            cargarMenu(document.getElementById('filtroHorario')?.value || null);
          } else if (data.error) {
            alert("Error al eliminar: " + data.error);
          }
        })
        .catch(err => console.error("Error al eliminar producto:", err));
    });
  }

  // Preview imagen editar
  const inputImagenEditar = document.getElementById("imagenProductoEditar");
  if (inputImagenEditar) {
    inputImagenEditar.addEventListener("change", function() {
      const file = this.files && this.files[0];
      const preview = document.getElementById("previewImagenActual");
      if (file && preview) {
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          preview.classList.remove("d-none");
        };
        reader.readAsDataURL(file);
      }
    });
  }

  // Inicialización: obtener horario activo y cargar productos
  (async function initMenuPage() {
    try {
      const res = await fetch('get_menu_horario.php', { credentials: 'include' });
      const j = await res.json();
      const horarioActivo = (j && j.horario) ? j.horario : 'dia';
      const filtro = document.getElementById('filtroHorario');
      if (filtro) filtro.value = horarioActivo;
      await cargarMenu(horarioActivo);
      // fallback por si hay render asíncrono adicional
      setTimeout(normalizeMenuTextColor, 120);
    } catch (e) {
      console.warn('No se pudo obtener horario activo, cargando por defecto', e);
      await cargarMenu('dia');
      setTimeout(normalizeMenuTextColor, 120);
    }
  })();
});

/* =========================
   Acciones rápidas: estado / eliminar / editar
   ========================= */
function cambiarEstado(id, estadoActual) {
  let nuevoEstado;
  if (estadoActual === "Activo") nuevoEstado = "No disponible";
  else if (estadoActual === "No disponible") nuevoEstado = "En proceso";
  else nuevoEstado = "Activo";

  const params = new URLSearchParams({ id, estado: nuevoEstado });

  fetch("menu.php", { method: "PUT", body: params })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        cargarMenu(document.getElementById('filtroHorario')?.value || null);
      } else if (data.error) {
        console.error("Error cambiar estado:", data.error);
      }
    })
    .catch(err => console.error("Error al cambiar estado:", err));
}

function abrirModalEliminar(idProducto) {
  const input = document.getElementById("idProductoEliminar");
  if (input) input.value = idProducto;
  const modal = new bootstrap.Modal(document.getElementById("modalEliminarMenu"));
  modal.show();
}

/* Abrir modal editar: carga datos del producto y rellena el formulario */
async function abrirModalEditar(idProducto) {
  try {
    // Intentar obtener datos del servidor (menu.php?id=...)
    const res = await fetch(`menu.php?id=${encodeURIComponent(idProducto)}`, { credentials: 'include' });
    const p = await res.json();
    if (!p) { alert("No se encontró el producto."); return; }

    // Rellenar formulario editar
    const f = document.getElementById("formEditarMenu");
    if (!f) return;

    document.getElementById("idProductoEditar").value = p.id || '';
    document.getElementById("nombreProductoEditar").value = p.nombre || '';
    document.getElementById("descripcionProductoEditar").value = p.descripcion || '';
    document.getElementById("categoriaProductoEditar").value = p.categoria || 'Comida';
    document.getElementById("horarioProductoEditar").value = p.horario || 'dia';
    document.getElementById("precioUsdEditar").value = p.precio_usd || '';
    document.getElementById("estadoProductoEditar").value = p.estado || 'Activo';

    const preview = document.getElementById("previewImagenActual");
    if (preview) {
      if (p.imagen_url || p.imagen) {
        preview.src = p.imagen_url || p.imagen;
        preview.classList.remove("d-none");
      } else {
        preview.classList.add("d-none");
      }
    }

    const modal = new bootstrap.Modal(document.getElementById("modalEditarMenu"));
    modal.show();
  } catch (err) {
    console.error("Error al abrir modal editar:", err);
    alert("No se pudo cargar los datos del producto para editar.");
  }
}
