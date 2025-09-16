 import { appState } from './state.js';
import { showMessage, showConfirmation } from './ui.js';

let editingItemIndex = null;
let onSubmitOrderCallback = () => {};
let onUpdateOrderCallback = () => {};
let onCancelOrderCallback = () => {};
let selectedLocation = '';
let itemEntryFieldset, summaryFieldset, itemEntryOverlay, summaryOverlay;

export function setEncoderCallbacks({ onSubmit, onUpdate, onCancel }) {
    onSubmitOrderCallback = onSubmit;
    onUpdateOrderCallback = onUpdate;
    onCancelOrderCallback = onCancel;
}

function applyAutomaticDiscount(customerName) {
    const discountInput = document.getElementById('discountPercentage');
    const discountMap = {
        'Rose Pharmacy Incorporated': '5.6',
        'Rojon Pharmacy Corporation': '3'
    };
    if (discountMap[customerName]) {
        discountInput.value = discountMap[customerName];
    }
    calculateTotalPrice();
}

function setOrderLocation(location) {
    selectedLocation = location;
    document.getElementById('orderLocation').value = location;
    const isDisabled = !location;
    if (itemEntryFieldset) itemEntryFieldset.disabled = isDisabled;
    if (summaryFieldset) summaryFieldset.disabled = isDisabled;
    if (itemEntryOverlay) itemEntryOverlay.style.display = isDisabled ? 'block' : 'none';
    if (summaryOverlay) summaryOverlay.style.display = isDisabled ? 'block' : 'none';

    if (!isDisabled) {
        document.getElementById('orderBu').focus();
        localStorage.setItem('defaultLocation', location);
        if (appState.orderItems.length > 0) {
            appState.orderItems = [];
            updateOrderSummary();
        }
    }
}

function saveOrderToStorage() {
    if (appState.isPdfWorkflowActive) return; // Don't save drafts during PDF workflow
    const orderDraft = {
        orderLocation: selectedLocation,
        orderBu: document.getElementById('orderBu').value,
        customerName: document.getElementById('customerName').value,
        discount: document.getElementById('discountPercentage').value,
        address: document.getElementById('customerAddress').value,
        poNumber: document.getElementById('poNumber').value,
        items: appState.orderItems,
        selectedCustomer: appState.selectedCustomer
    };
    sessionStorage.setItem('currentOrderDraft', JSON.stringify(orderDraft));
}

function loadOrderFromStorage() {
    const draftJSON = sessionStorage.getItem('currentOrderDraft');
    if (!draftJSON) {
        setOrderLocation(localStorage.getItem('defaultLocation') || '');
        return;
    }
    try {
        const draft = JSON.parse(draftJSON);
        if (!draft) return;
        document.getElementById('customerName').value = draft.customerName || '';
        document.getElementById('discountPercentage').value = draft.discount || '';
        document.getElementById('customerAddress').value = draft.address || '';
        document.getElementById('poNumber').value = draft.poNumber || '';
        document.getElementById('orderBu').value = draft.orderBu || '';
        if (draft.orderLocation) setOrderLocation(draft.orderLocation);
        appState.orderItems = Array.isArray(draft.items) ? draft.items : [];
        appState.selectedCustomer = draft.selectedCustomer || null;
        updateOrderSummary();
    } catch (e) {
        console.error("Failed to load draft", e);
        sessionStorage.removeItem('currentOrderDraft');
    }
}

export function resetEncoderState() {
    appState.editingOrderId = null;
    appState.selectedCustomer = null;
    appState.orderItems = [];
    appState.isPdfWorkflowActive = false; // Reset the flag
    
    setOrderLocation('');
    document.getElementById('orderBu').value = '';
    document.getElementById('customerName').value = '';
    document.getElementById('discountPercentage').value = '';
    document.getElementById('customerAddress').value = '';
    document.getElementById('poNumber').value = '';
    
    stopEditingItem();
    updateOrderSummary();
    sessionStorage.removeItem('currentOrderDraft');
    setOrderLocation(localStorage.getItem('defaultLocation') || '');
}

export function setupEncoderForEditMode() {
    // This function will need review if PDF editing is required, but is fine for now.
    resetEncoderState(); 
    appState.editingOrderId = appState.editingOrderDetails.id;
    document.getElementById('customerName').value = appState.editingOrderDetails.customer.name;
    document.getElementById('customerAddress').value = appState.editingOrderDetails.customer.address;
    document.getElementById('poNumber').value = appState.editingOrderDetails.customer.poNumber;
    appState.selectedCustomer = appState.customers.find(c => c.name === appState.editingOrderDetails.customer.name) || null;
    
    if (appState.editingOrderDetails) {
         setOrderLocation(appState.editingOrderDetails.location);
         document.getElementById('orderBu').value = appState.editingOrderDetails.bu;
    }

    document.getElementById('doneBtn').textContent = 'Update Order';
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    updateOrderSummary();
}

function findAndPopulateProduct(code) {
    const productInfo = appState.products[code];
    if (productInfo) {
        document.getElementById('itemBarcode').value = code;
        document.getElementById('itemDescription').value = productInfo.description;
        document.getElementById('skuSelectionContainer').classList.remove('hidden');
        document.getElementById('itemSkuSelect').innerHTML = productInfo.allSkus
            .filter(s => s.type === 'sku')
            .map(s => `<option value="${s.code}">${s.code}</option>`).join('');
        
        const highestStockSku = productInfo.allSkus
            .filter(s => s.type === 'sku')
            .sort((a, b) => {
                const stockA = appState.products[a.code]?.inventory.find(i => i.location === selectedLocation)?.stock || 0;
                const stockB = appState.products[b.code]?.inventory.find(i => i.location === selectedLocation)?.stock || 0;
                return stockB - stockA;
            })[0];

        if(highestStockSku) document.getElementById('itemSkuSelect').value = highestStockSku.code;

        updateStockDisplay();
        document.getElementById('itemQuantity').value = 1;
        calculateTotalPrice();
        document.getElementById('itemQuantity').focus();
    }
}

function calculateTotalPrice() {
    const selectedSku = document.getElementById('itemSkuSelect').value;
    let quantity = parseInt(document.getElementById('itemQuantity').value);
    const unit = document.getElementById('itemUnit').value;
    const discount = parseFloat(document.getElementById('discountPercentage').value) || 0;
    if (selectedSku && quantity > 0) {
        const productInfo = appState.products[selectedSku];
        if (productInfo && productInfo.sales_price > 0) {
            if (unit === 'case') quantity *= productInfo.pieces_per_case;
            let totalPrice = productInfo.sales_price * quantity;
            if (discount > 0) totalPrice -= totalPrice * (discount / 100);
            document.getElementById('itemPrice').value = totalPrice.toFixed(2);
        } else { document.getElementById('itemPrice').value = '0.00'; }
    } else { document.getElementById('itemPrice').value = ''; }
}

function updateStockDisplay() {
    const productCode = document.getElementById('itemSkuSelect').value;
    if (!productCode) return;
    const productInfo = appState.products[productCode];
    if (productInfo) {
        const invEntry = productInfo.inventory.find(i => i.location === selectedLocation);
        const stock = invEntry ? parseInt(invEntry.stock) : 0;
        const stockEl = document.getElementById('skuStockDisplay');
        stockEl.textContent = `Stock: ${stock.toLocaleString('en-US')}`;
        document.getElementById('caseInfoDisplay').textContent = `(1 case = ${productInfo.pieces_per_case} pcs)`;
    }
}

function updateOrderSummary() {
    const list = document.getElementById('orderItemsList');
    list.innerHTML = appState.orderItems.map((item, index) => `
        <tr class="text-sm">
            <td class="px-4 py-2">${item.description} (${item.sku})</td>
            <td class="px-4 py-2">${item.quantity.toLocaleString('en-US')}</td>
            <td class="px-4 py-2 text-right">
                <button class="edit-btn text-blue-600 hover:text-blue-900 mr-2 text-sm font-medium" data-index="${index}">Edit</button>
                <button class="delete-btn text-red-600 hover:text-red-900 text-sm font-medium" data-index="${index}">Delete</button>
            </td>
        </tr>`).join('');
    const total = appState.orderItems.reduce((sum, item) => sum + (item.price || 0), 0);
    document.getElementById('orderTotalDisplay').textContent = total.toLocaleString('en-US', { style: 'currency', currency: 'PHP' });
    saveOrderToStorage();
}

function clearItemInputs() {
    document.getElementById('itemBarcode').value = '';
    document.getElementById('itemDescription').value = '';
    document.getElementById('itemQuantity').value = '';
    document.getElementById('itemPrice').value = '';
    document.getElementById('skuSelectionContainer').classList.add('hidden');
    document.getElementById('itemBarcode').focus();
}

function startEditingItem(index) {
    editingItemIndex = index;
    const item = appState.orderItems[index];
    findAndPopulateProduct(item.sku);
    document.getElementById('itemQuantity').value = item.quantity;
    calculateTotalPrice();
    document.getElementById('addItemBtn').textContent = 'Update Item';
}

function stopEditingItem() {
    editingItemIndex = null;
    clearItemInputs();
    document.getElementById('addItemBtn').textContent = 'Add Item';
}

function handleItemSubmit() {
    const selectedSku = document.getElementById('itemSkuSelect').value;
    if (!selectedSku) return showMessage('Please select a valid SKU.', true);
    
    const productInfo = appState.products[selectedSku];
    let quantity = parseInt(document.getElementById('itemQuantity').value);
    const price = parseFloat(document.getElementById('itemPrice').value);
    
    if (!productInfo || !quantity || isNaN(price) || quantity <= 0) return showMessage('Please enter a valid quantity and price.', true);
    
    const finalQuantity = document.getElementById('itemUnit').value === 'case' ? quantity * productInfo.pieces_per_case : quantity;
    const invEntry = productInfo.inventory.find(i => i.location === selectedLocation);
    const stock = invEntry ? parseInt(invEntry.stock) : 0;
    
    const newItem = { sku: selectedSku, description: productInfo.description, quantity: finalQuantity, price, status: stock >= finalQuantity ? 'served' : 'unserved' };

    if (stock < finalQuantity) showMessage(`Stock for ${newItem.description} is insufficient. Marked as unserved.`, true);
    
    if (editingItemIndex !== null) appState.orderItems[editingItemIndex] = newItem;
    else appState.orderItems.push(newItem);
    
    updateOrderSummary();
    stopEditingItem();
}

export function populateEncoderFromPdf(data) {
    resetEncoderState();
    setOrderLocation(data.businessUnit === 'Nutri' ? 'Gensan' : 'Davao'); // Auto-select location
    document.getElementById('orderBu').value = data.businessUnit || '';
    document.getElementById('customerName').value = data.customerName || '';
    document.getElementById('customerAddress').value = data.customerAddress || '';
    document.getElementById('poNumber').value = data.poNumber || '';

    const customer = appState.customers.find(c => c.name.toUpperCase().includes('ROJON'));
    if (customer) {
        appState.selectedCustomer = customer;
        applyAutomaticDiscount(customer.name);
    }

    data.items.forEach(item => {
        const productInfo = appState.products[item.code];
        if (productInfo) {
            const quantity = parseInt(item.quantity);
            let price = (parseFloat(productInfo.sales_price) || 0) * quantity;
            const discount = parseFloat(document.getElementById('discountPercentage').value) || 0;
            if (discount > 0) price -= price * (discount / 100);
            const invEntry = productInfo.inventory.find(i => i.location === selectedLocation);
            const stock = invEntry ? parseInt(invEntry.stock) : 0;
            appState.orderItems.push({ sku: item.code, description: productInfo.description, quantity, price, status: stock >= quantity ? 'served' : 'unserved' });
        } else {
            showMessage(`SKU/Barcode from PDF not found: ${item.code}. It will be skipped.`, true);
        }
    });
    updateOrderSummary();
}

export function initEncoder() {
    itemEntryFieldset = document.getElementById('itemEntryFieldset');
    summaryFieldset = document.getElementById('summaryFieldset');
    itemEntryOverlay = document.getElementById('itemEntryOverlay');
    summaryOverlay = document.getElementById('summaryOverlay');

    loadOrderFromStorage(); 

    document.getElementById('orderLocation').addEventListener('change', (e) => setOrderLocation(e.target.value));
    document.getElementById('addItemBtn').addEventListener('click', handleItemSubmit);
    document.getElementById('doneBtn').addEventListener('click', () => {
        if (appState.editingOrderId) showConfirmation("Save changes?", () => onUpdateOrderCallback(selectedLocation));
        else showConfirmation("Submit new order?", () => onSubmitOrderCallback(selectedLocation));
    });
    document.getElementById('cancelEditBtn').addEventListener('click', onCancelOrderCallback);

    document.getElementById('orderItemsList').addEventListener('click', (e) => {
        if (e.target.classList.contains('edit-btn')) startEditingItem(parseInt(e.target.dataset.index));
        if (e.target.classList.contains('delete-btn')) {
            const index = parseInt(e.target.dataset.index);
            showConfirmation(`Delete item?`, () => {
                appState.orderItems.splice(index, 1);
                updateOrderSummary();
                if (editingItemIndex === index) stopEditingItem();
            });
        }
    });

    const customerNameInput = document.getElementById('customerName');
    customerNameInput.addEventListener('input', () => {
        appState.selectedCustomer = null;
        document.getElementById('discountPercentage').value = '';
        const suggestions = document.getElementById('customerSuggestions');
        const inputText = customerNameInput.value.toLowerCase();
        if (!inputText) return suggestions.classList.add('hidden');
        const filtered = appState.customers.filter(c => c.name.toLowerCase().includes(inputText));
        suggestions.innerHTML = filtered.map(c => `<div class="p-2 hover:bg-rose-100 cursor-pointer">${c.name}</div>`).join('');
        suggestions.classList.toggle('hidden', filtered.length === 0);
    });
    
    document.getElementById('customerSuggestions').addEventListener('click', (e) => {
        if (e.target.tagName === 'DIV') {
            const name = e.target.textContent;
            const customer = appState.customers.find(c => c.name === name);
            if (customer) {
                appState.selectedCustomer = customer;
                customerNameInput.value = customer.name;
                applyAutomaticDiscount(customer.name);
            }
            document.getElementById('customerSuggestions').classList.add('hidden');
        }
    });

    const setupSuggestions = (inputId, suggestionsId, sourceFunction) => {
        const input = document.getElementById(inputId);
        const suggestionsBox = document.getElementById(suggestionsId);
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            if (!term || term.length < 2) return suggestionsBox.classList.add('hidden');
            const suggestions = sourceFunction(term);
            if (suggestions.length > 0) {
                suggestionsBox.innerHTML = suggestions.map(s => `<div class="p-2 hover:bg-indigo-100 cursor-pointer" data-code="${s.code}">${s.html}</div>`).join('');
                suggestionsBox.classList.remove('hidden');
            } else { suggestionsBox.classList.add('hidden'); }
        });
        suggestionsBox.addEventListener('click', (e) => {
            const suggestion = e.target.closest('div[data-code]');
            if (suggestion) {
                findAndPopulateProduct(suggestion.dataset.code);
                suggestionsBox.classList.add('hidden');
            }
        });
    };

    setupSuggestions('itemBarcode', 'barcodeSuggestions', term => 
        Object.values(appState.products)
            .filter(p => p.sku.toLowerCase().includes(term))
            .slice(0, 10)
            .map(p => ({ code: p.sku, html: `<strong class="text-indigo-600">${p.sku}</strong> - ${p.description}` }))
    );

    setupSuggestions('itemDescription', 'descriptionSuggestions', term =>
        Object.values(appState.products)
            .filter((p, i, self) => p.description.toLowerCase().includes(term) && i === self.findIndex(t => t.description === p.description))
            .slice(0, 10)
            .map(p => ({ code: p.sku, html: p.description }))
    );
    
    ['orderBu', 'customerName', 'discountPercentage', 'customerAddress', 'poNumber'].forEach(id => document.getElementById(id).addEventListener('input', saveOrderToStorage));
    ['itemQuantity', 'itemUnit', 'discountPercentage'].forEach(id => document.getElementById(id).addEventListener('input', calculateTotalPrice));
    document.getElementById('itemSkuSelect').addEventListener('change', () => { calculateTotalPrice(); updateStockDisplay(); });
