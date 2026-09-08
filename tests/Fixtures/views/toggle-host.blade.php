<div>
    <x-xmorph::button @asChild class="btn-class"><a href="#">Link <span id="count">{{ $count }}</span></a></x-xmorph::button>
    <x-xmorph::button :asChild="$useChild" class="btn-dynamic"><a href="#">Dynamic</a></x-xmorph::button>
    <x-xmorph::button class="btn-plain"><a href="#">Plain</a></x-xmorph::button>
</div>
