// Utility function for showing notifications
function mostrarMensaje(mensaje, tipo = 'info') {
    // Create notification container if it doesn't exist
    let notifContainer = document.getElementById('notif-container');
    if (!notifContainer) {
        notifContainer = document.createElement('div');
        notifContainer.id = 'notif-container';
        notifContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(notifContainer);
    }

    // Create notification
    const notif = document.createElement('div');
    notif.className = `notificacion ${tipo}`;
    notif.style.cssText = `
        padding: 12px 24px;
        border-radius: 6px;
        color: white;
        font-size: 14px;
        max-width: 300px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        position: relative;
    `;

    // Set styles based on type
    switch (tipo) {
        case 'success':
            notif.style.backgroundColor = '#059669';
            break;
        case 'error':
            notif.style.backgroundColor = '#dc2626';
            break;
        case 'warning':
            notif.style.backgroundColor = '#d97706';
            break;
        default:
            notif.style.backgroundColor = '#3b82f6';
    }

    // Add message and close button
    notif.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px;">
            <span>${mensaje}</span>
            <button style="background: none; border: none; color: white; cursor: pointer; padding: 0; margin-left: auto;">✕</button>
        </div>
    `;

    // Add to container
    notifContainer.appendChild(notif);

    // Animate in
    setTimeout(() => {
        notif.style.opacity = '1';
        notif.style.transform = 'translateX(0)';
    }, 10);

    // Setup auto-close and events
    const closeNotif = () => {
        notif.style.opacity = '0';
        notif.style.transform = 'translateX(100%)';
        setTimeout(() => notif.remove(), 300);
    };

    // Close button click
    notif.querySelector('button').onclick = closeNotif;

    // Auto-close after 5 seconds
    setTimeout(closeNotif, 5000);
}

// Make it available globally
window.mostrarMensaje = mostrarMensaje;

