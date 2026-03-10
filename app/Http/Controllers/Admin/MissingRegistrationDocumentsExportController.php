<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MissingRegistrationDocumentsExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MissingRegistrationDocumentsExportController extends Controller
{
    public function __construct(
        private readonly MissingRegistrationDocumentsExportService $missingRegistrationDocumentsExportService,
    ) {
    }

    public function __invoke(): BinaryFileResponse
    {
        $admin = auth('admin')->user();

        abort_unless($admin && $admin->active, 403);

        $export = $this->missingRegistrationDocumentsExportService->create();

        return response()
            ->download(
                $export['path'],
                $export['download_name'],
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(true);
    }
}
