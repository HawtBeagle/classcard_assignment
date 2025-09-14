<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\User;

class QueryOptimizationController extends Controller
{
    public function search(Request $request)
    {
        $tenant_id = $request->input('tenant_id');
        $keyword = $request->input('keyword');
        $page = $request->input('page', 1); // default page
        $perPage = 50;

        $cacheKey = "users:tenant_{$tenant_id}:keyword_" . md5($keyword) . ":page_{$page}";
        $results = Cache::remember($cacheKey, 3600, function () use ($tenant_id, $keyword, $perPage, $page) {
        return User::where('tenant_id', $tenant_id)
            ->whereHas('formData.option', fn($q) => $q->where('label', 'like', "%{$keyword}%"))
            ->with(['formData' => fn($q) =>
            $q->whereHas('option', fn($q2) =>
                $q2->where('label', 'like', "%{$keyword}%")
            )->with(['option' => fn($q3) =>
                $q3->where('label', 'like', "%{$keyword}%")
            ])
        ])
            ->select(['id','name'])
            ->orderBy('id')
            ->paginate(50, ['*'], 'page', $page);
        });

        return response()->json($results);
    }
}
