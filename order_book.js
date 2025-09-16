 import { appState } from './state.js';
import { postData } from './api.js';
import { showLoader, hideLoader, showMessage } from './ui.js';

let currentPage = 1;
const ROWS_PER_PAGE = 50;

async function fetchOrderBookPage(page) {
    currentPage = page;
    showLoader();

    const filterData = {
        page: currentPage,
        limit: ROWS_PER_PAGE,
        location: document.getElementById('obLocFilter').value,
        customer: document.getElementById('obCustomerFilter').value,
        bu: document.getElementById('obBuFilter').value
    };

    try {
        const result = await postData('get_orders', filterData);
        if (result.success) {
            appState.processedOrders = result.data || [];
            appState.orderBookTotal = result.total_orders || 0;
            renderOrderBook();
        } else {
            showMessage(result.message || 'Failed to fetch orders.', true);
        }
    } catch (e) {
        showMessage('An error occurred while fetching orders.', true);
    } finally {
        hideLoader();
    }
}

export function renderOrderBook() {
    const list = document.getElementById('orderBookList');
    if (!list) return;

    const pageItems = appState.processedOrders;
    
    const grandTotal = pageItems.reduce((sum, order) => {
        return sum + order.items.reduce((itemSum, item) => itemSum + parseFloat(item.price), 0);
    }, 0);
    document.getElementById('orderBookGrandTotal').textContent = grandTotal.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });

    const totalPages = Math.ceil(appState.orderBookTotal / ROWS_PER_PAGE);
    
    if (pageItems.length === 0) {
        list.innerHTML = `<tr><td colspan="5" class="!text-center py-8 text-slate-500">No orders match filters.</td></tr>`;
    } else {
        list.innerHTML = pageItems.map(order => {
             const totalValue = order.items.reduce((sum, item) => sum + parseFloat(item.price), 0);
             return `
                <tr>
                    <td data-label="Customer / PO">
                        <div class="font-bold text-slate-800">${order.customer.name}</div>
                        <div class="text-xs text-slate-500">PO: ${order.customer.poNumber}</div>
                    </td>
                    <td data-label="Date" class="text-sm text-slate-600">${new Date(order.date.replace(' ', 'T')).toLocaleDateString()}</td>
                    <td data-label="Location / BU" class="text-sm text-slate-600">
                        <div>${order.location}</div>
                        <div class="text-xs">${order.bu}</div>
                    </td>
                    <td data-label="Total Value" class="font-semibold text-slate-800">${totalValue.toLocaleString('en-US', { style: 'currency', currency: 'PHP' })}</td>
                    <td data-label="Action" class="text-right">
                        <a href="view_order.php?id=${order.id}&context=orderBook" class="text-indigo-600 hover:text-indigo-900 font-medium text-sm">View</a>
                    </td>
                </tr>
            `;
        }).join('');
    }

    document.getElementById('obPageInfo').textContent = `Page ${currentPage} of ${totalPages || 1}`;
    document.getElementById('obPrevBtn').disabled = currentPage <= 1;
    document.getElementById('obNextBtn').disabled = currentPage >= totalPages;
}

export function populateOrderBookFilters() {
    const customerFilter = document.getElementById('obCustomerFilter');
    if (customerFilter) {
        customerFilter.innerHTML = '<option value="all">All Customers</option>' + 
            appState.customers.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
    }
}

export function initOrderBook() {
    ['obLocFilter', 'obBuFilter', 'obCustomerFilter'].forEach(id => {
        const el = document.getElementById(id);
        if(el) {
            el.addEventListener('input', () => fetchOrderBookPage(1));
        }
    });

    const prevBtn = document.getElementById('obPrevBtn');
    if(prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) fetchOrderBookPage(currentPage - 1);
        });
    }

    const nextBtn = document.getElementById('obNextBtn');
    if(nextBtn) {
        const totalPages = Math.ceil(appState.orderBookTotal / ROWS_PER_PAGE);
        if (currentPage < totalPages) {
            nextBtn.addEventListener('click', () => fetchOrderBookPage(currentPage + 1));
        }
    }
