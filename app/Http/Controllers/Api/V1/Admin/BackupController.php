<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Backup\StoreBackupRequest;
use App\Http\Resources\Admin\BackupResource;
use App\Models\Admin;
use App\Models\Backup;
use App\Models\License;
use App\Services\Admin\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 20);

        $backups = Backup::query()->with('license')->latest()->paginate($perPage)->withQueryString();

        return BackupResource::collection($backups);
    }

    public function store(StoreBackupRequest $request): JsonResponse
    {
        $license = null;

        if ($licenseId = $request->validated()['licenseId'] ?? null) {
            $license = License::findOrFail($licenseId);
        }

        /** @var Admin $admin */
        $admin = $request->user();

        $backup = $this->service->runManual($license, $admin);

        return (new BackupResource($backup->load('license')))->response()->setStatusCode(201);
    }

    /**
     * Streams the real gzipped dump if one exists on disk. Falls back to a
     * generated JSON snapshot of the row for legacy/seed rows with no
     * `file_path` — matching how the original Angular mock's download
     * behavior worked (there was never a real file behind those rows
     * either).
     */
    public function download(string $backup): BinaryFileResponse|JsonResponse
    {
        $model = Backup::with('license')->findOrFail($backup);

        if ($model->file_path) {
            $absolutePath = storage_path('app/'.$model->file_path);

            if (is_file($absolutePath)) {
                return response()->download($absolutePath, "backup-{$model->id}.sql.gz");
            }
        }

        return response()->json((new BackupResource($model))->toArray(request()))
            ->header('Content-Disposition', "attachment; filename=\"backup-{$model->id}.json\"");
    }

    public function destroy(string $backup): JsonResponse
    {
        $model = Backup::findOrFail($backup);

        if ($model->file_path) {
            $absolutePath = storage_path('app/'.$model->file_path);

            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }

        $model->delete();

        return response()->json(null, 204);
    }
}
