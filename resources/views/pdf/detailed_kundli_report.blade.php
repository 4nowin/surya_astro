<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $report['sections']['sec1_cover']['title'] ?? 'Astro Setu Detailed Kundli Report' }}</title>
    <style>
@if($locale === 'hi')
        @font-face {
            font-family: 'Noto Sans Devanagari';
            font-style: normal;
            font-weight: 400;
            src: url('{{ $fontRegular }}') format('truetype');
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            font-style: normal;
            font-weight: 700;
            src: url('{{ $fontBold }}') format('truetype');
        }
@endif
        @page {
            margin: 28px 30px 32px 30px;
            size: A4 portrait;
        }
        body {
            font-family: {{ $locale === 'hi' ? "'Noto Sans Devanagari', 'DejaVu Sans', sans-serif" : "'DejaVu Sans', Arial, sans-serif" }};
            font-size: 9px;
            color: #2D3748;
            line-height: 1.45;
            background-color: #FFFFFF;
        }
        .page-break {
            page-break-after: always;
        }
        .avoid-break {
            page-break-inside: avoid;
        }
        .header-bar {
            width: 100%;
            border-bottom: 2px solid #D4AF37;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header-bar table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo-img {
            max-height: 38px;
            max-width: 150px;
        }
        .brand-title {
            font-size: 16px;
            font-weight: bold;
            color: #8C1D40;
            letter-spacing: 0.5px;
        }
        .brand-sub {
            font-size: 8px;
            color: #718096;
        }
        .report-type-badge {
            text-align: right;
            font-size: 11px;
            font-weight: bold;
            color: #1A365D;
        }
        .report-meta-text {
            text-align: right;
            font-size: 8px;
            color: #718096;
        }

        /* Section Styling */
        .section-box {
            background-color: #FAFCFE;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 11px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #8C1D40;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .sub-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #1A365D;
            margin-top: 5px;
            margin-bottom: 3px;
        }

        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }
        table.data-table th {
            background-color: #EDF2F7;
            color: #2D3748;
            font-size: 8px;
            font-weight: bold;
            text-align: left;
            padding: 4px 5px;
            border: 1px solid #CBD5E0;
        }
        table.data-table td {
            font-size: 8px;
            padding: 3.5px 5px;
            border: 1px solid #E2E8F0;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #F7FAFC;
        }

        /* Callout Boxes */
        .alert-box {
            border-radius: 3px;
            padding: 6px 8px;
            margin-top: 6px;
            margin-bottom: 6px;
            font-size: 8px;
            line-height: 1.4;
        }
        .alert-info {
            background-color: #EBF8FF;
            border-left: 3px solid #3182CE;
            color: #2B6CB0;
        }
        .alert-warning {
            background-color: #FFFAF0;
            border-left: 3px solid #DD6B20;
            color: #C05621;
        }
        .alert-gold {
            background-color: #FEFCF6;
            border-left: 3px solid #D4AF37;
            color: #744210;
        }
        .alert-suppressed {
            background-color: #FFF5F5;
            border-left: 3px solid #E53E3E;
            color: #9B2C2C;
        }

        /* Grid Layouts via Fixed Tables */
        table.grid-2col {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.grid-2col td.col-left {
            width: 49%;
            vertical-align: top;
            padding-right: 1%;
        }
        table.grid-2col td.col-right {
            width: 49%;
            vertical-align: top;
            padding-left: 1%;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 7.5px;
            font-weight: bold;
        }
        .badge-certified {
            background-color: #C6F6D5;
            color: #22543D;
        }
        .badge-qualified {
            background-color: #FEFCBF;
            color: #744210;
        }
        .badge-suppressed {
            background-color: #FED7D7;
            color: #742A2A;
        }
        .badge-fallback {
            background-color: #E9D8FD;
            color: #44337A;
        }
    </style>
</head>
<body>

    {{-- Script for Dompdf dynamic page footer --}}
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $size = 8;
            $brandText = "Astro Setu — Vedic Astrology Platform";
            $pdf->page_text(30, 815, $brandText, $font, $size, [0.45, 0.5, 0.58]);
            $pdf->page_text(485, 815, "{{ $locale === 'hi' ? 'पृष्ठ' : 'Page' }} " . $PAGE_NUM . " / " . $PAGE_COUNT, $font, $size, [0.45, 0.5, 0.58]);
        }
    </script>

    @php
        $sec1 = $report['sections']['sec1_cover'] ?? [];
        $sec2 = $report['sections']['sec2_panchanga'] ?? [];
        $sec3 = $report['sections']['sec3_lagna_d1'] ?? [];
        $sec4 = $report['sections']['sec4_chandra'] ?? [];
        $sec5 = $report['sections']['sec5_navamsha_d9'] ?? [];
        $sec6 = $report['sections']['sec6_aspects'] ?? [];
        $sec7 = $report['sections']['sec7_nakshatra'] ?? [];
        $sec8 = $report['sections']['sec8_vimshottari_timeline'] ?? [];
        $sec9 = $report['sections']['sec9_current_dasha'] ?? [];
        $sec10 = $report['sections']['sec10_classical_yogas'] ?? [];
        $sec11 = $report['sections']['sec11_manglik_dosha'] ?? [];
        $sec12 = $report['sections']['sec12_saturn_transit'] ?? [];
        $sec13 = $report['sections']['sec13_remedies'] ?? [];
        $sec14 = $report['sections']['sec14_astrologer_referral'] ?? [];
        $sec15 = $report['sections']['sec15_precision_audit'] ?? [];
    @endphp

    {{-- ========================================== --}}
    {{-- SECTION 1: COVER PAGE                      --}}
    {{-- ========================================== --}}
    <div class="header-bar">
        <table>
            <tr>
                <td style="width: 50%;">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" class="logo-img" alt="Astro Setu"/>
                    @else
                        <div class="brand-title">ASTRO SETU</div>
                        <div class="brand-sub">Vedic Wisdom & Computational Precision</div>
                    @endif
                </td>
                <td style="width: 50%;">
                    <div class="report-type-badge">{{ $sec1['title'] ?? 'Vedic Janam Kundli' }}</div>
                    <div class="report-meta-text">{{ $sec1['report_version'] ?? 'M9.2' }} | {{ $report['meta']['generated_at'] ?? '' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-box" style="margin-top: 15px; padding: 14px 16px; border: 2px solid #D4AF37; background-color: #FFFDF8;">
        <div style="font-size: 15px; font-weight: bold; color: #8C1D40; text-align: center; margin-bottom: 4px;">
            {{ $sec1['name'] ?? 'Native' }}
        </div>
        <div style="font-size: 9px; color: #718096; text-align: center; margin-bottom: 12px;">
            {{ $locale === 'hi' ? 'वैदिक जन्म विवरण एवं खगोलीय निर्देशांक' : 'Vedic Birth Details & Astronomical Ephemeris Parameters' }}
        </div>

        <table class="data-table" style="margin-bottom: 0;">
            <tr>
                <th style="width: 25%;">{{ $locale === 'hi' ? 'जन्म तिथि' : 'Date of Birth' }}</th>
                <td style="width: 25%; font-weight: bold;">{{ $sec1['date_of_birth'] ?? '—' }}</td>
                <th style="width: 25%;">{{ $locale === 'hi' ? 'जन्म समय' : 'Birth Time' }}</th>
                <td style="width: 25%; font-weight: bold;">{{ $sec1['birth_time'] ?? ($locale === 'hi' ? 'अज्ञात / अनुमानित' : 'Unknown / Approximate') }}</td>
            </tr>
            <tr>
                <th>{{ $locale === 'hi' ? 'जन्म स्थान' : 'Birth Location' }}</th>
                <td>{{ $sec1['birth_city'] ?? '—' }}, {{ $sec1['birth_country'] ?? 'India' }}</td>
                <th>{{ $locale === 'hi' ? 'परिशुद्धता स्तर' : 'Precision Tier' }}</th>
                <td><span class="badge badge-certified">{{ strtoupper($sec1['precision_tier'] ?? 'verified') }}</span></td>
            </tr>
            <tr>
                <th>{{ $locale === 'hi' ? 'अक्षांश एवं देशांतर' : 'Coordinates' }}</th>
                <td>{{ round($sec1['latitude'] ?? 0, 4) }}°, {{ round($sec1['longitude'] ?? 0, 4) }}°</td>
                <th>{{ $locale === 'hi' ? 'समय क्षेत्र' : 'Timezone' }}</th>
                <td>{{ $sec1['timezone'] ?? 'UTC' }}</td>
            </tr>
            <tr>
                <th>{{ $locale === 'hi' ? 'अयनांश प्रणाली' : 'Ayanamsha' }}</th>
                <td>{{ $sec1['ayanamsa'] ?? 'True Chitra Lahiri' }}</td>
                <th>{{ $locale === 'hi' ? 'खगोलीय इंजन' : 'Ephemeris Engine' }}</th>
                <td>{{ $sec1['calculation_engine'] ?? 'Jayesh Mepani Moshier FFI' }}</td>
            </tr>
        </table>
    </div>

    {{-- Purushartha Framing Box --}}
    <div class="alert-box alert-gold" style="margin-top: 15px;">
        <div style="font-weight: bold; color: #744210; margin-bottom: 3px;">
            {{ $locale === 'hi' ? 'वैदिक पुरुषार्थ एवं कर्म दर्शन' : 'Classical Purushartha & Karmic Principle' }}
        </div>
        <div>{{ $sec1['purushartha_framing'] ?? '' }}</div>
    </div>

    <div class="alert-box alert-info" style="margin-top: 8px;">
        <div style="font-weight: bold; color: #2B6CB0; margin-bottom: 2px;">
            {{ $locale === 'hi' ? 'मार्गदर्शन एवं उपयोग अस्वीकरण' : 'Interpretive Notice' }}
        </div>
        <div>{{ $sec1['general_disclaimer'] ?? '' }}</div>
    </div>

    <div class="page-break"></div>

    {{-- ========================================== --}}
    {{-- SECTION 2: PANCHANGA & AVAKAHADA CHAKRA    --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec2['title'] ?? 'Panchanga & Avakahada Chakra' }}
            <span class="badge {{ $sec2['gate_b_status'] === 'certified' ? 'badge-certified' : 'badge-qualified' }}" style="float: right;">
                {{ strtoupper($sec2['gate_b_status'] ?? 'certified') }}
            </span>
        </div>

        <table class="grid-2col">
            <tr>
                <td class="col-left">
                    <div class="sub-title">{{ $locale === 'hi' ? 'दैनिक पंचांग' : 'Birth Panchanga Limbs' }}</div>
                    <table class="data-table">
                        <tr>
                            <th style="width: 40%;">{{ $locale === 'hi' ? 'तिथि' : 'Tithi' }}</th>
                            <td>{{ $sec2['panchanga']['tithi']['name'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'वार' : 'Vaara (Day)' }}</th>
                            <td>{{ $sec2['panchanga']['vara']['name'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'नक्षत्र' : 'Nakshatra' }}</th>
                            <td>{{ $sec2['panchanga']['nakshatra']['name'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'योग' : 'Yoga' }}</th>
                            <td>{{ $sec2['panchanga']['yoga']['name'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'करण' : 'Karana' }}</th>
                            <td>{{ $sec2['panchanga']['karana']['name'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'सूर्योदय / सूर्यास्त' : 'Sunrise / Sunset' }}</th>
                            <td>{{ $sec2['panchanga']['sunrise'] ?? '—' }} / {{ $sec2['panchanga']['sunset'] ?? '—' }}</td>
                        </tr>
                    </table>
                </td>
                <td class="col-right">
                    <div class="sub-title">{{ $locale === 'hi' ? 'अवकहड़ा चक्र' : 'Avakahada Attributes' }}</div>
                    <table class="data-table">
                        <tr>
                            <th style="width: 40%;">{{ $locale === 'hi' ? 'वर्ण' : 'Varna' }}</th>
                            <td>{{ $sec2['avakahada']['varna'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'वश्य' : 'Vashya' }}</th>
                            <td>{{ $sec2['avakahada']['vashya'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'योनि' : 'Yoni' }}</th>
                            <td>{{ $sec2['avakahada']['yoni'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'गण' : 'Gana' }}</th>
                            <td>{{ $sec2['avakahada']['gana'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'नाड़ी' : 'Nadi' }}</th>
                            <td>{{ $sec2['avakahada']['nadi'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ $locale === 'hi' ? 'नक्षत्र / राशि स्वामी' : 'Nakshatra / Rashi Lord' }}</th>
                            <td>{{ $sec2['avakahada']['nakshatra_lord'] ?? '—' }} / {{ $sec2['avakahada']['rashi_lord'] ?? '—' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        @if(!empty($sec2['transition_notes']))
            <div class="alert-box alert-warning">{{ $sec2['transition_notes'] }}</div>
        @endif

        {{-- Mandatory Nadi Non-Medical Disclaimer --}}
        <div class="alert-box alert-warning">
            <div style="font-weight: bold; margin-bottom: 2px;">{{ $locale === 'hi' ? 'नाड़ी स्वास्थ्य अस्वीकरण' : 'Nadi Health Notice' }}</div>
            <div>{{ $sec2['nadi_disclaimer'] ?? '' }}</div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 3: LAGNA KUNDLI (D-1) & PLANETS    --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec3['title'] ?? 'Lagna Kundli (D-1) & Planetary Table' }}
            <span class="badge {{ $sec3['gate_b_status'] === 'certified' ? 'badge-certified' : 'badge-suppressed' }}" style="float: right;">
                {{ strtoupper($sec3['gate_b_status'] ?? 'certified') }}
            </span>
        </div>

        @if(!empty($sec3['is_suppressed']))
            <div class="alert-box alert-suppressed">
                <div style="font-weight: bold; margin-bottom: 2px;">{{ $locale === 'hi' ? 'लग्न कुण्डली स्थगित सूचना' : 'Lagna Kundli Suppressed' }}</div>
                <div>{{ $sec3['reason'] ?? '' }}</div>
            </div>
        @else
            <table class="grid-2col">
                <tr>
                    <td style="width: 42%; vertical-align: middle; text-align: center;">
                        @if(!empty($sec3['svg']))
                            {!! $sec3['svg'] !!}
                        @endif
                    </td>
                    <td style="width: 58%; vertical-align: top; padding-left: 2%;">
                        <div class="sub-title">
                            {{ $locale === 'hi' ? 'लग्न: ' : 'Lagna (Ascendant): ' }}
                            {{ $sec3['ascendant']['name'] ?? '—' }} ({{ $sec3['ascendant']['longitude'] ?? '' }})
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">{{ $locale === 'hi' ? 'ग्रह' : 'Planet' }}</th>
                                    <th style="width: 25%;">{{ $locale === 'hi' ? 'राशि' : 'Sign' }}</th>
                                    <th style="width: 14%;">{{ $locale === 'hi' ? 'भाव' : 'House' }}</th>
                                    <th style="width: 23%;">{{ $locale === 'hi' ? 'नक्षत्र' : 'Nakshatra' }}</th>
                                    <th style="width: 18%;">{{ $locale === 'hi' ? 'अवस्था' : 'State' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sec3['planets'] as $p)
                                    <tr>
                                        <td style="font-weight: bold;">
                                            {{ $p['name'] }}
                                            @if($p['is_retrograde']) <span style="color:#C53030;">[R]</span> @endif
                                            @if($p['is_combust']) <span style="color:#DD6B20;">*</span> @endif
                                        </td>
                                        <td>{{ $p['rashi'] }}</td>
                                        <td style="text-align: center;">{{ $p['house'] }}</td>
                                        <td>{{ $p['nakshatra'] }} ({{ $p['pada'] }})</td>
                                        <td>{{ $p['is_retrograde'] ? 'वक्री / Ret' : 'मार्गी / Dir' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div style="font-size: 7.5px; color: #718096; margin-top: 2px;">
                            {{ $locale === 'hi' ? '[R] = वक्री ग्रह | * = अस्त (Combust) ग्रह' : '[R] = Retrograde Motion | * = Combust with Sun' }}
                        </div>
                    </td>
                </tr>
            </table>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 4: CHANDRA KUNDLI (MOON CHART)     --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec4['title'] ?? 'Chandra Kundli' }}
            <span class="badge {{ $sec4['gate_b_status'] === 'certified' ? 'badge-certified' : 'badge-suppressed' }}" style="float: right;">
                {{ strtoupper($sec4['gate_b_status'] ?? 'certified') }}
            </span>
        </div>

        @if(!empty($sec4['is_suppressed']))
            <div class="alert-box alert-suppressed">{{ $sec4['reason'] ?? '' }}</div>
        @else
            <table class="grid-2col">
                <tr>
                    <td style="width: 42%; vertical-align: middle; text-align: center;">
                        @if(!empty($sec4['svg']))
                            {!! $sec4['svg'] !!}
                        @endif
                    </td>
                    <td style="width: 58%; vertical-align: middle; padding-left: 2%;">
                        <div class="sub-title">{{ $locale === 'hi' ? 'चंद्र लग्न का शास्त्रीय महत्व' : 'Significance of Chandra Kundli' }}</div>
                        <p style="font-size: 8.5px; line-height: 1.45; color: #2D3748;">
                            {{ $sec4['summary'] ?? '' }}
                        </p>
                    </td>
                </tr>
            </table>
        @endif
    </div>

    <div class="page-break"></div>

    {{-- ========================================== --}}
    {{-- SECTION 5: D-9 NAVAMSHA & VARGOTTAMA       --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec5['title'] ?? 'D-9 Navamsha Chart & Vargottama' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        <table class="grid-2col">
            <tr>
                <td style="width: 42%; vertical-align: middle; text-align: center;">
                    @if(!empty($sec5['svg']))
                        {!! $sec5['svg'] !!}
                    @endif
                </td>
                <td style="width: 58%; vertical-align: top; padding-left: 2%;">
                    <div class="sub-title">{{ $locale === 'hi' ? 'वर्गोत्तम ग्रह (विशेष सामर्थ्य)' : 'Vargottama Planets (Peak Resilience)' }}</div>
                    @if(!empty($sec5['vargottama_planets']))
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>{{ $locale === 'hi' ? 'ग्रह' : 'Planet' }}</th>
                                    <th>{{ $locale === 'hi' ? 'समान राशि (D-1 व D-9)' : 'Invariant Sign' }}</th>
                                    <th>{{ $locale === 'hi' ? 'फल प्रभाव' : 'Influence' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sec5['vargottama_planets'] as $vp)
                                    <tr>
                                        <td style="font-weight: bold; color: #8C1D40;">{{ $vp['name'] }}</td>
                                        <td>{{ $vp['rashi'] }}</td>
                                        <td>{{ $locale === 'hi' ? 'समान राशि में स्थित होने से आंतरिक सुदृढ़ता एवं स्थिरता प्राप्त' : 'Occupies identical rashi in D-1 & D-9, conferring enduring strength' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div style="font-size: 8.5px; color: #718096; padding: 6px 0;">
                            {{ $locale === 'hi' ? 'इस पत्रिका में कोई ग्रह वर्गोत्तम नहीं है। ग्रह नवांश में विभिन्न राशियों में विभाजित हैं।' : 'No planets occupy identical signs in both D-1 and D-9 charts.' }}
                        </div>
                    @endif

                    @if(!empty($sec5['note']))
                        <div class="alert-box alert-info" style="margin-top: 6px;">
                            {{ $sec5['note'] }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 6: PARASHARI GRAHA DRISHTI MATRIX  --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec6['title'] ?? 'Parashari Graha Drishti Matrix' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        <div style="font-size: 8px; color: #718096; margin-bottom: 5px;">
            {{ $sec6['rule_clarification'] ?? '' }}
        </div>

        @if(!empty($sec6['aspects']))
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 25%;">{{ $locale === 'hi' ? 'दृष्टि कर्ता ग्रह' : 'Aspecting Planet' }}</th>
                        <th style="width: 25%;">{{ $locale === 'hi' ? 'दृष्टि प्राप्त ग्रह' : 'Aspect Receiving Planet' }}</th>
                        <th style="width: 25%;">{{ $locale === 'hi' ? 'दृष्टि प्रकार' : 'Aspect Type' }}</th>
                        <th style="width: 25%;">{{ $locale === 'hi' ? 'दृष्टि बल' : 'Strength' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sec6['aspects'] as $asp)
                        <tr>
                            <td style="font-weight: bold; color: #1A365D;">{{ $asp['source'] }}</td>
                            <td style="font-weight: bold;">{{ $asp['target'] }}</td>
                            <td>{{ $asp['type'] }}</td>
                            <td>{{ $asp['strength'] }}% (पूर्ण / Full)</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="font-size: 8.5px; color: #718096;">
                {{ $locale === 'hi' ? 'कोई पारस्परिक पूर्ण दृष्टि संबंध उपस्थित नहीं है।' : 'No mutual full Parashari aspects between classical planets.' }}
            </div>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 7: JANMA NAKSHATRA DEEP DIVE       --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec7['title'] ?? 'Janma Nakshatra Deep Dive' }}
            <span class="badge {{ $sec7['gate_b_status'] === 'certified' ? 'badge-certified' : 'badge-qualified' }}" style="float: right;">
                {{ strtoupper($sec7['gate_b_status'] ?? 'certified') }}
            </span>
        </div>

        <div style="font-size: 11px; font-weight: bold; color: #8C1D40; margin-bottom: 4px;">
            {{ $sec7['nakshatra_name'] ?? '' }} ({{ $locale === 'hi' ? 'चरण ' : 'Pada ' }}{{ $sec7['pada'] ?? 1 }})
        </div>

        <table class="data-table" style="margin-bottom: 6px;">
            <tr>
                <th style="width: 20%;">{{ $locale === 'hi' ? 'अधिष्ठाता देव' : 'Presiding Deity' }}</th>
                <td style="width: 30%;">{{ $sec7['deity'] ?? '' }}</td>
                <th style="width: 20%;">{{ $locale === 'hi' ? 'प्रतीक' : 'Symbol' }}</th>
                <td style="width: 30%;">{{ $sec7['symbol'] ?? '' }}</td>
            </tr>
            <tr>
                <th>{{ $locale === 'hi' ? 'मूल प्रकृति' : 'Inherent Nature' }}</th>
                <td colspan="3">{{ $sec7['nature'] ?? '' }}</td>
            </tr>
            <tr>
                <th>{{ $locale === 'hi' ? 'आध्यात्मिक मार्गदर्शन' : 'Spiritual Guidance' }}</th>
                <td colspan="3">{{ $sec7['guidance'] ?? '' }}</td>
            </tr>
        </table>
        <div style="font-size: 8px; color: #4A5568; font-style: italic;">
            {{ $sec7['pada_note'] ?? '' }}
        </div>
    </div>

    <div class="page-break"></div>

    {{-- ========================================== --}}
    {{-- SECTION 8: VIMSHOTTARI DASHA TIMELINE      --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec8['title'] ?? 'Vimshottari Mahadasha Timeline' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 18%;">{{ $locale === 'hi' ? 'महादशा' : 'Mahadasha' }}</th>
                    <th style="width: 22%;">{{ $locale === 'hi' ? 'अनुमानित अवधि' : 'Nominal Period' }}</th>
                    <th style="width: 30%;">{{ $locale === 'hi' ? 'अनिश्चितता सीमा [प्रारंभ - अंत]' : 'Uncertainty Bounds [Min, Max]' }}</th>
                    <th style="width: 30%;">{{ $locale === 'hi' ? 'मूल विषय' : 'Core Theme' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sec8['timeline'] as $m)
                    <tr @if(!empty($m['is_current'])) style="background-color: #FEFCBF;" @endif>
                        <td style="font-weight: bold; color: #8C1D40;">
                            {{ $m['name'] }}
                            @if(!empty($m['is_current']))
                                <span class="badge badge-certified">{{ $locale === 'hi' ? 'सक्रिय' : 'ACTIVE' }}</span>
                            @endif
                        </td>
                        <td>{{ $m['nominal_start'] }} — {{ $m['nominal_end'] }}</td>
                        <td>[{{ $m['date_bounds']['earliest_start'] }}] — [{{ $m['date_bounds']['latest_end'] }}]</td>
                        <td style="font-size: 7.5px;">{{ $m['theme'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 9: CURRENT RUNNING DASHA           --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec9['title'] ?? 'Current Running Dasha' }}
            <span class="badge {{ $sec9['gate_b_status'] === 'certified' ? 'badge-certified' : 'badge-qualified' }}" style="float: right;">
                {{ strtoupper($sec9['gate_b_status'] ?? 'certified') }}
            </span>
        </div>

        @if(!empty($sec9['is_suppressed']))
            <div class="alert-box alert-suppressed">{{ $sec9['reason'] ?? '' }}</div>
        @else
            <table class="data-table">
                <tr>
                    <th style="width: 33%;">{{ $locale === 'hi' ? 'वर्तमान महादशा' : 'Active Mahadasha' }}</th>
                    <th style="width: 33%;">{{ $locale === 'hi' ? 'वर्तमान अंतर्दशा' : 'Active Antardasha' }}</th>
                    <th style="width: 34%;">{{ $locale === 'hi' ? 'प्रत्यंतर्दशा (Gate B स्थिति)' : 'Active Pratyantardasha (Gate B)' }}</th>
                </tr>
                <tr>
                    <td>
                        <div style="font-size: 10px; font-weight: bold; color: #8C1D40;">{{ $sec9['mahadasha']['name'] ?? '—' }}</div>
                        <div style="font-size: 7.5px; color: #718096;">{{ $sec9['mahadasha']['start_date'] ?? '' }} — {{ $sec9['mahadasha']['end_date'] ?? '' }}</div>
                    </td>
                    <td>
                        <div style="font-size: 10px; font-weight: bold; color: #1A365D;">{{ $sec9['antardasha']['name'] ?? '—' }}</div>
                        <div style="font-size: 7.5px; color: #718096;">{{ $sec9['antardasha']['start_date'] ?? '' }} — {{ $sec9['antardasha']['end_date'] ?? '' }}</div>
                    </td>
                    <td>
                        @if(!empty($sec9['pratyantardasha_suppressed']))
                            <div style="color: #C53030; font-weight: bold;">{{ $locale === 'hi' ? 'गणितीय रूप से स्थगित' : 'Suppressed' }}</div>
                            <div style="font-size: 7.5px; color: #718096;">{{ $sec9['suppression_note'] ?? '' }}</div>
                        @else
                            <div style="font-size: 10px; font-weight: bold; color: #2B6CB0;">{{ $sec9['pratyantardasha']['name'] ?? '—' }}</div>
                            <div style="font-size: 7.5px; color: #718096;">{{ $sec9['pratyantardasha']['start_date'] ?? '' }} — {{ $sec9['pratyantardasha']['end_date'] ?? '' }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 10: CLASSICAL PLANETARY YOGAS      --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec10['title'] ?? 'Classical Planetary Yogas' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        @if(!empty($sec10['note']))
            <div class="alert-box alert-warning">{{ $sec10['note'] }}</div>
        @endif

        @if(!empty($sec10['active_yogas']))
            @foreach($sec10['active_yogas'] as $yoga)
                <div style="border: 1px solid #CBD5E0; border-radius: 3px; padding: 6px 8px; margin-bottom: 6px; background-color: #FFFFFF;">
                    <div style="font-size: 10px; font-weight: bold; color: #8C1D40; margin-bottom: 2px;">
                        {{ $yoga['name'] }}
                    </div>
                    <div style="font-size: 8px; color: #4A5568; margin-bottom: 3px;">
                        <strong>{{ $locale === 'hi' ? 'शास्त्रीय परिभाषा: ' : 'Classical Definition: ' }}</strong>{{ $yoga['definition'] }}
                    </div>
                    <div style="font-size: 8px; color: #2D3748; margin-bottom: 3px;">
                        <strong>{{ $locale === 'hi' ? 'फल प्रभाव: ' : 'Manifested Qualities: ' }}</strong>{{ $yoga['qualities'] }}
                    </div>
                    <div style="font-size: 8px; color: #744210; font-style: italic;">
                        <strong>{{ $locale === 'hi' ? 'नैतिक मार्गदर्शन: ' : 'Ethical Advice: ' }}</strong>{{ $yoga['ethical_advice'] }}
                    </div>
                </div>
            @endforeach
        @else
            <div style="font-size: 8.5px; color: #718096; padding: 4px 0;">
                {{ $locale === 'hi' ? 'इस पत्रिका में पंच महापुरुष, गजकेसरी अथवा बुधादित्य योगों का पूर्ण निर्माण नहीं पाया गया।' : 'No verified Panch Mahapurusha, Gaja Kesari, or Budhaditya yogas formed.' }}
            </div>
        @endif
    </div>

    <div class="page-break"></div>

    {{-- ========================================== --}}
    {{-- SECTION 11: MANGLIK DOSHA AUDIT            --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec11['title'] ?? 'Manglik Dosha Audit' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        <div style="font-size: 11px; font-weight: bold; color: #8C1D40; margin-bottom: 4px;">
            {{ $sec11['headline'] ?? '' }}
        </div>
        <p style="font-size: 8.5px; line-height: 1.45; margin-bottom: 5px;">
            {{ $sec11['summary'] ?? '' }}
        </p>

        @if(!empty($sec11['cancellation_note']))
            <div class="alert-box alert-gold">
                <strong>{{ $locale === 'hi' ? 'शास्त्रीय अपवाद व भंग: ' : 'Classical Bhanga & Clarification: ' }}</strong>
                {{ $sec11['cancellation_note'] }}
            </div>
        @endif

        <div class="alert-box alert-info">
            <strong>{{ $locale === 'hi' ? 'संबंध समन्वय परामर्श: ' : 'Harmonious Relationship Advice: ' }}</strong>
            {{ $sec11['ethical_guidance'] ?? '' }}
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 12: SATURN SADE SATI & DHAIYA      --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec12['title'] ?? 'Saturn Sade Sati & Dhaiya Transits' }}
            <span class="badge {{ $sec12['gate_b_status'] === 'certified' ? 'badge-certified' : 'badge-fallback' }}" style="float: right;">
                {{ strtoupper($sec12['gate_b_status'] ?? 'certified') }}
            </span>
        </div>

        <div style="font-size: 10px; font-weight: bold; color: #1A365D; margin-bottom: 3px;">
            {{ $sec12['current_phase_title'] ?? '' }}
        </div>
        <p style="font-size: 8.5px; line-height: 1.45; margin-bottom: 4px;">
            {{ $sec12['current_phase_core'] ?? '' }}
        </p>
        <div class="alert-box alert-info" style="margin-bottom: 6px;">
            <strong>{{ $locale === 'hi' ? 'शनि मार्गदर्शन: ' : 'Saturnian Advice: ' }}</strong>
            {{ $sec12['current_phase_advice'] ?? '' }}
        </div>

        @if(!empty($sec12['fallback_note']))
            <div class="alert-box alert-warning">{{ $sec12['fallback_note'] }}</div>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 13: CLASSICAL VEDIC REMEDIES       --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec13['title'] ?? 'Classical Vedic Remedies & Upay' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        @foreach($sec13['remedies'] as $rem)
            <div style="border-bottom: 1px dashed #CBD5E0; padding-bottom: 4px; margin-bottom: 5px;">
                <div style="font-size: 9.5px; font-weight: bold; color: #8C1D40;">
                    {{ $rem['planet'] }} {{ $locale === 'hi' ? 'शांति एवं सकारात्मक प्रभाव' : 'Remedies' }}
                </div>
                <div style="font-size: 8px; margin-top: 2px;">
                    <strong>{{ $locale === 'hi' ? 'वैदिक बीज मंत्र: ' : 'Vedic Beej Mantra: ' }}</strong>
                    <span style="color: #2B6CB0; font-family: monospace;">{{ $rem['mantra'] }}</span>
                </div>
                <div style="font-size: 8px; margin-top: 2px;">
                    <strong>{{ $locale === 'hi' ? 'सात्विक दान: ' : 'Satvik Dana: ' }}</strong>{{ $rem['dana'] }}
                </div>
                <div style="font-size: 8px; margin-top: 2px;">
                    <strong>{{ $locale === 'hi' ? 'सदाचार व जीवनशैली: ' : 'Lifestyle & Mindfulness: ' }}</strong>{{ $rem['lifestyle'] }}
                </div>
            </div>
        @endforeach

        {{-- Mandatory Gemstone Algorithmic Disclaimer --}}
        <div class="alert-box alert-warning" style="margin-top: 8px;">
            <div style="font-weight: bold; margin-bottom: 2px;">
                {{ $locale === 'hi' ? 'रत्न परामर्श अस्वीकरण (अति महत्वपूर्ण)' : 'Gemstone Algorithmic Prescription Disclaimer' }}
            </div>
            <div>{{ $sec13['gemstone_disclaimer'] ?? '' }}</div>
        </div>
    </div>

    <div class="page-break"></div>

    {{-- ========================================== --}}
    {{-- SECTION 14: ASTROLOGER REFERRAL            --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec14['title'] ?? 'Verified Astrologer Consultation' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        <p style="font-size: 8.5px; line-height: 1.45; margin-bottom: 8px;">
            {{ $sec14['summary'] ?? '' }}
        </p>

        @if(!empty($sec14['astrologers']))
            <table class="grid-2col">
                <tr>
                    @foreach($sec14['astrologers'] as $idx => $ast)
                        <td style="width: 32%; padding-right: 1%; vertical-align: top;">
                            <div style="border: 1px solid #CBD5E0; border-radius: 4px; padding: 6px; background-color: #FFFFFF; text-align: center;">
                                <div style="font-size: 10px; font-weight: bold; color: #8C1D40;">{{ $ast['name'] }}</div>
                                <div style="font-size: 7.5px; color: #718096; margin-top: 1px;">{{ $ast['expertise'] }}</div>
                                <div style="font-size: 7.5px; color: #4A5568; margin-top: 2px;">
                                    {{ $ast['experience'] }} {{ $locale === 'hi' ? 'वर्ष अनुभव' : 'Years Exp' }} | ★ {{ $ast['rating'] }}
                                </div>
                                <div style="font-size: 8px; font-weight: bold; color: #2B6CB0; margin-top: 3px;">
                                    ₹{{ $ast['price'] }} / {{ $locale === 'hi' ? 'मिनट' : 'min' }}
                                </div>
                            </div>
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
    </div>

    {{-- ========================================== --}}
    {{-- SECTION 15: PRECISION AUDIT & DISCLAIMERS  --}}
    {{-- ========================================== --}}
    <div class="section-box">
        <div class="section-title">
            {{ $sec15['title'] ?? 'Precision Audit & Disclaimers' }}
            <span class="badge badge-certified" style="float: right;">CERTIFIED</span>
        </div>

        <div class="sub-title">{{ $locale === 'hi' ? 'गेट-बी (Gate B) खंड-वार परिशुद्धता ऑडिट मैट्रिक्स' : 'Gate B Section-by-Section Precision Audit Matrix' }}</div>
        <table class="data-table" style="margin-bottom: 8px;">
            <thead>
                <tr>
                    <th style="width: 60%;">{{ $locale === 'hi' ? 'प्रतिवेदन खंड' : 'Report Section' }}</th>
                    <th style="width: 40%; text-align: center;">{{ $locale === 'hi' ? 'गेट-बी स्थिति (Gate B Status)' : 'Gate B Eligibility Status' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sec15['audit_table'] as $row)
                    <tr>
                        <td>{{ $row['section'] }}</td>
                        <td style="text-align: center;">
                            @if($row['status_key'] === 'certified')
                                <span class="badge badge-certified">CERTIFIED / प्रमाणित</span>
                            @elseif($row['status_key'] === 'qualified')
                                <span class="badge badge-qualified">QUALIFIED / सशर्त</span>
                            @elseif($row['status_key'] === 'fallback')
                                <span class="badge badge-fallback">QUALITATIVE FALLBACK / गुणात्मक</span>
                            @else
                                <span class="badge badge-suppressed">SUPPRESSED / स्थगित</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Legal Disclaimers --}}
        <div class="alert-box alert-gold">
            <div style="font-weight: bold; margin-bottom: 2px;">
                {{ $locale === 'hi' ? 'वैदिक कर्म सिद्धांत' : 'Purushartha Philosophy' }}
            </div>
            <div>{{ $sec15['purushartha_framing'] ?? '' }}</div>
        </div>

        <div class="alert-box alert-info">
            <div style="font-weight: bold; margin-bottom: 2px;">
                {{ $locale === 'hi' ? 'विधिक एवं चिकित्सीय अस्वीकरण' : 'Legal & Medical Disclaimer' }}
            </div>
            <div>{{ $sec15['general_disclaimer'] ?? '' }}</div>
        </div>
    </div>

</body>
</html>
