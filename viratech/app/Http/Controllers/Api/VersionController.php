<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dernière version publiée. Les applications comparent `build` à leur propre numéro de build et,
 * si le serveur est plus récent, proposent la mise à jour (url = APK client, url_admin = APK admin).
 */
class VersionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $v = AppVersion::latestPublished();

        return response()->json([
            'build' => $v?->build ?? 0,
            'version' => $v?->version,
            'url' => $v?->url,
            'url_admin' => $v?->url_admin,
            'notes' => $v?->notes ?? [],
        ]);
    }
}
