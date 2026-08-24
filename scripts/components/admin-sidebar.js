class DentalSidebar extends HTMLElement {
  connectedCallback() {
    // 1. Detectamos en qué página estamos actualmente
    const pathArray = window.location.pathname.split("/");
    const currentPage = pathArray.pop() || "tablero.html"; // Ej: "tablero.html"
    const currentFolder = pathArray.pop();                // Ej: "admin" o "cliente"

    this.innerHTML = `
      <aside class="sidebar">
        <div class="d-flex align-items-center gap-2 px-1 mb-4">
          <div class="brand-icon"><i class="bi bi-shield-fill-check"></i></div>
          <div>
            <div class="brand-name">DentalCare</div>
            <div class="brand-sub">Sistema Odontológico</div>
          </div>
        </div>

        <nav class="flex-grow-1">
          <a href="tablero.html" class="nav-link-custom ${currentPage === 'tablero.html' ? 'active' : ''}">
            <i class="bi bi-grid-1x2-fill"></i> Tablero
          </a>
          <a href="pacientes.html" class="nav-link-custom ${currentPage === 'pacientes.html' ? 'active' : ''}">
            <i class="bi bi-people"></i> Pacientes
          </a>
          <a href="citas.html" class="nav-link-custom ${currentPage === 'citas.html' ? 'active' : ''}">
            <i class="bi bi-calendar-check"></i> Citas
          </a>
          <a href="tratamientos.html" class="nav-link-custom ${currentPage === 'tratamientos.html' ? 'active' : ''}">
            <i class="bi bi-clipboard2-pulse"></i> Tratamientos
          </a>
          <a href="odontograma.html" class="nav-link-custom ${currentPage === 'odontograma.html' ? 'active' : ''}">
            <i class="bi bi-emoji-smile"></i> Odontograma
          </a>
          <a href="consentimientos.html" class="nav-link-custom ${currentPage === 'consentimientos.html' ? 'active' : ''}">
            <i class="bi bi-file-earmark-text"></i> Consentimientos
          </a>
          <a href="recordatorios.html" class="nav-link-custom ${currentPage === 'recordatorios.html' ? 'active' : ''}">
            <i class="bi bi-bell"></i> Recordatorios
          </a>
          <a href="finanzas.html" class="nav-link-custom ${currentPage === 'finanzas.html' ? 'active' : ''}">
            <i class="bi bi-cash-coin"></i> Finanzas
          </a>
          <a href="reportes.html" class="nav-link-custom ${currentPage === 'reportes.html' ? 'active' : ''}">
            <i class="bi bi-bar-chart"></i> Reportes
          </a>
          <a href="inventario.html" class="nav-link-custom ${currentPage === 'inventario.html' ? 'active' : ''}">
            <i class="bi bi-box-seam"></i> Inventario
          </a>
          <a href="recetas.html" class="nav-link-custom ${currentPage === 'recetas.html' ? 'active' : ''}">
            <i class="bi bi-journal-medical"></i> Recetas
          </a>
          <a href="configuracion.html" class="nav-link-custom ${currentPage === 'configuracion.html' ? 'active' : ''}">
            <i class="bi bi-gear"></i> Configuración
          </a>
        </nav>

        <!-- [BACKEND] Este link SÍ destruye la sesión en el servidor
             (a diferencia de solo navegar a la pantalla de login) -->
        <a href="../auth/CerrarSesion.php" class="nav-link-custom nav-logout">
          <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
        </a>
      </aside>
    `;
  }
}

// Registramos el componente para usarlo como <dental-sidebar></dental-sidebar>
customElements.define('dental-sidebar', DentalSidebar);