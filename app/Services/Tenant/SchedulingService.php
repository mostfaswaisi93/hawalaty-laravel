<?php

namespace App\Services\Tenant;

use App\Enums\AppointmentStatus;
use App\Enums\BusinessCapability;
use App\Models\Tenant\Appointment;
use App\Models\Tenant\MenuItem;
use App\Models\Tenant\ServiceResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The diary.
 *
 * One rule carries the module: a resource cannot be in two places at once. Every
 * other feature here is convenience; double-booking a technician is the failure
 * that sends a customer away.
 */
class SchedulingService
{
    /**
     * Default length when a service does not say how long it takes.
     */
    public const DEFAULT_MINUTES = 30;

    public function __construct(private readonly CustomerService $customers) {}

    public function enabled(): bool
    {
        return app(BusinessProfile::class)->has(BusinessCapability::Scheduling);
    }

    /**
     * Book a slot, refusing an overlap.
     */
    public function book(array $data): Appointment
    {
        $item = filled($data['itemId'] ?? null) ? MenuItem::find($data['itemId']) : null;

        $startsAt = Carbon::parse($data['startsAt']);
        $endsAt = $this->resolveEnd($startsAt, $data['endsAt'] ?? null, $item);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new RuntimeException(__('وقت الانتهاء يجب أن يكون بعد وقت البدء.'));
        }

        $resourceId = $data['resourceId'] ?? null;

        $this->assertFree($resourceId, $startsAt, $endsAt);

        return DB::connection('tenant')->transaction(function () use ($data, $item, $startsAt, $endsAt, $resourceId) {
            $customer = $this->customers->findOrCreate($data['customerName'], $data['customerPhone'] ?? null);

            return Appointment::create([
                'menu_item_id' => $item?->id,
                'service_resource_id' => $resourceId,
                'customer_id' => $customer?->id,
                'customer_name' => $data['customerName'],
                'customer_phone' => $data['customerPhone'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Booked,
                'deposit' => $data['deposit'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'team_member_id' => auth('tenant')->id(),
            ]);
        });
    }

    /**
     * Move a booking, checking the new slot the same way.
     */
    public function reschedule(Appointment $appointment, string $startsAt, ?string $endsAt = null): Appointment
    {
        $start = Carbon::parse($startsAt);
        $end = $this->resolveEnd($start, $endsAt, $appointment->item, $appointment->minutes());

        if ($end->lessThanOrEqualTo($start)) {
            throw new RuntimeException(__('وقت الانتهاء يجب أن يكون بعد وقت البدء.'));
        }

        $this->assertFree($appointment->service_resource_id, $start, $end, $appointment->id);

        $appointment->update(['starts_at' => $start, 'ends_at' => $end]);

        return $appointment;
    }

    public function setStatus(Appointment $appointment, AppointmentStatus $status): Appointment
    {
        $appointment->update(['status' => $status]);

        return $appointment;
    }

    /**
     * A day's diary, grouped by resource so it reads as a board.
     *
     * @return Collection<string, Collection<int, Appointment>>
     */
    public function day(string $date, ?string $resourceId = null): Collection
    {
        return Appointment::query()
            ->with(['item', 'resource', 'customer'])
            ->onDay($date)
            ->when($resourceId, fn ($query) => $query->where('service_resource_id', $resourceId))
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Appointment $appointment) => $appointment->service_resource_id ?? '');
    }

    /**
     * Free gaps for a resource on a day, within working hours.
     *
     * Deliberately simple: the shop's hours are a setting, not a per-person
     * roster. A roster is a real need but a different module, and pretending to
     * have one by guessing would put wrong times in front of a customer.
     *
     * @return list<array{from: Carbon, to: Carbon}>
     */
    public function openSlots(string $resourceId, string $date, int $fromHour = 8, int $toHour = 18): array
    {
        $dayStart = Carbon::parse($date)->setTime($fromHour, 0);
        $dayEnd = Carbon::parse($date)->setTime($toHour, 0);

        $taken = Appointment::query()
            ->where('service_resource_id', $resourceId)
            ->holding()
            ->onDay($date)
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        $cursor = $dayStart;

        foreach ($taken as $appointment) {
            if ($appointment->starts_at->greaterThan($cursor)) {
                $slots[] = ['from' => $cursor->copy(), 'to' => $appointment->starts_at->copy()];
            }

            if ($appointment->ends_at->greaterThan($cursor)) {
                $cursor = $appointment->ends_at->copy();
            }
        }

        if ($cursor->lessThan($dayEnd)) {
            $slots[] = ['from' => $cursor, 'to' => $dayEnd];
        }

        return $slots;
    }

    /**
     * @return Collection<int, ServiceResource>
     */
    public function resources(bool $activeOnly = true): Collection
    {
        return ServiceResource::query()
            ->with('teamMember')
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('name')
            ->get();
    }

    /**
     * Refuse a slot that collides with one already held.
     *
     * Overlap, not equality: two appointments clash whenever one starts before
     * the other ends. Touching ends are fine - a job finishing at ten and the
     * next starting at ten is a full diary, not a conflict.
     */
    private function assertFree(?string $resourceId, Carbon $startsAt, Carbon $endsAt, ?string $ignoreId = null): void
    {
        if (blank($resourceId)) {
            return;
        }

        $clash = Appointment::query()
            ->where('service_resource_id', $resourceId)
            ->holding()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->with('resource')
            ->first();

        if ($clash instanceof Appointment) {
            throw new RuntimeException(__(':resource محجوز في :time.', [
                'resource' => $clash->resource?->name ?? __('المورد'),
                'time' => $clash->timeLabel(),
            ]));
        }
    }

    private function resolveEnd(Carbon $startsAt, ?string $endsAt, ?MenuItem $item, ?int $fallbackMinutes = null): Carbon
    {
        if (filled($endsAt)) {
            return Carbon::parse($endsAt);
        }

        $minutes = (int) ($item?->service_minutes ?: 0);

        if ($minutes <= 0) {
            $minutes = $fallbackMinutes ?: self::DEFAULT_MINUTES;
        }

        return $startsAt->copy()->addMinutes($minutes);
    }
}
