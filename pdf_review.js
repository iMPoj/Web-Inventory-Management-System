 import { showConfirmation } from './ui.js';

let onSaveCallback = () => {};
let onCancelCallback = () => {};

export function getReviewedOrderData() {
    const items = [];
    document.querySelectorAll('#pdf_orderItemsList tr').forEach(row => {
        const qtyInput = row.querySelector('.pdf-item-qty');
        items.push({
            sku: row.dataset.sku,
            description: row.dataset.description,
            quantity: parseInt(qtyInput.value),
            price: parseFloat(row.dataset.price) // The price is now stored on the row
        });
    });

    const customerId = document.getElementById('pdf_customerName').dataset.customerId || null;

    return {
        customer_id: customerId,
        customer_address: document.getElementById('pdf_customerAddress').value,
        po_number: document.getElementById('pdf_poNumber').value,
        items: JSON.stringify(items),
        location: document.getElementById('pdf_location').value,
        bu: document.getElementById('pdf_bu').value,
        discount: document.getElementById('pdf_discount').value
    };
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('#pdf_orderItemsList tr').forEach(row => {
        const qty = parseInt(row.querySelector('.pdf-item-qty').value);
        const pricePerPiece = parseFloat(row.dataset.price) / parseInt(row.dataset.originalQty); // Calculate price per piece
        total += pricePerPiece * qty;
    });

    const discount = parseFloat(document.getElementById('pdf_discount').value) || 0;
    if (discount > 0) {
        total -= total * (discount / 100);
    }
    
    document.getElementById('pdf_orderTotalDisplay').textContent = total.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });
}

function renderItems(items) {
    const list = document.getElementById('pdf_orderItemsList');
    list.innerHTML = items.map((item, index) => `
        <tr class="text-sm" data-sku="${item.sku}" data-description="${item.description}" data-price="${item.price}" data-original-qty="${item.quantity}">
            <td class="px-4 py-2">
                <p class="font-medium text-slate-800">${item.description}</p>
                <p class="font-mono text-xs text-slate-500">${item.sku}</p>
            </td>
            <td class="px-4 py-2">
                <input type="number" value="${item.quantity}" data-index="${index}" class="pdf-item-qty w-20 rounded-md border-slate-300 shadow-sm text-sm">
            </td>
            <td class="px-4 py-2 text-right">
                <button class="pdf-delete-item-btn text-red-500 hover:text-red-700 text-sm" data-index="${index}">Delete</button>
            </td>
        </tr>
    `).join('');
}

export function populateReviewPage(data, queueStatus) {
    document.getElementById('pdf_location').value = data.location;
    document.getElementById('pdf_bu').value = data.bu;
    document.getElementById('pdf_customerName').value = data.customerName;
    document.getElementById('pdf_customerName').dataset.customerId = data.customerId || '';
    document.getElementById('pdf_customerAddress').value = data.customerAddress;
    document.getElementById('pdf_poNumber').value = data.poNumber;
    document.getElementById('pdfQueueStatus').textContent = `File ${queueStatus.current} of ${queueStatus.total}`;
    document.getElementById('pdf_discount').value = data.discount;
    
    renderItems(data.items);
    calculateTotal();
}

export function initPdfReviewPage(callbacks) {
    onSaveCallback = callbacks.onSave;
    onCancelCallback = callbacks.onCancel;

    document.getElementById('pdf_saveAndNextBtn').addEventListener('click', onSaveCallback);
    document.getElementById('pdf_cancelWorkflowBtn').addEventListener('click', () => {
        showConfirmation('Are you sure you want to cancel the PDF upload process?', onCancelCallback);
    });

    document.getElementById('pdf_orderItemsList').addEventListener('input', (e) => {
        if (e.target.classList.contains('pdf-item-qty')) {
            calculateTotal();
        }
    });

    document.getElementById('pdf_discount').addEventListener('input', calculateTotal);
    
    document.getElementById('pdf_orderItemsList').addEventListener('click', (e) => {
        if (e.target.classList.contains('pdf-delete-item-btn')) {
             const row = e.target.closest('tr');
             row.remove();
             calculateTotal();
        }
    });
