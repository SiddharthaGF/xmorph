@php
[$xmorphIsAsChild, $xmorphAttributes] = \SiddharthaGF\XMorph\XmorphServiceProvider::consumeAsChildFlag($attributes);
$xmorphIsAsChild = ($__xmorphAsChild ?? false) || $xmorphIsAsChild;
@endphp
@if ($xmorphIsAsChild)
{!! \SiddharthaGF\XMorph\AsChild::renderSlot($xmorphAttributes, $slot->toHtml()) !!}
@else
<button {{ $xmorphAttributes->merge(['type' => 'button']) }}>{{ $slot }}</button>
@endif
