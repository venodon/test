<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * @param Request $request
     * @return mixed
     */
    public function create(Request $request)
    {
        return LoyaltyAccount::create($request->all());
    }

    /**
     * @param $type
     * @param $id
     * @return JsonResponse
     */
    public function activate($type, $id)
    {
        if ($id && in_array($type,['phone', 'card', 'email'])) {
            if ($account = LoyaltyAccount::where($type, '=', $id)->first()) {
                if (!$account->active) {
                    $account->active = true;
                    $account->save();
                    $account->notify('Account restored');
                }
            } else {
                return response()->json(['message' => 'Account is not found'], 400);
            }
        } else {
            throw new \InvalidArgumentException('Wrong parameters');
        }
        return response()->json(['success' => true]);
    }

    /**
     * @param $type
     * @param $id
     * @return JsonResponse
     */
    public function deactivate($type, $id)
    {
        if ($id && in_array($type,['phone', 'card', 'email'])) {
            if ($account = LoyaltyAccount::where($type, '=', $id)->first()) {
                if ($account->active) {
                    $account->active = false;
                    $account->save();
                    $account->notify('Account banned');
                }
            } else {
                return response()->json(['message' => 'Account is not found'], 400);
            }
        } else {
            throw new \InvalidArgumentException('Wrong parameters');
        }
        return response()->json(['success' => true]);
    }

    /**
     * @param $type
     * @param $id
     * @return JsonResponse
     */
    public function balance($type, $id)
    {
        if ($id && in_array($type,['phone', 'card', 'email'])) {
            if ($account = LoyaltyAccount::where($type, '=', $id)->first()) {
                return response()->json(['balance' => $account->getBalance()], 400);
            }
            return response()->json(['message' => 'Account is not found'], 400);
        }
        throw new \InvalidArgumentException('Wrong parameters');
    }
}
