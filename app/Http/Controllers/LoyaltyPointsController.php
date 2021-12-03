<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use Illuminate\Http\JsonResponse;

class LoyaltyPointsController extends Controller
{
    /**
     * @return JsonResponse|mixed
     */
    public function deposit()
    {
        $data = $_POST;
        return LoyaltyAccount::deposit($data);
    }

    /**
     * @return JsonResponse|void
     */
    public function cancel()
    {
        $data = $_POST;
        return LoyaltyAccount::cancel($data);
    }

    public function withdraw()
    {
        $data = $_POST;
        return LoyaltyAccount::withdraw($data);
    }
}
