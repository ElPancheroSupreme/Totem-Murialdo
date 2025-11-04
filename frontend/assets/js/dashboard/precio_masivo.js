// Lógica para el botón de tipo de precio masivo ($/%):
document.addEventListener('DOMContentLoaded', function () {
  const btnTipo = document.getElementById('btn-precio-tipo');
  if (btnTipo) {
    btnTipo.addEventListener('click', function () {
      btnTipo.textContent = btnTipo.textContent.trim() === '$' ? '%' : '$';
    });
  }
  const btnAplicar = document.getElementById('btn-precio-aplicar');
  if (btnAplicar) {
    btnAplicar.addEventListener('click', async function () {
      const tipo = document.getElementById('btn-precio-tipo').textContent.trim();
      const valor = parseFloat(document.getElementById('input-precio-masivo').value);
      if (isNaN(valor) || valor === 0) {
        // Usar sistema unificado de notificaciones si está disponible
        if (window.notificacionesManager) {
          window.notificacionesManager.info('Ingresa un valor válido para modificar el precio');
        } else if (window.productosManager?.mostrarAlerta) {
          window.productosManager.mostrarAlerta('Ingresa un valor válido para modificar el precio', 'info');
        }
        return;
      }
      // Obtener productos seleccionados
      const checkboxes = document.querySelectorAll('.producto-checkbox:checked');
      if (checkboxes.length === 0) {
        // Usar sistema unificado de notificaciones si está disponible
        if (window.notificacionesManager) {
          window.notificacionesManager.info('Selecciona productos para modificar el precio');
        } else if (window.productosManager?.mostrarAlerta) {
          window.productosManager.mostrarAlerta('Selecciona productos para modificar el precio', 'info');
        }
        return;
      }
      let actualizados = 0;
      for (const cb of checkboxes) {
        const id = cb.getAttribute('data-producto-id');
        // Obtener datos actuales del producto
        const producto = window.productosManager?.productos.find(p => (p.id_producto || p.id) == id);
        if (!producto) continue;
        let nuevoPrecio = parseFloat(producto.precio_venta);
        if (tipo === '$') {
          nuevoPrecio += valor;
        } else {
          nuevoPrecio += (nuevoPrecio * valor / 100);
        }
        // No permitir precios negativos
        if (nuevoPrecio < 0) nuevoPrecio = 0;
        // Actualizar producto
        const formData = new FormData();
        formData.append('nombre', producto.nombre);
        formData.append('precio_venta', nuevoPrecio.toFixed(2));
        formData.append('precio_lista', producto.precio_lista || '');
        formData.append('id_categoria', producto.id_categoria);
        formData.append('id_punto_venta', producto.id_punto_venta);
        formData.append('es_personalizable', producto.es_personalizable);
        // No se actualiza imagen ni opciones_personalizacion
        await window.productosManager.actualizarProducto(id, formData);
        actualizados++;
      }
      if (actualizados > 0) {
        // Usar sistema unificado de notificaciones si está disponible
        if (window.notificacionesManager) {
          window.notificacionesManager.exito(`Se actualizaron los precios de ${actualizados} productos.`);
        } else if (window.productosManager?.mostrarAlerta) {
          window.productosManager.mostrarAlerta(`Se actualizaron los precios de ${actualizados} productos.`, 'success');
        }
      }
    });
  }
});
