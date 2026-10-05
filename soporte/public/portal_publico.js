const enlace = document.querySelector('[data-enlace]');
const boton = document.querySelector('[data-copiar]');
if (enlace && boton) {
    enlace.value = new URL(enlace.value, window.location.origin).href;
    boton.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(enlace.value); boton.textContent = 'Enlace copiado'; }
        catch { enlace.focus(); enlace.select(); boton.textContent = 'Seleccione y copie el enlace'; }
    });
}
