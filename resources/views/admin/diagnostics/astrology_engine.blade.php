@extends('admin.layouts.admin')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="h3 text-gray-800 mb-1">
                <i class="fa-solid fa-microchip text-primary me-2"></i>
                Astrology Engine — PHP FFI Diagnostic Tool
            </h2>
            <p class="text-muted small mb-0">
                Hostinger Web Runtime Compatibility & Native Binary Execution Assessment
            </p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyDiagnosticJson()">
                <i class="fa-regular fa-copy me-1"></i> Copy JSON
            </button>
            <a href="{{ route('admin.diagnostics.astrology.export', ['format' => 'json']) }}" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-file-code me-1"></i> Export JSON
            </a>
            <a href="{{ route('admin.diagnostics.astrology.export', ['format' => 'txt']) }}" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-download me-1"></i> Download Text Report
            </a>
        </div>
    </div>

    <!-- Overall Verdict Alert Box -->
    @php
        $verdict = $diagnostics['overall_verdict'];
        $alertClass = match($verdict['level']) {
            'SUCCESS' => 'alert-success border-success',
            'PARTIAL_SUCCESS' => 'alert-warning border-warning',
            default => 'alert-danger border-danger'
        };
        $iconClass = match($verdict['level']) {
            'SUCCESS' => 'fa-circle-check text-success',
            'PARTIAL_SUCCESS' => 'fa-triangle-exclamation text-warning',
            default => 'fa-circle-xmark text-danger'
        };
    @endphp
    <div class="alert {{ $alertClass }} shadow-sm mb-4">
        <div class="d-flex align-items-start">
            <div class="fs-2 me-3 mt-1">
                <i class="fa-solid {{ $iconClass }}"></i>
            </div>
            <div>
                <h4 class="alert-heading fw-bold mb-1">{{ $verdict['title'] }}</h4>
                <p class="mb-2">{{ $verdict['summary'] }}</p>
                <div class="small fw-semibold">
                    <span class="badge bg-dark me-1">Runtime: {{ $diagnostics['context'] }} ({{ $diagnostics['php_environment']['php_sapi']['value'] }})</span>
                    <span class="badge bg-secondary me-1">Timestamp: {{ $diagnostics['timestamp'] }}</span>
                </div>
                <hr class="my-2">
                <p class="small mb-0"><strong>Recommended Next Step:</strong> {{ $verdict['next_step'] }}</p>
            </div>
        </div>
    </div>

    <!-- Notice Box: Web vs CLI SAPI distinction -->
    <div class="card border-0 shadow-sm mb-4 bg-light">
        <div class="card-body py-2 px-3 small text-muted d-flex align-items-center">
            <i class="fa-solid fa-info-circle text-info me-2 fs-5"></i>
            <div>
                <strong>Important Hostinger Note:</strong> This page tests the <strong>Web PHP runtime</strong> (<code>{{ $diagnostics['php_environment']['php_sapi']['value'] }}</code>). 
                To test the <strong>CLI PHP runtime</strong>, connect via SSH or Hostinger Web Terminal and execute:
                <code class="text-dark bg-white px-2 py-1 rounded border ms-1">php artisan astrology:diagnose</code>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Section 1: PHP Environment -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fa-brands fa-php text-primary me-2"></i>
                        1. PHP Environment Directives
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Directive / Setting</th>
                                    <th>Detected Value</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                                @foreach($diagnostics['php_environment'] as $key => $item)
                                    @php
                                        $badgeClass = match($item['status']) {
                                            'PASS' => 'bg-success',
                                            'FAIL' => 'bg-danger',
                                            'WARNING' => 'bg-warning text-dark',
                                            default => 'bg-secondary'
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item['label'] }}</div>
                                            @if(!empty($item['detail']))
                                                <div class="text-muted" style="font-size: 0.8rem;">{{ $item['detail'] }}</div>
                                            @endif
                                        </td>
                                        <td><code class="text-dark">{{ $item['value'] }}</code></td>
                                        <td><span class="badge {{ $badgeClass }}">{{ $item['status'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: FFI Core & Native Library Tests -->
        <div class="col-lg-6">
            <div class="card shadow-sm mb-4 border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fa-solid fa-gears text-primary me-2"></i>
                        2. FFI Core Test (Standard C Library)
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $ffiCore = $diagnostics['ffi_core_test'];
                        $coreBadge = match($ffiCore['status']) {
                            'PASS' => 'bg-success',
                            'BLOCKED' => 'bg-warning text-dark',
                            default => 'bg-danger'
                        };
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-semibold">Test Call: <code>abs(-42)</code></span>
                        <span class="badge {{ $coreBadge }} fs-6">{{ $ffiCore['status'] }}</span>
                    </div>

                    <ul class="list-group list-group-flush small mb-3">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Expected Value:</span>
                            <span class="fw-bold">{{ $ffiCore['expected_value'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Actual Returned Value:</span>
                            <span class="fw-bold {{ $ffiCore['actual_value'] === 42 ? 'text-success' : 'text-danger' }}">
                                {{ $ffiCore['actual_value'] !== null ? $ffiCore['actual_value'] : 'null (Call failed)' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Library Linked:</span>
                            <code>{{ $ffiCore['library_used'] ?: 'None' }}</code>
                        </li>
                        @if($ffiCore['error_message'])
                        <li class="list-group-item px-0 text-danger">
                            <strong>Error Captured:</strong>
                            <div class="p-2 bg-light rounded mt-1 font-monospace" style="word-break: break-all; font-size: 0.75rem;">
                                {{ $ffiCore['error_message'] }}
                            </div>
                        </li>
                        @endif
                    </ul>
                    <p class="small text-muted mb-0"><strong>Analysis:</strong> {{ $ffiCore['analysis'] }}</p>
                </div>
            </div>

            <!-- Section 3: Custom Native Library Test -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fa-solid fa-cube text-primary me-2"></i>
                        3. Custom Shared Library Test (Application Storage)
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $native = $diagnostics['native_library_test'];
                        $nativeBadge = match($native['status']) {
                            'PASS' => 'bg-success',
                            'NOT_TESTED' => 'bg-info text-dark',
                            'BLOCKED' => 'bg-warning text-dark',
                            default => 'bg-danger'
                        };
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-semibold">Custom Binary Loading</span>
                        <span class="badge {{ $nativeBadge }} fs-6">{{ $native['status'] }}</span>
                    </div>

                    <ul class="list-group list-group-flush small mb-3">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Target Path:</span>
                            <code style="font-size: 0.75rem;">{{ $native['target_file'] }}</code>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">File Present:</span>
                            <span class="badge {{ $native['file_exists'] ? 'bg-success' : 'bg-secondary' }}">
                                {{ $native['file_exists'] ? 'YES' : 'NO' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Is Readable:</span>
                            <span class="badge {{ $native['is_readable'] ? 'bg-success' : 'bg-secondary' }}">
                                {{ $native['is_readable'] ? 'YES' : 'NO' }}
                            </span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Test Function Output:</span>
                            <span class="fw-bold">{{ $native['actual_value'] !== null ? $native['actual_value'] : 'N/A' }}</span>
                        </li>
                        @if($native['error_message'])
                        <li class="list-group-item px-0 text-danger">
                            <strong>Notice:</strong>
                            <div class="p-2 bg-light rounded mt-1 font-monospace" style="word-break: break-all; font-size: 0.75rem;">
                                {{ $native['error_message'] }}
                            </div>
                        </li>
                        @endif
                    </ul>
                    <p class="small text-muted mb-2"><strong>Analysis:</strong> {{ $native['analysis'] }}</p>
                    <div class="bg-light p-2 rounded small border">
                        <div class="fw-bold mb-1">To test on Hostinger Linux server:</div>
                        <code class="d-block text-dark font-monospace" style="font-size: 0.75rem;">
                            {{ $native['build_instruction'] }}
                        </code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Filesystem & Process Restrictions -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fa-solid fa-folder-tree text-primary me-2"></i>
                        4. Filesystem & Execution Diagnostics
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Target Path</th>
                                    <th>Permissions</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($diagnostics['filesystem_and_security'] as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $item['label'] }}</div>
                                            <code class="text-muted" style="font-size: 0.75rem;">{{ $item['path'] }}</code>
                                        </td>
                                        <td>
                                            <span class="badge {{ $item['is_readable'] ? 'bg-success' : 'bg-danger' }}">
                                                Read: {{ $item['is_readable'] ? 'YES' : 'NO' }}
                                            </span>
                                            @if(isset($item['is_writable']))
                                                <span class="badge {{ $item['is_writable'] ? 'bg-success' : 'bg-danger' }}">
                                                    Write: {{ $item['is_writable'] ? 'YES' : 'NO' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $item['status'] === 'PASS' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                {{ $item['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: Candidate Package Feasibility Matrix -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold">
                        <i class="fa-solid fa-vial-circle-check text-primary me-2"></i>
                        5. Astrology Candidate Compatibility
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Engine / Candidate</th>
                                    <th>Runtime Feasibility</th>
                                    <th>Commercial Licensing</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($diagnostics['astrology_candidates'] as $pkg)
                                    @php
                                        $fBadge = str_contains($pkg['feasibility_status'], 'VIABLE') ? 'bg-success' : 'bg-danger';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $pkg['name'] }}</div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                Native Libs: {{ implode(', ', $pkg['native_binaries']) }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $fBadge }}">{{ $pkg['feasibility_status'] }}</span>
                                            <div class="text-muted mt-1" style="font-size: 0.75rem;">{{ $pkg['reason'] }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">{{ $pkg['commercial_licensing'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Raw JSON container for Clipboard copy -->
    <textarea id="rawDiagnosticJson" style="display: none;">{{ $jsonReport }}</textarea>

</div>

<script>
function copyDiagnosticJson() {
    const jsonText = document.getElementById('rawDiagnosticJson').value;
    navigator.clipboard.writeText(jsonText).then(() => {
        alert("Diagnostic report copied to clipboard successfully!");
    }).catch(err => {
        console.error("Copy failed: ", err);
    });
}
</script>
@endsection
