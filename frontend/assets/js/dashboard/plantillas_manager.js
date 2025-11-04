// plantillas_manager.js
// Lógica de gestión de plantillas de mensaje para proveedores

const plantillasManager = {
    plantillas: [],
    async abrirModalGestion() {
        await this.cargarPlantillas();
        document.getElementById('modal-gestionar-plantillas').style.display = 'flex';
    },
    cerrarModalGestion() {
        document.getElementById('modal-gestionar-plantillas').style.display = 'none';
    },
    abrirModalForm(plantilla = null) {
        document.getElementById('form-plantilla').reset();
        document.getElementById('plantilla-id').value = plantilla ? plantilla.id_plantilla : '';
        document.getElementById('plantilla-nombre').value = plantilla ? plantilla.nombre : '';
        document.getElementById('plantilla-contenido').value = plantilla ? plantilla.contenido : '';
        document.getElementById('titulo-modal-plantilla').textContent = plantilla ? 'Editar Plantilla' : 'Nueva Plantilla';
        document.getElementById('modal-form-plantilla').style.display = 'flex';
    },
    cerrarModalForm() {
        document.getElementById('modal-form-plantilla').style.display = 'none';
    },

    editarPlantilla(id) {
        console.log('Editando plantilla con ID:', id);
        console.log('Plantillas disponibles:', this.plantillas);
        const plantilla = this.plantillas.find(p => p.id_plantilla == id);
        if (plantilla) {
            console.log('Plantilla encontrada:', plantilla);
            this.abrirModalForm(plantilla);
        } else {
            console.error('Plantilla no encontrada con ID:', id);
            window.notificacionesManager?.mostrar('Plantilla no encontrada', 'error');
        }
    },
    async cargarPlantillas() {
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php?action=get_plantillas');
            const data = await response.json();
            if (data.success) {
                this.plantillas = data.plantillas;
                this.renderPlantillas();
            } else {
                this.plantillas = [];
                this.renderPlantillas();
            }
        } catch (e) {
            this.plantillas = [];
            this.renderPlantillas();
        }
    },
    renderPlantillas() {
        const cont = document.getElementById('plantillas-lista');
        if (!cont) return;
        if (!this.plantillas.length) {
            cont.innerHTML = '<div class="plantillas-lista-vacio">No hay plantillas registradas</div>';
            return;
        }
        let html = `<table>
            <thead><tr>
                <th>Título</th>
                <th>Mensaje</th>
                <th style="text-align:center;">Acciones</th>
            </tr></thead><tbody>`;
        for (const p of this.plantillas) {
            html += `<tr>
                <td>${p.nombre}</td>
                <td style="white-space:pre-line;max-width:320px;">${p.contenido}</td>
                <td style="text-align:center;">
                    <button class="btn-icon" title="Editar" onclick="plantillasManager.editarPlantilla(${p.id_plantilla})">✏️</button>
                    <button class="btn-icon" title="Eliminar" onclick="plantillasManager.eliminarPlantilla(${p.id_plantilla})">🗑️</button>
                </td>
            </tr>`;
        }
        html += '</tbody></table>';
        cont.innerHTML = html;
    },
    async guardarPlantilla(e) {
        e.preventDefault();
        const id = document.getElementById('plantilla-id').value;
        const nombre = document.getElementById('plantilla-nombre').value.trim();
        const contenido = document.getElementById('plantilla-contenido').value.trim();
        if (!nombre || !contenido) {
            window.notificacionesManager?.mostrar('Complete todos los campos', 'warning');
            return;
        }
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: id ? 'editar_plantilla' : 'crear_plantilla',
                    id_plantilla: id,
                    nombre,
                    contenido
                })
            });
            const result = await response.json();
            if (result.success) {
                window.notificacionesManager?.mostrar('Plantilla guardada', 'success');
                this.cerrarModalForm();
                await this.cargarPlantillas();
            } else {
                window.notificacionesManager?.mostrar(result.message || 'Error al guardar', 'error');
            }
        } catch (e) {
            window.notificacionesManager?.mostrar('Error de conexión', 'error');
        }
    },
    async eliminarPlantilla(id) {
        if (!confirm('¿Eliminar esta plantilla?')) return;
        try {
            const response = await fetch('/Totem_Murialdo/backend/admin/api/api_proveedores.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'eliminar_plantilla', id_plantilla: id })
            });
            const result = await response.json();
            if (result.success) {
                window.notificacionesManager?.mostrar('Plantilla eliminada', 'success');
                await this.cargarPlantillas();
            } else {
                window.notificacionesManager?.mostrar(result.message || 'Error al eliminar', 'error');
            }
        } catch (e) {
            window.notificacionesManager?.mostrar('Error de conexión', 'error');
        }
    },
    bindEvents() {
        document.getElementById('btn-gestionar-plantillas')?.addEventListener('click', () => this.abrirModalGestion());
        document.getElementById('btn-nueva-plantilla')?.addEventListener('click', () => this.abrirModalForm());
        document.getElementById('form-plantilla')?.addEventListener('submit', (e) => this.guardarPlantilla(e));
        document.getElementById('modal-gestionar-plantillas')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this.cerrarModalGestion();
        });
        document.getElementById('modal-form-plantilla')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) this.cerrarModalForm();
        });
    }
};

document.addEventListener('DOMContentLoaded', () => plantillasManager.bindEvents());
