<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $locale === 'hi' ? 'वैदिक जन्म पत्रिका - एस्ट्रो सेतु' : 'Janam Kundli Report - Astro Setu' }}</title>
    <style>
        @font-face {
            font-family: 'NotoDevanagari';
            src: url('{{ $fontRegular }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'NotoDevanagari';
            src: url('{{ $fontBold }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'NotoDevanagari';
            src: url('{{ $fontRegular }}') format('truetype');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'NotoDevanagari';
            src: url('{{ $fontBold }}') format('truetype');
            font-weight: bold;
            font-style: italic;
        }

        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url('{{ $fontRegular }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url('{{ $fontBold }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url('{{ $fontRegular }}') format('truetype');
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: 'Noto Sans Devanagari';
            src: url('{{ $fontBold }}') format('truetype');
            font-weight: bold;
            font-style: italic;
        }

        @page {
            margin: 18px 26px 20px 26px;
            size: A4 portrait;
        }

        body {
            font-family: {!! $locale === 'hi' ? 'NotoDevanagari, "Noto Sans Devanagari", sans-serif' : "'DejaVu Sans', sans-serif" !!};
            color: #1A202C;
            font-size: 8.5px;
            line-height: 1.35;
        }

        body, table, td, th, div, span, p, em, strong, b, i {
            font-family: {!! $locale === 'hi' ? 'NotoDevanagari, "Noto Sans Devanagari", sans-serif' : "'DejaVu Sans', sans-serif" !!};
        }

        .header {
            width: 100%;
            border-bottom: 2px solid #8C1D40;
            padding-bottom: 5px;
            margin-bottom: 6px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .brand-title {
            font-size: 17px;
            font-weight: bold;
            color: #8C1D40;
            letter-spacing: 0.5px;
        }
        .brand-sub {
            font-size: 8px;
            color: #718096;
            margin-top: 1px;
        }
        .report-title {
            text-align: right;
            font-size: 12.5px;
            font-weight: bold;
            color: #2B6CB0;
        }
        .report-date {
            text-align: right;
            font-size: 8px;
            color: #A0AEC0;
        }
        .section-box {
            background-color: #F7FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 5px 8px;
            margin-bottom: 6px;
        }
        .section-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #8C1D40;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 2px;
            margin-bottom: 4px;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-grid td {
            padding: 2px 4px;
            font-size: 8px;
            vertical-align: top;
        }
        .meta-label {
            color: #4A5568;
            font-weight: bold;
            width: 25%;
        }
        .meta-value {
            color: #1A202C;
            width: 25%;
        }
        .chart-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        .chart-cell {
            vertical-align: top;
            text-align: center;
        }
        .chart-heading {
            font-size: 10px;
            font-weight: bold;
            color: #2C5282;
            margin-bottom: 4px;
        }
        .chart-img-frame {
            width: 140pt;
            height: 140pt;
            display: block;
            margin: 0 auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            margin-bottom: 6px;
        }
        .data-table th {
            background-color: #EDF2F7;
            color: #2D3748;
            font-weight: bold;
            font-size: 7.5px;
            padding: 3px 4px;
            border: 1px solid #CBD5E0;
            text-align: left;
        }
        .data-table td {
            font-size: 7.5px;
            padding: 2.5px 4px;
            border: 1px solid #E2E8F0;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background-color: #F8FAFC;
        }
        .dignity-badge {
            font-weight: bold;
        }
        .dignity-exalted { color: #22543D; font-weight: bold; }
        .dignity-own, .dignity-own_sign { color: #2B6CB0; font-weight: bold; }
        .dignity-debilitated { color: #9B2C2C; font-weight: bold; }
        .disclaimer-box {
            background-color: #FFFDF0;
            border: 1px solid #F6E05E;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 7px;
            color: #744210;
            line-height: 1.35;
            margin-top: 8px;
            page-break-inside: avoid;
        }
        .page-break {
            page-break-after: always;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.5px;
            color: #A0AEC0;
            border-top: 1px solid #E2E8F0;
            padding-top: 3px;
        }
    </style>
</head>
<body>

    <div class="footer">
        Astro Setu • {{ $locale === 'hi' ? 'वैदिक ज्योतिष विश्लेषण (पराशर प्रणाली)' : 'Vedic Astrological Analysis (Parashara System)' }} • True Chitra Paksha Lahiri Ayanamsha
    </div>

    <!-- PAGE 1: Header, Profile, Charts, Planetary Positions, Janma Nakshatra -->
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand-title">ASTRO SETU</div>
                    <div class="brand-sub">{{ $locale === 'hi' ? 'प्रामाणिक वैदिक ज्योतिष अनुसंधान' : 'Authentic Vedic Astrology & Panchang' }}</div>
                </td>
                <td>
                    <div class="report-title">{{ $locale === 'hi' ? 'जन्म कुंडली प्रतिवेदन' : 'Janam Kundli Report' }}</div>
                    <div class="report-date">{{ \Carbon\Carbon::now()->format('d M Y, H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Birth Profile Information -->
    <div class="section-box">
        <div class="section-title">{{ $locale === 'hi' ? 'जन्म विवरण' : 'Birth Profile Details' }}</div>
        <table class="meta-grid">
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'नाम' : 'Name' }}:</td>
                <td class="meta-value">{{ $kundli['profile']['name'] ?? 'Self' }}</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'जन्म तिथि' : 'Date of Birth' }}:</td>
                <td class="meta-value">{{ $kundli['profile']['date_of_birth'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'जन्म समय' : 'Birth Time' }}:</td>
                <td class="meta-value">
                    {{ $kundli['profile']['formatted_birth_time'] ?? ($kundli['profile']['birth_time'] ?? '-') }}
                </td>
                <td class="meta-label">{{ $locale === 'hi' ? 'जन्म स्थान' : 'Birth City' }}:</td>
                <td class="meta-value">{{ $kundli['profile']['birth_city'] ?? ($kundli['profile']['place_of_birth'] ?? '-') }}</td>
            </tr>
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'अक्षांश / देशांतर' : 'Coordinates' }}:</td>
                <td class="meta-value">{{ round((float)($kundli['profile']['latitude'] ?? 0), 4) }}°, {{ round((float)($kundli['profile']['longitude'] ?? 0), 4) }}°</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'अयनांश' : 'Ayanamsha' }}:</td>
                <td class="meta-value">True Chitra Lahiri</td>
            </tr>
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'लग्न राशि' : 'Ascendant (Lagna)' }}:</td>
                <td class="meta-value">
                    @if(($kundli['ascendant']['status'] ?? '') === 'confirmed')
                        <strong>{{ $kundli['ascendant']['localized_name'] ?? ($kundli['ascendant']['name'] ?? '-') }}</strong>
                    @else
                        <em>{{ $locale === 'hi' ? 'समय अनिश्चित' : 'Deferred (Approx Time)' }}</em>
                    @endif
                </td>
                <td class="meta-label">{{ $locale === 'hi' ? 'चंद्र राशि' : 'Moon Sign (Rashi)' }}:</td>
                <td class="meta-value">
                    <strong>{{ $kundli['planets']['moon']['rashi']['localized_name'] ?? ($kundli['planets']['moon']['rashi']['name'] ?? '-') }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <!-- Chart Visualizations -->
    <table class="chart-table">
        @if($lagnaSvg && $chandraSvg)
            <tr>
                <td class="chart-cell" style="width: 50%; padding-right: 8px;">
                    <div class="chart-heading">{{ $locale === 'hi' ? 'लग्न कुण्डली (D-1)' : 'Lagna Chart (D-1)' }}</div>
                    <img src="data:image/svg+xml;charset=utf-8;base64,{{ base64_encode($lagnaSvg) }}" class="chart-img-frame" width="140" height="140" style="width: 140pt; height: 140pt; display: block; margin: 0 auto;" alt="Lagna Chart (D-1)"/>
                </td>
                <td class="chart-cell" style="width: 50%; padding-left: 8px;">
                    <div class="chart-heading">{{ $locale === 'hi' ? 'चन्द्र कुण्डली' : 'Chandra Chart (Moon)' }}</div>
                    <img src="data:image/svg+xml;charset=utf-8;base64,{{ base64_encode($chandraSvg) }}" class="chart-img-frame" width="140" height="140" style="width: 140pt; height: 140pt; display: block; margin: 0 auto;" alt="Chandra Chart (Moon)"/>
                </td>
            </tr>
        @elseif($chandraSvg)
            <tr>
                <td class="chart-cell" colspan="2" style="width: 100%;">
                    <div class="chart-heading">{{ $locale === 'hi' ? 'चन्द्र कुण्डली' : 'Chandra Kundli (Moon Ascendant)' }}</div>
                    <img src="data:image/svg+xml;charset=utf-8;base64,{{ base64_encode($chandraSvg) }}" class="chart-img-frame" width="140" height="140" style="width: 140pt; height: 140pt; display: block; margin: 0 auto;" alt="Chandra Chart (Moon)"/>
                </td>
            </tr>
        @endif
    </table>

    <!-- Planetary Positions Table -->
    <div>
        <div class="section-title">{{ $locale === 'hi' ? 'ग्रह स्थिति एवं बल' : 'Planetary Positions & Dignities' }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ $locale === 'hi' ? 'ग्रह' : 'Planet' }}</th>
                    <th>{{ $locale === 'hi' ? 'राशि' : 'Rashi' }}</th>
                    <th>{{ $locale === 'hi' ? 'अंश' : 'Longitude (DMS)' }}</th>
                    <th>{{ $locale === 'hi' ? 'नक्षत्र (पाद)' : 'Nakshatra (Pada)' }}</th>
                    <th>{{ $locale === 'hi' ? 'स्वामी' : 'Lord' }}</th>
                    <th>{{ $locale === 'hi' ? 'स्थिति' : 'Dignity' }}</th>
                    <th>{{ $locale === 'hi' ? 'वक्री / अस्त' : 'Vakri / Asta' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($kundli['planets'] ?? [] as $key => $p)
                @php
                    $pRashi = $p['rashi'] ?? [];
                    $pNak = $p['nakshatra'] ?? [];
                @endphp
                <tr>
                    <td><strong>{{ $p['localized_name'] ?? ($p['name'] ?? $key) }}</strong></td>
                    <td>{{ $pRashi['localized_name'] ?? ($pRashi['name'] ?? '-') }}</td>
                    <td>{{ $p['degree_dms'] ?? (isset($p['longitude']) ? round((float)$p['longitude'], 2) . '°' : '-') }}</td>
                    <td>{{ $pNak['localized_name'] ?? ($pNak['name'] ?? '-') }} ({{ $pNak['pada'] ?? 1 }})</td>
                    <td>{{ $pNak['localized_lord'] ?? ($pNak['lord'] ?? '-') }}</td>
                    <td>
                        <span class="dignity-badge dignity-{{ $p['dignity'] ?? 'neutral' }}">
                            {{ $p['localized_dignity'] ?? ucfirst($p['dignity'] ?? 'neutral') }}
                        </span>
                    </td>
                    <td>{{ $p['localized_motion'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Janma Nakshatra Details (Positioned on Page 1 to balance vertical space) -->
    @php
        $nakshatraData = $kundli['janma_nakshatra_normalized']
            ?? ($kundli['interpretations'][$locale]['nakshatra']
            ?? ($kundli['interpretations']['en']['nakshatra'] ?? null));
    @endphp
    @if($nakshatraData)
    <div class="section-box" style="margin-top: 4px;">
        <div class="section-title">{{ $locale === 'hi' ? 'जन्म नक्षत्र विवरण' : 'Janma Nakshatra Details' }}</div>
        <table class="meta-grid">
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'नक्षत्र नाम' : 'Nakshatra' }}:</td>
                <td class="meta-value"><strong>{{ $nakshatraData['localized_name'] ?? ($nakshatraData['name'] ?? '-') }}</strong> ({{ $locale === 'hi' ? 'पाद' : 'Pada' }} {{ $nakshatraData['pada'] ?? 1 }})</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'नक्षत्र स्वामी' : 'Ruling Lord' }}:</td>
                <td class="meta-value">{{ $nakshatraData['localized_lord'] ?? ($nakshatraData['lord'] ?? '-') }}</td>
            </tr>
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'अधिष्ठाता देवता' : 'Presiding Deity' }}:</td>
                <td class="meta-value">{{ $nakshatraData['deity'] ?? '-' }}</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'प्रतीक चिन्ह' : 'Symbol' }}:</td>
                <td class="meta-value">{{ $nakshatraData['symbol'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'गण' : 'Gana' }}:</td>
                <td class="meta-value">{{ $nakshatraData['localized_gana'] ?? ($nakshatraData['gana'] ?? '-') }}</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'पारंपरिक महत्व' : 'Significance' }}:</td>
                <td class="meta-value">{{ $nakshatraData['significance'] ?? '-' }}</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="page-break"></div>

    <!-- PAGE 2: 12 House Placements, Vimshottari Dasha, Disclaimer -->
    @if(!empty($kundli['houses']))
    <div>
        <div class="section-title">{{ $locale === 'hi' ? 'द्वादश भाव विवरण' : '12 House (Bhava) Details' }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 7%;">{{ $locale === 'hi' ? 'भाव' : 'House' }}</th>
                    <th style="width: 19%;">{{ $locale === 'hi' ? 'संज्ञा' : 'Bhava Title' }}</th>
                    <th style="width: 14%;">{{ $locale === 'hi' ? 'राशि' : 'Sign' }}</th>
                    <th style="width: 13%;">{{ $locale === 'hi' ? 'भावेश' : 'Lord' }}</th>
                    <th style="width: 19%;">{{ $locale === 'hi' ? 'उपस्थित ग्रह' : 'Resident Planets' }}</th>
                    <th style="width: 28%;">{{ $locale === 'hi' ? 'महत्व' : 'Traditional Significance' }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $interpHouses = $kundli['interpretations'][$locale]['houses'] ?? ($kundli['interpretations']['en']['houses'] ?? []);
                @endphp
                @foreach(($kundli['houses'] ?? []) as $h)
                @php
                    $hNum = $h['house_number'] ?? 0;
                    $hInfo = $interpHouses[$hNum] ?? null;
                @endphp
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $hNum }}</td>
                    <td>{{ $hInfo['title'] ?? ($hNum . 'th House') }}</td>
                    <td>{{ $h['localized_sign_name'] ?? ($h['sign_name'] ?? '-') }}</td>
                    <td>{{ $h['localized_sign_lord'] ?? ($h['sign_lord'] ?? '-') }}</td>
                    <td>{{ $h['localized_residents'] ?? '-' }}</td>
                    <td>{{ $hInfo['significance'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Vimshottari Dasha Section -->
    @if(!empty($kundli['dasha']) && ($kundli['dasha']['status'] ?? '') === 'available')
    <div style="margin-top: 6px;">
        <div class="section-title">{{ $locale === 'hi' ? 'विंशोत्तरी दशा चक्र' : 'Vimshottari Dasha Timeline (120-Year Cycle)' }}</div>

        @if(!empty($kundli['dasha']['current_dasha']))
        @php
            $curr = $kundli['dasha']['current_dasha'];
            $mdLord = $curr['mahadasha']['localized_lord'] ?? ($curr['mahadasha']['lord'] ?? '-');
            $adLord = $curr['antardasha']['localized_lord'] ?? ($curr['antardasha']['lord'] ?? '-');
            $endDate = !empty($curr['antardasha']['end_date']) ? \Carbon\Carbon::parse($curr['antardasha']['end_date'])->format('d M Y') : '-';
        @endphp
        <div style="background-color: #EBF8FF; border-left: 3px solid #3182CE; padding: 5px 8px; margin-bottom: 6px; font-size: 8px;">
            <strong>{{ $locale === 'hi' ? 'वर्तमान दशा:' : 'Active Dasha:' }}</strong>
            {{ $mdLord }} - {{ $adLord }}
            ({{ $locale === 'hi' ? 'समाप्ति:' : 'Until:' }} {{ $endDate }})
        </div>
        @endif

        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ $locale === 'hi' ? 'महादशा' : 'Mahadasha' }}</th>
                    <th>{{ $locale === 'hi' ? 'अवधि' : 'Years' }}</th>
                    <th>{{ $locale === 'hi' ? 'आरंभ तिथि' : 'Start Date' }}</th>
                    <th>{{ $locale === 'hi' ? 'समाप्ति तिथि' : 'End Date' }}</th>
                    <th>{{ $locale === 'hi' ? 'स्थिति' : 'Status' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($kundli['dasha']['mahadashas'] ?? []) as $md)
                @php
                    $mdLord = $md['localized_lord'] ?? ($md['lord'] ?? '-');
                    $mdStart = !empty($md['start_date']) ? \Carbon\Carbon::parse($md['start_date'])->format('d M Y') : '-';
                    $mdEnd = !empty($md['end_date']) ? \Carbon\Carbon::parse($md['end_date'])->format('d M Y') : '-';
                @endphp
                <tr style="{{ !empty($md['is_current']) ? 'background-color: #FEFCBF; font-weight: bold;' : '' }}">
                    <td>{{ $mdLord }}</td>
                    <td>{{ $md['years'] ?? 0 }} {{ $locale === 'hi' ? 'वर्ष' : 'Yrs' }}</td>
                    <td>{{ $mdStart }}</td>
                    <td>{{ $mdEnd }}</td>
                    <td>{{ $md['localized_status'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Disclaimers -->
    <div class="disclaimer-box">
        <strong>{{ $locale === 'hi' ? 'महत्वपूर्ण अस्वीकरण एवं मार्गदर्शन:' : 'Important Educational Disclaimer:' }}</strong><br/>
        {{ $locale === 'hi' ? \App\Services\Astrology\Interpretation\KundliInterpretationService::DISCLAIMER_HI : \App\Services\Astrology\Interpretation\KundliInterpretationService::DISCLAIMER_EN }}
    </div>

</body>
</html>
