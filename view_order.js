 import { showLoader, hideLoader, showMessage } from './ui.js';
import { postData } from './api.js';

let isEditMode = false;

function toggleEditMode(edit) {
    isEditMode = edit;

    document.querySelectorAll('.item-row').forEach(row => {
        // When entering edit mode, make ALL rows visible
        if (edit) {
            row.style.display = 'table-row';
        }
        row.querySelector('.sku-text-display').classList.toggle('hidden', edit);
        row.querySelector('.sku-select').classList.toggle('hidden', !edit);
        
        row.querySelectorAll('.quantity-input, .price-input, .status-toggle-btn').forEach(el => {
            el.disabled = !edit;
        });
    });

    document.getElementById('editOrderBtn').classList.toggle('hidden', edit);
    document.getElementById('saveChangesBtn').classList.toggle('hidden', !edit);
    document.getElementById('cancelChangesBtn').classList.toggle('hidden', !edit);
}

document.addEventListener('DOMContentLoaded', () => {
    // Populate SKU dropdowns for each item row
    document.querySelectorAll('.item-row').forEach((row) => {
        const originalSku = row.dataset.originalSku;
        const skuSelect = row.querySelector('.sku-select');
        const productInfo = window.productsBySku[originalSku];

        if (productInfo && productInfo.allSkus) {
            skuSelect.innerHTML = productInfo.allSkus.map(s => 
                `<option value="${s.code}" ${s.code === originalSku ? 'selected' : ''}>${s.code} (${s.type})</option>`
            ).join('');
        }
    });
    
    // Initialize and handle status toggle buttons
    document.querySelectorAll('.status-toggle-btn').forEach(btn => {
        const row = btn.closest('.item-row');
        let currentStatus = row.dataset.originalStatus;
        
        const updateButtonState = () => {
            btn.dataset.status = currentStatus; // Store status in data attribute
            if (currentStatus === 'served') {
                btn.textContent = 'Served';
                btn.classList.add('bg-green-100', 'text-green-800');
                btn.classList.remove('bg-red-100', 'text-red-800');
            } else {
                btn.textContent = 'Unserved';
                btn.classList.add('bg-red-100', 'text-red-800');
                btn.classList.remove('bg-green-100', 'text-green-800');
            }
        };

        btn.addEventListener('click', () => {
            if (!isEditMode) return;
            currentStatus = (currentStatus === 'served') ? 'unserved' : 'served';
            updateButtonState();
        });
        updateButtonState();
    });

    // --- NEW LOGIC: Filter visible rows based on context ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('context') === 'unserved') {
        document.querySelectorAll('.item-row').forEach(row => {
            if (row.dataset.originalStatus === 'served') {
                row.style.display = 'none';
            }
        });
    }

    // Event listeners for buttons
    document.getElementById('editOrderBtn').addEventListener('click', () => toggleEditMode(true));
    document.getElementById('cancelChangesBtn').addEventListener('click', () => window.location.reload());
    document.getElementById('saveChangesBtn').addEventListener('click', async () => {
        showLoader();
        const updatedItems = [];
        document.querySelectorAll('.item-row').forEach(row => {
            updatedItems.push({
                sku: row.querySelector('.sku-select').value,
                description: row.querySelector('.font-medium').textContent,
                quantity: row.querySelector('.quantity-input').value,
                price: row.querySelector('.price-input').value,
                status: row.querySelector('.status-toggle-btn').dataset.status
            });
        });

        const result = await postData('update_order_items', {
            order_id: window.orderId,
            location: window.orderLocation,
            items: JSON.stringify(updatedItems)
        });

        hideLoader();
        if (result.success) {
            showMessage(result.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
             showMessage(result.message || 'Failed to save changes.', true);
        }
    });
})