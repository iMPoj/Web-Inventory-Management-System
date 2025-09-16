 <div id="encoderPage" class="hidden">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4 border-b pb-2">Customer & Order Details</h2>
                
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="orderLocation" class="block text-sm font-medium text-slate-700">Order Location (Warehouse)</label>
                        <select id="orderLocation" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm bg-slate-50 font-medium">
                            <option value="">-- Select a Location First --</option>
                            <option value="Davao">Davao</option>
                            <option value="Gensan">Gensan</option>
                        </select>
                    </div>
                    <div>
                        <label for="orderBu" class="block text-sm font-medium text-slate-700">Business Unit</label>
                        <select id="orderBu" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm bg-slate-50 font-medium">
                            <option value="">-- Select a BU --</option>
                            <option value="Health">Health</option>
                            <option value="Hygiene">Hygiene</option>
                            <option value="Nutri">Nutri</option>
                        </select>
                    </div>
                
                    <div>
                        <label for="customerName" class="block text-sm font-medium text-slate-700">Customer Name</label>
                        <div class="relative">
                            <input type="text" id="customerName" placeholder="Type to search..." class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <div id="customerSuggestions" class="absolute z-30 w-full bg-white border border-slate-300 rounded-md mt-1 max-h-60 overflow-y-auto hidden"></div>
                        </div>
                    </div>
                    <div>
                        <label for="discountPercentage" class="block text-sm font-medium text-slate-700">Discount (%)</label>
                        <input type="number" id="discountPercentage" placeholder="e.g., 5" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="customerAddress" class="block text-sm font-medium text-slate-700">Customer Address</label>
                        <input type="text" id="customerAddress" placeholder="e.g., Padada" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="poNumber" class="block text-sm font-medium text-slate-700">PO Number</label>
                        <input type="text" id="poNumber" placeholder="Enter PO Number" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>
            </div>

            <fieldset id="itemEntryFieldset" disabled class="bg-white p-6 rounded-lg shadow-md relative">
                <div id="itemEntryOverlay" class="absolute inset-0 bg-slate-50 bg-opacity-50 z-10"></div>
                <h2 class="text-xl font-semibold mb-4 border-b pb-2">Add Order Items</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4 items-end">
                    <div class="sm:col-span-2 relative">
                        <label for="itemBarcode" class="block text-sm font-medium text-slate-700">Barcode / SKU</label>
                        <input type="text" id="itemBarcode" placeholder="Type to search Barcode or SKU..." class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <div id="barcodeSuggestions" class="absolute z-20 w-full bg-white border border-slate-300 rounded-md mt-1 max-h-60 overflow-y-auto hidden"></div>
                    </div>
                    <div class="sm:col-span-2 relative">
                        <label for="itemDescription" class="block text-sm font-medium text-slate-700">Description</label>
                        <input type="text" id="itemDescription" placeholder="Type to search by description..." class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <div id="descriptionSuggestions" class="absolute z-20 w-full bg-white border border-slate-300 rounded-md mt-1 max-h-60 overflow-y-auto hidden"></div>
                    </div>
                    <div id="skuSelectionContainer" class="hidden sm:col-span-2">
                        <label for="itemSkuSelect" class="block text-sm font-medium text-slate-700">Select SKU</label>
                        <div class="flex items-center space-x-2">
                            <select id="itemSkuSelect" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></select>
                            <span id="skuStockDisplay" class="mt-1 text-sm text-slate-600 font-medium whitespace-nowrap"></span>
                        </div>
                    </div>
                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                        <div><label for="itemQuantity" class="block text-sm font-medium text-slate-700">Quantity</label><input type="number" id="itemQuantity" placeholder="0" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></div>
                        <div><label for="itemUnit" class="block text-sm font-medium text-slate-700">Unit</label><select id="itemUnit" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><option value="pcs">Pcs</option><option value="case">Case</option></select></div>
                        <span id="caseInfoDisplay" class="text-xs text-slate-500 whitespace-nowrap self-center pb-2"></span>
                    </div>
                    <div class="sm:col-span-2"><label for="itemPrice" class="block text-sm font-medium text-slate-700">Total Price</label><input type="number" id="itemPrice" placeholder="0.00" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></div>
                    <div id="formActions" class="sm:col-span-2"><button id="addItemBtn" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Add Item</button></div>
                </div>
            </fieldset> 
        </div>

        <div class="md:col-span-1 space-y-6">
            <fieldset id="summaryFieldset" disabled class="bg-white p-6 rounded-lg shadow-md sticky top-8 relative">
                <div id="summaryOverlay" class="absolute inset-0 bg-slate-50 bg-opacity-50 z-10"></div>
                <h2 class="text-xl font-semibold mb-4">Order Summary</h2>
                <div class="mt-2 border rounded-lg overflow-x-auto max-h-80">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Description</th><th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Qty</th><th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">Actions</th></tr></thead>
                        <tbody id="orderItemsList" class="bg-white divide-y divide-slate-200"></tbody>
                    </table>
                </div>
                <div class="mt-4 border-t pt-4">
                    <div class="flex justify-between font-bold text-lg"><span class="text-slate-800">Total Price:</span><span id="orderTotalDisplay" class="text-slate-900">0.00</span></div>
                </div>
                <div class="mt-6 grid grid-cols-1 gap-2">
                    <button id="doneBtn" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Process Order</button>
                    <button id="cancelEditBtn" class="w-full bg-slate-500 text-white py-2 px-4 rounded-md hover:bg-slate-600 hidden">Cancel Edit</button>
                </div>
            </fieldset>
        </div>
    </div>
</div