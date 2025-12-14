<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Asset;
use App\Services\OrderMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected $matchingService;

    public function __construct(OrderMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'nullable|string|in:BTC,ETH',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Order::where('status', Order::STATUS_OPEN);

        if ($request->has('symbol')) {
            $query->where('symbol', $request->symbol);
        }

        $orders = $query->with('user:id,name')
            ->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'buy_orders' => $orders->where('side', 'buy')->values(),
            'sell_orders' => $orders->where('side', 'sell')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string|in:BTC,ETH',
            'side' => 'required|string|in:buy,sell',
            'price' => 'required|numeric|min:0.00000001',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        try {
            $order = DB::transaction(function () use ($request, $user) {
                $user = $user->lockForUpdate()->find($user->id);

                $totalCost = $request->price * $request->amount;

                if ($request->side === 'buy') {
                    if ($user->balance < $totalCost) {
                        throw new \Exception('Insufficient balance');
                    }

                    $user->balance = $user->balance - $totalCost;
                    $user->save();
                } else {
                    $asset = Asset::firstOrCreate(
                        ['user_id' => $user->id, 'symbol' => $request->symbol],
                        ['amount' => 0, 'locked_amount' => 0]
                    );

                    $asset = $asset->lockForUpdate()->find($asset->id);

                    $available = $asset->amount - $asset->locked_amount;

                    if ($available < $request->amount) {
                        throw new \Exception('Insufficient asset amount');
                    }

                    $asset->locked_amount = $asset->locked_amount + $request->amount;
                    $asset->save();
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'symbol' => $request->symbol,
                    'side' => $request->side,
                    'price' => $request->price,
                    'amount' => $request->amount,
                    'status' => Order::STATUS_OPEN,
                ]);

                return $order;
            });

            $this->matchingService->matchOrder($order);

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->fresh(),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        try {
            DB::transaction(function () use ($user, $id) {
                $order = Order::where('id', $id)
                    ->where('user_id', $user->id)
                    ->where('status', Order::STATUS_OPEN)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedUser = $user->lockForUpdate()->find($user->id);

                if ($order->side === 'buy') {
                    $refund = $order->price * $order->amount;
                    $lockedUser->balance = $lockedUser->balance + $refund;
                    $lockedUser->save();
                } else {
                    $asset = Asset::where('user_id', $user->id)
                        ->where('symbol', $order->symbol)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $asset->locked_amount = $asset->locked_amount - $order->amount;
                    $asset->save();
                }

                $order->status = Order::STATUS_CANCELLED;
                $order->save();
            });

            return response()->json(['message' => 'Order cancelled successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function match(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::find($request->order_id);

        if ($order->status !== Order::STATUS_OPEN) {
            return response()->json(['error' => 'Order is not open'], 400);
        }

        $matched = $this->matchingService->matchOrder($order);

        return response()->json([
            'matched' => $matched,
            'message' => $matched ? 'Order matched successfully' : 'No matching order found',
        ]);
    }
}
