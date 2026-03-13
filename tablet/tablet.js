// tablet.js
const API_BASE = "/Restaurante_Sonia_Burger/Restaurante_SB";
const REALTIME_HOST = "http://192.168.1.139:3000"; // cambia si tu PC tiene otra IP o puerto
let socket = null;
let cajaId = document.getElementById("selectCaja").value;
let token = ""; // se establecerá al pulsar "Usar token"

function showMsg(txt, type="info") {
  const el = document.getElementById("mensaje");
  el.innerHTML = `<div class="alert alert-${type}">${txt}</div>`;
  setTimeout(()=> el.innerHTML = "", 4000);
}

async function cargarMenu() {
  try {
    const res = await fetch(`${API_BASE}/gerente/Seccion_gerencia/Menu/menu_cajero.php`);
    const data = await res.json();
    renderMenu(data || []);
  } catch (e) {
    console.error("Error cargando menú:", e);
    showMsg("Error cargando menú", "danger");
  }
}

function renderMenu(items) {
  const cont = document.getElementById("menu");
  cont.innerHTML = "";
  items.forEach(p => {
    const card = document.createElement("div");
    card.className = "card";
    card.innerHTML = `<div class="card-body">
      <h5 class="card-title">${p.nombre}</h5>
      <p class="card-text">$${Number(p.precio_usd||0).toFixed(2)}</p>
    </div>`;
    card.addEventListener("click", () => seleccionarPlato(p, card));
    cont.appendChild(card);
  });
}

function conectarSocket() {
  if (socket) socket.disconnect();
  if (!token) { showMsg("Debe establecer token antes de conectar", "warning"); return; }
  socket = io(REALTIME_HOST, { auth: { cajaId, token } });
  socket.on("connect", () => showMsg("Conectado al realtime", "success"));
  socket.on("connect_error", (err) => showMsg("Error conexión: " + (err.message||err), "danger"));
}

function seleccionarPlato(plato, cardEl) {
  if (!socket || !socket.connected) {
    showMsg("No conectado al realtime. Pulse 'Usar token' y conecte.", "warning");
    return;
  }
  // animación visual
  document.querySelectorAll(".card").forEach(c=>c.classList.remove("plato-seleccionado"));
  cardEl.classList.add("plato-seleccionado");

  // emitir selección
  socket.emit("seleccion_plato", {
    cajaId,
    platoId: String(plato.id),
    nombre: plato.nombre,
    cantidad: 1
  });
  showMsg(`Seleccionado: ${plato.nombre}`, "info");
}

// UI: cambiar caja o token
document.getElementById("selectCaja").addEventListener("change", (e) => {
  cajaId = e.target.value;
  showMsg("Caja seleccionada: " + cajaId, "info");
});

document.getElementById("btnSetToken").addEventListener("click", async () => {
  // Para pruebas: pedir token al usuario (en producción, token se provisiona desde gerente)
  token = prompt("Introduce token para " + cajaId + " (ej: TOKEN_SECRETO_C1):", "");
  if (token) {
    conectarSocket();
  }
});

// inicializar
cargarMenu();
