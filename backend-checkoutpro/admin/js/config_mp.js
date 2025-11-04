// backend/admin/js/config_mp.js

document.addEventListener('DOMContentLoaded', function () {
    const guardarBtn = document.createElement('button');
    guardarBtn.textContent = 'Guardar Credenciales';
    guardarBtn.className = 'btn-green';
    guardarBtn.style = 'margin-top:18px;min-width:180px;';

    // Insertar el botón al final de la sección de seguridad
    const panelSeguridad = document.getElementById('panel-seguridad');
    if (panelSeguridad) {
        panelSeguridad.appendChild(guardarBtn);
    }

    guardarBtn.addEventListener('click', async function () {
        const keys = [
            'MP_ACCESS_TOKEN',
            'MP_PUBLIC_KEY',
            'MP_CLIENT_ID',
            'MP_CLIENT_SECRET',
            'MP_USER_ID',
            'MP_EXTERNAL_POS_ID',
            'MP_SPONSOR_ID',
            'MP_ENVIRONMENT',
            'MP_NOTIFICATION_URL',
            'APP_NAME',
            'APP_VERSION'
        ];
        const data = {};
        keys.forEach(key => {
            let input = document.getElementById(key.toLowerCase().replace(/_/g, '-'));
            if (input) {
                data[key] = input.value;
            }
        });
        guardarBtn.disabled = true;
        guardarBtn.textContent = 'Guardando...';
        try {
            const res = await fetch('/Totem_Murialdo/backend/admin/api/guardar_config_mp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                guardarBtn.textContent = 'Guardado ✔';
                setTimeout(() => {
                    guardarBtn.textContent = 'Guardar Credenciales';
                    guardarBtn.disabled = false;
                }, 1500);
            } else {
                guardarBtn.textContent = 'Error';
                guardarBtn.disabled = false;
                alert('No se pudo guardar.');
            }
        } catch (e) {
            guardarBtn.textContent = 'Error';
            guardarBtn.disabled = false;
            alert('Error de conexión.');
        }
    });
});
