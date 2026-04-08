import './bootstrap';
import Chart from 'chart.js/auto';
import { DataTable } from 'simple-datatables';
import 'simple-datatables/dist/style.css';

const toastElement = () => document.getElementById('app-toast');
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const showToast = (message, isError = false) => {
    const element = toastElement();
    if (!element || !message) {
        return;
    }

    element.textContent = message;
    element.classList.remove(
        'hidden',
        'translate-y-0',
        'opacity-100',
        'bg-slate-900',
        'bg-emerald-500',
        'bg-rose-600'
    );
    element.classList.add(isError ? 'bg-rose-600' : 'bg-emerald-500');

    window.clearTimeout(element._timeout);
    requestAnimationFrame(() => {
        element.classList.remove('translate-y-3', 'opacity-0');
        element.classList.add('translate-y-0', 'opacity-100');
    });

    element._timeout = window.setTimeout(() => {
        element.classList.remove('translate-y-0', 'opacity-100');
        element.classList.add('translate-y-3', 'opacity-0');
        window.setTimeout(() => element.classList.add('hidden'), 250);
    }, 10000);
};

const initPageToasts = () => {
    const toast = document.querySelector('[data-page-toast]');
    if (!toast) {
        return;
    }

    showToast(toast.dataset.toastMessage, toast.dataset.toastType === 'error');
};

const submitAjaxForm = async (form) => {
    const submitter = form.querySelector('[type="submit"]');
    const originalLabel = submitter?.innerHTML;

    try {
        submitter?.setAttribute('disabled', 'disabled');
        if (submitter) {
            submitter.innerHTML = 'Saving...';
        }

        const response = await fetch(form.action, {
            method: form.method.toUpperCase(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: new FormData(form),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = payload.message || Object.values(payload.errors || {}).flat()[0] || 'Request gagal diproses.';
            showToast(message, true);
            return;
        }

        showToast(payload.message || form.dataset.successMessage || 'Perubahan berhasil disimpan.');

        if (form.closest('dialog')) {
            form.closest('dialog').close();
        }

        window.setTimeout(() => {
            if (payload.redirect) {
                window.location.href = payload.redirect;
                return;
            }

            if (response.redirected && response.url) {
                window.location.href = response.url;
                return;
            }

            if (form.dataset.reload !== 'false') {
                window.location.reload();
            }
        }, 450);
    } catch {
        showToast('Terjadi error jaringan. Coba lagi.', true);
    } finally {
        submitter?.removeAttribute('disabled');
        if (submitter && originalLabel) {
            submitter.innerHTML = originalLabel;
        }
    }
};

const initAjaxForms = () => {
    document.querySelectorAll('form[data-ajax-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAjaxForm(form);
        });
    });
};

const filterSelectOptions = (select, allowedIds) => {
    if (!select) {
        return;
    }

    const currentValue = select.value;

    Array.from(select.options).forEach((option) => {
        if (!option.value) {
            option.hidden = false;
            return;
        }

        option.hidden = !allowedIds.includes(option.value);
    });

    const selected = Array.from(select.options).some((option) => option.value === currentValue && !option.hidden);
    if (!selected) {
        select.value = '';
    }
};

const initTicketForm = () => {
    const projectSelect = document.querySelector('[data-ticket-project]');
    const categorySelect = document.querySelector('[data-ticket-category]');
    const subcategorySelect = document.querySelector('[data-ticket-subcategory]');
    const requesterSelect = document.querySelector('[data-ticket-requester]');
    const assigneeSelect = document.querySelector('[data-ticket-assignee]');
    const deviceSelect = document.querySelector('[data-ticket-device]');
    const projectHint = document.querySelector('[data-project-hint]');
    const teamHint = document.querySelector('[data-team-hint]');
    const assigneeHint = document.querySelector('[data-assignee-hint]');

    const syncProjectMembers = () => {
        if (!projectSelect) {
            return;
        }

        const selectedOption = projectSelect.selectedOptions[0];
        const clientIds = (selectedOption?.dataset.clients || '').split(',').filter(Boolean);
        const agentIds = (selectedOption?.dataset.agents || '').split(',').filter(Boolean);
        const deviceIds = (selectedOption?.dataset.devices || '').split(',').filter(Boolean);

        filterSelectOptions(requesterSelect, clientIds);
        filterSelectOptions(assigneeSelect, agentIds);
        filterSelectOptions(deviceSelect, deviceIds);

        if (projectHint) {
            projectHint.textContent = selectedOption?.value
                ? `Project active: ${selectedOption.textContent.trim()}`
                : 'Pilih project untuk melihat requester dan assignee yang valid.';
        }
    };

    const syncSubcategories = () => {
        if (!categorySelect || !subcategorySelect) {
            return;
        }

        const currentCategory = categorySelect.value;
        const currentValue = subcategorySelect.value;

        Array.from(subcategorySelect.options).forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            option.hidden = option.dataset.parent !== currentCategory;
        });

        const visibleCurrent = Array.from(subcategorySelect.options).some((option) => option.value === currentValue && !option.hidden);
        if (!visibleCurrent) {
            subcategorySelect.value = '';
        }

        const selectedOption = categorySelect.selectedOptions[0];
        if (teamHint) {
            teamHint.textContent = 'Category berlaku untuk semua project.';
        }
        if (assigneeHint) {
            assigneeHint.textContent = 'Assignment mengikuti member project yang dipilih.';
        }
    };

    projectSelect?.addEventListener('change', syncProjectMembers);
    categorySelect?.addEventListener('change', syncSubcategories);
    syncProjectMembers();
    syncSubcategories();
};

const initDialogs = () => {
    if (!document.body.dataset.dialogsReady) {
        document.addEventListener('click', (event) => {
            const openButton = event.target.closest('[data-open-dialog]');
            if (openButton) {
                event.preventDefault();
                const dialog = document.getElementById(openButton.dataset.openDialog);
                dialog?.showModal();
                return;
            }

            const closeButton = event.target.closest('[data-close-dialog]');
            if (closeButton) {
                event.preventDefault();
                closeButton.closest('dialog')?.close();
            }
        });

        document.body.dataset.dialogsReady = 'true';
    }

    document.querySelectorAll('dialog').forEach((dialog) => {
        if (dialog.dataset.backdropReady === 'true') {
            return;
        }

        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            const inside = rect.top <= event.clientY
                && event.clientY <= rect.top + rect.height
                && rect.left <= event.clientX
                && event.clientX <= rect.left + rect.width;

            if (!inside) {
                dialog.close();
            }
        });

        dialog.dataset.backdropReady = 'true';
    });
};

const parseChartData = (id) => {
    const element = document.getElementById(id);
    if (!element) {
        return null;
    }

    try {
        return JSON.parse(element.textContent || 'null');
    } catch {
        return null;
    }
};

const initCharts = () => {
    const statusData = parseChartData('status-chart-data');
    const monthlyData = parseChartData('monthly-chart-data');
    const categoryData = parseChartData('category-chart-data');

    const statusCanvas = document.getElementById('statusChart');
    if (statusCanvas && statusData) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{ data: statusData.values, backgroundColor: statusData.colors, borderWidth: 0 }],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                cutout: '68%',
            },
        });
    }

    const monthlyCanvas = document.getElementById('monthlyChart');
    if (monthlyCanvas && monthlyData) {
        new Chart(monthlyCanvas, {
            type: 'line',
            data: {
                labels: monthlyData.labels,
                datasets: [{
                    label: 'Tickets',
                    data: monthlyData.values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    fill: true,
                    tension: 0.35,
                }],
            },
            options: { responsive: true, plugins: { legend: { display: false } } },
        });
    }

    const categoryCanvas = document.getElementById('categoryChart');
    if (categoryCanvas && categoryData) {
        new Chart(categoryCanvas, {
            type: 'bar',
            data: {
                labels: categoryData.labels,
                datasets: [{ data: categoryData.values, backgroundColor: ['#2563eb', '#0284c7', '#0f766e', '#d97706', '#64748b'] }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
            },
        });
    }
};

const initNotificationActions = () => {
    if (document.body.dataset.notificationActionsReady === 'true') {
        return;
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-notification]');
        if (!button) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const item = button.closest('[data-notification-item]');
        const list = document.querySelector('[data-notification-list]');
        const emptyState = document.querySelector('[data-notification-empty]');

        try {
            button.setAttribute('disabled', 'disabled');

            const response = await fetch(button.dataset.deleteNotification, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                showToast(payload.message || 'Notifikasi gagal dihapus.', true);
                button.removeAttribute('disabled');
                return;
            }

            item?.remove();
            if (list && !list.querySelector('[data-notification-item]')) {
                emptyState?.classList.remove('hidden');
            }

            showToast(payload.message || 'Notifikasi berhasil dihapus.');
        } catch {
            showToast('Terjadi error jaringan. Coba lagi.', true);
            button.removeAttribute('disabled');
        }
    });

    document.body.dataset.notificationActionsReady = 'true';
};
const initDataTables = () => {
    document.querySelectorAll('[data-datatable]').forEach((table) => {
        if (table.dataset.datatableReady === 'true') {
            return;
        }

        const wrapper = table.closest('.datatable-shell');
        wrapper?.classList.add('datatable-shell');

        new DataTable(table, {
            perPage: 10,
            perPageSelect: [10, 25, 50, 100],
            searchable: true,
            fixedHeight: false,
            labels: {
                placeholder: 'Cari data...',
                perPage: '{select} per halaman',
                noRows: 'Tidak ada data',
                noResults: 'Data tidak ditemukan',
                info: 'Menampilkan {start} sampai {end} dari {rows} data',
            },
        });

        table.dataset.datatableReady = 'true';
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initPageToasts();
    initAjaxForms();
    initTicketForm();
    initDialogs();
    initCharts();
    initDataTables();
});

