<?php

namespace App\Support;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;

class CompanyBrand
{
    public const LOGO = '/brand/arevantes-logo.svg';

    public function customPath(?CompanyProfile $profile): ?string
    {
        $path = $profile?->logo_path;

        return $path && Storage::disk(config('filesystems.private'))->exists($path) ? $path : null;
    }

    public function for(?CompanyProfile $profile): array
    {
        $path = $this->customPath($profile);

        return [
            'name' => $profile?->trade_name ?: ($profile?->legal_name ?: config('company.legal_name', 'AREVANTES')),
            'logoUrl' => $path ? '/company-fiscal/logo?v='.rawurlencode($path) : self::LOGO,
            'canonicalLogoUrl' => self::LOGO,
            'custom' => $path !== null,
        ];
    }

    public function dataUri(?CompanyProfile $profile): string
    {
        $path = $this->customPath($profile);
        $disk = Storage::disk(config('filesystems.private'));

        return 'data:'.($path ? $disk->mimeType($path) : 'image/svg+xml').';base64,'.base64_encode($path ? $disk->get($path) : file_get_contents(public_path(ltrim(self::LOGO, '/'))));
    }
}
