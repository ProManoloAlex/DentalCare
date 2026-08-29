/**
 * admin-pacientes.js
 * Responsable de la tabla de "Gestión de Pacientes": listado real,
 * filtros (búsqueda/estado/orden), KPIs, y registrar un paciente nuevo.
 *
 * [BACKEND] Endpoints que consume:
 *   GET  /api/admin/pacientes/listar.php?buscar=&estado=&orden=
 *   POST /api/admin/pacientes/crear.php
 *
 * Nota: "Ver detalle", "Editar" y "Eliminar" (los 3 íconos de acción
 * en cada fila) todavía NO están conectados — se agregan en el
 * siguiente paso. Por ahora muestran un aviso.
 */

let pacienteActualId = null; // paciente actualmente abierto en el modal de detalle -- lo usan las pestañas de carga diferida (Historial, Facturación, Odontograma)

document.addEventListener('DOMContentLoaded', () => {
  cargarPacientes();
  activarFiltros();
  activarFormularioNuevoPaciente();
  activarAccionesTabla();
  activarToggleAccesoPortal();
  activarFormularioOtorgarAcceso();

  document.querySelector('[data-bs-target="#tabFacturacion"]').addEventListener('shown.bs.tab', () => {
    if (pacienteActualId) cargarFacturacion(pacienteActualId);
  });
  document.querySelector('[data-bs-target="#tabHistorial"]').addEventListener('shown.bs.tab', () => {
    if (pacienteActualId) cargarHistorialMedico(pacienteActualId);
  });
  document.querySelector('[data-bs-target="#tabOdontograma"]').addEventListener('shown.bs.tab', () => {
    if (pacienteActualId) cargarOdontogramaPaciente(pacienteActualId);
  });
});

function activarToggleAccesoPortal() {
  document.getElementById('darAccesoPortal').addEventListener('change', function () {
    document.getElementById('camposContrasenaPortal').classList.toggle('d-none', !this.checked);
  });
}

function getIniciales(nombre) {
  return nombre.trim().split(/\s+/).slice(0, 2).map(p => p[0].toUpperCase()).join('');
}

/**
 * Reemplaza confirm() nativo (que el navegador prefija con "localhost dice:").
 * Devuelve una Promise<boolean>: true si el usuario confirmó, false si canceló/cerró el modal.
 */
function confirmarAccion(mensaje, { titulo = 'Confirmar acción', textoBoton = 'Confirmar', colorBoton = 'btn-danger' } = {}) {
  return new Promise((resolve) => {
    const modalEl = document.getElementById('modalConfirmarAccion');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.getElementById('confirmarAccionTitulo').textContent = titulo;
    document.getElementById('confirmarAccionMensaje').textContent = mensaje;

    const botonViejo = document.getElementById('confirmarAccionBoton');
    botonViejo.textContent = textoBoton;
    botonViejo.className = `btn ${colorBoton}`;

    // Clona el botón para quitar listeners de usos anteriores del modal
    const boton = botonViejo.cloneNode(true);
    botonViejo.parentNode.replaceChild(boton, botonViejo);

    let confirmado = false;
    boton.addEventListener('click', () => {
      confirmado = true;
      modal.hide();
    });

    modalEl.addEventListener('hidden.bs.modal', () => resolve(confirmado), { once: true });

    modal.show();
  });
}

function activarFiltros() {
  let debounceTimer;
  document.getElementById('inputBuscar').addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(cargarPacientes, 350);
  });

  document.getElementById('selectEstado').addEventListener('change', cargarPacientes);
  document.getElementById('selectOrden').addEventListener('change', cargarPacientes);
}

function cargarPacientes() {
  const buscar = document.getElementById('inputBuscar').value.trim();
  const estado = document.getElementById('selectEstado').value;
  const orden = document.getElementById('selectOrden').value;

  const params = new URLSearchParams();
  if (buscar) params.set('buscar', buscar);
  if (estado) params.set('estado', estado);
  params.set('orden', orden);

  fetch(`/api/admin/pacientes/listar.php?${params.toString()}`)
    .then(res => {
      if (!res.ok) throw new Error(`Status ${res.status}`);
      return res.json();
    })
    .then(data => {
      renderResumen(data.resumen);
      renderTabla(data.pacientes);
    })
    .catch(err => {
      console.error('Error al cargar pacientes:', err);
      const tbody = document.getElementById('tbodyPacientes');
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger small text-center py-3">No se pudo cargar la lista (${err.message}).</td></tr>`;
    });
}

function renderResumen(resumen) {
  document.getElementById('kpiTotal').textContent = resumen.total;
  document.getElementById('kpiActivos').textContent = resumen.activos;
  document.getElementById('kpiConCitas').textContent = resumen.con_citas_proximas;
  document.getElementById('kpiConSaldo').textContent = resumen.con_saldo;
}

function renderTabla(pacientes) {
  const tbody = document.getElementById('tbodyPacientes');
  const emptyState = document.getElementById('emptyStatePacientes');

  if (pacientes.length === 0) {
    tbody.innerHTML = '';
    emptyState.style.display = 'block';
    return;
  }

  emptyState.style.display = 'none';
  tbody.innerHTML = pacientes.map(p => `
    <tr>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar-initial-sq">${getIniciales(p.nombre)}</div>
          <div>
            <div class="fw-semibold">${p.nombre}</div>
            <div class="text-muted small">${p.folio}</div>
          </div>
        </div>
      </td>
      <td>
        <div>${p.correo}</div>
        <div class="text-muted small">${p.telefono ?? '—'}</div>
      </td>
      <td>${p.edad}</td>
      <td class="text-muted">${p.ultima_visita}</td>
      <td class="text-muted">${p.proxima_cita}</td>
      <td>${p.visitas}</td>
      <td class="${p.tiene_saldo ? '' : 'text-muted'}" style="${p.tiene_saldo ? 'color:var(--orange-600, #e0930f); font-weight:600;' : ''}">
        $${p.saldo}
      </td>
      <td><span class="status-badge ${p.activo ? 'status-activo' : 'status-inactivo'}">${p.activo ? 'Activo' : 'Inactivo'}</span></td>
      <td>
        <i class="bi bi-eye action-icon" title="Ver detalle" data-id="${p.id}" data-action="ver"></i>
        <i class="bi bi-pencil action-icon" title="Editar" data-id="${p.id}" data-action="editar"></i>
        <i class="bi ${p.activo ? 'bi-trash' : 'bi-arrow-counterclockwise'} action-icon" 
           title="${p.activo ? 'Desactivar' : 'Reactivar'}" 
           data-id="${p.id}" data-activo="${p.activo}" data-action="eliminar" 
           style="${p.activo ? 'color:#dc2626' : 'color:#16a34a'}"></i>
      </td>
    </tr>
  `).join('');
}

function activarFormularioNuevoPaciente() {
  document.getElementById('formNuevoPaciente').addEventListener('submit', function (e) {
    e.preventDefault();

    const darAcceso = document.getElementById('darAccesoPortal').checked;
    const fd = new FormData(this);
    const datos = Object.fromEntries(fd.entries());
    datos.darAccesoPortal = darAcceso;

    if (darAcceso) {
      const confirmacion = document.getElementById('confirmarContrasenaPortal').value;
      if (!datos.contrasenaPortal || datos.contrasenaPortal.length < 6) {
        alert('La contraseña del portal debe tener al menos 6 caracteres.');
        return;
      }
      if (datos.contrasenaPortal !== confirmacion) {
        alert('Las contraseñas no coinciden.');
        return;
      }
    }

    fetch('/api/admin/pacientes/crear.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          this.reset();
          document.getElementById('camposContrasenaPortal').classList.add('d-none');
          document.activeElement.blur();
          bootstrap.Modal.getInstance(document.getElementById('modalNuevoPaciente')).hide();
          cargarPacientes();
        } else {
          alert(resp.mensaje || 'No se pudo registrar el paciente.');
        }
      })
      .catch(err => {
        console.error('Error al crear paciente:', err);
        alert('Ocurrió un error al registrar el paciente.');
      });
  });
}

function activarAccionesTabla() {
  document.getElementById('tbodyPacientes').addEventListener('click', function (e) {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const accion = target.getAttribute('data-action');
    const id = target.getAttribute('data-id');

    if (accion === 'ver') abrirDetalle(id);
    if (accion === 'editar') abrirEdicionDirecta(id);
    if (accion === 'eliminar') cambiarEstadoPaciente(id, target.getAttribute('data-activo') === 'true');
  });

  document.getElementById('formEditarPaciente').addEventListener('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this);
    const datos = Object.fromEntries(fd.entries());
    datos.id = this.elements['pacienteId'].value;

    fetch('/api/admin/pacientes/actualizar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          document.activeElement.blur();
          bootstrap.Modal.getInstance(document.getElementById('modalEditarPaciente')).hide();
          cargarPacientes();
        } else {
          alert(resp.mensaje || 'No se pudo actualizar el paciente.');
        }
      })
      .catch(err => {
        console.error('Error al actualizar paciente:', err);
        alert('Ocurrió un error al guardar los cambios.');
      });
  });
}

function abrirDetalle(id) {
  fetch(`/api/admin/pacientes/detalle.php?id=${id}`)
    .then(res => res.json())
    .then(p => {
      if (!p.ok) {
        alert(p.mensaje || 'No se pudo cargar el detalle.');
        return;
      }

      document.getElementById('detAvatar').textContent = getIniciales(p.nombre);
      document.getElementById('detNombre').textContent = p.nombre;
      document.getElementById('detId').textContent = p.folio;
      document.getElementById('detEstadoBadge').textContent = p.activo ? 'Activo' : 'Inactivo';
      document.getElementById('detEstadoBadge').className = `status-badge ${p.activo ? 'status-activo' : 'status-inactivo'}`;

      document.getElementById('detTotalVisitas').textContent = p.visitas;
      document.getElementById('detUltimaVisitaKpi').textContent = p.ultima_visita;
      document.getElementById('detProximaCitaKpi').textContent = p.proxima_cita;
      document.getElementById('detSaldoPendiente').textContent = `$${p.saldo}`;

      document.getElementById('detEdad').textContent = p.edad;
      document.getElementById('detFechaNac').textContent = p.fecha_nacimiento_larga;
      document.getElementById('detGenero').textContent = p.genero;
      document.getElementById('detSangre').textContent = p.tipo_sangre;
      document.getElementById('detDireccion').textContent = p.direccion;
      document.getElementById('detEmail').textContent = p.correo;
      document.getElementById('detTelefono').textContent = p.telefono ?? '—';
      document.getElementById('detContactoEmergencia').textContent = p.contacto_emergencia;
      document.getElementById('detAlergias').textContent = p.alergias;

      const btnAcceso = document.getElementById('btnOtorgarAcceso');
      if (p.tiene_acceso) {
        btnAcceso.classList.add('d-none');
      } else {
        btnAcceso.classList.remove('d-none');
        btnAcceso.onclick = () => {
          document.getElementById('formOtorgarAcceso').elements['pacienteId'].value = p.id;
        };
      }

      // El botón "Editar" dentro del modal de detalle abre el modal
      // de edición ya precargado con estos mismos datos.
      document.getElementById('btnEditarDesdeDetalle').onclick = () => precargarFormularioEdicion(p);
      pacienteActualId = p.id;
      new bootstrap.Modal(document.getElementById('modalDetallePaciente')).show();
    })
    .catch(err => {
      console.error('Error al cargar detalle:', err);
      alert('Ocurrió un error al cargar el detalle del paciente.');
    });
}

function activarFormularioOtorgarAcceso() {
  document.getElementById('formOtorgarAcceso').addEventListener('submit', function (e) {
    e.preventDefault();

    const id = this.elements['pacienteId'].value;
    const contrasena = this.elements['contrasena'].value;
    const confirmacion = document.getElementById('confirmarContrasenaAcceso').value;

    if (contrasena.length < 6) {
      alert('La contraseña debe tener al menos 6 caracteres.');
      return;
    }
    if (contrasena !== confirmacion) {
      alert('Las contraseñas no coinciden.');
      return;
    }

    fetch('/api/admin/pacientes/otorgar-acceso.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, contrasena }),
    })
      .then(res => res.json())
      .then(resp => {
        if (resp.ok) {
          this.reset();
          document.activeElement.blur();
          bootstrap.Modal.getInstance(document.getElementById('modalOtorgarAcceso')).hide();
          alert('Acceso otorgado. Ya puedes compartirle al paciente su correo y la contraseña que asignaste.');
          cargarPacientes();
        } else {
          alert(resp.mensaje || 'No se pudo otorgar el acceso.');
        }
      })
      .catch(err => {
        console.error('Error al otorgar acceso:', err);
        alert('Ocurrió un error al generar el acceso.');
      });
  });
}


function abrirEdicionDirecta(id) {
  fetch(`/api/admin/pacientes/detalle.php?id=${id}`)
    .then(res => res.json())
    .then(p => {
      if (!p.ok) {
        alert(p.mensaje || 'No se pudo cargar el paciente.');
        return;
      }
      precargarFormularioEdicion(p);
      new bootstrap.Modal(document.getElementById('modalEditarPaciente')).show();
    })
    .catch(err => {
      console.error('Error al cargar paciente para editar:', err);
      alert('Ocurrió un error al cargar el paciente.');
    });
}

function precargarFormularioEdicion(p) {
  const form = document.getElementById('formEditarPaciente');
  form.elements['pacienteId'].value = p.id;
  form.elements['nombre'].value = p.nombre;
  form.elements['fechaNacimiento'].value = p.fecha_nacimiento_input;
  form.elements['genero'].value = p.genero_valor;
  form.elements['email'].value = p.correo;
  form.elements['telefono'].value = p.telefono ?? '';
  form.elements['direccion'].value = p.direccion === '—' ? '' : p.direccion;
  form.elements['tipoSangre'].value = p.tipo_sangre_valor;
  form.elements['alergias'].value = p.alergias === '—' ? '' : p.alergias;
  form.elements['contactoEmergencia'].value = p.contacto_emergencia === '—' ? '' : p.contacto_emergencia;
}

async function cambiarEstadoPaciente(id, estaActivo) {
  const mensaje = estaActivo
    ? '¿Desactivar a este paciente? Su expediente NO se borra, solo deja de aparecer como activo.'
    : '¿Reactivar a este paciente?';

  const confirmado = await confirmarAccion(mensaje, {
    titulo: estaActivo ? 'Desactivar paciente' : 'Reactivar paciente',
    textoBoton: estaActivo ? 'Desactivar' : 'Reactivar',
    colorBoton: estaActivo ? 'btn-danger' : 'btn-teal',
  });
  if (!confirmado) return;

  fetch('/api/admin/pacientes/cambiar-estado.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, activo: !estaActivo }),
  })
    .then(res => res.json())
    .then(resp => {
      if (resp.ok) {
        cargarPacientes();
      } else {
        alert(resp.mensaje || 'No se pudo cambiar el estado del paciente.');
      }
    })
    .catch(err => {
      console.error('Error al cambiar estado:', err);
      alert('Ocurrió un error al cambiar el estado.');
    });
}

function cargarFacturacion(id) {
  const tbody = document.querySelector('#tabFacturacion tbody');
  const vacio = document.querySelector('#tabFacturacion .empty-state');
  tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted small py-3">Cargando...</td></tr>';
  vacio.style.display = 'none';

  fetch(`/api/admin/pacientes/facturacion.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (!data.ok) {
        tbody.innerHTML = `<tr><td colspan="4" class="text-danger small text-center py-3">${data.mensaje || 'No se pudo cargar.'}</td></tr>`;
        return;
      }
      document.querySelector('#tabFacturacion .bill-kpi.green .val').textContent = `$${data.resumen.total_pagado}`;
      document.querySelector('#tabFacturacion .bill-kpi.orange .val').textContent = `$${data.resumen.saldo_pendiente}`;
      document.querySelector('#tabFacturacion .bill-kpi.teal .val').textContent = `$${data.resumen.total_facturado}`;

      if (data.citas.length === 0) {
        tbody.innerHTML = '';
        vacio.style.display = 'block';
        return;
      }
      tbody.innerHTML = data.citas.map(c => `
        <tr>
          <td>${c.fecha}</td>
          <td>${c.servicio}</td>
          <td>$${c.monto}</td>
          <td><span class="status-badge ${c.pagado ? 'status-activo' : 'status-inactivo'}">${c.estado_pago}</span></td>
        </tr>
      `).join('');
    })
    .catch(err => {
      console.error('Error al cargar facturación:', err);
      tbody.innerHTML = '<tr><td colspan="4" class="text-danger small text-center py-3">Error de conexión.</td></tr>';
    });
}

function cargarHistorialMedico(id) {
  const tbody = document.querySelector('#tabHistorial tbody');
  const vacio = document.querySelector('#tabHistorial .empty-state');
  tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted small py-3">Cargando...</td></tr>';
  vacio.style.display = 'none';

  fetch(`/api/admin/pacientes/historial-medico.php?id=${id}`)
    .then(res => res.json())
    .then(data => {
      if (!data.ok) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-danger small text-center py-3">${data.mensaje || 'No se pudo cargar.'}</td></tr>`;
        return;
      }
      if (data.tratamientos.length === 0) {
        tbody.innerHTML = '';
        vacio.style.display = 'block';
        return;
      }
      tbody.innerHTML = data.tratamientos.map(t => `
        <tr>
          <td>${t.fecha}</td>
          <td>${t.tratamiento}</td>
          <td>${t.categoria}</td>
          <td>${t.dentista}</td>
          <td>${t.sesiones}</td>
          <td><span class="status-badge ${t.estado === 'Completado' ? 'status-activo' : (t.estado === 'Cancelado' ? 'status-inactivo' : '')}">${t.estado}</span></td>
          <td>$${t.costo}</td>
          <td>$${t.pendiente}</td>
        </tr>
      `).join('');
    })
    .catch(err => {
      console.error('Error al cargar historial médico:', err);
      tbody.innerHTML = '<tr><td colspan="8" class="text-danger small text-center py-3">Error de conexión.</td></tr>';
    });
}

// Mismo catálogo de condiciones que admin-odontograma.js -- si agregas o
// cambias una condición allá, actualiza también esta copia (solo lectura
// aquí, así que no vale la pena compartir el archivo por una tabla tan chica).
const CONDICIONES_ODO = {
  sano:       { abbr: '',    bg: '#ffffff', border:'#cbd5e1', color: '#94a3b8' },
  caries:     { abbr: 'Car', bg: '#fecaca', border:'#fecaca', color: '#dc2626' },
  obturado:   { abbr: 'Obt', bg: '#bbf7d0', border:'#bbf7d0', color: '#16a34a' },
  extraido:   { abbr: '×',   bg: '#e2e8f0', border:'#e2e8f0', color: '#64748b' },
  implante:   { abbr: 'Imp', bg: '#99f6e4', border:'#99f6e4', color: '#0f766e' },
  corona:     { abbr: 'Cor', bg: '#fef08a', border:'#fef08a', color: '#a16207' },
  endodoncia: { abbr: 'End', bg: '#fed7aa', border:'#fed7aa', color: '#c2410c' },
  fractura:   { abbr: 'Fra', bg: '#fbcfe8', border:'#fbcfe8', color: '#be185d' },
};

function cargarOdontogramaPaciente(id) {
  document.getElementById('linkVerOdontogramaCompleto').href = `odontograma.html?pacienteId=${id}`;

  fetch(`/api/admin/odontograma/obtener.php?pacienteId=${id}`)
    .then(res => res.json())
    .then(data => {
      if (!data.ok) return;

      document.getElementById('odoDetalleUltimaActualizacion').textContent = 'Última actualización: ' + data.ultimaActualizacion;

      const crearBox = (numero) => {
        const d = data.dientes[numero];
        const c = CONDICIONES_ODO[d.condicion];
        return `
          <div class="tooth-col">
            <span class="tooth-num">${numero}</span>
            <div class="tooth-box-o" style="background:${c.bg}; border-color:${c.border}; color:${c.color}; cursor:default;" title="Diente #${numero} - ${d.condicion}">
              ${c.abbr}
            </div>
          </div>
        `;
      };

      document.getElementById('detFilaSuperior').innerHTML = Array.from({length: 16}, (_, i) => crearBox(i + 1)).join('');
      document.getElementById('detFilaInferior').innerHTML = Array.from({length: 16}, (_, i) => crearBox(32 - i)).join('');

      const contadores = { caries: 0, obturado: 0, extraido: 0, implante: 0 };
      Object.values(data.dientes).forEach(d => {
        if (contadores.hasOwnProperty(d.condicion)) contadores[d.condicion]++;
      });
      document.getElementById('detStatCaries').textContent = contadores.caries;
      document.getElementById('detStatObturado').textContent = contadores.obturado;
      document.getElementById('detStatExtraido').textContent = contadores.extraido;
      document.getElementById('detStatImplante').textContent = contadores.implante;
    })
    .catch(err => console.error('Error al cargar odontograma del paciente:', err));
}