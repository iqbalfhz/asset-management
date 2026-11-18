<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\HandOverAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\Assignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

// Aset dibaca lewat global scope yang sama dengan panel: aset di luar hak lihat token terbaca 404.
class AssetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Asset::class);

        $filters = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $assets = Asset::query()
            ->with(AssetResource::relations())
            ->when($filters['cari'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('kode', 'like', "%{$search}%")
                ->orWhere('nama', 'like', "%{$search}%")
                ->orWhere('no_seri', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->whereRelation('status', 'nama', $status))
            ->orderBy('kode')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return AssetResource::collection($assets);
    }

    public function show(Asset $asset): AssetResource
    {
        Gate::authorize('view', $asset);

        return new AssetResource($asset->load(AssetResource::relations()));
    }

    public function handOver(HandOverAssetRequest $request, Asset $asset): JsonResponse
    {
        try {
            $noBast = Assignment::handOver(new Collection([$asset]), $request->target(), $request->details());
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['aset' => $exception->getMessage()]);
        }

        return (new AssetResource($asset->refresh()->load(AssetResource::relations())))
            ->additional(['no_bast' => $noBast])
            ->response()
            ->setStatusCode(201);
    }
}
