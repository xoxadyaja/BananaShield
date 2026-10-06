@extends('layouts.app')
@section('title', 'Manage farms - BananaShield')
@section('content')
<div class="hero-bar manage-farms-hero">
    <div><h1 class="page-title">Manage farms.</h1><p class="page-copy">Add farms, document their blocks, and keep each banana plant reference easy to find.</p></div>
    <span class="record-count"><strong>{{ $farms->count() }}</strong><span>{{ \Illuminate\Support\Str::plural('farm', $farms->count()) }}</span></span>
</div>

<section class="manage-farms-layout">
    <aside class="manage-farms-actions" aria-label="Farm management actions">
        <form class="farm-create-form form-stack" method="POST" action="{{ route('farm-settings.farms.store') }}">
            @csrf
            <div><h2>Add a farm</h2><p>Enter the details you want to manage for this farm.</p></div>
            <label class="field"><span class="field-label">Farm name</span><input id="add-farm-name" required class="input" name="farm_name" value="{{ old('farm_name') }}" maxlength="120" placeholder="Example: San Isidro Banana Farm"></label>
            <label class="field"><span class="field-label">Barangay <small>Optional</small></span><input class="input" name="barangay" value="{{ old('barangay') }}" maxlength="120" placeholder="Example: San Isidro"></label>
            <label class="field"><span class="field-label">Municipality</span><input required class="input" name="municipality" value="{{ old('municipality') }}" maxlength="120" placeholder="Example: Padada"></label>
            <label class="field"><span class="field-label">Province</span><input required class="input" name="province" value="{{ old('province', 'Davao del Sur') }}" maxlength="120"></label>
            <label class="field"><span class="field-label">Total area <small>hectares &middot; optional</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" name="total_area_hectares" value="{{ old('total_area_hectares') }}" placeholder="Example: 12.5"></label>
            <label class="field"><span class="field-label">Primary banana varieties <small>Optional</small></span><input class="input" name="primary_varieties" value="{{ old('primary_varieties') }}" maxlength="1000" placeholder="Example: Cardava, Tundan"></label>
            <button class="btn btn-primary" type="submit">Add farm</button>
        </form>

        <section class="farm-list-section" aria-labelledby="registered-farms-heading">
        <div class="farm-list-heading">
            <div><h2 id="registered-farms-heading">Registered farms</h2><p>Select a farm to edit its details and registered blocks.</p></div>
            <span><strong>{{ $farms->count() }}</strong> {{ \Illuminate\Support\Str::plural('farm', $farms->count()) }}</span>
        </div>

        <div class="farm-record-list">
            @forelse($farms as $farm)
                <article class="farm-list-item">
                    <button type="button" class="farm-list-summary" data-open-farm-modal="farm-modal-{{ $farm->id }}" aria-haspopup="dialog" aria-controls="farm-modal-{{ $farm->id }}">
                        <span class="farm-list-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6"/></svg></span>
                        <span class="farm-list-primary">
                            <span class="farm-list-label">Registered farm</span>
                            <strong>{{ $farm->farm_name }}</strong>
                            <span>{{ collect([$farm->barangay, $farm->municipality, $farm->province])->filter()->implode(', ') ?: 'Location details not provided' }}</span>
                        </span>
                        <span class="farm-list-edit"><span>Manage farm</span><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></span>
                    </button>

                    <dialog id="farm-modal-{{ $farm->id }}" class="farm-manage-modal" data-farm-modal @if((string) old('_editing_farm_id') === (string) $farm->id) data-open-on-load @endif aria-labelledby="farm-modal-title-{{ $farm->id }}">
                        <div class="farm-manage-modal-shell">
                            <header class="farm-manage-modal-header">
                                <div><span>Manage farm</span><h2 id="farm-modal-title-{{ $farm->id }}">{{ $farm->farm_name }}</h2><p>{{ collect([$farm->barangay, $farm->municipality, $farm->province])->filter()->implode(', ') ?: 'Location details not provided' }}</p></div>
                                <button type="button" class="farm-modal-close" data-close-farm-modal aria-label="Close farm management"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
                            </header>
                            <div class="farm-list-editor farm-manage-modal-body">
                        <form class="farm-record-card" method="POST" action="{{ route('farm-settings.farms.update', $farm) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="_editing_farm_id" value="{{ $farm->id }}">
                            <div class="farm-editor-heading"><h3>Farm details</h3><p>Update the farm information below, then save your changes.</p></div>
                            <div class="field-grid farm-record-fields">
                                <label class="field"><span class="field-label">Farm name</span><input required class="input" name="farm_name" value="{{ $farm->farm_name }}" maxlength="120"></label>
                                <label class="field"><span class="field-label">Total area <small>hectares</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" name="total_area_hectares" value="{{ $farm->total_area_hectares }}"></label>
                                <label class="field"><span class="field-label">Barangay <small>Optional</small></span><input class="input" name="barangay" value="{{ $farm->barangay }}" maxlength="120"></label>
                                <label class="field"><span class="field-label">Municipality</span><input required class="input" name="municipality" value="{{ $farm->municipality }}" maxlength="120"></label>
                                <label class="field"><span class="field-label">Province</span><input required class="input" name="province" value="{{ $farm->province }}" maxlength="120"></label>
                                <label class="field"><span class="field-label">Primary banana varieties <small>Optional</small></span><input class="input" name="primary_varieties" value="{{ $farm->primary_varieties }}" maxlength="1000" placeholder="Example: Cardava, Tundan"></label>
                            </div>
                            <button class="btn btn-secondary" type="submit">Save farm details</button>
                        </form>

                        <section class="farm-blocks" aria-labelledby="farm-blocks-{{ $farm->id }}">
                            <div class="farm-blocks-heading">
                                <div><h3 id="farm-blocks-{{ $farm->id }}">Blocks and plant codenames</h3><p>Add new blocks here or update those already registered for this farm.</p></div>
                                <span class="farm-block-count"><strong>{{ $farm->sections->count() }}</strong> {{ \Illuminate\Support\Str::plural('block', $farm->sections->count()) }}</span>
                            </div>

                            @php($isEditingFarm = (string) old('_editing_farm_id') === (string) $farm->id)
                            @php($farmBatchBlocks = $isEditingFarm ? old('blocks', [[]]) : [[]])
                            @php($farmBatchBlocks = is_array($farmBatchBlocks) && count($farmBatchBlocks) ? $farmBatchBlocks : [[]])
                            @php($isOnlyDefaultBlock = count($farmBatchBlocks) === 1)
                            <div class="farm-inline-block-adder">
                                <div class="farm-inline-block-heading"><h4>Add blocks</h4><p>New blocks will be saved directly to {{ $farm->farm_name }}.</p></div>
                                <form id="farm-block-batch-{{ $farm->id }}" class="farm-block-batch" data-block-batch-form method="POST" action="{{ route('farm-settings.farms.blocks.batch-store', $farm) }}">
                                    @csrf
                                    <input type="hidden" name="_editing_farm_id" value="{{ $farm->id }}">
                                    @if($isEditingFarm && $errors->any())
                                        <p class="form-feedback-error" role="alert">{{ collect($errors->getMessages())->filter(fn ($messages, $key) => $key === 'blocks' || str_starts_with($key, 'blocks.'))->flatten()->first() }}</p>
                                    @endif
                                    <div class="farm-block-batch-rows" data-block-rows>
                                        @foreach($farmBatchBlocks as $index => $row)
                                            <fieldset class="farm-block-entry {{ $isOnlyDefaultBlock ? 'farm-block-entry-default' : '' }}" data-block-entry>
                                                <legend data-block-legend @if($isOnlyDefaultBlock) hidden @endif>Block <span data-block-number>{{ $index + 1 }}</span></legend>
                                                <div class="field-grid farm-block-fields">
                                                    <label class="field"><span class="field-label">Block name</span><input required class="input" data-block-field="blocks[__INDEX__][name]" name="blocks[{{ $index }}][name]" value="{{ data_get($row, 'name') }}" maxlength="120" placeholder="Example: North Block"></label>
                                                    <label class="field"><span class="field-label">Area <small>hectares, optional</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" data-block-field="blocks[__INDEX__][area_hectares]" name="blocks[{{ $index }}][area_hectares]" value="{{ data_get($row, 'area_hectares') }}" placeholder="Example: 4.25"></label>
                                                    <label class="field field-full"><span class="field-label">Plant codenames <small>One per line or separated by commas</small></span><textarea class="input" data-block-field="blocks[__INDEX__][plant_codenames]" name="blocks[{{ $index }}][plant_codenames]" maxlength="2000" rows="3" placeholder="Example: NB-P001&#10;NB-P002">{{ data_get($row, 'plant_codenames') }}</textarea></label>
                                                    <label class="field field-full"><span class="field-label">Block notes <small>Optional</small></span><textarea class="input" data-block-field="blocks[__INDEX__][notes]" name="blocks[{{ $index }}][notes]" maxlength="1000" rows="2" placeholder="Landmark, planting detail, or management note">{{ data_get($row, 'notes') }}</textarea></label>
                                                </div>
                                                <button class="block-remove" type="button" data-remove-block @if($isOnlyDefaultBlock) hidden @endif>Remove this block</button>
                                            </fieldset>
                                        @endforeach
                                    </div>
                                    <template data-block-template><fieldset class="farm-block-entry" data-block-entry><legend data-block-legend>Block <span data-block-number></span></legend><div class="field-grid farm-block-fields"><label class="field"><span class="field-label">Block name</span><input required class="input" data-block-field="blocks[__INDEX__][name]" maxlength="120" placeholder="Example: North Block"></label><label class="field"><span class="field-label">Area <small>hectares, optional</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" data-block-field="blocks[__INDEX__][area_hectares]" placeholder="Example: 4.25"></label><label class="field field-full"><span class="field-label">Plant codenames <small>One per line or separated by commas</small></span><textarea class="input" data-block-field="blocks[__INDEX__][plant_codenames]" maxlength="2000" rows="3" placeholder="Example: NB-P001&#10;NB-P002"></textarea></label><label class="field field-full"><span class="field-label">Block notes <small>Optional</small></span><textarea class="input" data-block-field="blocks[__INDEX__][notes]" maxlength="1000" rows="2" placeholder="Landmark, planting detail, or management note"></textarea></label></div><button class="block-remove" type="button" data-remove-block>Remove this block</button></fieldset></template>
                                    <div class="farm-block-batch-actions"><button class="btn btn-secondary" type="button" data-add-block>Add another block</button><button class="btn btn-primary" type="submit">Save blocks</button></div>
                                </form>
                            </div>

                            <div class="farm-block-list">
                                @forelse($farm->sections as $block)
                                    <form class="farm-block-form" method="POST" action="{{ route('farm-settings.farms.blocks.update', [$farm, $block]) }}">
                                        @csrf @method('PATCH')
                                        <div class="farm-block-head"><h4>{{ $block->name }}</h4><span class="case-status {{ $block->active ? 'success' : 'neutral' }}">{{ $block->active ? 'Active' : 'Inactive' }}</span></div>
                                        <div class="field-grid farm-block-fields">
                                            <label class="field"><span class="field-label">Block name</span><input required class="input" name="name" value="{{ $block->name }}" maxlength="120"></label>
                                            <label class="field"><span class="field-label">Area <small>hectares, optional</small></span><input type="number" min="0" max="999999.99" step="0.01" class="input" name="area_hectares" value="{{ $block->area_hectares }}"></label>
                                            <label class="field field-full"><span class="field-label">Plant codenames <small>One per line or separated by commas</small></span><textarea class="input" name="plant_codenames" maxlength="2000" rows="3" placeholder="Example: NB-P001&#10;NB-P002">{{ implode("\n", $block->plant_codenames ?? []) }}</textarea></label>
                                            <label class="field field-full"><span class="field-label">Block notes <small>Optional</small></span><textarea class="input" name="notes" maxlength="1000" rows="2" placeholder="Landmark, planting detail, or management note">{{ $block->notes }}</textarea></label>
                                        </div>
                                        <button class="btn btn-secondary" type="submit">Save block details</button>
                                    </form>
                                @empty
                                    <p class="farm-block-empty">No blocks recorded for this farm yet. Use Add blocks above to record the first one.</p>
                                @endforelse
                            </div>
                        </section>
                            </div>
                        </div>
                    </dialog>
                </article>
            @empty
                <div class="empty-state farm-empty"><span class="empty-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 20h18M5 20V9l7-5 7 5v11M9 20v-6h6v6"/></svg></span><h2>No farms yet</h2><p>Add the first farm to begin keeping its location, blocks, and banana-plant references organized.</p></div>
            @endforelse
        </div>
    </section>
    </aside>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-block-batch-form]').forEach((blockForm) => {
    const rows = blockForm.querySelector('[data-block-rows]');
    const template = blockForm.querySelector('template');
    const addButton = blockForm.querySelector('[data-add-block]');
    const maximumBlocks = 20;

    const refresh = () => {
        const entries = [...rows.querySelectorAll('[data-block-entry]')];
        const hasOnlyOneEntry = entries.length === 1;

        entries.forEach((entry, index) => {
            entry.classList.toggle('farm-block-entry-default', hasOnlyOneEntry);
            entry.querySelector('[data-block-number]').textContent = index + 1;
            entry.querySelectorAll('[data-block-field]').forEach((field) => {
                field.name = field.dataset.blockField.replace('__INDEX__', index);
            });
            entry.querySelector('[data-block-legend]').hidden = hasOnlyOneEntry;
            entry.querySelector('[data-remove-block]').hidden = hasOnlyOneEntry;
        });

        addButton.disabled = entries.length >= maximumBlocks;
    };

    addButton.addEventListener('click', () => {
        if (rows.querySelectorAll('[data-block-entry]').length >= maximumBlocks) return;
        rows.append(template.content.cloneNode(true));
        refresh();
        rows.lastElementChild.querySelector('input')?.focus();
    });

    blockForm.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-block]');
        if (! removeButton) return;
        removeButton.closest('[data-block-entry]').remove();
        refresh();
    });

    refresh();
});
document.querySelectorAll('[data-farm-modal]').forEach((farmModal) => {
    const closeModal = () => farmModal.close();

    farmModal.querySelector('[data-close-farm-modal]').addEventListener('click', closeModal);
    farmModal.addEventListener('click', (event) => {
        if (event.target === farmModal) closeModal();
    });
    farmModal.addEventListener('close', () => {
        document.documentElement.classList.remove('farm-modal-open');
    });

    if (farmModal.hasAttribute('data-open-on-load')) {
        farmModal.showModal();
        document.documentElement.classList.add('farm-modal-open');
    }
});

document.querySelectorAll('[data-open-farm-modal]').forEach((openButton) => {
    openButton.addEventListener('click', () => {
        const farmModal = document.getElementById(openButton.dataset.openFarmModal);
        if (! farmModal || farmModal.open) return;

        farmModal.showModal();
        document.documentElement.classList.add('farm-modal-open');
    });
});
</script>
@endpush