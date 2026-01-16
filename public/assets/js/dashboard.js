/**
 * Dashboard module
 * Moves the inline AJAX helpers and NumberFormat setup out of dashboard.php.
 */
(function () {
  'use strict';

  const config = window.DASHBOARD_CONFIG || {};
  const currencySymbol = config.currencySymbol || '';

// ============================================
// DASHBOARD AJAX (NO PAGE REFRESH)
// ============================================
  function setTimeRange(range) {
    const currentRange = new URLSearchParams(window.location.search).get('range') || '30d';
    if (range === currentRange) return;

    loadDashboardData(range);
  }

  function refreshDashboard() {
    const currentRange = new URLSearchParams(window.location.search).get('range') || '30d';
    loadDashboardData(currentRange);
  }

  function loadDashboardData(range) {
    const dashboardContent = document.querySelector('.dashboard-section');
    if (dashboardContent) {
      dashboardContent.style.opacity = '0.6';
      dashboardContent.style.pointerEvents = 'none';
    }

    fetch(`/api/dashboard.php?action=get_stats&range=${range}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const newUrl = new URL(window.location.href);
          newUrl.searchParams.set('range', range);
          window.history.pushState({}, '', newUrl);

          location.reload();
        }
      })
      .catch(error => console.error('Error loading dashboard:', error))
      .finally(() => {
        if (dashboardContent) {
          dashboardContent.style.opacity = '1';
          dashboardContent.style.pointerEvents = '';
        }
      });
  }

  function autoApplyNumberFormatting() {
    if (typeof NumberFormat === 'undefined') return;

// ============================================
// APPLY NUMBER FORMAT API TO CURRENCY VALUES
// ============================================
    NumberFormat.autoApply(currencySymbol, {
      customSelectors: [
        { selector: '.financial-card-value', maxWidth: 1 },
        { selector: '.metric-value', maxWidth: 1 },
        { selector: '.stat-item-value', maxWidth: 80 },
        { selector: '.aging-amount', maxWidth: 90 },
        { selector: '.account-balance', maxWidth: 90 },
        { selector: '.expense-amount', maxWidth: 80 }
      ]
    });
  }

  window.setTimeRange = setTimeRange;
  window.refreshDashboard = refreshDashboard;

  window.addEventListener('load', function () {
    autoApplyNumberFormatting();
  });
})();
