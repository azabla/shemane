<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Fulfilment\FulfilmentProviders;
use App\Support\Modules\StoreModules;
use App\Support\StoreAccess;
use App\Support\TeamPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Liberu\Ecommerce\Contracts\Fulfilment\ChecksAvailability;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentLine;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentProvider;
use Liberu\Ecommerce\Contracts\Fulfilment\FulfilmentRequest;
use Liberu\Ecommerce\Contracts\Fulfilment\Recipient;
use Liberu\Ecommerce\Contracts\Fulfilment\SupplierUnavailable;

/** Staff access to the suppliers the request's store has active, on that store's own credentials. */
class DropshippingController extends Controller
{
    public function __construct(private readonly FulfilmentProviders $providers, private readonly StoreModules $modules) {}

    public function suppliers(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);
        $suppliers = [];

        foreach ($this->providers->activeFor((int) Store::current()?->id) as $key => $provider) {
            $definition = $this->modules->definition($provider->moduleKey());
            // An allowlist of fields, so a new setting can never leak through here.
            $suppliers[$key] = ['name' => $definition?->label(), 'description' => $definition?->description()];
        }

        return response()->json(['suppliers' => $suppliers]);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        if ($invalid = $this->invalid($request, ['supplier_id' => 'required|string', 'sku' => 'required|string', 'quantity' => 'sometimes|integer|min:1'])) {
            return $invalid;
        }

        return $this->withProvider($request, function (FulfilmentProvider $provider, array $settings) use ($request) {
            if (! $provider instanceof ChecksAvailability) {
                return ['success' => false, 'message' => 'This supplier cannot report availability.'];
            }

            return ['success' => true, 'data' => $provider->availability($request->sku, (int) ($request->quantity ?? 1), $settings)];
        });
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        if ($invalid = $this->invalid($request, ['supplier_id' => 'required|string', 'order_data' => 'required|array', 'order_data.items' => 'required|array', 'order_data.shipping_address' => 'required|array'])) {
            return $invalid;
        }

        return $this->withProvider($request, function (FulfilmentProvider $provider, array $settings) use ($request) {
            $result = $provider->submit($this->manualRequest($request->order_data), $settings);

            return $result->accepted
                ? ['success' => true, 'data' => $result->response]
                : ['success' => false, 'message' => $result->rejectionReason, 'error' => $result->response];
        });
    }

    public function trackOrder(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        if ($invalid = $this->invalid($request, ['supplier_id' => 'required|string', 'order_reference' => 'required|string'])) {
            return $invalid;
        }

        return $this->withProvider($request, function (FulfilmentProvider $provider, array $settings) use ($request) {
            $update = $provider->track($request->order_reference, $settings);

            return ['success' => true, 'data' => $update === null ? null : [
                'tracking_number' => $update->trackingNumber,
                'carrier' => $update->carrier,
                'tracking_url' => $update->trackingUrl,
            ]];
        });
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless(StoreAccess::allows($request->user(), TeamPermission::ORDERS_MANAGE, null, 'update_order'), 403);
    }

    private function invalid(Request $request, array $rules): ?JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);

        return $validator->fails() ? response()->json(['success' => false, 'errors' => $validator->errors()], 422) : null;
    }

    /** @param  callable(FulfilmentProvider, array<string, mixed>): array<string, mixed>  $call */
    private function withProvider(Request $request, callable $call): JsonResponse
    {
        $storeId = (int) Store::current()?->id;

        if (! $this->providers->canFulfil($request->supplier_id, $storeId)) {
            return response()->json(['success' => false, 'message' => 'Supplier not found'], 400);
        }

        $provider = $this->providers->provider($request->supplier_id, $storeId);

        try {
            $result = $call($provider, $this->providers->settings($provider, $storeId));
        } catch (SupplierUnavailable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /** @param  array<string, mixed>  $data */
    private function manualRequest(array $data): FulfilmentRequest
    {
        $address = $data['shipping_address'];

        return new FulfilmentRequest(
            (string) ($data['reference'] ?? 'manual-'.Str::uuid()),
            (int) Store::current()?->id,
            new Recipient(
                $data['recipient_name'] ?? $data['customer_name'] ?? null,
                $data['recipient_email'] ?? $data['customer_email'] ?? null,
                $address['address1'] ?? $address['line1'] ?? $address['street'] ?? null,
                $address['city'] ?? null,
                $address['state'] ?? null,
                $address['zip'] ?? $address['postal_code'] ?? null,
                $address['country'] ?? $address['country_code'] ?? null,
            ),
            array_map(fn (array $line) => new FulfilmentLine(
                (int) ($line['product_id'] ?? 0),
                (int) ($line['quantity'] ?? 1),
                (float) ($line['price'] ?? 0),
                $line['sku'] ?? null,
                isset($line['supplier_variant_id']) ? (string) $line['supplier_variant_id'] : null,
            ), $data['items']),
        );
    }
}
