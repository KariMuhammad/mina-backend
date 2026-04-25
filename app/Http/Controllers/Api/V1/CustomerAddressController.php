<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Rules\ValidCityRule;
use App\Support\CustomerAddressFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = $request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get();

        return response()->json([
            'data' => $rows->map(fn (Address $a) => CustomerAddressFormatter::toArray($a))->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'address_line' => ['required_without:address_line_1', 'nullable', 'string', 'max:255'],
            'address_line_1' => ['required_without:address_line', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100', new ValidCityRule()],
            'postal_code' => ['required_without:zip', 'nullable', 'string', 'max:20'],
            'zip' => ['required_without:postal_code', 'nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $attrs = $this->attributesFromValidated($validated);
        $isDefault = (bool) ($validated['is_default'] ?? false);

        if ($isDefault) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $attrs['is_default'] = $isDefault;
        $address = $request->user()->addresses()->create($attrs);

        return response()->json([
            'message' => 'Address created successfully.',
            'address' => CustomerAddressFormatter::toArray($address),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = Address::query()->find($id);
        if ($address === null) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }
        if ((int) $address->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Access denied! This resource does not belong to you.'], 403);
        }

        $validated = $request->validate([
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:32'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:100', new ValidCityRule()],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'zip' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country' => ['sometimes', 'required', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_default', $validated) && $validated['is_default']) {
            $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $patch = $this->patchFromValidated($validated);
        if ($patch !== []) {
            $address->update($patch);
        }

        return response()->json([
            'message' => 'Address updated successfully.',
            'address' => CustomerAddressFormatter::toArray($address->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $address = Address::query()->find($id);
        if ($address === null) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }
        if ((int) $address->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Access denied! This resource does not belong to you.'], 403);
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted successfully.']);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFromValidated(array $validated): array
    {
        return [
            'name' => $validated['label'] ?? $validated['name'] ?? null,
            'phone' => $validated['phone'],
            'address_line_1' => (string) ($validated['address_line'] ?? $validated['address_line_1'] ?? ''),
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'],
            'zip' => (string) ($validated['postal_code'] ?? $validated['zip'] ?? ''),
            'country' => $validated['country'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function patchFromValidated(array $validated): array
    {
        $out = [];

        if (array_key_exists('label', $validated) || array_key_exists('name', $validated)) {
            $out['name'] = $validated['label'] ?? $validated['name'] ?? null;
        }

        if (array_key_exists('phone', $validated)) {
            $out['phone'] = $validated['phone'];
        }

        if (array_key_exists('address_line', $validated) || array_key_exists('address_line_1', $validated)) {
            $out['address_line_1'] = (string) ($validated['address_line'] ?? $validated['address_line_1'] ?? '');
        }

        if (array_key_exists('address_line_2', $validated)) {
            $out['address_line_2'] = $validated['address_line_2'];
        }

        if (array_key_exists('city', $validated)) {
            $out['city'] = $validated['city'];
        }

        if (array_key_exists('postal_code', $validated) || array_key_exists('zip', $validated)) {
            $out['zip'] = (string) ($validated['postal_code'] ?? $validated['zip'] ?? '');
        }

        if (array_key_exists('country', $validated)) {
            $out['country'] = $validated['country'];
        }

        if (array_key_exists('is_default', $validated)) {
            $out['is_default'] = (bool) $validated['is_default'];
        }

        return $out;
    }
}
