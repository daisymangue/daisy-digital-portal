<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /** Files live on the private disk. Every download passes this ownership check. */
    public function download(Request $request, File $file)
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || ($user->isClient() && $file->client_id === $user->client_id), 404);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        AuditLog::record('file.downloaded', $file);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
