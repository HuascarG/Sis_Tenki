// ============================================================
// TENKI - Main JS
// ============================================================

// --- Flash auto-dismiss
document.addEventListener('DOMContentLoaded', () => {
  const alerts = document.querySelectorAll('.alert[data-auto-dismiss]');
  alerts.forEach(a => setTimeout(() => { a.style.opacity = '0'; setTimeout(() => a.remove(), 400); }, 3000));
});

// --- Confirm delete with POST & CSRF (SweetAlert2)
function confirmDelete(url, name = 'este registro') {
  Swal.fire({
    title: '¿Estás seguro?',
    html: `Estás a punto de eliminar <strong>${name}</strong>.<br>Esta acción no se puede deshacer.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc3545',
    cancelButtonColor: '#6c757d',
    confirmButtonText: '<i class="bi bi-trash me-1"></i>Sí, eliminar',
    cancelButtonText: 'Cancelar',
    reverseButtons: true
  }).then((result) => {
    if (result.isConfirmed) {
      // Obtener token CSRF de la meta etiqueta
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

      // Crear formulario POST dinámico para evitar vulnerabilidades de GET
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = url;

      if (csrfToken) {
        const inputCsrf = document.createElement('input');
        inputCsrf.type = 'hidden';
        inputCsrf.name = 'csrf_token';
        inputCsrf.value = csrfToken;
        form.appendChild(inputCsrf);
      }

      document.body.appendChild(form);
      form.submit();
    }
  });
}

// Interceptor global para inyectar token CSRF en todos los formularios y peticiones AJAX (fetch)
document.addEventListener('DOMContentLoaded', () => {
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  if (!csrfMeta) return;
  const token = csrfMeta.getAttribute('content');

  // Inyectar en formularios (incluyendo los modales ajax sin method explícito)
  document.querySelectorAll('form').forEach(form => {
    if (!form.querySelector('input[name="csrf_token"]')) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'csrf_token';
      input.value = token;
      form.prepend(input);
    }
  });

  // Interceptar window.fetch para inyectar X-CSRF-Token automáticamente en cualquier POST
  const originalFetch = window.fetch;
  window.fetch = function(resource, init = {}) {
    if (init && init.method && init.method.toUpperCase() === 'POST') {
      init.headers = init.headers || {};
      if (init.headers instanceof Headers) {
        if (!init.headers.has('X-CSRF-Token')) {
          init.headers.append('X-CSRF-Token', token);
        }
      } else if (Array.isArray(init.headers)) {
        init.headers.push(['X-CSRF-Token', token]);
      } else {
        init.headers['X-CSRF-Token'] = token;
      }

      // Si el cuerpo es FormData y no tiene el csrf_token, agregarlo también
      if (init.body instanceof FormData && !init.body.has('csrf_token')) {
        init.body.append('csrf_token', token);
      }
    }
    return originalFetch(resource, init);
  };
});

// --- Toggle sidebar (mobile) — auto-close offcanvas when a nav link is clicked
document.addEventListener('DOMContentLoaded', () => {
  const offcanvasEl = document.getElementById('sidebarOffcanvas');
  if (offcanvasEl) {
    const offcanvasInstance = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
    // When any nav-item inside the offcanvas is clicked, close the sidebar
    offcanvasEl.querySelectorAll('.nav-item').forEach(link => {
      link.addEventListener('click', () => {
        offcanvasInstance.hide();
      });
    });
  }
});

// --- Retraer y expandir sidebar (Escritorio / Pantalla completa)
window.toggleSidebarRetract = function(forceCollapse) {
  const html = document.documentElement;
  const body = document.body;
  const isCollapsed = html.classList.contains('sidebar-collapsed') || body.classList.contains('sidebar-collapsed');
  const shouldCollapse = (typeof forceCollapse === 'boolean') ? forceCollapse : !isCollapsed;

  if (shouldCollapse) {
    html.classList.add('sidebar-collapsed');
    body.classList.add('sidebar-collapsed');
    try { localStorage.setItem('sidebar_collapsed', 'true'); } catch(e) {}
  } else {
    html.classList.remove('sidebar-collapsed');
    body.classList.remove('sidebar-collapsed');
    try { localStorage.setItem('sidebar_collapsed', 'false'); } catch(e) {}
  }

  // Notificar a componentes y tablas del cambio de ancho
  setTimeout(() => window.dispatchEvent(new Event('resize')), 100);
  setTimeout(() => window.dispatchEvent(new Event('resize')), 300);
};

document.addEventListener('DOMContentLoaded', () => {
  const collapseBtn   = document.getElementById('btnSidebarCollapse');
  const toggleBtn     = document.getElementById('sidebarToggleBtn');
  const floatingBtn   = document.getElementById('sidebarFloatingToggle');

  // Sincronizar clase en body si ya estaba en el HTML
  if (document.documentElement.classList.contains('sidebar-collapsed')) {
    document.body.classList.add('sidebar-collapsed');
  }

  if (collapseBtn) {
    collapseBtn.onclick = (e) => {
      e.preventDefault();
      window.toggleSidebarRetract(true);
    };
  }

  if (toggleBtn) {
    toggleBtn.onclick = (e) => {
      e.preventDefault();
      window.toggleSidebarRetract();
    };
  }

  if (floatingBtn) {
    floatingBtn.onclick = (e) => {
      e.preventDefault();
      window.toggleSidebarRetract(false);
    };
  }

  // Atajo de teclado: Ctrl + B o Alt + S
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey && e.key.toLowerCase() === 'b') || (e.altKey && e.key.toLowerCase() === 's')) {
      const tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
      if (['input', 'textarea', 'select'].includes(tag)) return;
      e.preventDefault();
      window.toggleSidebarRetract();
    }
  });
});

// --- Format currency inputs
document.querySelectorAll('.currency-input').forEach(input => {
  input.addEventListener('blur', () => {
    const val = parseFloat(input.value);
    if (!isNaN(val)) input.value = val.toFixed(2);
  });
});

// ============================================================
// CART / NUEVA VENTA
// ============================================================
// --- Filter Select Options
function filterProductSelect(searchId, selectId, countId) {
  const input = document.getElementById(searchId);
  const select = document.getElementById(selectId);
  const counter = countId ? document.getElementById(countId) : null;
  if (!input || !select) return;
  
  const filter = input.value.toLowerCase().trim();
  const options = select.options;
  let count = 0;
  
  for (let i = 1; i < options.length; i++) {
    const text = options[i].text.toLowerCase();
    // Allow searching by exact code quickly or general text
    if (text.includes(filter)) {
      options[i].style.display = '';
      count++;
    } else {
      options[i].style.display = 'none';
    }
  }
  if (counter) counter.textContent = `(${count})`;
  
  // Exclusividad: auto-seleccionar si queda solo 1
  if (count === 1 && filter.length > 2) {
    for (let i = 1; i < options.length; i++) {
       if (options[i].style.display === '') {
         select.selectedIndex = i;
         if (select.onchange) select.onchange(); // trigger any attached handlers
         break;
       }
    }
  } else if (count > 0 && select.options[select.selectedIndex]?.style.display === 'none') {
    select.selectedIndex = 0;
    if (select.onchange) select.onchange();
  }
}

let cart = {};

function addToCart() {
  const select   = document.getElementById('prod_select');
  const qty      = parseInt(document.getElementById('prod_qty').value) || 1;
  const inputSearch = document.getElementById('prod_search');
  if (!select || !select.value) return;

  const id       = select.value;
  const name     = select.options[select.selectedIndex].dataset.name;
  const price    = parseFloat(select.options[select.selectedIndex].dataset.price);
  const stock    = parseInt(select.options[select.selectedIndex].dataset.stock);

  if (qty < 1 || qty > stock) {
    Swal.fire({ icon: 'warning', title: 'Stock insuficiente', text: `Stock disponible: ${stock} unidades`, confirmButtonColor: '#f97316' });
    return;
  }

  if (cart[id]) {
    cart[id].qty += qty;
    if (cart[id].qty > stock) { cart[id].qty = stock; Swal.fire({ icon: 'info', title: 'Límite alcanzado', text: 'Se ha alcanzado el límite de stock disponible.', confirmButtonColor: '#f97316', timer: 2500, timerProgressBar: true }); }
  } else {
    cart[id] = { id, name, price, qty, stock };
  }
  
  // Limpiar buscador despues de agregar
  if (typeof clearProductSearch === 'function') {
      clearProductSearch();
  } else if (inputSearch) {
      inputSearch.value = '';
      if (typeof filterProductSelect === 'function') {
          filterProductSelect('prod_search', 'prod_select', 'prod_count');
      }
      document.getElementById('prod_qty').value = 1;
      inputSearch.focus();
  }
  
  renderCart();
}

function removeFromCart(id) {
  delete cart[id];
  renderCart();
}

function renderCart() {
  const tbody  = document.getElementById('cart-tbody');
  const hidden = document.getElementById('cart-data');
  const tasaEl = document.getElementById('tasa_bcv');
  if (!tbody) return;

  const tasa = tasaEl ? (parseFloat(tasaEl.value) || 1) : 1;

  tbody.innerHTML = '';
  let subtotal = 0;

  Object.values(cart).forEach(item => {
    const line = item.price * item.qty;
    const lineBs = line * tasa;
    subtotal += line;
    tbody.innerHTML += `
      <tr>
        <td>${item.name}</td>
        <td>
          <input type="number" class="form-control" style="width:80px" value="${item.qty}" min="1" max="${item.stock}"
            onchange="cart['${item.id}'].qty=Math.min(parseInt(this.value)||1,${item.stock});renderCart()">
        </td>
        <td style="line-height: 1.1;">
          <input type="number" class="form-control form-control-sm" style="width:90px" value="${item.price.toFixed(2)}" min="0.01" step="0.01"
            onchange="cart['${item.id}'].price=Math.max(0.01,parseFloat(this.value)||0.01);renderCart()">
          <small class="text-muted" style="font-size:11px">Bs ${(item.price * tasa).toFixed(2)}</small>
        </td>
        <td style="line-height: 1.1;">
          <span class="d-block fw-semi">$${line.toFixed(2)}</span>
          <small class="text-muted fw-semi" style="font-size:11px">Bs ${lineBs.toFixed(2)}</small>
        </td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeFromCart('${item.id}')">✕</button></td>
      </tr>`;
  });

  const ivaToggle = document.getElementById('toggle_iva');
  const aplicarIva = ivaToggle ? ivaToggle.checked : true;
  const iva     = aplicarIva ? subtotal * 0.16 : 0;
  const total   = subtotal + iva;
  const totalBs = total * tasa;

  const elSub     = document.getElementById('cart-subtotal');
  const elIva     = document.getElementById('cart-iva');
  const elTotal   = document.getElementById('cart-total');
  const elTotalBs = document.getElementById('cart-total-bs');
  
  if (elSub)     elSub.textContent   = '$' + subtotal.toFixed(2);
  if (elIva)     elIva.textContent   = '$' + iva.toFixed(2);
  if (elTotal)   elTotal.textContent = '$' + total.toFixed(2);
  if (elTotalBs) elTotalBs.textContent = 'Bs ' + totalBs.toFixed(2);

  if (hidden) hidden.value = JSON.stringify(Object.values(cart));
}

// ============================================================
// CART / NUEVA COMPRA
// ============================================================
let cartCompra = {};

function addToCartCompra() {
  const select = document.getElementById('comp_prod_select');
  const qtyEl  = document.getElementById('comp_prod_qty');
  const priceEl= document.getElementById('comp_prod_price');
  if (!select || !select.value) return;

  const id    = select.value;
  const name  = select.options[select.selectedIndex].dataset.name;
  const qty   = parseInt(qtyEl.value) || 1;
  const price = parseFloat(priceEl.value) || 0;

  if (cartCompra[id]) {
    cartCompra[id].qty   += qty;
    cartCompra[id].price  = price;
  } else {
    cartCompra[id] = { id, name, price, qty };
  }
  
  // Limpiar buscador de compra despues de agregar
  const inputSearchComp = document.getElementById('comp_prod_search');
  if (inputSearchComp) {
      inputSearchComp.value = '';
      filterProductSelect('comp_prod_search', 'comp_prod_select', null);
      qtyEl.value = 1;
      priceEl.value = 0;
      inputSearchComp.focus();
  }
  
  renderCartCompra();
}

function removeFromCartCompra(id) {
  delete cartCompra[id];
  renderCartCompra();
}

function renderCartCompra() {
  const tbody  = document.getElementById('compra-tbody');
  const hidden = document.getElementById('compra-data');
  if (!tbody) return;

  tbody.innerHTML = '';
  let subtotal = 0;

  Object.values(cartCompra).forEach(item => {
    const line = item.price * item.qty;
    subtotal += line;
    tbody.innerHTML += `
      <tr>
        <td>${item.name}</td>
        <td>${item.qty}</td>
        <td>$${item.price.toFixed(2)}</td>
        <td class="fw-semi">$${line.toFixed(2)}</td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeFromCartCompra('${item.id}')">✕</button></td>
      </tr>`;
  });

  const iva   = subtotal * 0.16;
  const total = subtotal + iva;

  const elSub   = document.getElementById('comp-subtotal');
  const elIva   = document.getElementById('comp-iva');
  const elTotal = document.getElementById('comp-total');
  if (elSub)   elSub.textContent   = '$' + subtotal.toFixed(2);
  if (elIva)   elIva.textContent   = '$' + iva.toFixed(2);
  if (elTotal) elTotal.textContent = '$' + total.toFixed(2);

  if (hidden) hidden.value = JSON.stringify(Object.values(cartCompra));
}
