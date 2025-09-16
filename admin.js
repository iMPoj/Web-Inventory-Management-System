 import { appState } from './state.js';
import { postData } from './api.js';
import { showLoader, hideLoader, showMessage, showConfirmation } from './ui.js';

let loadAllDataCallback = () => {};
let setActiveTabCallback = () => {};
let adminCurrentPage = 1;
const ADMIN_ROWS_PER_PAGE = 50;

export function setLoadAllDataCallback(callback) { loadAllDataCallback = callback; }
export function setAdminSetActiveTabCallback(callback) { setActiveTabCallback = callback; }

function handleAdminTabSwitch(tabName) {
    const buttons = { inventory: document.getElementById('inventoryAdminBtn'), customer: document.getElementById('customerAdminBtn'), export: document.getElementById('exportAdminBtn') };
    const sections = { inventory: document.getElementById('adminInventorySection'), customer: document.getElementById('adminCustomerSection'), export: document.getElementById('adminExportSection') };
    for (const name in buttons) {
        if (buttons[name] && sections[name]) {
            if (name === tabName) {
                sections[name].classList.remove('hidden');
                buttons[name].classList.add('active', 'border-indigo-500', 'text-indigo-600');
                buttons[name].classList.remove('border-transparent', 'text-slate-500');
            } else {
                sections[name].classList.add('hidden');
                buttons[name].classList.remove('active', 'border-indigo-500', 'text-indigo-600');
                buttons[name].classList.add('border-transparent', 'text-slate-500');
            }
        }
    }
}

function handleInventorySubTabSwitch(tabName) {
    document.querySelectorAll('.inventory-sub-tab-btn').forEach(btn => {
        if (btn.dataset.tab === tabName) {
            btn.classList.add('border-indigo-500', 'text-indigo-600');
            btn.classList.remove('border-transparent', 'text-slate-500');
        } else {
            btn.classList.remove('border-indigo-500', 'text-indigo-600');
            btn.classList.add('border-transparent', 'text-slate-500');
        }
    });
    document.querySelectorAll('.inventory-sub-tab-content').forEach(content => {
        content.classList.toggle('hidden', content.id !== `${tabName}Tab`);
    });
}

function renderCustomerManagementList() {
    const list = document.getElementById('customerManagementList');
    if (!list) return;
    list.innerHTML = appState.customers.map(c => `
        <div class="flex justify-between items-center bg-white p-2 rounded shadow-sm">
            <span class="${c.is_priority ? 'font-semibold text-indigo-600' : ''}">${c.name}</span>
            <div class="flex items-center gap-4">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" class="sr-only peer priority-toggle" data-id="${c.id}" ${c.is_priority ? 'checked' : ''}>
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-indigo-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    <span class="ml-3 text-sm font-medium text-gray-900">Priority</span>
                </label>
                <button class="delete-customer-btn text-red-500 hover:text-red-700 text-sm" data-id="${c.id}" data-name="${c.name}">Delete</button>
            </div>
        </div>
    `).join('');
}

function renderAdminProductList() {
    const list = document.getElementById('adminProductList');
    if (!list) return;
    const selectedLoc = document.getElementById('adminLocFilter').value;
    const allSkus = Object.values(appState.products).filter(p => p.type === 'sku');
    let skusWithLocationStock = allSkus.map(product => {
        const invEntry = product.inventory.find(inv => inv.location === selectedLoc);
        return { ...product, locationStock: invEntry ? parseInt(invEntry.stock) : 0 };
    }).sort((a, b) => a.sku.localeCompare(b.sku));
    
    const totalItems = skusWithLocationStock.length;
    const totalPages = Math.ceil(totalItems / ADMIN_ROWS_PER_PAGE);
    adminCurrentPage = Math.min(adminCurrentPage, totalPages || 1);
    const start = (adminCurrentPage - 1) * ADMIN_ROWS_PER_PAGE;
    const pageSkus = skusWithLocationStock.slice(start, start + ADMIN_ROWS_PER_PAGE);

    list.innerHTML = pageSkus.map(product => {
        const stockColor = product.locationStock <= 10 ? 'text-red-600 font-semibold' : '';
        return `
             <tr class="text-sm">
                <td data-label="SKU">${product.sku}</td>
                <td data-label="Description" class="hidden md:table-cell">${product.description}</td>
                <td data-label="Stock" class="${stockColor}">${product.locationStock.toLocaleString('en-US')}</td>
                <td data-label="Actions" class="text-right">
                   <button class="admin-delete-btn text-red-600 hover:text-red-900 text-sm" data-sku="${product.sku}">Delete</button>
                </td>
            </tr>`;
    }).join('') || `<tr><td colspan="4" class="text-center py-4">No SKUs found.</td></tr>`;

    document.getElementById('invPageInfo').textContent = `Page ${adminCurrentPage} of ${totalPages || 1}`;
    document.getElementById('invPrevBtn').disabled = (adminCurrentPage === 1);
    document.getElementById('invNextBtn').disabled = (adminCurrentPage >= totalPages);
}

async function exportData(format) {
    const month = document.getElementById('exportMonth').value;
    const year = document.getElementById('exportYear').value;
    const location = document.getElementById('exportLoc').value;

    if (!year || !month) { return showMessage("Please select a valid month and year.", true); }
    showLoader();
    const result = await postData('get_orders_for_export', { month, year, location });
    hideLoader();
    if (!result.success || result.data.length === 0) { return showMessage("No data found for the selected period.", true); }

    const headers = ['OrderDate', 'Location', 'BU', 'Customer', 'Address', 'PONumber', 'SKU', 'Description', 'Quantity', 'Price', 'Status'];
    const delimiter = format === 'csv' ? ',' : '\t';
    const sanitize = (value) => (String(value).includes(delimiter) || String(value).includes('"') || String(value).includes('\n')) && format === 'csv' ? `"${String(value).replace(/"/g, '""')}"` : String(value);

    let fileContent = headers.join(delimiter) + '\n';
    result.data.forEach(order => {
        order.items.forEach(item => {
            const row = [order.date.split(' ')[0], order.location, order.bu, order.customer.name, order.customer.address, order.customer.poNumber, item.sku, item.description, item.quantity, item.price, item.status];
            fileContent += row.map(sanitize).join(delimiter) + '\n';
        });
    });

    const blob = new Blob([fileContent], { type: `text/${format};charset=utf-8;` });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `orders_${location}_${year}-${String(month).padStart(2, '0')}.${format}`;
    link.click();
    link.remove();
}

export async function initializeAdminPage() {
    handleAdminTabSwitch('inventory'); 
    adminCurrentPage = 1;
    await Promise.all([renderAdminProductList(), renderCustomerManagementList()]);
    const now = new Date();
    document.getElementById('exportMonth').value = now.getMonth() + 1;
    document.getElementById('exportYear').value = now.getFullYear();
    handleInventorySubTabSwitch('manageStock');
}

export function initAdmin() {
    document.querySelectorAll('.admin-tab-btn').forEach(btn => btn.addEventListener('click', () => handleAdminTabSwitch(btn.id.replace('AdminBtn', ''))));
    document.querySelectorAll('.inventory-sub-tab-btn').forEach(btn => btn.addEventListener('click', () => handleInventorySubTabSwitch(btn.dataset.tab)));
    document.getElementById('adminLocFilter').addEventListener('input', () => { adminCurrentPage = 1; renderAdminProductList(); });
    
    document.getElementById('addCustomerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const newCustomerNameInput = document.getElementById('newCustomerName');
        const name = newCustomerNameInput.value.trim();
        if (!name) return;
        const result = await postData('add_customer', { name });
        if (result.success) {
            appState.customers.push({ id: result.id, name: name, is_priority: 0 });
            appState.customers.sort((a, b) => a.name.localeCompare(b.name));
            renderCustomerManagementList();
            newCustomerNameInput.value = '';
            showMessage('Customer added successfully.');
        }
    });

    document.getElementById('customerManagementList').addEventListener('click', (e) => {
        if (e.target.classList.contains('delete-customer-btn')) {
            const customerId = e.target.dataset.id;
            const customerName = e.target.dataset.name;
            showConfirmation(`Delete customer "${customerName}"?`, async () => {
                const result = await postData('delete_customer', { id: customerId });
                if (result.success) {
                    appState.customers = appState.customers.filter(c => c.id != customerId);
                    renderCustomerManagementList();
                    showMessage('Customer deleted.');
                }
            });
        }
    });

    document.getElementById('customerManagementList').addEventListener('change', async (e) => {
        if (e.target.classList.contains('priority-toggle')) {
            const customerId = e.target.dataset.id;
            const isChecked = e.target.checked;
            const customer = appState.customers.find(c => c.id == customerId);
            if (!customer) return;
            const result = await postData('toggle_customer_priority', { id: customerId, is_priority: isChecked ? 1 : 0 });
            if (result.success) {
                customer.is_priority = isChecked ? 1 : 0;
                showMessage(`${customer.name} priority status updated.`);
                renderCustomerManagementList();
            } else {
                e.target.checked = !isChecked;
                showMessage(`Failed to update status.`, true);
            }
        }
    });
    
    document.getElementById('processBulkAddProductsBtn').addEventListener('click', async () => {
        const bulkInput = document.getElementById('bulkAddProductsInput');
        if (!bulkInput.value.trim()) return showMessage('Bulk data is empty.', true);
        showLoader();
        const result = await postData('bulk_add_products', { data: bulkInput.value.trim() });
        hideLoader();
        if (result.success) { await loadAllDataCallback(); bulkInput.value = ''; showMessage(result.message); }
    });

    document.getElementById('processBulkUpdateStockBtn').addEventListener('click', async () => {
        const bulkInput = document.getElementById('bulkUpdateStockInput');
        const location = document.getElementById('adminLocFilter').value;
        if (!bulkInput.value.trim()) return showMessage('Bulk data is empty.', true);
        showLoader();
        const result = await postData('bulk_update_stock', { data: bulkInput.value.trim(), location: location }); 
        hideLoader();
        if (result.success) { await loadAllDataCallback(); bulkInput.value = ''; showMessage(result.message); }
    });

    document.getElementById('processBulkAddAliasBtn').addEventListener('click', async () => {
        const bulkInput = document.getElementById('bulkAddAliasInput');
        if (!bulkInput.value.trim()) return showMessage('Bulk alias data is empty.', true);
        showLoader();
        const result = await postData('bulk_add_aliases', { data: bulkInput.value.trim() });
        hideLoader();
        if (result.success) { await loadAllDataCallback(); bulkInput.value = ''; showMessage(result.message); }
    });

    document.getElementById('adminProductList').addEventListener('click', (e) => {
        if (e.target.classList.contains('admin-delete-btn')) {
            const sku = e.target.dataset.sku;
            showConfirmation(`Delete code ${sku}? This will delete it from ALL locations.`, async () => {
                if ((await postData('delete_code', { code: sku })).success) {
                    await loadAllDataCallback();
                    showMessage(`Code ${sku} deleted.`);
                }
            });
        }
    });

    document.getElementById('runUnlinkedSkuReportBtn').addEventListener('click', async () => {
        showLoader();
        const resultsContainer = document.getElementById('unlinkedSkuResults');
        const resultsList = document.getElementById('unlinkedSkuList');
        const selectedLoc = document.getElementById('adminLocFilter').value;
        const result = await postData('get_unlinked_skus', { location: selectedLoc });
        hideLoader();
        if (result.success && Array.isArray(result.data)) {
            resultsList.innerHTML = result.data.length > 0 ? result.data.map(item => `
                <tr class="text-sm">
                    <td class="px-4 py-2 font-mono">${item.sku}</td>
                    <td class="px-4 py-2">${item.description}</td>
                    <td class="px-4 py-2 font-medium text-slate-700">${item.current_stock ?? 0}</td>
                </tr>
            `).join('') : '<tr><td colspan="3" class="text-center py-4 text-slate-500">Good job! No unlinked SKUs found.</td></tr>';
            resultsContainer.classList.remove('hidden');
        }
    });

    document.getElementById('exportCsvBtn').addEventListener('click', () => exportData('csv'));
    document.getElementById('exportTsvBtn').addEventListener('click', () => exportData('tsv'));

    document.getElementById('invPrevBtn').addEventListener('click', () => { if (adminCurrentPage > 1) { adminCurrentPage--; renderAdminProductList(); } });
    document.getElementById('invNextBtn').addEventListener('click', () => { adminCurrentPage++; renderAdminProductList(); });
