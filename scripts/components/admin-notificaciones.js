/**
 * <dental-notificaciones></dental-notificaciones>
 *
 * Web Component (mismo patrón que <dental-sidebar>): toda la campana
 * de notificaciones -- markup, estilos y comportamiento -- vive en esta
 * sola clase. Cada página admin solo necesita poner la etiqueta y el
 * <script> de este archivo, nada de HTML/CSS repetido por página.
 *
 * Al vivir todo dentro de la clase (métodos privados con _), es
 * IMPOSIBLE que choque de nombres con otro admin-*.js -- que fue
 * justo el bug que tuvimos antes con una función global duplicada.
 */
class DentalNotificaciones extends HTMLElement {
  static _estiloInyectado = false;
  static INTERVALO_REFRESCO_MS = 60000;

  connectedCallback() {
    this._inyectarEstiloUnaVez();
    this.innerHTML = `
      <div class="notif-bell-wrap">
        <button class="icon-btn" id="notifBtn"><i class="bi bi-bell"></i><span class="notif-badge d-none" id="notifBadge">0</span></button>
        <div class="notif-dropdown d-none" id="notifDropdown">
          <div class="notif-dropdown-header"><span>Notificaciones</span><a href="#" id="notifMarcarTodas">Marcar todas leídas</a></div>
          <div id="notifLista"><div class="text-muted small p-3">Cargando...</div></div>
        </div>
      </div>
    `;

    this._btn = this.querySelector('#notifBtn');
    this._dropdown = this.querySelector('#notifDropdown');
    this._badge = this.querySelector('#notifBadge');
    this._lista = this.querySelector('#notifLista');

    this._activarEventos();
    this._actualizarContador();
    this._intervalo = setInterval(() => this._actualizarContador(), DentalNotificaciones.INTERVALO_REFRESCO_MS);
  }

  disconnectedCallback() {
    clearInterval(this._intervalo);
  }

  // ---------- estilos (se inyectan una sola vez en <head>, sin importar cuántas páginas los usen) ----------

  _inyectarEstiloUnaVez() {
    if (DentalNotificaciones._estiloInyectado) return;
    DentalNotificaciones._estiloInyectado = true;

    const style = document.createElement('style');
    style.textContent = `
      .notif-bell-wrap{ position:relative; }
      .notif-badge{
        position:absolute; top:-2px; right:-2px; background:#dc2626; color:#fff;
        font-size:0.62rem; font-weight:700; min-width:16px; height:16px; border-radius:8px;
        display:flex; align-items:center; justify-content:center; padding:0 3px;
      }
      .notif-dropdown{
        position:absolute; top:calc(100% + 8px); right:0; width:340px; max-height:420px;
        background:#fff; border:1px solid #e2e8f0; border-radius:12px;
        box-shadow:0 10px 30px rgba(0,0,0,0.12); z-index:1050; overflow:hidden; display:flex; flex-direction:column;
      }
      .notif-dropdown-header{
        display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1rem;
        border-bottom:1px solid #e2e8f0; font-weight:700; font-size:0.85rem;
      }
      .notif-dropdown-header a{ font-size:0.75rem; font-weight:500; color:#0f766e; text-decoration:none; }
      #notifLista{ overflow-y:auto; max-height:360px; }
      .notif-item{ display:flex; gap:0.6rem; padding:0.7rem 1rem; border-bottom:1px solid #f1f5f9; cursor:pointer; }
      .notif-item:hover{ background:#f8fafc; }
      .notif-item.no-leida{ background:#f0fdfa; }
      .notif-item .notif-dot{ width:8px; height:8px; border-radius:50%; background:#0d9488; margin-top:6px; flex-shrink:0; }
      .notif-item .notif-dot.leida{ background:transparent; }
      .notif-item .notif-titulo{ font-weight:600; font-size:0.82rem; }
      .notif-item .notif-mensaje{ font-size:0.78rem; color:#64748b; }
      .notif-item .notif-fecha{ font-size:0.7rem; color:#94a3b8; margin-top:2px; }
    `;
    document.head.appendChild(style);
  }

  // ---------- eventos ----------

  _activarEventos() {
    this._btn.addEventListener('click', (e) => {
      e.stopPropagation();
      this._dropdown.classList.contains('d-none') ? this._abrir() : this._cerrar();
    });

    document.addEventListener('click', (e) => {
      if (!this.contains(e.target)) this._cerrar();
    });

    this.querySelector('#notifMarcarTodas').addEventListener('click', async (e) => {
      e.preventDefault();
      try {
        await fetch('/api/admin/notificaciones/marcar-todas-leidas.php', { method: 'POST' });
        this._cargarLista();
      } catch (err) {
        console.error('Error al marcar todas como leídas:', err);
      }
    });
  }

  _abrir() {
    this._dropdown.classList.remove('d-none');
    this._cargarLista();
  }

  _cerrar() {
    this._dropdown.classList.add('d-none');
  }

  // ---------- datos ----------

  _pintarBadge(noLeidas) {
    if (noLeidas > 0) {
      this._badge.textContent = noLeidas > 9 ? '9+' : noLeidas;
      this._badge.classList.remove('d-none');
    } else {
      this._badge.classList.add('d-none');
    }
  }

  // Solo el contador, para el refresco automático de fondo -- no
  // recarga la lista completa cada minuto en cada pestaña abierta.
  async _actualizarContador() {
    try {
      const res = await fetch('/api/admin/notificaciones/listar.php');
      const data = await res.json();
      if (data.ok) this._pintarBadge(data.noLeidas);
    } catch (err) {
      console.error('Error al actualizar notificaciones:', err);
    }
  }

  _tiempoRelativo(fechaStr) {
    const fecha = new Date(fechaStr.replace(' ', 'T'));
    const diffMin = Math.round((Date.now() - fecha.getTime()) / 60000);
    if (diffMin < 1) return 'Justo ahora';
    if (diffMin < 60) return `Hace ${diffMin} min`;
    const diffH = Math.round(diffMin / 60);
    if (diffH < 24) return `Hace ${diffH} h`;
    return `Hace ${Math.round(diffH / 24)} d`;
  }

  async _cargarLista() {
    try {
      const res = await fetch('/api/admin/notificaciones/listar.php');
      const data = await res.json();
      if (!data.ok) return;

      this._pintarBadge(data.noLeidas);

      this._lista.innerHTML = data.recientes.map(n => `
        <div class="notif-item ${Number(n.leida) ? '' : 'no-leida'}" data-id="${n.id}">
          <div class="notif-dot ${Number(n.leida) ? 'leida' : ''}"></div>
          <div class="flex-grow-1">
            <div class="notif-titulo">${n.titulo}</div>
            ${n.mensaje ? `<div class="notif-mensaje">${n.mensaje}</div>` : ''}
            <div class="notif-fecha">${this._tiempoRelativo(n.fecha_creacion)}</div>
          </div>
        </div>
      `).join('') || '<div class="text-muted small p-3">No tienes notificaciones todavía.</div>';

      this._lista.querySelectorAll('.notif-item').forEach(item => {
        item.addEventListener('click', async () => {
          if (!item.classList.contains('no-leida')) return;
          item.classList.remove('no-leida');
          item.querySelector('.notif-dot').classList.add('leida');
          try {
            await fetch('/api/admin/notificaciones/marcar-leida.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: item.dataset.id }),
            });
            this._actualizarContador();
          } catch (err) {
            console.error('Error al marcar como leída:', err);
          }
        });
      });
    } catch (err) {
      console.error('Error al cargar notificaciones:', err);
      this._lista.innerHTML = '<div class="text-danger small p-3">No se pudieron cargar las notificaciones.</div>';
    }
  }
}

customElements.define('dental-notificaciones', DentalNotificaciones);