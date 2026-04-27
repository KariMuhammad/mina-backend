<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        if ($request->hasFile('avatar')) {
            // Delete old avatar from Cloudinary
            if ($user->avatar_url && str_starts_with($user->avatar_url, 'http')) {
                $publicId = $this->extractCloudinaryPublicId($user->avatar_url);
                if ($publicId) {
                    Storage::disk('cloudinary')->delete($publicId);
                }
            }

            // Upload new avatar
            $path = Storage::disk('cloudinary')->putFile('avatars', $request->file('avatar'));
            $user->avatar_url = Storage::disk('cloudinary')->url($path);
        }

        $user->save();

        return response()->json([
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'avatar_url'     => $user->avatar_url,
            'wallet_balance' => $user->wallet_balance,
            'loyalty_points' => $user->loyalty_points,
            'role'           => $user->role,
            'created_at'     => $user->created_at,
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        // Users who signed up via phone/OTP may have no password
        if (!$user->password) {
            return response()->json([
                'message' => 'لا توجد كلمة مرور لهذا الحساب. تم التسجيل عبر رقم الهاتف.'
            ], 422);
        }

        $request->validate([
            'current_password'      => 'required|string',
            'new_password'          => 'required|string|min:8|confirmed|different:current_password',
        ], [
            'current_password.required'      => 'كلمة المرور الحالية مطلوبة',
            'new_password.required'           => 'كلمة المرور الجديدة مطلوبة',
            'new_password.min'                => 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل',
            'new_password.confirmed'          => 'تأكيد كلمة المرور غير متطابق',
            'new_password.different'          => 'كلمة المرور الجديدة يجب أن تختلف عن الحالية',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // If user has a password, require it for confirmation
        if ($user->password) {
            $request->validate([
                'password' => 'required|string',
            ], [
                'password.required' => 'كلمة المرور مطلوبة لتأكيد الحذف',
            ]);

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'كلمة المرور غير صحيحة'
                ], 422);
            }
        }

        // Delete avatar from Cloudinary if exists
        if ($user->avatar_url && str_starts_with($user->avatar_url, 'http')) {
            $publicId = $this->extractCloudinaryPublicId($user->avatar_url);
            if ($publicId) {
                Storage::disk('cloudinary')->delete($publicId);
            }
        }

        // Revoke all tokens (logout from all devices)
        $user->tokens()->delete();

        // Delete the account
        // DB cascade handles: addresses, cart_items, favorites
        // Orders are preserved (user_id set to null via nullOnDelete)
        $user->delete();

        return response()->json([
            'message' => 'تم حذف الحساب بنجاح'
        ]);
    }

    private function extractCloudinaryPublicId(string $url): ?string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';

        if (preg_match('#/image/upload/(?:[^/]+/)*v\d+/(.+)#', $path, $matches)) {
            return preg_replace('/\.[^.]+$/', '', $matches[1]);
        }

        if (preg_match('#/image/upload/(.+)#', $path, $matches)) {
            return preg_replace('/\.[^.]+$/', '', $matches[1]);
        }

        return null;
    }
}
