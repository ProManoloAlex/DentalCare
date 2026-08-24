// ============================================================
// MÓDULO INVENTARIO — conectado al backend real.
// ============================================================

function money(n) {
  return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const NOMBRE_CATEGORIA = { Restauracion: 'Restauración', Anestesia: 'Anestesia', Ortodoncia: 'Ortodoncia', Descartables: 'Descartables', Esterilizacion: 'Esterilización', Instrumentos: 'Instrumentos' };
const COLOR_CATEGORIA = { Restauracion: '#0d9488', Anestesia: '#f472b6', Ortodoncia: '#f59e0b', Descartables: '#2563eb', Esterilizacion: '#8b5cf6', Instrumentos: '#fb923c' };

document.addEventListener('DOMContentLoaded', function () {
  activarTabs();
  activarFiltros();
  activarModalProducto();
  activarFormularioMovimiento();
  activarModalOrden();
  cargarProductosParaSelectores();

  cargarResumen();
});

// ---------- TABS ----------

const cargadoPorTab = { tabResumenInv: true, tabProductos: false, tabMovimientos: false, tabOrdenes: false };

function activarTabs() {
  const tabs = { tabResumenInv: 'vistaResumenInv', tabProductos: 'vistaProductos', tabMovimientos: 'vistaMovimientos', tabOrdenes: 'vistaOrdenes' };
  const accionesTop = {
    tabResumenInv: '',
    tabProductos: '<button class="btn btn-teal" id="btnNuevoProducto"><i class="bi bi-plus-lg"></i> Nuevo Producto</button>',
    tabMovimientos: '',
    tabOrdenes: '<button class="btn btn-teal" data-bs-toggle="modal" data-bs-target="#modalOrden"><i class="bi bi-plus-lg"></i> Nueva Orden</button>',
  };

  Object.keys(tabs).forEach((tabId) => {
    document.getElementById(tabId).addEventListener('click', function () {
      Object.entries(tabs).forEach(([btnId, viewId]) => {
        document.getElementById(viewId).classList.toggle('d-none', btnId !== tabId);
        document.getElementById(btnId).classList.toggle('btn-teal', btnId === tabId);
        document.getElementById(btnId).classList.toggle('btn-outline-soft', btnId !== tabId);
      });
      document.getElementById('accionesTopInv').innerHTML = accionesTop[tabId];
      if (tabId === 'tabProductos') document.getElementById('btnNuevoProducto').addEventListener('click', () => abrirModalProducto(null));

      if (!cargadoPorTab[tabId]) {
        cargadoPorTab[tabId] = true;
        ({ tabProductos: cargarProductos, tabMovimientos: cargarMovimientos, tabOrdenes: cargarOrdenes })[tabId]?.();
      }
    });
  });
}

function activarFiltros() {
  ['filtroProdBusqueda', 'filtroProdCategoria', 'filtroProdStock'].forEach((id) => document.getElementById(id).addEventListener('input', cargarProductos));
  ['filtroMovBusqueda', 'filtroMovTipo'].forEach((id) => document.getElementById(id).addEventListener('input', cargarMovimientos));
  document.getElementById('filtroOcEstado').addEventListener('input', cargarOrdenes);
}

// ---------- RESUMEN ----------

async function cargarResumen() {
  try {
    const res = await fetch('/api/admin/inventario/resumen.php');
    const data = await res.json();
    if (!data.ok) return;

    const r = data.resumen;
    document.getElementById('kpiTotalProductos').textContent = r.kpis.totalProductos;
    document.getElementById('kpiStockCritico').textContent = r.kpis.stockCritico;
    document.getElementById('kpiValorInventario').textContent = money(r.kpis.valorInventario);
    document.getElementById('kpiPorVencer').textContent = r.kpis.porVencer60;

    document.getElementById('alertasCount').textContent = r.alertasStock.length + ' alertas';
    document.getElementById('alertasStockContainer').innerHTML = r.alertasStock.map((p) => `
      <div class="alert-stock-item">
        <div class="d-flex justify-content-between">
          <span class="fw-semibold small">${p.nombre}</span>
          <span class="fw-bold small text-danger">${p.stock} ${p.unidad || ''}</span>
        </div>
        <div class="mini-bar-track"><div class="mini-bar-fill" style="width:${Math.min(100, (p.stock / Math.max(p.stock_minimo, 1)) * 100)}%"></div></div>
        <div class="text-muted small mt-1">Mínimo: ${p.stock_minimo}</div>
      </div>
    `).join('') || '<div class="text-muted small">Sin alertas de stock por ahora</div>';

    document.getElementById('stockCategoriaContainer').innerHTML = r.stockPorCategoria.map((c) => `
      <div class="cat-row-inv d-flex justify-content-between align-items-center">
        <span class="small"><span class="cat-pill-inv" style="background:${COLOR_CATEGORIA[c.categoria]}22; color:${COLOR_CATEGORIA[c.categoria]}">${NOMBRE_CATEGORIA[c.categoria] || c.categoria}</span></span>
        <span class="small text-end"><span class="fw-semibold">${c.total_productos} prod.</span> <span class="text-muted">${money(c.valor)}</span></span>
      </div>
    `).join('') || '<div class="text-muted small">Sin productos registrados</div>';

    document.getElementById('ultimosMovimientosContainer').innerHTML = r.ultimosMovimientos.map((m) => renderMovItem(m)).join('') || '<div class="text-muted small">Sin movimientos todavía</div>';
  } catch (e) {
    console.error('Error al cargar resumen de inventario:', e);
  }
}

function renderMovItem(m) {
  const esEntrada = m.tipo === 'entrada';
  const color = esEntrada ? '#16a34a' : (m.tipo === 'salida' ? '#dc2626' : '#d97706');
  const icono = esEntrada ? 'bi-arrow-up' : (m.tipo === 'salida' ? 'bi-arrow-down' : 'bi-sliders');
  return `
    <div class="mov-item">
      <div class="mov-icon" style="background:${color}22; color:${color}"><i class="bi ${icono}"></i></div>
      <div class="flex-grow-1">
        <div class="fw-semibold small">${m.producto_nombre}</div>
        <div class="text-muted small">${m.fecha}</div>
      </div>
      <div class="fw-bold small" style="color:${color}">${esEntrada ? '+' : '-'}${Math.abs(m.cantidad)}</div>
    </div>`;
}

// ---------- PRODUCTOS ----------

let productosCache = [];

async function cargarProductos() {
  const buscar = document.getElementById('filtroProdBusqueda').value.trim();
  const categoria = document.getElementById('filtroProdCategoria').value;
  const stock = document.getElementById('filtroProdStock').value;

  try {
    const params = new URLSearchParams({ buscar, categoria, stock });
    const res = await fetch('/api/admin/inventario/productos/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    productosCache = data.productos;
    document.getElementById('tbodyProductos').innerHTML = productosCache.map((p) => {
      const critico = Number(p.stock) <= Number(p.stock_minimo);
      const claseStock = critico ? 'stock-critico' : 'stock-ok';
      return `
        <tr>
          <td><div class="fw-semibold">${p.nombre}</div><div class="text-muted small">${p.codigo} ${p.marca ? '· ' + p.marca : ''}</div></td>
          <td><span class="cat-pill-inv" style="background:${COLOR_CATEGORIA[p.categoria]}22; color:${COLOR_CATEGORIA[p.categoria]}">${NOMBRE_CATEGORIA[p.categoria] || p.categoria}</span></td>
          <td><span class="stock-badge ${claseStock}">${p.stock} ${p.unidad || ''}</span><div class="text-muted small">mín. ${p.stock_minimo}</div></td>
          <td>${money(p.precio)}</td>
          <td>${p.ubicacion || '—'}</td>
          <td>
            <i class="bi bi-arrow-left-right action-icon" data-action="movimiento" data-id="${p.id}" title="Registrar movimiento" style="cursor:pointer; margin-right:8px;"></i>
            <i class="bi bi-pencil action-icon" data-action="editar" data-id="${p.id}" title="Editar" style="cursor:pointer;"></i>
          </td>
        </tr>`;
    }).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">No hay productos registrados</td></tr>';
  } catch (e) {
    console.error('Error al cargar productos:', e);
  }
}

document.addEventListener('click', function (e) {
  const movBtn = e.target.closest('[data-action="movimiento"]');
  if (movBtn) { abrirModalMovimiento(Number(movBtn.getAttribute('data-id'))); return; }

  const editBtn = e.target.closest('[data-action="editar"]');
  if (editBtn) { abrirModalProducto(Number(editBtn.getAttribute('data-id'))); return; }
});

function activarModalProducto() {
  document.getElementById('formProducto').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const productoId = fd.get('productoId');

    const payload = {
      nombre: fd.get('nombre'), codigo: fd.get('codigo'), categoria: fd.get('categoria'),
      marca: fd.get('marca'), unidad: fd.get('unidad'), stock: fd.get('stock'),
      stockMinimo: fd.get('stockMinimo'), stockMaximo: fd.get('stockMaximo') || null,
      precio: fd.get('precio'), vencimiento: fd.get('vencimiento') || null,
      proveedor: fd.get('proveedor'), ubicacion: fd.get('ubicacion'),
      activo: fd.get('activo') === 'on',
    };

    const esEdicion = !!productoId;
    if (esEdicion) payload.productoId = Number(productoId);

    try {
      const res = await fetch(`/api/admin/inventario/productos/${esEdicion ? 'actualizar' : 'crear'}.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) { alert(data.mensaje || 'No se pudo guardar el producto.'); return; }

      bootstrap.Modal.getInstance(document.getElementById('modalProducto')).hide();
      cargarProductos();
      cargarProductosParaSelectores();
      if (cargadoPorTab.tabResumenInv) cargarResumen();
    } catch (err) {
      alert('Error de conexión al guardar el producto.');
    }
  });
}

function abrirModalProducto(productoId) {
  const form = document.getElementById('formProducto');
  form.reset();
  document.getElementById('inputStockActual').disabled = false;
  document.getElementById('stockActualHint').textContent = '';

  if (productoId) {
    const p = productosCache.find((x) => x.id === productoId);
    if (!p) return;

    document.getElementById('modalProductoTitulo').textContent = 'Editar Producto';
    form.elements['productoId'].value = p.id;
    form.elements['nombre'].value = p.nombre;
    form.elements['codigo'].value = p.codigo;
    form.elements['categoria'].value = p.categoria;
    form.elements['marca'].value = p.marca || '';
    form.elements['unidad'].value = p.unidad || '';
    form.elements['stock'].value = p.stock;
    form.elements['stockMinimo'].value = p.stock_minimo;
    form.elements['stockMaximo'].value = p.stock_maximo || '';
    form.elements['precio'].value = p.precio;
    form.elements['vencimiento'].value = p.vencimiento || '';
    form.elements['proveedor'].value = p.proveedor || '';
    form.elements['ubicacion'].value = p.ubicacion || '';
    form.elements['activo'].checked = !!Number(p.activo);

    // El stock actual solo se puede cambiar vía Movimientos, para que
    // siempre quede el historial de por qué cambió.
    document.getElementById('inputStockActual').disabled = true;
    document.getElementById('stockActualHint').textContent = 'Para cambiar el stock, usa el ícono de movimiento en la tabla de Productos.';
  } else {
    document.getElementById('modalProductoTitulo').textContent = 'Nuevo Producto';
    form.elements['activo'].checked = true;
  }

  new bootstrap.Modal(document.getElementById('modalProducto')).show();
}

// ---------- MOVIMIENTOS ----------

async function cargarMovimientos() {
  const buscar = document.getElementById('filtroMovBusqueda').value.trim();
  const tipo = document.getElementById('filtroMovTipo').value;

  try {
    const params = new URLSearchParams({ buscar, tipo });
    const res = await fetch('/api/admin/inventario/movimientos/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    document.getElementById('kpiEntradas').textContent = data.conteoTipos.entrada;
    document.getElementById('kpiSalidas').textContent = data.conteoTipos.salida;
    document.getElementById('kpiAjustes').textContent = data.conteoTipos.ajuste;

    document.getElementById('movimientosContainer').innerHTML = data.movimientos.map((m) => {
      const esEntrada = m.tipo === 'entrada';
      const color = esEntrada ? '#16a34a' : (m.tipo === 'salida' ? '#dc2626' : '#d97706');
      const icono = esEntrada ? 'bi-arrow-up' : (m.tipo === 'salida' ? 'bi-arrow-down' : 'bi-sliders');
      return `
        <div class="mov-item">
          <div class="mov-icon" style="background:${color}22; color:${color}"><i class="bi ${icono}"></i></div>
          <div class="flex-grow-1">
            <div class="fw-semibold small">${m.producto_nombre}</div>
            <div class="text-muted small">${m.motivo || '—'}</div>
            <div class="text-muted small">${m.fecha} · ${m.registrado_por}</div>
          </div>
          <div class="text-end">
            <div class="fw-bold small" style="color:${color}">${esEntrada ? '+' : '-'}${Math.abs(m.cantidad)}</div>
            <div class="text-muted small">${m.stock_antes} → ${m.stock_despues}</div>
          </div>
        </div>`;
    }).join('') || '<div class="text-muted small text-center py-3">No hay movimientos registrados</div>';
  } catch (e) {
    console.error('Error al cargar movimientos:', e);
  }
}

let tipoMovSeleccionado = 'Entrada';

function abrirModalMovimiento(productoId) {
  const p = productosCache.find((x) => x.id === productoId);
  const form = document.getElementById('formMovimiento');
  form.reset();
  tipoMovSeleccionado = 'Entrada';
  actualizarTipoMovBtns();

  const selectProducto = document.getElementById('movSelectProducto');

  if (p) {
    form.elements['productoId'].value = p.id;
    form.dataset.stockActual = p.stock;
    document.getElementById('movProductoNombre').textContent = p.nombre;
    document.getElementById('movProductoStock').textContent = `Stock actual: ${p.stock} ${p.unidad || ''}`;
    selectProducto.classList.add('d-none');
  } else {
    form.elements['productoId'].value = '';
    form.dataset.stockActual = '';
    document.getElementById('movProductoNombre').textContent = 'Elige un producto abajo';
    document.getElementById('movProductoStock').textContent = '';

    selectProducto.innerHTML = '<option value="">Seleccionar producto...</option>' +
      productosParaSelector.map((prod) => `<option value="${prod.id}" data-stock="${prod.stock}" data-unidad="${prod.unidad || ''}">${prod.nombre}</option>`).join('');
    selectProducto.value = '';
    selectProducto.classList.remove('d-none');
  }
  new bootstrap.Modal(document.getElementById('modalMovimiento')).show();
}

// Cuando el modal se abrió sin producto preseleccionado, elegirlo del select
// debe llenar productoId + stock actual, igual que si se hubiera abierto
// desde el ícono de una fila específica en la tabla de Productos.
document.getElementById('movSelectProducto').addEventListener('change', function () {
  const opcion = this.options[this.selectedIndex];
  const form = document.getElementById('formMovimiento');

  if (!this.value) {
    form.elements['productoId'].value = '';
    form.dataset.stockActual = '';
    document.getElementById('movProductoStock').textContent = '';
    return;
  }

  form.elements['productoId'].value = this.value;
  form.dataset.stockActual = opcion.getAttribute('data-stock');
  document.getElementById('movProductoStock').textContent = `Stock actual: ${opcion.getAttribute('data-stock')} ${opcion.getAttribute('data-unidad') || ''}`;
});

function activarFormularioMovimiento() {
  document.getElementById('btnRegistrarMovGeneral').addEventListener('click', () => abrirModalMovimiento(null));

  document.querySelectorAll('.tipo-mov-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
      tipoMovSeleccionado = this.getAttribute('data-tipo');
      document.querySelector('#formMovimiento [name="tipoMovimiento"]').value = tipoMovSeleccionado;
      actualizarTipoMovBtns();
    });
  });

  document.getElementById('formMovimiento').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const productoId = Number(fd.get('productoId'));

    if (!productoId) {
      alert('Selecciona un producto desde la pestaña Productos para registrar su movimiento.');
      return;
    }

    let cantidad = Number(fd.get('cantidad'));
    const tipo = tipoMovSeleccionado.toLowerCase();

    if (tipo === 'ajuste') {
      const stockActual = Number(this.dataset.stockActual || 0);
      cantidad = cantidad - stockActual; // delta firmado: puede ser negativo
      if (cantidad === 0) {
        alert('El stock contado es igual al actual — no hay ningún ajuste que registrar.');
        return;
      }
    }

    const payload = {
      productoId,
      tipo,
      cantidad,
      motivo: fd.get('motivo'),
      fecha: new Date().toISOString().slice(0, 10),
    };

    try {
      const res = await fetch('/api/admin/inventario/movimientos/crear.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) { alert(data.mensaje || 'No se pudo registrar el movimiento.'); return; }

      bootstrap.Modal.getInstance(document.getElementById('modalMovimiento')).hide();
      cargarMovimientos();
      cargarProductos();
      cargarProductosParaSelectores();
      if (cargadoPorTab.tabResumenInv) cargarResumen();
    } catch (err) {
      alert('Error de conexión al registrar el movimiento.');
    }
  });
}

function actualizarTipoMovBtns() {
  document.querySelectorAll('.tipo-mov-btn').forEach((b) => b.classList.toggle('selected', b.getAttribute('data-tipo') === tipoMovSeleccionado));

  const label = document.getElementById('labelCampoCantidad');
  const hint = document.getElementById('hintCampoCantidad');
  const input = document.getElementById('inputCantidadMov');

  if (tipoMovSeleccionado === 'Ajuste') {
    label.textContent = 'Stock real contado *';
    hint.textContent = 'Escribe el stock que contaste físicamente; el sistema calcula solo la diferencia.';
    input.min = 0;
  } else {
    label.textContent = 'Cantidad *';
    hint.textContent = '';
    input.min = 1;
  }
}

// ---------- ÓRDENES DE COMPRA ----------

async function cargarOrdenes() {
  const estado = document.getElementById('filtroOcEstado').value;

  try {
    const params = new URLSearchParams({ estado });
    const res = await fetch('/api/admin/inventario/ordenes/listar.php?' + params);
    const data = await res.json();
    if (!data.ok) return;

    document.getElementById('kpiOcPendiente').textContent = data.resumen.porEstado.pendiente;
    document.getElementById('kpiOcAprobada').textContent = data.resumen.porEstado.aprobada;
    document.getElementById('kpiOcRecibida').textContent = data.resumen.porEstado.recibida;
    document.getElementById('kpiOcCancelada').textContent = data.resumen.porEstado.cancelada;

    const alerta = document.getElementById('alertaOcPorRecibir');
    if (data.resumen.proximaAEntregar) {
      const p = data.resumen.proximaAEntregar;
      alerta.classList.remove('d-none');
      alerta.innerHTML = `<i class="bi bi-info-circle-fill" style="color:#d97706"></i> <span class="small">Próxima entrega: <strong>${p.folio}</strong> de ${p.proveedor} — ${p.fecha_entrega_estimada}</span>`;
    } else {
      alerta.classList.add('d-none');
    }

    document.getElementById('ordenesContainer').innerHTML = data.ordenes.map((o) => renderOrdenItem(o)).join('') || '<div class="text-muted small text-center py-3">No hay órdenes de compra</div>';
  } catch (e) {
    console.error('Error al cargar órdenes:', e);
  }
}

const ESTADO_OC_CLASE = { pendiente: 'status-pendiente-oc', aprobada: 'status-aprobada-oc', recibida: 'status-recibida-oc', cancelada: 'status-cancelada-oc' };
const ESTADO_OC_TEXTO = { pendiente: 'Pendiente', aprobada: 'Aprobada', recibida: 'Recibida', cancelada: 'Cancelada' };
const ESTADO_OC_ICONO = { pendiente: 'bi-clock', aprobada: 'bi-check-circle', recibida: 'bi-box-seam', cancelada: 'bi-x-circle' };

function renderOrdenItem(o) {
  const acciones = {
    pendiente: `<i class="bi bi-check-circle action-icon" data-action="aprobar" data-id="${o.id}" title="Aprobar" style="color:#16a34a; cursor:pointer; margin-right:8px;"></i><i class="bi bi-x-circle action-icon" data-action="cancelar" data-id="${o.id}" title="Cancelar" style="color:#dc2626; cursor:pointer;"></i>`,
    aprobada: `<i class="bi bi-box-seam action-icon" data-action="recibir" data-id="${o.id}" title="Marcar como recibida" style="color:#16a34a; cursor:pointer; margin-right:8px;"></i><i class="bi bi-x-circle action-icon" data-action="cancelar" data-id="${o.id}" title="Cancelar" style="color:#dc2626; cursor:pointer;"></i>`,
    recibida: '',
    cancelada: '',
  };
  return `
    <div class="oc-item">
      <div class="oc-icon ${ESTADO_OC_CLASE[o.estado]}"><i class="bi ${ESTADO_OC_ICONO[o.estado]}"></i></div>
      <div class="flex-grow-1">
        <div class="fw-semibold small">${o.folio} — ${o.proveedor}</div>
        <div class="text-muted small">${o.productos_count} producto(s) · Creada ${o.fecha_creacion.slice(0, 10)}${o.fecha_entrega_estimada ? ' · Entrega ' + o.fecha_entrega_estimada : ''}</div>
      </div>
      <div class="text-end me-2">
        <div class="fw-bold small">${money(o.total)}</div>
        <span class="cat-pill-inv ${ESTADO_OC_CLASE[o.estado]}">${ESTADO_OC_TEXTO[o.estado]}</span>
      </div>
      <div>${acciones[o.estado]}</div>
    </div>`;
}

let accionOrdenPendiente = null;

document.addEventListener('click', function (e) {
  const btn = e.target.closest('[data-action="aprobar"], [data-action="recibir"], [data-action="cancelar"]');
  if (!btn) return;

  const accion = btn.getAttribute('data-action');
  const ordenId = Number(btn.getAttribute('data-id'));

  if (accion === 'recibir' || accion === 'cancelar') {
    accionOrdenPendiente = { accion, ordenId };
    document.getElementById('confirmarAccionTitulo').textContent = accion === 'recibir' ? 'Marcar como recibida' : 'Cancelar orden de compra';
    document.getElementById('confirmarAccionMensaje').textContent = accion === 'recibir'
      ? 'Al marcar esta orden como recibida se sumará el stock de cada producto automáticamente. ¿Continuar?'
      : '¿Cancelar esta orden de compra?';
    document.getElementById('btnConfirmarAccionOrden').className = accion === 'cancelar' ? 'btn btn-danger' : 'btn btn-teal';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmarAccionOrden')).show();
    return;
  }

  // "aprobar" nunca pidió confirmación, sigue siendo directo.
  ejecutarCambioEstadoOrden(accion, ordenId);
});

document.getElementById('btnConfirmarAccionOrden').addEventListener('click', function () {
  if (!accionOrdenPendiente) return;
  bootstrap.Modal.getInstance(document.getElementById('modalConfirmarAccionOrden'))?.hide();
  ejecutarCambioEstadoOrden(accionOrdenPendiente.accion, accionOrdenPendiente.ordenId);
  accionOrdenPendiente = null;
});

async function ejecutarCambioEstadoOrden(accion, ordenId) {
  const estadoNuevo = { aprobar: 'aprobada', recibir: 'recibida', cancelar: 'cancelada' }[accion];

  try {
    const res = await fetch('/api/admin/inventario/ordenes/cambiar-estado.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ordenId, estado: estadoNuevo }),
    });
    const data = await res.json();

    if (!data.ok) { alert(data.mensaje || 'No se pudo actualizar la orden.'); return; }

    cargarOrdenes();
    if (accion === 'recibir') { cargarProductos(); cargarProductosParaSelectores(); if (cargadoPorTab.tabResumenInv) cargarResumen(); }
  } catch (err) {
    alert('Error de conexión al actualizar la orden.');
  }
}

// Red de seguridad: si queda un modal-backdrop huérfano (bug conocido de
// Bootstrap 5 al abrir/cerrar modales repetidamente), lo limpiamos apenas
// se confirme que ya no hay ningún modal abierto.
document.addEventListener('hidden.bs.modal', function () {
  if (document.querySelectorAll('.modal.show').length === 0) {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  }
});

// ---------- MODAL: NUEVA ORDEN ----------

async function cargarProductosParaSelectores() {
  try {
    const res = await fetch('/api/admin/inventario/productos/catalogo.php');
    const data = await res.json();
    if (data.ok) productosParaSelector = data.productos;
  } catch (e) {
    console.error('Error al cargar catálogo de productos:', e);
  }
}

let productosParaSelector = [];

function crearLineaOrden() {
  const div = document.createElement('div');
  div.className = 'oc-linea-row';
  div.innerHTML = `
    <select class="form-select form-select-sm linea-producto">
      <option value="">Seleccionar producto</option>
      ${productosParaSelector.map((p) => `<option value="${p.id}" data-precio="${p.precio}">${p.nombre}</option>`).join('')}
    </select>
    <input type="number" class="form-control form-control-sm linea-cantidad" placeholder="Cant." min="1" value="1">
    <input type="number" class="form-control form-control-sm linea-precio" placeholder="Precio" min="0" step="0.01">
    <i class="bi bi-trash action-icon" style="color:#dc2626; cursor:pointer;" data-action="quitar-linea"></i>
  `;
  document.getElementById('lineasOrdenContainer').appendChild(div);
}

function calcularTotalOrden() {
  let total = 0;
  document.querySelectorAll('.oc-linea-row').forEach((row) => {
    const cant = parseFloat(row.querySelector('.linea-cantidad').value) || 0;
    const precio = parseFloat(row.querySelector('.linea-precio').value) || 0;
    total += cant * precio;
  });
  document.getElementById('totalOrdenTexto').textContent = 'Total: ' + money(total);
}

function activarModalOrden() {
  document.getElementById('btnAgregarLineaOrden').addEventListener('click', crearLineaOrden);

  document.getElementById('lineasOrdenContainer').addEventListener('click', function (e) {
    if (e.target.closest('[data-action="quitar-linea"]')) {
      e.target.closest('.oc-linea-row').remove();
      calcularTotalOrden();
    }
  });
  document.getElementById('lineasOrdenContainer').addEventListener('change', function (e) {
    if (e.target.classList.contains('linea-producto')) {
      const row = e.target.closest('.oc-linea-row');
      const precio = e.target.options[e.target.selectedIndex].getAttribute('data-precio');
      if (precio) row.querySelector('.linea-precio').value = precio;
    }
    calcularTotalOrden();
  });
  document.getElementById('lineasOrdenContainer').addEventListener('input', calcularTotalOrden);

  document.getElementById('modalOrden').addEventListener('show.bs.modal', function () {
    document.getElementById('formOrden').reset();
    document.getElementById('lineasOrdenContainer').innerHTML = '';
    crearLineaOrden();
    calcularTotalOrden();
  });

  document.getElementById('formOrden').addEventListener('submit', async function (e) {
    e.preventDefault();
    const fd = new FormData(this);

    const lineas = [];
    document.querySelectorAll('.oc-linea-row').forEach((row) => {
      const productoId = row.querySelector('.linea-producto').value;
      const cantidad = parseFloat(row.querySelector('.linea-cantidad').value) || 0;
      const precioUnitario = parseFloat(row.querySelector('.linea-precio').value) || 0;
      if (productoId) lineas.push({ productoId: Number(productoId), cantidad, precioUnitario });
    });

    const payload = { proveedor: fd.get('proveedor'), fechaEntrega: fd.get('fechaEntrega'), notas: fd.get('notas'), lineas };

    try {
      const res = await fetch('/api/admin/inventario/ordenes/crear.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await res.json();

      if (!data.ok) { alert(data.mensaje || 'No se pudo crear la orden.'); return; }

      bootstrap.Modal.getInstance(document.getElementById('modalOrden')).hide();
      cargarOrdenes();
    } catch (err) {
      alert('Error de conexión al crear la orden.');
    }
  });
}