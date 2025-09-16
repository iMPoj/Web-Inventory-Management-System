 <div id="pdfReviewPage" class="hidden">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <div class="md:col-span-2 space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <div class="flex justify-between items-center border-b pb-2 mb-4">
                    <h2 class="text-xl font-semibold">Reviewing PDF Order</h2>
                    <div id="pdfQueueStatus" class="text-sm font-medium text-slate-500">File 1 of 1</div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="pdf_location" class="block text-sm font-medium text-slate-700">Location</label>
                        <select id="pdf_location" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm bg-slate-50 font-medium">
                            <option value="Davao">Davao</option>
                            <option value="Gensan">Gensan</option>
                        </select>
                    </div>
                    <div>
                        <label for="pdf_bu" class="block text-sm font-medium text-slate-700">Business Unit</label>
                        <select id="pdf_bu" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm bg-slate-50 font-medium">
                            <option value="Health">Health</option>
                            <option value="Hygiene">Hygiene</option>
                            <option value="Nutri">Nutri</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="pdf_customerName" class="block text-sm font-medium text-slate-700">Customer Name</label>
                        <input type="text" id="pdf_customerName" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                     <div>
                        <label for="pdf_discount" class="block text-sm font-medium text-slate-700">Discount (%)</label>
                        <input type="number" id="pdf_discount" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div>
                        <label for="pdf_poNumber" class="block text-sm font-medium text-slate-700">PO Number</label>
                        <input type="text" id="pdf_poNumber" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="pdf_customerAddress" class="block text-sm font-medium text-slate-700">Customer Address</label>
                        <input type="text" id="pdf_customerAddress" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-md sticky top-8">
                <h2 class="text-xl font-semibold mb-4">Order Summary</h2>
                <div class="mt-2 border rounded-lg overflow-x-auto max-h-96">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Item</th><th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Qty</th><th class="px-4 py-2 text-right text-xs font-medium text-slate-500 uppercase">Actions</th></tr></thead>
                        <tbody id="pdf_orderItemsList" class="bg-white divide-y divide-slate-200"></tbody>
                    </table>
                </div>
                <div class="mt-4 border-t pt-4">
                    <div class="flex justify-between font-bold text-lg"><span class="text-slate-800">Total Price:</span><span id="pdf_orderTotalDisplay" class="text-slate-900">0.00</span></div>
                </div>
                <div class="mt-6 grid grid-cols-1 gap-2">
                    <button id="pdf_saveAndNextBtn" class="w-full bg-emerald-600 text-white py-2 px-4 rounded-md hover:bg-emerald-700">Save & Next</button>
                    <button id="pdf_cancelWorkflowBtn" class="w-full bg-slate-500 text-white py-2 px-4 rounded-md hover:bg-slate-600">Cancel Workflow</button>
                </div>
            </div>
        </div>
    </div>
</div