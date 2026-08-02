<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Enums\TaxCategory;
use App\Models\Tenant\MenuCategory;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\PriceChange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MenuService
{
    public function __construct(private readonly StockService $stock) {}

    public function listCategories(): Collection
    {
        return MenuCategory::query()->orderBy('position')->get();
    }

    public function createCategory(array $data): MenuCategory
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $nextPosition = ((int) MenuCategory::lockForUpdate()->max('position')) + 1;

            $category = MenuCategory::create([
                'name' => $data['name'],

                'icon' => filled($data['icon'] ?? null) ? $data['icon'] : null,
                'position' => $nextPosition,
            ]);

            AuditLogger::record(AuditAction::Created, AuditEntityType::MenuItem, $category->name, AuditCategory::Menu);

            return $category;
        });
    }

    public function deleteCategory(MenuCategory $category): void
    {
        $name = $category->name;
        $category->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::MenuItem, $name, AuditCategory::Menu);
    }

    public function listItems(?string $search = null): Collection
    {
        return MenuItem::query()->with('menuCategory')->whereSearch('name', $search)->get();
    }

    /**
     * Input key to column, for everything that is a straight copy.
     *
     * A map rather than thirty near-identical `if (array_key_exists(...))`
     * blocks: the fields differ only in name, and writing that difference out
     * thirty times is thirty chances to write it wrong.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'name' => 'name',
        'menuCategoryId' => 'menu_category_id',
        'price' => 'price',
        'active' => 'active',

        'barcode' => 'barcode',
        'sku' => 'sku',
        'pluCode' => 'plu_code',

        'unitCode' => 'unit_code',
        'purchaseUnitCode' => 'purchase_unit_code',
        'purchaseConversion' => 'purchase_conversion',

        'taxRate' => 'tax_rate',
        'taxIncluded' => 'tax_included',

        'trackStock' => 'track_stock',
        'reorderPoint' => 'reorder_point',

        'trackSerials' => 'track_serials',
        'warrantyMonths' => 'warranty_months',
        'serviceMinutes' => 'service_minutes',
        'coreCharge' => 'core_charge',

        'isPrescription' => 'is_prescription',
        'controlSchedule' => 'control_schedule',
        'atcCode' => 'atc_code',
        'activeIngredient' => 'active_ingredient',
        'strength' => 'strength',
        'dosageForm' => 'dosage_form',
        'coldChain' => 'cold_chain',
    ];

    /**
     * Fields stored as null rather than an empty string, so "not set" and "set
     * to nothing" stay distinguishable in the catalogue.
     *
     * @var list<string>
     */
    private const NULLABLE_TEXT = [
        'icon', 'barcode', 'sku', 'pluCode',
        'controlSchedule', 'atcCode', 'activeIngredient', 'strength', 'dosageForm',
    ];

    public function createItem(array $data): MenuItem
    {
        $rate = (float) ($data['taxRate'] ?? 0);

        $item = MenuItem::create(array_merge($this->mapped($data), [
            'menu_category_id' => $data['menuCategoryId'],
            'name' => $data['name'],
            'price' => $data['price'],
            'kind' => ($data['kind'] ?? 'goods') === 'service' ? 'service' : 'goods',
            'icon' => $this->text($data['icon'] ?? null),
            'tax_rate' => $rate,

            // Inferred when nobody has said, so an invoice never carries a blank
            // category - the standard has no default for it.
            'tax_category' => $data['taxCategory'] ?? TaxCategory::inferFrom($rate)->value,
            'tax_included' => $data['taxIncluded'] ?? true,
            'active' => $data['active'] ?? true,
        ]));

        $item->load('menuCategory');

        $this->recordPrice($item, null, (float) $item->price);

        AuditLogger::record(AuditAction::Created, AuditEntityType::MenuItem, $item->name, AuditCategory::Menu);

        return $item;
    }

    public function updateItem(MenuItem $item, array $data): MenuItem
    {
        $this->assertSafeToChange($item, $data);

        $update = $this->mapped($data);

        if (array_key_exists('icon', $data)) {
            $update['icon'] = $this->text($data['icon']);
        }

        if (array_key_exists('kind', $data)) {
            $update['kind'] = $data['kind'] === 'service' ? 'service' : 'goods';
        }

        if (array_key_exists('taxCategory', $data)) {
            $update['tax_category'] = $data['taxCategory']
                ?: TaxCategory::inferFrom((float) ($data['taxRate'] ?? $item->tax_rate))->value;
        }

        /*
         * Cost is the rolled weighted average once anything has moved. Typing
         * over it then is an unaudited restatement of what the shop paid, and
         * the ledger - which is the version an auditor follows - would disagree
         * with the catalogue from that moment on. Before the first movement
         * there is nothing to contradict, so an opening cost is allowed.
         */
        if (array_key_exists('cost', $data) && ! $item->movements()->exists()) {
            $update['cost'] = (float) $data['cost'];
        }

        // Read before the write, or there is nothing left to compare against.
        $previous = (float) $item->price;

        $item->update($update);
        $item->load('menuCategory');

        if (array_key_exists('price', $data) && abs((float) $item->price - $previous) >= 0.0005) {
            $this->recordPrice($item, $previous, (float) $item->price);
        }

        AuditLogger::record(AuditAction::Updated, AuditEntityType::MenuItem, $item->name, AuditCategory::Menu);

        return $item;
    }

    /**
     * Refuse the changes that would make the ledger stop describing reality.
     *
     * None of these are style preferences. Each one leaves recorded history
     * saying something that is no longer true, and history is the thing this
     * system exists to keep straight.
     */
    private function assertSafeToChange(MenuItem $item, array $data): void
    {
        $hasHistory = $item->movements()->exists();

        // Quantities in the ledger were counted in the old unit. Reinterpreting
        // them as a new one turns 5 kilos into 5 pieces without a single row
        // changing.
        if ($hasHistory
            && array_key_exists('unitCode', $data)
            && $data['unitCode'] !== $item->unit_code) {
            throw new RuntimeException(__('لا يمكن تغيير وحدة القياس بعد أن تحرّك المخزون — كميات الدفتر مسجّلة بالوحدة القديمة.'));
        }

        // Goods that have moved cannot become a service: a service has nothing
        // to count, and the balance would be orphaned rather than closed.
        if ($hasHistory
            && array_key_exists('kind', $data)
            && $data['kind'] === 'service'
            && ! $item->isService()) {
            throw new RuntimeException(__('لا يمكن تحويل صنف تحرّك مخزونه إلى خدمة — صفِّ رصيده أولًا.'));
        }

        $onHand = $hasHistory ? $this->stock->onHand($item->id) : 0.0;

        if (abs($onHand) < 0.0005) {
            return;
        }

        // Switching tracking off with a balance still on the shelf abandons it:
        // nothing would issue it, and nothing would report it missing.
        if (array_key_exists('trackStock', $data)
            && ! $data['trackStock']
            && $item->track_stock) {
            throw new RuntimeException(__('لا يمكن إيقاف تتبّع المخزون ورصيد الصنف ليس صفرًا — صفِّه أولًا.'));
        }

        // Turning serial tracking on strands whatever is already on the shelf:
        // those units have no serials, and an issue of a serialised item without
        // one is refused - so the balance becomes unsellable.
        if (array_key_exists('trackSerials', $data)
            && $data['trackSerials']
            && ! $item->track_serials) {
            throw new RuntimeException(__('لا يمكن تشغيل التتبّع التسلسلي ورصيد الصنف ليس صفرًا — الوحدات الموجودة بلا أرقام ولن تقدر تخرج.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapped(array $data): array
    {
        $update = [];

        foreach (self::FIELDS as $input => $column) {
            if (! array_key_exists($input, $data)) {
                continue;
            }

            $update[$column] = in_array($input, self::NULLABLE_TEXT, true)
                ? $this->text($data[$input])
                : $data[$input];
        }

        return $update;
    }

    private function text(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }

    /**
     * Note a price alongside the cost it was chosen against.
     *
     * The cost is the point. Knowing a price was set at 10 says nothing; knowing
     * it was set at 10 when the item cost 6, and the item now costs 9, is the
     * whole margin-erosion report.
     */
    private function recordPrice(MenuItem $item, ?float $old, float $new): void
    {
        PriceChange::create([
            'menu_item_id' => $item->id,
            'old_price' => $old,
            'new_price' => $new,
            'cost_at_change' => (float) $item->cost,
            'team_member_id' => auth('tenant')->id(),
        ]);
    }

    public function deleteItem(MenuItem $item): void
    {
        $name = $item->name;
        $item->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::MenuItem, $name, AuditCategory::Menu);
    }

    public function exportRows(): array
    {
        return MenuCategory::query()
            ->with(['menuItems' => fn ($query) => $query->orderBy('name')])
            ->orderBy('position')
            ->get()
            ->flatMap(fn (MenuCategory $category) => $category->menuItems->map(fn (MenuItem $item) => [
                'category' => self::sanitizeCsvField($category->name),
                'name' => self::sanitizeCsvField($item->name),
                'price' => (string) $item->price,
                'active' => $item->active ? '1' : '0',
            ]))
            ->all();
    }

    private static function sanitizeCsvField(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    public function importCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);
        $columnIndex = array_flip($header);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::connection('tenant')->transaction(function () use ($handle, $columnIndex, &$created, &$updated, &$skipped) {
            $categoriesByName = MenuCategory::query()->get()->keyBy('name');
            $nextPosition = ((int) MenuCategory::max('position')) + 1;

            $itemsByKey = MenuItem::query()->get()->keyBy(fn (MenuItem $item) => "{$item->menu_category_id}|{$item->name}");

            while (($row = fgetcsv($handle)) !== false) {
                $categoryName = trim((string) ($row[$columnIndex['category'] ?? -1] ?? ''));
                $name = trim((string) ($row[$columnIndex['name'] ?? -1] ?? ''));
                $price = $row[$columnIndex['price'] ?? -1] ?? null;
                $active = $row[$columnIndex['active'] ?? -1] ?? '1';

                if ($categoryName === '' || $name === '' || ! is_numeric($price) || (float) $price < 0) {
                    $skipped++;

                    continue;
                }

                $category = $categoriesByName->get($categoryName);

                if (! $category) {
                    $category = MenuCategory::create(['name' => $categoryName, 'position' => $nextPosition++]);
                    $categoriesByName->put($categoryName, $category);
                }

                $itemKey = "{$category->id}|{$name}";
                $item = $itemsByKey->get($itemKey);
                $attributes = [
                    'price' => $price,
                    'active' => in_array(strtolower((string) $active), ['0', 'false', 'no', ''], true) ? false : true,
                ];

                if ($item) {
                    // A bulk import is the most common way a price moves and the
                    // easiest way to move one by accident, so it is exactly the
                    // path that has to leave a trail.
                    $previous = (float) $item->price;

                    $item->update($attributes);
                    $updated++;

                    if (abs((float) $item->price - $previous) >= 0.0005) {
                        $this->recordPrice($item, $previous, (float) $item->price);
                    }
                } else {
                    $item = MenuItem::create([...$attributes, 'menu_category_id' => $category->id, 'name' => $name]);
                    $itemsByKey->put($itemKey, $item);
                    $created++;

                    $this->recordPrice($item, null, (float) $item->price);
                }
            }
        });

        fclose($handle);

        AuditLogger::record(AuditAction::Updated, AuditEntityType::MenuItem, __('استيراد جماعي'), AuditCategory::Menu);

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
