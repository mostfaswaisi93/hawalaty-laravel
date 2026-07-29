<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HasPerPage;
use App\Models\Backup;
use App\Models\License;
use App\Services\Admin\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    use HasPerPage;

    public function __construct(private readonly BackupService $service) {}

    public function index(Request $request): View
    {
        $perPage = $this->perPage($request);

        return view('admin.backups.index', [
            'backups' => Backup::with('license')->latest()->paginate($perPage)->withQueryString(),
            'licenses' => License::query()->orderBy('customer_name')->get(),
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'licenseId' => ['nullable', 'string', 'exists:licenses,id'],
        ]);

        $license = ! empty($data['licenseId']) ? License::findOrFail($data['licenseId']) : null;

        $backup = $this->service->runManual($license, auth('admin')->user());

        return redirect()->route('admin.backups.index')->with(
            $backup->status->value === 'completed' ? 'success' : 'error',
            $backup->status->value === 'completed'
                ? __('تم إنشاء النسخة الاحتياطية بنجاح.')
                : __('فشل إنشاء النسخة الاحتياطية - راجِع السجلات.'),
        );
    }

    public function download(string $backup): BinaryFileResponse|StreamedResponse
    {
        $model = Backup::with('license')->findOrFail($backup);

        if ($model->file_path) {
            $absolutePath = storage_path('app/'.$model->file_path);

            if (is_file($absolutePath)) {
                return response()->download($absolutePath, "backup-{$model->id}.sql.gz");
            }
        }

        $snapshot = json_encode([
            'id' => $model->id,
            'license' => $model->license?->customer_name,
            'trigger' => $model->trigger->value,
            'status' => $model->status->value,
            'size_mb' => $model->size_mb,
            'created_at' => $model->created_at?->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response()->streamDownload(fn () => print ($snapshot), "backup-{$model->id}.json");
    }

    public function destroy(string $backup): RedirectResponse
    {
        $model = Backup::findOrFail($backup);

        if ($model->file_path) {
            $absolutePath = storage_path('app/'.$model->file_path);

            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }

        $model->delete();

        return redirect()->route('admin.backups.index')->with('success', __('تم حذف النسخة الاحتياطية.'));
    }
}
