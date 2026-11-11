<?php

namespace App\Http\Controllers;

use App\Models\LabProfile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class LabBrandMarkController extends Controller
{
    /**
     * Imagen de marca del panel (logo del encabezado, pantalla de acceso y favicon): el sello del perfil
     * activo del laboratorio, o su logo si no hay sello. Es la misma imagen que se imprime en cada reporte.
     */
    public function __invoke(): Response
    {
        $profile = LabProfile::query()->where('is_active', true)->first();
        $disk = Storage::disk('local');
        $path = collect([$profile?->institutional_seal_path, $profile?->logo_path])
            ->first(fn (?string $path): bool => filled($path) && $disk->exists($path));

        abort_if($path === null, 404);

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
