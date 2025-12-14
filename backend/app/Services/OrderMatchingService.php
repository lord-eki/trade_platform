<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Asset;
use App\Models\Trade;
use App\Events\OrderMatched;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderMatchingService
{
    const COMMISSION_RATE = 0.015; // 1.5%

    public function matchOrder(Order $newOrder): bool
    {
        return DB::transaction(function () use ($newOrder) {
            // Lock the new order for update
            $order = Order::where('id', $newOrder->id)
                ->where('status', Order::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return false;
            }

            // Find matching counter order
            $counterOrder = $this->findCounterOrder($order);

            if (!$counterOrder) {
                return false;
            }

            // Execute the match
            return $this->executeMatch($order, $counterOrder);
        });
    }

    private function findCounterOrder(Order $order): ?Order
    {
        $query = Order::where('symbol', $order->symbol)
            ->where('status', Order::STATUS_OPEN)
            ->where('user_id', '!=', $order->user_id)
            ->lockForUpdate();

        if ($order->side === 'buy') {
            // Buy order: find sell order with price <= buy price
            return $query->where('side', 'sell')
                ->where('price', '<=', $order->price)
                ->orderBy('price', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();
        } else {
            // Sell order: find buy order with price >= sell price
            return $query->where('side', 'buy')
                ->where('price', '>=', $order->price)
                ->orderBy('price', 'desc')
                ->orderBy('created_at', 'asc')
                ->first();
        }
    }

    private function executeMatch(Order $order1, Order $order2): bool
    {
        // Determine buyer and seller
        $buyOrder = $order1->side === 'buy' ? $order1 : $order2;
        $sellOrder = $order1->side === 'sell' ? $order1 : $order2;

        // Use the older order's price - price-time priority
        $matchPrice = $sellOrder->created_at < $buyOrder->created_at 
            ? $sellOrder->price 
            : $buyOrder->price;

        // Match amount is minimum of both orders
        $matchAmount = min($buyOrder->amount, $sellOrder->amount);
        $matchTotal = bcmul($matchPrice, $matchAmount, 8);
        $commission = bcmul($matchTotal, self::COMMISSION_RATE, 8);

        // Lock users for update
        $buyer = User::where('id', $buyOrder->user_id)->lockForUpdate()->first();
        $seller = User::where('id', $sellOrder->user_id)->lockForUpdate()->first();

        // Process buyer side
        $buyerTotalCost = bcmul($buyOrder->price, $matchAmount, 8);
        $refund = bcsub($buyerTotalCost, $matchTotal, 8);
        
        if (bccomp($refund, '0', 8) > 0) {
            $buyer->balance = bcadd($buyer->balance, $refund, 8);
        }
        
        // Deduct commission from buyer
        $buyer->balance = bcsub($buyer->balance, $commission, 8);
        $buyer->save();

        // Add asset to buyer
        $buyerAsset = Asset::firstOrCreate(
            ['user_id' => $buyer->id, 'symbol' => $buyOrder->symbol],
            ['amount' => 0, 'locked_amount' => 0]
        );
        $buyerAsset->amount = bcadd($buyerAsset->amount, $matchAmount, 8);
        $buyerAsset->save();

        // Process seller side
        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->first();

        $sellerAsset->locked_amount = bcsub($sellerAsset->locked_amount, $matchAmount, 8);
        $sellerAsset->save();

        // Credit seller 
        $seller->balance = bcadd($seller->balance, $matchTotal, 8);
        $seller->save();

        // Update order statuses
        $buyOrder->status = Order::STATUS_FILLED;
        $buyOrder->save();

        $sellOrder->status = Order::STATUS_FILLED;
        $sellOrder->save();

        // Create trade record
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => $buyOrder->symbol,
            'price' => $matchPrice,
            'amount' => $matchAmount,
            'total' => $matchTotal,
            'commission' => $commission,
        ]);

        // Broadcast events
        broadcast(new OrderMatched($buyer->id, $buyOrder, $trade))->toOthers();
        broadcast(new OrderMatched($seller->id, $sellOrder, $trade))->toOthers();

        return true;
    }
}