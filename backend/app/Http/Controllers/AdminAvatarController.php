<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AdminAvatar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The signed-in administrator's own account picture (panel authenticated route). There is no
 * user parameter: an account can only ever load its own picture here.
 */
class AdminAvatarController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = $request->user('web');
        abort_unless($user instanceof User, 404);

        return AdminAvatar::response($user);
    }
}
