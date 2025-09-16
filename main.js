 import { appState } from './state.js';
import { fetchData, postData } from './api.js';
import { showLoader, hideLoader, showMessage, showConfirmation } from './ui.js';
import { initDashboard, renderDashboard, populateDashboardFilters } from './dashboard.js';
import { initStocksDashboard, renderStocksDashboard } from './stocks_dashboard.js';
import { initEncoder, resetEncoderState, setupEncoderForEditMode, setEncoderCallbacks } from './encoder.js';
import { initAdmin, initializeAdminPage, setLoadAllDataCallback } from './admin.js';
import { initOrderBook, renderOrderBook, populateOrderBookFilters } from './order_book.js';
import { initUnservedPage, renderUnservedPage, populateUnservedFilters } from './unserved.js';
import { initFulfillablePage, initializeFulfillablePage, populateFulfillableFilters } from './fulfillable.js';
import { initPdfProcessor, processNextPdfInQueue, saveReviewedOrderAndProceed } from './pdf_processor.js';
import { initPdfReviewPage } from './pdf_review.js';

function setActiveTab(activeTabId) {
    appState.activeTab = activeTabId;
    const tabs = {
        'dashboard': document.getElementById('dashboardTabBtn'), 
        'stocksDashboard': document.getElementById('stocksDashboardTabBtn'),
        'encoder': document.getElementById('encoderTabBtn'), 
        'orderBook': document.getElementById('orderBookTabBtn'),
        'unserved': document.getElementById('unservedTabBtn'),
        'fulfillable': document.getElementById('fulfillableTabBtn'),
        'admin': document.getElementById('adminTabBtn'),
        'pdf_review': document.getElementById('pdfReviewTabBtn')
    };
    const pages = {
        'dashboard': document.getElementById('dashboardPage'), 
        'stocksDashboard': document.getElementById('stocksDashboardPage'),
        'encoder': document.getElementById('encoderPage'), 
        'orderBook': document.getElementById('orderBookPage'),
        'unserved': document.getElementById('unservedPage'),
        'fulfillable': document.getElementById('fulfillablePage'),
        'admin': document.getElementById('adminPage'),
        'pdf_review': document.getElementById('pdfReviewPage')
    };
    
    for (const id in tabs) {
        if (tabs[id]) { 
            tabs[id].classList.toggle('active', id === activeTabId);
            if (pages[id]) pages[id].classList.toggle('hidden', id !== activeTabId);
        }
    }

    if (activeTabId !== 'encoder' && !appState.isPdfWorkflowActive) {
        resetEncoderState();
    }
    
    if (window.innerWidth < 768) {
        document.body.classList.remove('sidebar-open');
    }

    if (activeTabId === 'dashboard') renderDashboard();
    if (activeTabId === 'stocksDashboard') renderStocksDashboard();
    if (activeTabId === 'orderBook') renderOrderBook();
    if (activeTabId === 'unserved') renderUnservedPage();
    if (activeTabId === 'fulfillable') initializeFulfillablePage();
}

async function loadAllData() {
    showLoader();
    try {
        const [productsResponse, customersData, ordersResponse] = await Promise.all([
            fetchData('get_products'),
            fetchData('get_customers'),
            postData('get_orders', { page: 1, limit: 50 })
        ]);
        
        appState.products = {};
        if (productsResponse && Array.isArray(productsResponse.data)) {
            productsResponse.data.forEach(p => {
                p.codes.forEach(s => {
                    appState.products[s.code] = {
                        productId: p.id, description: p.description, bu: p.bu, 
                        inventory: s.inventory || [], sku: s.code, type: s.type, 
                        sales_price: parseFloat(s.sales_price) || 0.00,
                        pieces_per_case: parseInt(s.pieces_per_case) || 1, 
                        is_promo: p.is_promo, allSkus: p.codes 
                    };
                });
            });
        }
        appState.customers = Array.isArray(customersData) ? customersData : [];
        appState.processedOrders = (ordersResponse && Array.isArray(ordersResponse.data)) ? ordersResponse.data : [];
        appState.orderBookTotal = ordersResponse.total_orders || 0;
        
        populateDashboardFilters();
        populateOrderBookFilters();
        populateUnservedFilters();
        populateFulfillableFilters();
        setActiveTab(appState.activeTab);

    } catch (e) {
        console.error("A critical error occurred:", e);
        showMessage("Failed to load page data.", true);
    } finally {
        hideLoader();
    }
}

function initApp() {
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    const closeBtn = document.getElementById('sidebar-close-btn');
    const overlay = document.getElementById('sidebar-overlay');
    
    const setSidebarOpen = (isOpen) => { document.body.classList.toggle('sidebar-open', isOpen); };
    toggleBtn?.addEventListener('click', () => setSidebarOpen(true));
    closeBtn?.addEventListener('click', () => setSidebarOpen(false));
    overlay?.addEventListener('click', () => setSidebarOpen(false));

    setLoadAllDataCallback(loadAllData);
    setEncoderCallbacks({ 
        onSubmit: async (location) => {
            const orderData = {
                customer_id: appState.selectedCustomer ? appState.selectedCustomer.id : null,
                customer_address: document.getElementById('customerAddress').value,
                po_number: document.getElementById('poNumber').value,
                items: JSON.stringify(appState.orderItems),
                location: location,
                bu: document.getElementById('orderBu').value,
                discount: document.getElementById('discountPercentage').value
            };
            const result = await postData('add_order', orderData);
            if (result.success) {
                showMessage(`Order successfully submitted with ID: ${result.order_id}`);
                await loadAllData();
                resetEncoderState();
            } else { showMessage(result.message || 'Failed to process order.', true); }
        }
    });

    initDashboard();
    initStocksDashboard();
    initEncoder();
    initAdmin();
    initOrderBook();
    initUnservedPage();
    initFulfillablePage();
    initPdfProcessor({
        onProcessingFinished: async () => {
            await loadAllData();
            setActiveTab('orderBook');
        },
        setActiveTab: setActiveTab
    });
    initPdfReviewPage({
        onSave: saveReviewedOrderAndProceed,
        onCancel: () => {
            appState.isPdfWorkflowActive = false;
            setActiveTab('dashboard');
        }
    });

    document.getElementById('dashboardTabBtn')?.addEventListener('click', () => setActiveTab('dashboard'));
    document.getElementById('stocksDashboardTabBtn')?.addEventListener('click', () => setActiveTab('stocksDashboard'));
    document.getElementById('encoderTabBtn')?.addEventListener('click', () => setActiveTab('encoder'));
    document.getElementById('orderBookTabBtn')?.addEventListener('click', () => setActiveTab('orderBook'));
    document.getElementById('unservedTabBtn')?.addEventListener('click', () => setActiveTab('unserved'));
    document.getElementById('fulfillableTabBtn')?.addEventListener('click', () => setActiveTab('fulfillable'));
    document.getElementById('adminTabBtn')?.addEventListener('click', () => { setActiveTab('admin'); initializeAdminPage(); });
    
    document.getElementById('logoutBtn')?.addEventListener('click', async () => {
        showLoader();
        await postData('logout', {});
        window.location.href = 'login.php';
    });
}

window.addEventListener('DOMContentLoaded', async () => {
    initApp();
    await loadAllData();
    const hash = window.location.hash.substring(1);
    if (['dashboard', 'stocksDashboard', 'encoder', 'orderBook', 'unserved', 'admin', 'fulfillable'].includes(hash)) {
        setActiveTab(hash);
        window.location.hash = '';
    } else {
        setActiveTab('dashboard');
    }
})