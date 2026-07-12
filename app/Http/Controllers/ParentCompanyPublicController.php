<?php

namespace App\Http\Controllers;

use App\Models\ParentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ParentCompanyPublicController extends Controller
{
    public function privacyPolicy(): View
    {
        return view('privacy-policy');
    }

    public function show(ParentCompany $parentCompany): View
    {
        return view('companies.show', [
            'parentCompany' => $parentCompany,
            'appDeepLinkUrl' => $parentCompany->appDeepLinkUrl(),
        ]);
    }

    public function assetLinks(): JsonResponse
    {
        $fingerprints = config('deep_links.android_sha256_fingerprints', []);
        $packageName = (string) config('deep_links.android_package', 'com.safriat.safriat');

        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $packageName,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ],
        ]);
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        $appId = trim((string) config('deep_links.ios_app_id', ''));
        $details = [];
        if ($appId !== '') {
            $details[] = [
                'appIDs' => [$appId],
                'components' => [
                    [
                        '/' => '/companies/*',
                    ],
                ],
            ];
        }

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => $details,
            ],
        ])->header('Content-Type', 'application/json');
    }
}
