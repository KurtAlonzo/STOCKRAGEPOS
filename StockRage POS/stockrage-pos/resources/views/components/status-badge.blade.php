@props(['product'])
@if($product->status === 'out')
    <span class="badge-out">Out of stock</span>
@elseif($product->status === 'low')
    <span class="badge-low">Low stock</span>
@else
    <span class="badge-ok">OK</span>
@endif
