function updateFilter(key, value) {
  const url = new URL(window.location.href);
  url.searchParams.set(key, value);
  url.searchParams.delete('page');
  showLoader(true);
  window.location.href = url.toString();
}

function applyPriceFilter() {
  const minInput = document.getElementById('price-min');
  const maxInput = document.getElementById('price-max');
  const url = new URL(window.location.href);
  if (minInput && minInput.value !== '') {
    url.searchParams.set('min_prix', minInput.value);
  } else {
    url.searchParams.delete('min_prix');
  }
  if (maxInput && maxInput.value !== '') {
    url.searchParams.set('max_prix', maxInput.value);
  } else {
    url.searchParams.delete('max_prix');
  }
  url.searchParams.delete('page');
  showLoader(true);
  window.location.href = url.toString();
}

function confirmLogin() {
  alert('Veuillez vous connecter ou créer un compte pour utiliser le panier.');
  window.location.href = window.location.pathname.replace(/^(.*\/)[^\/]*$/, '$1') + 'auth.php';
  return false;
}

function switchTab(mode) {
  const target = 'tab-' + mode;
  // Remove active from all tabs
  document.querySelectorAll('.auth-tab').forEach(tab => tab.classList.remove('active'));
  // Add active to the clicked tab
  document.querySelector(`[data-target="${target}"]`).classList.add('active');
  // Hide all panels
  document.querySelectorAll('.auth-panel').forEach(panel => panel.classList.remove('active'));
  // Show the target panel
  document.getElementById(target).classList.add('active');
}

function showLoader(visible) {
  const overlay = document.getElementById('products-loader');
  if (!overlay) return;
  if (visible) {
    overlay.hidden = false;
  } else {
    overlay.hidden = true;
  }
}

window.addEventListener('DOMContentLoaded', function () {
  const overlay = document.getElementById('products-loader');
  if (overlay) {
    overlay.hidden = true;
  }
});
