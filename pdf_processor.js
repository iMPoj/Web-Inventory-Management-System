 import { appState } from './state.js';
import { showLoader, hideLoader, showMessage } from './ui.js';
import { getReviewedOrderData, populateReviewPage } from './pdf_review.js';
import { postData } from './api.js';

let pdfQueue = [];
let totalFiles = 0;
let onProcessingFinishedCallback = () => {};
let setActiveTabCallback = () => {};

export async function saveReviewedOrderAndProceed() {
    const orderPayload = getReviewedOrderData();
    showLoader();
    const result = await postData('add_order', orderPayload);
    hideLoader();

    if (result.success) {
        showMessage(`Order for PO #${orderPayload.po_number} saved.`);
        await processNextPdfInQueue();
    } else {
        showMessage(result.message || 'Failed to save order.', true);
        appState.isPdfWorkflowActive = false; 
        onProcessingFinishedCallback();
    }
}

function extractValue(text, regex, defaultValue = null) {
    const match = text.match(regex);
    return match ? match[1].replace(/\s+/g, ' ').trim() : defaultValue;
}

function processExtractedText(text) {
    const buMap = { 'MEADJOHNSON': 'Nutri', 'LYSOL': 'Hygiene', 'O.T.C': 'Health' };
    
    const poNumber = extractValue(text, /PO No\.:\s*(\S+)/i);
    const customerName = "ROJON PHARMACY CORPORATION";
    const deliveryAddress = extractValue(text, /Delivery\/Ship To\s*([\s\S]*?)\s*(?:ΤΙΝ ΝΟ|Term Day)/i);
    const supplier = extractValue(text, /SURE-SPOT DIST\/(\w+)/i);
    const businessUnit = supplier ? buMap[supplier.toUpperCase()] : 'Health';

    if (!poNumber || !deliveryAddress) {
        throw new Error(`Could not find PO Number or Address. Check PDF format.`);
    }

    const items = [];
    const itemsTextMatch = text.match(/TOTAL\s+AMOUNT\s*([\s\S]*?)\s*(?:\*{6} Nothing Follows \*{6}|PO ITEM\(S\))/i);
    
    if (itemsTextMatch) {
        const cleanedItemText = itemsTextMatch[1].replace(/ BARCODE:/g, '\nBARCODE:').replace(/ VENDOR ITEM CODE:/g, '\nVENDOR ITEM CODE:');
        const lines = cleanedItemText.split(/\n/);
        
        let currentItem = {};

        for (const line of lines) {
            const trimmedLine = line.trim();
            if (!trimmedLine) continue;

            const codeMatch = trimmedLine.match(/(?:BARCODE|VENDOR ITEM CODE):\s*(\S+)/i);
            const qtyMatch = line.match(/\s+(\d+)\s+[0-9,.]+\s+[0-9,.]*\s+[0-9,.]*\s+[0-9,.]+\s*$/);

            if (codeMatch) {
                if (currentItem.code && currentItem.quantity) {
                    items.push(currentItem);
                }
                currentItem = { description: '', code: codeMatch[1] };
            } else {
                if (qtyMatch && !currentItem.quantity) {
                    currentItem.quantity = parseInt(qtyMatch[1], 10);
                } else if (!/^\d/.test(trimmedLine)) {
                    if (!currentItem.description) currentItem.description = '';
                    currentItem.description += ' ' + trimmedLine.split(/\s*(?:PC|SET|BX|PCK)\s*/)[0].trim();
                    currentItem.description = currentItem.description.trim();
                }
            }
        }
        if (currentItem.code && currentItem.quantity) {
            items.push(currentItem);
        }
    }

    if (items.length === 0) {
        throw new Error(`Could not find any valid items in the PDF.`);
    }
    
    const customer = appState.customers.find(c => c.name === "ROJON PHARMACY CORPORATION");
    const discount = (customer && customer.default_discount) ? customer.default_discount : '3.00';

    return {
        location: businessUnit === 'Nutri' ? 'Gensan' : 'Davao',
        bu: businessUnit,
        poNumber, customerName, customerId: customer ? customer.id : null,
        customerAddress: deliveryAddress.replace(/\n/g, ' ').trim(),
        discount, items
    };
}

export async function processNextPdfInQueue() {
    if (pdfQueue.length === 0) {
        appState.isPdfWorkflowActive = false;
        showMessage('All PDFs have been processed!');
        onProcessingFinishedCallback();
        return;
    }

    showLoader();
    const file = pdfQueue.shift();
    
    const reader = new FileReader();
    reader.onload = async (event) => {
        try {
            // This line points to the local worker file you uploaded.
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'js/pdf.worker.min.js';
            
            const pdf = await pdfjsLib.getDocument({ data: event.target.result }).promise;
            let allText = '';
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                allText += textContent.items.map(item => item.str).join(' ') + '\n';
            }
            
            const extractedData = processExtractedText(allText);
            const queueStatus = { current: totalFiles - pdfQueue.length, total: totalFiles };

            populateReviewPage(extractedData, queueStatus);
            setActiveTabCallback('pdf_review');
            
        } catch (error) {
            hideLoader();
            console.error('Error processing PDF:', error);
            showMessage(`Error in ${file.name}: ${error.message}`, true);
            appState.isPdfWorkflowActive = false;
            setActiveTabCallback('dashboard');
        } finally {
            hideLoader();
        }
    };
    reader.onerror = () => {
        hideLoader();
        showMessage(`Error reading file: ${file.name}`, true);
        appState.isPdfWorkflowActive = false;
    };
    reader.readAsArrayBuffer(file);
}

function showModal() {
    pdfQueue = [];
    updateFileListUI();
    document.getElementById('pdfUploadModal').classList.remove('hidden');
}
function hideModal() {
    document.getElementById('pdfUploadModal').classList.add('hidden');
}
function updateFileListUI() {
    const fileListEl = document.getElementById('pdfFileList');
    const processBtn = document.getElementById('startPdfProcessingBtn');
    fileListEl.innerHTML = pdfQueue.map(file => `<div class="p-2 bg-slate-100 rounded text-sm text-slate-700">${file.name}</div>`).join('');
    processBtn.disabled = pdfQueue.length === 0;
}
function handleFiles(files) {
    const pdfFiles = Array.from(files).filter(file => file.type === 'application/pdf');
    if (pdfFiles.length !== files.length) showMessage('Some non-PDF files were ignored.', true);
    pdfQueue.push(...pdfFiles);
    totalFiles = pdfQueue.length;
    updateFileListUI();
}

export function initPdfProcessor(callbacks) {
    onProcessingFinishedCallback = callbacks.onProcessingFinished;
    setActiveTabCallback = callbacks.setActiveTab;

    const openBtn = document.getElementById('pdfOrderTabBtn');
    const closeBtn = document.getElementById('closePdfModalBtn');
    const dropZone = document.getElementById('pdfDropZone');
    const fileInput = document.getElementById('pdfFileInput');
    const startBtn = document.getElementById('startPdfProcessingBtn');
    
    openBtn?.addEventListener('click', showModal);
    closeBtn?.addEventListener('click', hideModal);
    dropZone?.addEventListener('click', () => fileInput.click());
    fileInput?.addEventListener('change', () => { handleFiles(fileInput.files); fileInput.value = ''; });
    
    dropZone?.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('bg-indigo-100', 'border-indigo-600'); });
    dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('bg-indigo-100', 'border-indigo-600'));
    dropZone?.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('bg-indigo-100', 'border-indigo-600');
        handleFiles(e.dataTransfer.files);
    });

    startBtn?.addEventListener('click', () => {
        if(pdfQueue.length > 0) {
            appState.isPdfWorkflowActive = true;
            hideModal();
            processNextPdfInQueue();
        }
    });
