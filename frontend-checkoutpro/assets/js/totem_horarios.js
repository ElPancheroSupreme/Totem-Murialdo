// Script para frontend del tótem: deshabilita la UI fuera de horario
async function verificarHorariosTotem() {
    try {
        const res = await fetch('/Totem_Murialdo/backend-checkoutpro/admin/api/configuracion_horarios.php');
        const data = await res.json();
        // Nuevo formato: { success: true, config: { habilitar_horarios, dias: { lunes: {...}, ... } } }
        if (!data.success || !data.config) {
            mostrarOverlayFueraHorario('Error de configuración de horarios');
            return;
        }
        const config = data.config;
        if (!config.habilitar_horarios) {
            ocultarOverlayFueraHorario(); // Si no se usan horarios, siempre habilitado
            return;
        }
        // Obtener hora local UTC-3 (Buenos Aires, etc)
        const ahoraUTC = new Date(Date.now() - 3 * 60 * 60 * 1000);
        const dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        const diaActual = dias[ahoraUTC.getUTCDay()];
        const franja = config.dias && config.dias[diaActual];
        if (!franja || !franja.habilitado) {
            mostrarOverlayFueraHorario();
            return;
        }
        const desde = franja.desde;
        const hasta = franja.hasta;
        function horaAminutos(hhmm) {
            const [h, m] = hhmm.split(':').map(Number);
            return h * 60 + m;
        }
        const minActual = ahoraUTC.getUTCHours() * 60 + ahoraUTC.getUTCMinutes();
        const minDesde = horaAminutos(desde);
        const minHasta = horaAminutos(hasta);
        if (minDesde === minHasta) {
            mostrarOverlayFueraHorario();
            return;
        }
        if (!(minActual >= minDesde && minActual < minHasta)) {
            mostrarOverlayFueraHorario();
            return;
        }
        ocultarOverlayFueraHorario();
    } catch (e) {
        mostrarOverlayFueraHorario('Error al verificar horarios');
    }
}

function mostrarOverlayFueraHorario(msg) {
    let overlay = document.getElementById('fuera-horario-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'fuera-horario-overlay';
        overlay.style.position = 'fixed';
        overlay.style.top = 0;
        overlay.style.left = 0;
        overlay.style.width = '100vw';
        overlay.style.height = '100vh';
        overlay.style.background = 'rgba(0,0,0,0.85)';
        overlay.style.zIndex = 9999;
        overlay.style.display = 'flex';
        overlay.style.flexDirection = 'column';
        overlay.style.justifyContent = 'center';
        overlay.style.alignItems = 'center';
        overlay.style.color = '#fff';
        overlay.style.fontSize = '2rem';
        overlay.innerHTML = `<div style='background:#222;padding:32px 48px;border-radius:18px;box-shadow:0 4px 32px #0008;text-align:center;'>
      <div style='font-size:2.2rem;margin-bottom:18px;'>⏰</div>
      <div>El tótem está fuera del horario de atención</div>
      <div style='font-size:1.1rem;margin-top:10px;'>${msg || 'Por favor, vuelva en el horario habilitado.'}</div>
    </div>`;
        document.body.appendChild(overlay);
    } else {
        overlay.style.display = 'flex';
    }
    // Deshabilitar toda la UI
    document.body.style.pointerEvents = 'none';
    overlay.style.pointerEvents = 'auto';
}

function ocultarOverlayFueraHorario() {
    const overlay = document.getElementById('fuera-horario-overlay');
    if (overlay) overlay.style.display = 'none';
    document.body.style.pointerEvents = '';
}

// Llamar a la función al cargar el tótem y cada minuto
verificarHorariosTotem();
setInterval(verificarHorariosTotem, 60000);

