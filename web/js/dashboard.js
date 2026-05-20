'use strict';

(function () {
  const cfg = window.dashboardConfig;
  const DATA_URL = cfg.dataUrl;
  const INDEX_URL = cfg.indexUrl;
  const COL_KEY = 'stats_sort_col';
  const DIR_KEY = 'stats_sort_dir';

  const form = document.getElementById('filter-form');
  const tbody = document.querySelector('#stats-table tbody');
  const sortHeaders = document.querySelectorAll('[data-sort-col]');

  let currentSort = localStorage.getItem(COL_KEY) ?? cfg.sort;
  let currentDir = localStorage.getItem(DIR_KEY) ?? cfg.dir;
  let appliedFilter = parseFilterFromUrl();
  let requestsChart = null;
  let browsersChart = null;

  function parseFilterFromUrl() {
    const result = new URLSearchParams();
    const url = new URLSearchParams(window.location.search);
    for (const key of ['date_from', 'date_to', 'os', 'architecture']) {
      const val = url.get(key);
      if (val) result.set(key, val);
    }
    return result;
  }

  function readFormParams() {
    const params = new URLSearchParams();
    for (const el of form.querySelectorAll('[name]')) {
      if (el.value) params.set(el.name, el.value);
    }
    return params;
  }

  function buildParams() {
    const params = new URLSearchParams(appliedFilter);
    params.set('sort', currentSort);
    params.set('dir', currentDir);
    return params;
  }

  function buildDataUrl() {
    const sep = DATA_URL.includes('?') ? '&' : '?';
    return `${DATA_URL}${sep}${buildParams()}`;
  }

  function syncUrl() {
    const sep = INDEX_URL.includes('?') ? '&' : '?';
    history.pushState(null, '', `${INDEX_URL}${sep}${buildParams()}`);
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function truncate(str, max) {
    str = String(str ?? '');
    return str.length > max ? `${str.slice(0, max)}…` : str;
  }

  function renderRows(rows) {
    if (!rows?.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="p-3 text-muted">No data for the selected filters.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(row => `
            <tr>
                <td>${escapeHtml(row.date)}</td>
                <td>${Number(row.count).toLocaleString()}</td>
                <td><span class="url-preview" title="${escapeHtml(row.top_url)}">${escapeHtml(truncate(row.top_url, 80))}</span></td>
                <td>${escapeHtml(row.top_browser)}</td>
            </tr>
        `).join('');
  }

  function updateHeaders() {
    for (const th of sortHeaders) {
      const icon = th.querySelector('.sort-icon');
      if (icon) icon.textContent = th.dataset.sortCol === currentSort
        ? (currentDir === 'ASC' ? '▲' : '▼') : '';
    }
  }

  function updateCharts(dates, counts, datasets) {
    if (requestsChart) {
      requestsChart.data.labels = dates;
      requestsChart.data.datasets[0].data = counts;
      requestsChart.update();
    }
    if (browsersChart) {
      browsersChart.data.labels = dates;
      browsersChart.data.datasets = datasets;
      browsersChart.update();
    }
  }

  async function fetchData(withCharts) {
    updateHeaders();
    try {
      const data = await fetch(buildDataUrl()).then(r => r.json());
      renderRows(data.dailyStats);
      if (withCharts) updateCharts(data.chartDates, data.chartCounts, data.browserDatasets);
    } catch (err) {
      console.error('Fetch failed:', err);
    }
  }

  document.querySelector('#stats-table thead').addEventListener('click', e => {
    const th = e.target.closest('[data-sort-col]');
    if (!th) return;

    const col = th.dataset.sortCol;
    if (col === currentSort) {
      currentDir = currentDir === 'ASC' ? 'DESC' : 'ASC';
    } else {
      currentSort = col;
      currentDir = 'ASC';
    }
    localStorage.setItem(COL_KEY, currentSort);
    localStorage.setItem(DIR_KEY, currentDir);
    syncUrl();
    fetchData(false);
  });

  document.getElementById('apply-btn').addEventListener('click', () => {
    appliedFilter = readFormParams();
    syncUrl();
    fetchData(true);
  });

  document.getElementById('reset-btn').addEventListener('click', () => {
    for (const el of form.querySelectorAll('[name]')) el.value = '';
    appliedFilter = new URLSearchParams();
    syncUrl();
    fetchData(true);
  });

  if (currentSort !== cfg.sort || currentDir !== cfg.dir) {
    fetchData(false);
  } else {
    updateHeaders();
  }

  requestsChart = new Chart(document.getElementById('requestsChart'), {
    type: 'bar',
    data: {
      labels: cfg.chartDates,
      datasets: [{
        label: 'Requests',
        data: cfg.chartCounts,
        backgroundColor: 'rgba(54,162,235,0.6)',
        borderColor: 'rgb(54,162,235)',
        borderWidth: 1,
      }],
    },
    options: {
      responsive: true,
      plugins: {legend: {display: false}},
      scales: {y: {beginAtZero: true, ticks: {precision: 0}}},
    },
  });

  browsersChart = new Chart(document.getElementById('browsersChart'), {
    type: 'line',
    data: {
      labels: cfg.chartDates,
      datasets: cfg.browserDatasets,
    },
    options: {
      responsive: true,
      interaction: {mode: 'index', intersect: false},
      plugins: {
        tooltip: {
          callbacks: {
            label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y}%`,
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {callback: v => `${v}%`},
        },
      },
    },
  });
}());
