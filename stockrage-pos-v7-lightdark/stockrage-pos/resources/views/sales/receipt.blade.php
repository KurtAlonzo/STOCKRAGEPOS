@php
    $business = \App\Models\Setting::get('business_name', 'Takoyaki POS');
    $footer = \App\Models\Setting::get('receipt_footer', 'Thank you! Come again.');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $sale->id }} · Stockrage POS</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e5e8; font: 13px/1.45 ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; color: #111; }
        .bar { max-width: 340px; margin: 18px auto 0; display: flex; gap: 8px; justify-content: space-between; }
        .btn { display: inline-block; padding: 9px 14px; border-radius: 8px; border: 0; font: 600 13px system-ui, sans-serif; text-decoration: none; cursor: pointer; }
        .btn-primary { background: #d6151e; color: #fff; }
        .btn-light { background: #fff; color: #111; border: 1px solid #c9c9cf; }
        .paper { width: 340px; margin: 12px auto 28px; background: #fff; padding: 22px 20px 26px; box-shadow: 0 6px 24px rgba(0,0,0,.18); }
        .center { text-align: center; }
        .logo { height: 42px; width: auto; }
        h1 { font: 700 15px/1.3 system-ui, sans-serif; margin: 8px 0 0; letter-spacing: .04em; text-transform: uppercase; }
        .muted { color: #555; }
        hr { border: 0; border-top: 1px dashed #999; margin: 12px 0; }
        .row { display: flex; justify-content: space-between; gap: 10px; }
        .item { margin: 6px 0; }
        .item .name { font-weight: 700; }
        .total { font-size: 16px; font-weight: 800; }
        .void { margin: 10px 0; border: 2px solid #b30f18; color: #b30f18; text-align: center; font-weight: 800; letter-spacing: .15em; padding: 4px; }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; }
            .bar { display: none; }
            .paper { width: 80mm; margin: 0; padding: 6mm 5mm; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <a href="{{ route('sales.show', $sale) }}" class="btn btn-light">← Back to sale</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print receipt</button>
    </div>

    <div class="paper">
        <div class="center">
            <img src="{{ asset('images/stockrage-logo.png') }}" alt="Stockrage POS" class="logo">
            <h1>{{ $business }}</h1>
        </div>

        <hr>
        <div class="row"><span>Receipt</span><strong>#{{ $sale->id }}</strong></div>
        <div class="row"><span>Date</span><span>{{ $sale->created_at->format('M j, Y g:i A') }}</span></div>
        <div class="row"><span>Cashier</span><span>{{ $sale->cashier?->name ?? '—' }}</span></div>
        <div class="row"><span>Payment</span><span style="text-transform: capitalize">{{ $sale->payment_method }}</span></div>
        <hr>

        @if($sale->isVoided())
            <div class="void">VOIDED</div>
        @endif

        @foreach($sale->items as $item)
            <div class="item">
                <div class="name">{{ $item->product->name }}</div>
                <div class="row muted">
                    <span>{{ $item->qty }} × ₱{{ number_format($item->price, 2) }}</span>
                    <span>₱{{ number_format($item->subtotal, 2) }}</span>
                </div>
            </div>
        @endforeach

        <hr>
        <div class="row total"><span>TOTAL</span><span>₱{{ number_format($sale->total, 2) }}</span></div>
        <div class="row"><span>Amount paid</span><span>₱{{ number_format($sale->amount_paid, 2) }}</span></div>
        <div class="row"><span>Change</span><span>₱{{ number_format($sale->change_amount, 2) }}</span></div>
        <div class="row muted"><span>Items</span><span>{{ $sale->items->sum('qty') }}</span></div>

        @if($footer)
            <hr>
            <p class="center muted" style="margin: 0">{{ $footer }}</p>
        @endif
    </div>

    @if(request()->boolean('print'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
