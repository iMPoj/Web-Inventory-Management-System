 import { appState } from './state.js';
import { fetchData } from './api.js';

let fulfillableItems = [];

function renderFulfillablePage() {
    const list = document.getElementById('fulfillableList');
    if (!list) return;

    const locFilter = document.getElementById('ffLocFilter').value;
    const customerFilter = document.getElementById('ffCustomerFilter').value;

    let filtered = fulfillableItems.filter(item => {
        const locMatch = locFilter === 'all' || item.location === locFilter;
        const customerMatch = customerFilter === 'all' || item.customer_name === customerFilter;
        return locMatch && customerMatch;
    });

    const grandTotal = filtered.reduce((sum, item) => {
        const product = appState.products[item.sku];
        const pricePerPiece = (product?.sales_price || 0);
        const itemValue = pricePerPiece * item.quantity;
        return sum + itemValue;
    }, 0);
    document.getElementById('fulfillableGrandTotal').textContent = grandTotal.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });

    if (filtered.length === 0) {
        list.innerHTML = `<tr><td colspan="6" class="!text-center py-8 text-slate-500">🎉 No fulfillable items found.</td></tr>`;
        return;
    }

    list.innerHTML = filtered.map(item => {
        const stockUpdateDate = item.stock_update_date ? new Date(item.stock_update_date.replace(' ', 'T')).toLocaleDateString() : 'N/A';
        
        let availableSkusHtml = '<span class="text-xs text-slate-400">N/A</span>';
        if (item.available_skus_with_stock) {
            availableSkusHtml = item.available_skus_with_stock.split(',').map(s => {
                const [sku, stock] = s.split(':');
                return `<div class="flex justify-end md:justify-start items-center gap-2">
                            <span class="font-mono text-indigo-600 text-xs">${sku}</span>
                            <span class="font-semibold text-green-600 text-xs py-0.5 px-2 bg-green-100 rounded-full">${parseInt(stock).toLocaleString()} pcs</span>
                        </div>`;
            }).join('');
        }
        
        const actionButtonHtml = (window.userRole === 'admin' || window.userRole === 'encoder') ? `<a href="view_order.php?id=${item.order_id}&context=fulfillable" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">Fulfill</a>` : '';

        return `
            <tr>
                <td data-label="Customer / PO">
                    <div class="font-bold text-slate-800">${item.customer_name}</div>
                    <div class="text-xs text-slate-500">PO: ${item.po_number}</div>
                </td>
                <td data-label="Unserved Item">
                    <div>${item.description}</div>
                    <div class="font-mono text-xs text-slate-500">${item.sku}</div>
                </td>
                <td data-label="Qty Needed" class="font-bold text-red-600 text-center">${parseInt(item.quantity).toLocaleString()}</td>
                <td data-label="Available Stock">${availableSkusHtml}</td>
                <td data-label="Last Stock Update" class="text-slate-500 text-sm">${stockUpdateDate}</td>
                <td data-label="Action" class="text-right">${actionButtonHtml}</td>
            </tr>
        `;
    }).join('');
}

export function populateFulfillableFilters() {
    const customerFilter = document.getElementById('ffCustomerFilter');
    if (!customerFilter) return;
    const uniqueCustomers = ['all', ...new Set(fulfillableItems.map(item => item.customer_name))];
    customerFilter.innerHTML = uniqueCustomers.map(name => 
        `<option value="${name}">${name === 'all' ? 'All Priority Customers' : name}</option>`
    ).join('');
}

export async function initializeFulfillablePage() {
    const result = await fetchData('get_fulfillable_items');
    if (result.success && Array.isArray(result.data)) {
        fulfillableItems = result.data;
        populateFulfillableFilters();
        renderFulfillablePage();
    }
}

export function initFulfillablePage() {
    ['ffLocFilter', 'ffCustomerFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', renderFulfillablePage);
    });
