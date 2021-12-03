<?php

namespace App\Models;

use App\Mail\AccountActivated;
use App\Mail\AccountDeactivated;
use App\Mail\LoyaltyPointsReceived;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 *
 * @property int $id
 * @property string $phone
 * @property string $card
 * @property string $email
 * @property bool $email_notification
 * @property bool $phone_notification
 * @property bool $active
 **/
class LoyaltyAccount extends Model
{
    protected $table = 'loyalty_account';

    protected $fillable = [
        'phone',
        'card',
        'email',
        'email_notification',
        'phone_notification',
        'active',
    ];

    /**
     * Получение текущего баланса по карте лояльности
     * TODO: не особо нравится использование float для баланса. Я бы поставил int*100
     * @return float
     */
    public function getBalance(): float
    {
        return LoyaltyPointsTransaction::where('canceled', '=', 0)->where('account_id', '=', $this->id)->sum('points_amount');
    }


    /**
     * TODO: дохлое, нигде не используется, или вставить где надо или удалить
     */
    public function notify()
    {
        if ($this->email && $this->email_notification) {
            if ($this->active) {
                Mail::to($this)->send(new AccountActivated($this->getBalance()));
            } else {
                Mail::to($this)->send(new AccountDeactivated());
            }
        }
        if ($this->phone && $this->phone_notification) {
            // instead SMS component
            Log::info('Account: phone: ' . $this->phone . ' ' . ($this->active ? 'Activated' : 'Deactivated'));
        }
    }

    /**
     * @param $data
     * @return JsonResponse|mixed
     */
    public static function deposit($data)
    {
        // Разбираем данные с проверкой на существование
        $id = Arr::get($data, 'account_id');
        $type = Arr::get($data, 'account_type');
        $loyalty_points_rule = Arr::get($data, 'loyalty_points_rule');
        $description = Arr::get($data, 'description');
        $payment_id = Arr::get($data, 'payment_id');
        $payment_amount = Arr::get($data, 'payment_amount');
        $payment_time = Arr::get($data, 'payment_time');

        Log::info('Deposit transaction input: ' . print_r($data, true));
        if ($id && in_array($type, ['phone', 'card', 'email'])) {
            if ($account = self::where($type, '=', $id)->first()) {
                /* @var $account self */
                if ($account->active) {
                    try {
                        $transaction = LoyaltyPointsTransaction::performPaymentLoyaltyPoints(
                            $account->id, $loyalty_points_rule, $description, $payment_id, $payment_amount, $payment_time
                        );
                        Log::info($transaction);
                        if ($account->email && $account->email_notification) {
                            Mail::to($account)->send(
                                new LoyaltyPointsReceived($transaction->points_amount, $account->getBalance())
                            );
                        }
                        if ($account->phone && $account->phone_notification) {
                            // instead SMS component
                            Log::info('You received' . $transaction->points_amount . 'Your balance' . $account->getBalance());
                        }
                        return $transaction;
                    } catch (\Exception $e) {
                        Log::info($e->getMessage());
                    }
                }
                Log::info('Account is not active');
                return response()->json(['message' => 'Account is not active'], 400);
            }
            Log::info('Account is not found');
            return response()->json(['message' => 'Account is not found'], 400);
        }
        Log::info('Wrong account parameters');
        throw new \InvalidArgumentException('Wrong account parameters');
    }

    /**
     * @param $data
     * @return JsonResponse|void
     */
    public static function cancel($data)
    {
        $reason = $data['cancellation_reason'];
        $transaction_id = $data['transaction_id'];
        if ($reason) {
            return response()->json(['message' => 'Cancellation reason is not specified'], 400);
        }

        if ($transaction = LoyaltyPointsTransaction::where('id', '=', $transaction_id)->where('canceled', '=', 0)->first()) {
            $transaction->canceled = time();
            $transaction->cancellation_reason = $reason;
            $transaction->save();
        } else {
            return response()->json(['message' => 'Transaction is not found'], 400);
        }
    }

    /**
     * @param $data
     * @return JsonResponse|mixed
     */
    public static function withdraw($data)
    {
        Log::info('Withdraw loyalty points transaction input: ' . print_r($data, true));
        $type = Arr::get($data,'account_type');
        $id = Arr::get($data,'account_id');
        $points_amount = Arr::get($data,'points_amount');
        if ($id && in_array($type, ['phone', 'card', 'email'])) {
            if ($account = self::where($type, '=', $id)->first()) {
                if ($account->active) {
                    if ($points_amount <= 0) {
                        Log::info('Wrong loyalty points amount: ' . $points_amount);
                        return response()->json(['message' => 'Wrong loyalty points amount'], 400);
                    }
                    if ($account->getBalance() < $points_amount) {
                        Log::info('Insufficient funds: ' . $points_amount);
                        return response()->json(['message' => 'Insufficient funds'], 400);
                    }

                    $transaction = LoyaltyPointsTransaction::withdrawLoyaltyPoints($account->id, $data['points_amount'], $data['description']);
                    Log::info($transaction);
                    return $transaction;
                }
                Log::info('Account is not active: ' . $type . ' ' . $id);
                return response()->json(['message' => 'Account is not active'], 400);
            }
            Log::info('Account is not found:' . $type . ' ' . $id);
            return response()->json(['message' => 'Account is not found'], 400);
        }
        Log::info('Wrong account parameters');
        throw new \InvalidArgumentException('Wrong account parameters');
    }
}
