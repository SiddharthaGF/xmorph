{{-- Conditional-attributes variant slot child (single root). --}}
{{-- Parent attributes for this fixture are expected to be built via --}}
{{-- $attributes->merge(['class' => ...])->class([...]) so conditional --}}
{{-- classes/styles resolve through ComponentAttributeBag semantics --}}
{{-- before AsChild::renderSlot() delegates to RootElementParser. --}}
<button type="button" class="btn" wire:click="save" x-data="{ open: false }">Save</button>
