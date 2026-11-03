@extends('admin.layouts.admin')

@section('title', '| Edit Personalized Daily Insight Rule')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="mb-0">Edit Interpretation Rule</h2>
        <p class="text-muted mb-0">Update Gochara transit rule <code>{{ $rule->rule_identifier }}</code>.</p>
    </div>
    <div>
        <a href="{{ route('daily-insight-rules.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left"></i> Back to Rules
        </a>
    </div>
</div>

@include('admin.layouts.partials.messages')

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('daily-insight-rules.update', $rule) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="rule_identifier" class="form-label">Rule Identifier <span class="text-danger">*</span></label>
                    <input type="text" name="rule_identifier" id="rule_identifier" class="form-control @error('rule_identifier') is-invalid @enderror" value="{{ old('rule_identifier', $rule->rule_identifier) }}" required>
                    @error('rule_identifier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                    <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                        @foreach ($categories as $catKey => $cat)
                            <option value="{{ $catKey }}" {{ old('category', $rule->category) === $catKey ? 'selected' : '' }}>
                                {{ $cat['name_en'] }} ({{ $cat['name_hi'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="tone" class="form-label">Tone <span class="text-danger">*</span></label>
                    <select name="tone" id="tone" class="form-select @error('tone') is-invalid @enderror" required>
                        @foreach ($tones as $tKey => $t)
                            <option value="{{ $tKey }}" {{ old('tone', $rule->tone) === $tKey ? 'selected' : '' }}>
                                {{ $t['label_en'] }} ({{ $t['label_hi'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('tone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="planet" class="form-label">Planet <span class="text-danger">*</span></label>
                    <select name="planet" id="planet" class="form-select @error('planet') is-invalid @enderror" required>
                        @foreach ($planets as $pKey => $p)
                            <option value="{{ $pKey }}" {{ old('planet', $rule->planet) === $pKey ? 'selected' : '' }}>
                                {{ $p['name_en'] }} ({{ $p['name_hi'] }})
                            </option>
                        @endforeach
                    </select>
                    @error('planet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="house" class="form-label">House from Moon (1-12) <span class="text-danger">*</span></label>
                    <select name="house" id="house" class="form-select @error('house') is-invalid @enderror" required>
                        @for ($h = 1; $h <= 12; $h++)
                            <option value="{{ $h }}" {{ old('house', $rule->house) == $h ? 'selected' : '' }}>House {{ $h }}</option>
                        @endfor
                    </select>
                    @error('house')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-3">
                    <label for="is_retrograde" class="form-label">Retrograde Filter</label>
                    <select name="is_retrograde" id="is_retrograde" class="form-select">
                        <option value="" {{ old('is_retrograde', $rule->is_retrograde) === null ? 'selected' : '' }}>Any Motion</option>
                        <option value="1" {{ old('is_retrograde', $rule->is_retrograde) === true || old('is_retrograde', $rule->is_retrograde) === 1 ? 'selected' : '' }}>Retrograde Only</option>
                        <option value="0" {{ old('is_retrograde', $rule->is_retrograde) === false || old('is_retrograde', $rule->is_retrograde) === 0 ? 'selected' : '' }}>Direct Only</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="priority" class="form-label">Priority (1-100) <span class="text-danger">*</span></label>
                    <input type="number" name="priority" id="priority" class="form-control" value="{{ old('priority', $rule->priority) }}" min="1" max="100" required>
                </div>

                <div class="col-md-6">
                    <label for="headline_en" class="form-label">Headline (English) <span class="text-danger">*</span></label>
                    <input type="text" name="headline_en" id="headline_en" class="form-control @error('headline_en') is-invalid @enderror" value="{{ old('headline_en', $rule->headline_en) }}" required>
                    @error('headline_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="headline_hi" class="form-label">Headline (Hindi) <span class="text-danger">*</span></label>
                    <input type="text" name="headline_hi" id="headline_hi" class="form-control @error('headline_hi') is-invalid @enderror" value="{{ old('headline_hi', $rule->headline_hi) }}" required>
                    @error('headline_hi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="interpretation_en" class="form-label">Interpretation (English) <span class="text-danger">*</span></label>
                    <textarea name="interpretation_en" id="interpretation_en" rows="5" class="form-control @error('interpretation_en') is-invalid @enderror" required>{{ old('interpretation_en', $rule->interpretation_en) }}</textarea>
                    @error('interpretation_en')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="interpretation_hi" class="form-label">Interpretation (Hindi) <span class="text-danger">*</span></label>
                    <textarea name="interpretation_hi" id="interpretation_hi" rows="5" class="form-control @error('interpretation_hi') is-invalid @enderror" required>{{ old('interpretation_hi', $rule->interpretation_hi) }}</textarea>
                    @error('interpretation_hi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $rule->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Rule is Active and Enabled for Calculations</label>
                    </div>
                </div>

                <div class="col-12 mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa fa-save"></i> Update Rule
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
