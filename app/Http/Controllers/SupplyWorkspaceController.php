<?php

namespace App\Http\Controllers;

use App\Models\Installation;
use App\Models\InstallationMaterialRequirement;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\PurchaseDocument;
use App\Models\ReplenishmentPolicy;
use App\Models\StockMovement;
use App\Models\StockMovementLine;
use App\Models\StockReservation;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Accounting\SupplierPaymentSourceResolver;
use App\Services\Billing\BillingWorkspace;
use App\Services\Documents\PurchaseInvoiceMatcher;
use App\Services\Inventory\StockPositionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplyWorkspaceController extends Controller
{
    public function dashboard(Request $request, StockPositionService $stock): Response
    {
        abort_unless(Gate::any(['manage-catalog', 'view-purchases']), 403);
        $request->validate(['period' => 'nullable|in:month,year']);
        $from = ($request->input('period', 'year') === 'month' ? now()->startOfMonth() : now()->subMonths(11)->startOfMonth());
        $catalog = Gate::allows('manage-catalog');
        $purchases = Gate::allows('view-purchases');
        $positions = $catalog ? $stock->positions() : collect();
        $policies = $catalog ? ReplenishmentPolicy::where('is_active', true)->get() : collect();
        $low = $policies->filter(function ($policy) use ($positions) {
            $position = $positions->first(fn ($row) => $row['warehouse']['id'] === $policy->warehouse_id && $row['product']['id'] === $policy->product_id);

            return ($position['available'] ?? 0) <= (float) $policy->reorder_point;
        })->count();
        $pending = $this->pendingOrders();
        $consumption = StockMovement::where('type', 'issue')->whereNotNull('installation_id')->where('posted_at', '>=', $from);
        $stockGroups = $positions->groupBy('product.id');
        $series = $catalog ? StockMovementLine::query()->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')
            ->where('posted_at', '>=', $from)->selectRaw('stock_movements.posted_at, stock_movement_lines.quantity')->get()
            ->groupBy(fn ($row) => substr($row->posted_at, 0, 7))->map(fn ($rows, $month) => ['month' => $month, 'incoming' => $rows->where('quantity', '>', 0)->count(), 'outgoing' => $rows->where('quantity', '<', 0)->count()])->values() : [];

        return Inertia::render('supply/dashboard', [
            'period' => $request->input('period', 'year'),
            'metrics' => [
                'in_stock' => $catalog ? $stockGroups->filter(fn ($rows) => $rows->sum('on_hand') > 0)->count() : null,
                'low_stock' => $catalog ? $low : null,
                'pending_orders' => $purchases ? (clone $pending)->count() : null,
                'expected_receipts' => $purchases ? (clone $pending)->whereRaw('coalesce(supplier_confirmed_delivery_date, expected_delivery_date) between ? and ?', [today()->toDateString(), today()->addDays(7)->toDateString()])->count() : null,
                'deliveries' => $catalog ? StockMovement::where('type', 'delivery')->where('posted_at', '>=', $from)->count() : null,
                'consumption' => $catalog ? $consumption->count() : null,
            ],
            'stockStates' => $catalog ? [
                ['name' => 'Con existencias', 'value' => $stockGroups->filter(fn ($rows) => $rows->sum('on_hand') > 0)->count()],
                ['name' => 'Sin existencias', 'value' => $stockGroups->filter(fn ($rows) => $rows->sum('on_hand') <= 0)->count()],
            ] : [],
            'materialTypes' => $catalog ? Product::where('track_stock', true)->selectRaw('type, count(*) as value')->groupBy('type')->get()->map(fn ($row) => ['name' => $row->type === 'service' ? 'Servicio' : 'Producto', 'value' => (int) $row->value]) : [],
            'series' => $series,
            'needs' => $catalog ? $this->requirements($request, 5) : null,
            'movements' => $catalog ? $this->movements()->limit(5)->get()->map(fn ($row) => $this->movement($row)) : [],
            'capabilities' => $this->capabilities(),
        ]);
    }

    public function products(Request $request): Response
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'type' => 'nullable|in:product,service', 'supplier' => 'nullable|integer|exists:suppliers,id', 'status' => 'nullable|in:active,inactive', 'page' => 'nullable|integer|min:1']);
        $stock = DB::table('stock_movement_lines')->selectRaw('product_id, sum(quantity) physical')->groupBy('product_id');
        $usable = DB::table('stock_movement_lines')->join('warehouses', 'warehouses.id', '=', 'stock_movement_lines.warehouse_id')->where('warehouses.is_usable', true)->selectRaw('product_id, sum(quantity) usable')->groupBy('product_id');
        $reserved = DB::table('stock_reservations')->where('status', 'active')->selectRaw('product_id, sum(quantity - consumed_quantity) reserved')->groupBy('product_id');
        $query = Product::with('unit:id,name,symbol')->leftJoinSub($stock, 'physical_stock', 'products.id', '=', 'physical_stock.product_id')
            ->leftJoinSub($usable, 'usable_stock', 'products.id', '=', 'usable_stock.product_id')->leftJoinSub($reserved, 'reserved_stock', 'products.id', '=', 'reserved_stock.product_id')
            ->select('products.*')->selectRaw('coalesce(physical, 0) physical, coalesce(usable, 0) usable, coalesce(reserved, 0) reserved')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['supplier'] ?? null, fn ($q, $id) => $q->whereHas('suppliers', fn ($q) => $q->where('supplier_id', $id)))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'));
        $products = $query->orderBy('name')->paginate(15)->withQueryString();
        $products->through(fn ($product) => $this->product($product));

        return Inertia::render('supply/products', ['products' => $products, 'filters' => $filters, 'suppliers' => Supplier::orderBy('legal_name')->get(['id', 'legal_name']), 'capabilities' => $this->capabilities(), 'editor' => $this->editor()]);
    }

    public function productShow(Product $product, StockPositionService $stock): Response
    {
        $product->load(['unit:id,name,symbol', 'suppliers.supplier:id,legal_name', 'components.product:id,name,sku']);
        $supplierRows = $product->suppliers->map(fn ($row) => [
            'id' => $row->supplier_id, 'name' => $row->supplier?->legal_name, 'sku' => $row->supplier_sku,
            'lead_days' => $row->lead_time_days, 'price' => Gate::allows('view-finance') ? $row->last_unit_price : null,
        ]);

        return Inertia::render('supply/product', [
            'product' => [...$this->product($product), 'description' => $product->description, 'track_serials' => $product->track_serials, 'lead_days' => $product->preferred_lead_time_days],
            'positions' => $stock->positions()->filter(fn ($row) => $row['product']['id'] === $product->id)->values(),
            'suppliers' => $supplierRows,
            'movements' => $this->movements()->whereHas('lines', fn ($q) => $q->where('product_id', $product->id))->paginate(15)->withQueryString()->through(fn ($row) => $this->movement($row)),
            'capabilities' => $this->capabilities(),
            'editor' => $this->editor(),
            'editableProduct' => Gate::allows('view-finance') ? $product->load('taxRate:id,name,rate', 'components.product.unit:id,name,symbol') : null,
        ]);
    }

    public function inventory(Request $request, StockPositionService $stock): Response
    {
        if ($request->filled('planning_request')) {
            return app(InventoryController::class)->index($request, $stock);
        }
        $filters = $request->validate(['view' => 'nullable|in:stock,needs,reservations,movements,receipts,deliveries,consumption,returns', 'search' => 'nullable|string|max:100', 'warehouse' => 'nullable|integer|exists:warehouses,id', 'installation' => 'nullable|integer|exists:installations,id', 'page' => 'nullable|integer|min:1']);
        $view = $filters['view'] ?? match ($request->route()->getName()) {
            'inventory.reservations.index' => 'reservations', 'inventory.movements.index' => 'movements', default => 'stock',
        };
        $positions = $stock->positions()->filter(fn ($row) => (! isset($filters['warehouse']) || $row['warehouse']['id'] === (int) $filters['warehouse']) && (! ($filters['search'] ?? '') || str_contains(mb_strtolower($row['product']['name'].' '.$row['product']['sku']), mb_strtolower($filters['search']))));
        $positionPage = new LengthAwarePaginator($positions->forPage((int) $request->input('page', 1), 15)->values(), $positions->count(), 15, (int) $request->input('page', 1), ['path' => $request->url(), 'query' => $request->query()]);
        $movements = $this->movements()->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%")))
            ->when($filters['warehouse'] ?? null, fn ($q, $id) => $q->whereHas('lines', fn ($q) => $q->where('warehouse_id', $id)))
            ->when($filters['installation'] ?? null, fn ($q, $id) => $q->where('installation_id', $id))
            ->when($view === 'receipts', fn ($q) => $q->where('type', 'receipt'))
            ->when($view === 'deliveries', fn ($q) => $q->where('type', 'delivery'))
            ->when($view === 'consumption', fn ($q) => $q->where('type', 'issue')->whereNotNull('installation_id'))
            ->when($view === 'returns', fn ($q) => $q->whereIn('type', ['purchase_return', 'internal_return']));
        $reservations = StockReservation::with(['product:id,name,sku', 'warehouse:id,name', 'installation:id,project_reference,site_name', 'workOrder:id,number'])
            ->when($filters['installation'] ?? null, fn ($q, $id) => $q->where('installation_id', $id))
            ->when($filters['warehouse'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")));

        return Inertia::render('supply/inventory', [
            'view' => $view, 'filters' => $filters,
            'needOptions' => $view === 'needs' ? ['products' => Product::where('is_active', true)->where('track_stock', true)->orderBy('name')->get(['id', 'name']), 'installations' => Installation::where('status', '!=', 'cancelled')->orderBy('project_reference')->get(['id', 'project_reference', 'site_name'])] : null, 'positions' => $view === 'stock' ? $positionPage : null,
            'needs' => $view === 'needs' ? $this->requirements($request) : null,
            'reservations' => $view === 'reservations' ? $reservations->latest()->paginate(15)->withQueryString() : null,
            'movements' => in_array($view, ['movements', 'receipts', 'deliveries', 'consumption', 'returns']) ? $movements->paginate(15)->withQueryString()->through(fn ($row) => $this->movement($row)) : null,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']), 'capabilities' => $this->capabilities(),
        ]);
    }

    public function purchases(Request $request): Response
    {
        if ($request->filled('attention') || $request->filled('planning_request') || $request->input('view') === 'manage' || ($request->filled('type') && $request->input('type') !== 'purchase_order')) {
            Gate::authorize('view-finance');

            return app(PurchaseController::class)->index($request);
        }
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|string|max:40', 'supplier' => 'nullable|integer|exists:suppliers,id', 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $query = PurchaseDocument::where('type', 'purchase_order')->with('supplier:id,legal_name')->withCount(['lines', 'lines as pending_lines_count' => fn ($q) => $q->whereColumn('received_quantity', '<', 'quantity')->whereHas('product', fn ($q) => $q->where('track_stock', true))])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhereHas('supplier', fn ($q) => $q->where('legal_name', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['supplier'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('issue_date', '<=', $date));
        $orders = $query->latest('issue_date')->latest('id')->paginate(15)->withQueryString()->through(fn ($row) => [
            ...$row->only(['id', 'number', 'status', 'issue_date', 'expected_delivery_date', 'supplier_confirmed_delivery_date', 'supplier', 'lines_count', 'pending_lines_count']),
            'amount' => Gate::allows('view-finance') ? $row->amount : null,
        ]);

        return Inertia::render('supply/purchases', [
            'orders' => $orders, 'filters' => $filters,
            'attention' => ['late' => PurchaseDocument::where('type', 'purchase_order')->whereIn('status', ['ordered', 'partially_received'])->whereRaw('coalesce(supplier_confirmed_delivery_date, expected_delivery_date) < ?', [today()->toDateString()])->count()],
            'statuses' => PurchaseDocument::where('type', 'purchase_order')->distinct()->orderBy('status')->pluck('status'),
            'suppliers' => Supplier::where('is_active', true)->orderBy('legal_name')->get(['id', 'legal_name', 'trade_name']),
            'products' => Gate::allows('view-finance') && Gate::allows('manage-purchases') ? Product::where('is_active', true)->with('taxRate:id,rate')->orderBy('name')->get(['id', 'sku', 'name', 'description', 'cost_price', 'tax_rate_id', 'track_stock', 'track_serials']) : [],
            'capabilities' => $this->capabilities(),
        ]);
    }

    public function purchaseShow(Request $request, PurchaseDocument $purchaseDocument): Response
    {
        if (in_array($purchaseDocument->type, ['supplier_invoice', 'supplier_credit_note'], true) && $request->input('view') !== 'manage') {
            return app(BillingWorkspaceController::class)->supplierShow($request, 'purchase', $purchaseDocument->id, app(BillingWorkspace::class));
        }

        if ($purchaseDocument->type !== 'purchase_order' || $request->input('view') === 'manage') {
            Gate::authorize('view-finance');

            return app(PurchaseController::class)->show($purchaseDocument, app(PurchaseInvoiceMatcher::class), app(SupplierPaymentSourceResolver::class));
        }
        $purchaseDocument->load(['supplier:id,legal_name,email,phone,address_line_1,postal_code,city', 'destinationWarehouse:id,name,address,city', 'lines.product:id,name,sku,track_stock,track_serials,unit_id', 'lines.product.unit:id,symbol', 'receipts.movement.lines.product:id,name,sku,unit_id', 'receipts.movement.lines.product.unit:id,symbol', 'receipts.movement.lines.warehouse:id,name', 'events.user:id,name', 'derivedDocuments:id,source_document_id,number,type,status']);
        $lines = $purchaseDocument->lines->map(fn ($line) => [
            ...$line->only(['id', 'product_id', 'description', 'quantity', 'received_quantity', 'product']),
            'unit_price' => Gate::allows('view-finance') ? $line->unit_price : null,
            'total' => Gate::allows('view-finance') ? $line->total : null,
        ]);

        return Inertia::render('supply/order', [
            'order' => [...$purchaseDocument->only(['id', 'number', 'status', 'issue_date', 'expected_delivery_date', 'supplier_confirmed_delivery_date', 'notes']),
                'supplier' => ['id' => $purchaseDocument->supplier_id, ...Arr::only($purchaseDocument->supplierIdentity(), ['legal_name', 'trade_name', 'tax_id', 'email', 'phone', 'address_line_1', 'postal_code', 'city', 'country', 'payment_terms_days'])], 'destination' => $purchaseDocument->destinationWarehouse,
                'subtotal' => Gate::allows('view-finance') ? $purchaseDocument->subtotal : null,
                'tax_amount' => Gate::allows('view-finance') ? $purchaseDocument->tax_amount : null,
                'amount' => Gate::allows('view-finance') ? $purchaseDocument->amount : null,
            ],
            'lines' => $lines,
            'receipts' => $purchaseDocument->receipts->map(fn ($row) => ['id' => $row->id, 'number' => $row->number, 'received_at' => $row->received_at, 'supplier_delivery_note' => $row->supplier_delivery_note, 'movement' => $this->movement($row->movement)]),
            'events' => $purchaseDocument->events->map(fn ($row) => $row->only(['id', 'event', 'created_at', 'user'])),
            'related' => $purchaseDocument->derivedDocuments,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'capabilities' => [...$this->capabilities(), 'receive' => Gate::allows('receive-purchases')],
        ]);
    }

    public function suppliers(Request $request): Response
    {
        $filters = $request->validate(['search' => 'nullable|string|max:100', 'status' => 'nullable|in:active,inactive']);
        $rows = Supplier::query()->select(['id', 'legal_name', 'trade_name', 'tax_id', 'email', 'phone', 'city', 'is_active'])
            ->withCount(['products', 'purchaseDocuments'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('legal_name', 'like', "%{$search}%")->orWhere('tax_id', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('is_active', $status === 'active'));

        return Inertia::render('supply/suppliers', ['suppliers' => $rows->orderBy('legal_name')->paginate(15)->withQueryString(), 'filters' => $filters, 'capabilities' => $this->capabilities()]);
    }

    public function supplierShow(Supplier $supplier): Response
    {
        $supplier->load(['contacts', 'products.product:id,name,sku', 'purchaseDocuments' => fn ($q) => $q->latest('issue_date')->limit(15)]);

        return Inertia::render('supply/supplier', [
            'supplier' => $supplier->only(['id', 'legal_name', 'trade_name', 'tax_id', 'email', 'phone', 'address_line_1', 'city', 'province', 'postal_code', 'country', 'payment_terms_days', 'is_active']),
            'editableSupplier' => Gate::allows('view-finance') ? $supplier->withoutRelations() : null,
            'contacts' => $supplier->contacts->map(fn ($row) => $row->only(['id', 'first_name', 'last_name', 'position', 'email', 'phone'])),
            'products' => $supplier->products->map(fn ($row) => ['id' => $row->id, 'product' => $row->product, 'supplier_sku' => $row->supplier_sku, 'lead_time_days' => $row->lead_time_days, 'price' => Gate::allows('view-finance') ? $row->last_unit_price : null]),
            'purchases' => Gate::allows('view-purchases') ? $supplier->purchaseDocuments->map(fn ($row) => [...$row->only(['id', 'number', 'type', 'status', 'issue_date']), 'amount' => Gate::allows('view-finance') ? $row->amount : null]) : [],
            'capabilities' => $this->capabilities(),
        ]);
    }

    private function editor(): ?array
    {
        if (! Gate::allows('view-finance')) {
            return null;
        }

        return [
            'units' => Unit::where('is_active', true)->orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderByDesc('is_default')->get(),
            'accountingAccounts' => LedgerAccount::where('is_active', true)->where('is_postable', true)->whereIn('type', ['income', 'expense'])->orderBy('code')->get(['code', 'name', 'type']),
            'catalogProducts' => Product::with(['unit', 'taxRate', 'components.product.unit'])->orderBy('name')->get(),
        ];
    }

    private function product(Product $product): array
    {
        return [...$product->only(['id', 'name', 'sku', 'type', 'unit', 'is_active', 'track_stock']),
            'physical' => (float) ($product->physical ?? 0), 'reserved' => (float) ($product->reserved ?? 0),
            'available' => (float) ($product->usable ?? 0) - (float) ($product->reserved ?? 0),
            'cost_price' => Gate::allows('view-finance') ? $product->cost_price : null,
            'sale_price' => Gate::allows('view-finance') ? $product->sale_price : null];
    }

    private function requirements(Request $request, int $perPage = 15)
    {
        $reserved = DB::table('stock_reservations')->where('status', 'active')->selectRaw('installation_id, product_id, sum(quantity - consumed_quantity) reserved')->groupBy('installation_id', 'product_id');
        $consumed = DB::table('stock_movement_lines')->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_lines.stock_movement_id')->where('stock_movements.type', 'issue')->selectRaw('installation_id, product_id, -sum(quantity) consumed')->groupBy('installation_id', 'product_id');

        $page = InstallationMaterialRequirement::with(['product:id,name,sku,unit_id', 'product.unit:id,symbol', 'installation:id,project_reference,site_name,scheduled_date'])
            ->leftJoinSub($reserved, 'commitments', fn ($join) => $join->on('installation_material_requirements.installation_id', '=', 'commitments.installation_id')->on('installation_material_requirements.product_id', '=', 'commitments.product_id'))
            ->leftJoinSub($consumed, 'usage', fn ($join) => $join->on('installation_material_requirements.installation_id', '=', 'usage.installation_id')->on('installation_material_requirements.product_id', '=', 'usage.product_id'))
            ->select('installation_material_requirements.installation_id', 'installation_material_requirements.product_id')
            ->selectRaw('min(installation_material_requirements.id) id, min(installation_material_requirements.description) description, sum(planned_quantity) planned_quantity, coalesce(max(reserved), 0) reserved, coalesce(max(consumed), 0) consumed')
            ->groupBy('installation_material_requirements.installation_id', 'installation_material_requirements.product_id')
            ->when($request->input('installation'), fn ($q, $id) => $q->where('installation_material_requirements.installation_id', $id))
            ->when($request->input('search'), fn ($q, $search) => $q->where(fn ($q) => $q->where('installation_material_requirements.description', 'like', "%{$search}%")->orWhereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))->orWhereHas('installation', fn ($q) => $q->where('project_reference', 'like', "%{$search}%"))))
            ->orderBy('id')->paginate($perPage)->withQueryString();
        $ids = $page->getCollection()->pluck('product_id')->filter()->unique();
        $physical = DB::table('stock_movement_lines')->join('warehouses', 'warehouses.id', '=', 'stock_movement_lines.warehouse_id')->where('warehouses.is_usable', true)->whereIn('product_id', $ids)->selectRaw('product_id, sum(quantity) total')->groupBy('product_id')->pluck('total', 'product_id');
        $committed = DB::table('stock_reservations')->where('status', 'active')->whereIn('product_id', $ids)->selectRaw('product_id, sum(quantity - consumed_quantity) total')->groupBy('product_id')->pluck('total', 'product_id');
        $page->getCollection()->each(function ($need) use ($physical, $committed) {
            $available = max(0, (float) ($physical[$need->product_id] ?? 0) - (float) ($committed[$need->product_id] ?? 0));
            $need->setAttribute('available', round($available, 3));
            $need->setAttribute('shortage', round(max(0, (float) $need->planned_quantity - (float) $need->reserved - (float) $need->consumed - $available), 3));
        });

        return $page;

    }

    private function movements()
    {
        return StockMovement::with(['recipient:id,name', 'sourceMovement:id,number', 'creator:id,name', 'installation:id,project_reference,site_name', 'workOrder:id,number', 'purchaseDocument:id,number', 'lines.product:id,name,sku,unit_id', 'lines.product.unit:id,symbol', 'lines.warehouse:id,name'])->latest('posted_at')->latest('id');
    }

    private function movement(StockMovement $row): array
    {
        return [...$row->only(['id', 'number', 'type', 'reference', 'posted_at', 'material_condition', 'recipient', 'sourceMovement', 'creator', 'installation', 'workOrder', 'purchaseDocument']),
            'lines' => $row->lines->map(fn ($line) => [...$line->only(['id', 'quantity', 'product', 'warehouse']), 'unit_cost' => Gate::allows('view-finance') ? $line->unit_cost : null])];
    }

    private function pendingOrders()
    {
        return PurchaseDocument::where('type', 'purchase_order')->whereIn('status', ['ordered', 'partially_received'])->whereHas('lines', fn ($q) => $q->whereColumn('received_quantity', '<', 'quantity')->whereHas('product', fn ($q) => $q->where('track_stock', true)));
    }

    private function capabilities(): array
    {
        return ['catalog' => Gate::allows('manage-catalog'), 'purchases' => Gate::allows('view-purchases'), 'managePurchases' => Gate::allows('manage-purchases'), 'finance' => Gate::allows('view-finance')];
    }
}
