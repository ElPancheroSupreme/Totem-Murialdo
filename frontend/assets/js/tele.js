// tele_test.js - Carga dinámica de pedidos en pantalla TV

document.addEventListener('DOMContentLoaded', async function () {

  // Función para obtener pedidos desde el backend
  async function fetchPedidos() {
    try {
      const res = await fetch('../../backend/admin/api/tele_pedidos.php');
      if (!res.ok) throw new Error('Error al obtener pedidos: ' + res.status);
      const data = await res.json();
      return data;
    } catch (e) {
      console.error('No se pudieron obtener los pedidos:', e);
      return { preparacion: [], listos: [] };
    }
  }

  function renderPedidos(lista, contenedorId) {
    const cont = document.getElementById(contenedorId);
    if (!cont) return;
    cont.innerHTML = '';
    if (!Array.isArray(lista)) return;
    lista.forEach((pedido) => {
      const div = document.createElement('div');
      div.className = 'pedido-box';
      div.textContent = pedido.codigo;
      cont.appendChild(div);
    });
  }

  async function actualizarPantalla() {
    const datos = await fetchPedidos();
    renderPedidos(datos.preparacion || [], 'preparacion-list');
    renderPedidos(datos.listos || [], 'listos-list');
  }

  // Primera carga
  await actualizarPantalla();
  // Refrescar cada 10 segundos
  setInterval(actualizarPantalla, 10000);
});
