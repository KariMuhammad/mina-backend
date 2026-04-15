<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\ImageService;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    public function update(UpdateProfileRequest $request, ImageService $imageService)
    {
        $user = $request->user();
        
        $data = $request->only(['name', 'phone']);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $imageService->replace(
                $user->avatar_path ?? null,
                $request->file('avatar'),
                'avatars'
            );
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user
        ]);
    }
}
