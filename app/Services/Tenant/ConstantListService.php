<?php

namespace App\Services\Tenant;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditEntityType;
use App\Models\Tenant\ConstantList;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ConstantListService
{
    public function list(): Collection
    {
        return ConstantList::with('items')->get();
    }

    public function create(array $data): ConstantList
    {
        $list = ConstantList::create([
            // Mirrors ConstantsService.addCustomList()'s key generation
            // scheme in the Angular app ('custom-' + Date.now().toString(36)).
            'key' => 'custom-'.Str::lower(Str::random(10)),
            'title' => $data['title'],
        ]);

        $list->load('items');

        AuditLogger::record(AuditAction::Created, AuditEntityType::Constant, $list->title, AuditCategory::Constants);

        return $list;
    }

    public function delete(ConstantList $list): void
    {
        $title = $list->title;
        $list->delete();

        AuditLogger::record(AuditAction::Deleted, AuditEntityType::Constant, $title, AuditCategory::Constants);
    }

    public function addItem(ConstantList $list, array $data): ConstantList
    {
        $nextPosition = ((int) $list->items()->max('position')) + 1;

        $list->items()->create([
            'value' => $data['value'],
            'position' => $nextPosition,
        ]);

        $list->load('items');

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Constant, $list->title, AuditCategory::Constants);

        return $list;
    }

    /**
     * ->items()->findOrFail() (not ConstantListItem::findOrFail()) ensures
     * the item actually belongs to THIS list.
     */
    public function deleteItem(ConstantList $list, string $itemId): void
    {
        $item = $list->items()->findOrFail($itemId);
        $item->delete();

        AuditLogger::record(AuditAction::Updated, AuditEntityType::Constant, $list->title, AuditCategory::Constants);
    }
}
