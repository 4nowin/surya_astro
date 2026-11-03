<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $locale === 'hi' ? 'वैदिक जन्म पत्रिका - एस्ट्रो सेतु' : 'Vedic Janam Kundli - Astro Setu' }}</title>
    <style>
        @page {
            margin: 28px 32px 32px 32px;
            size: A4 portrait;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #2D3748;
            line-height: 1.45;
            background-color: #FFFFFF;
        }
        .header {
            border-bottom: 2px solid #D4AF37;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header table {
            width: 100%;
        }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            color: #8C1D40;
            letter-spacing: 0.5px;
        }
        .brand-sub {
            font-size: 9px;
            color: #718096;
            margin-top: 2px;
        }
        .report-title {
            text-align: right;
            font-size: 14px;
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
            padding: 8px 10px;
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #8C1D40;
            border-bottom: 1px solid #E2E8F0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-grid td {
            padding: 3px 4px;
            font-size: 9px;
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
        .chart-container {
            width: 100%;
            margin-bottom: 14px;
        }
        .chart-col {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }
        .chart-heading {
            font-size: 11px;
            font-weight: bold;
            color: #2C5282;
            margin-bottom: 6px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #EDF2F7;
            color: #2D3748;
            font-weight: bold;
            font-size: 8.5px;
            padding: 5px 4px;
            border: 1px solid #CBD5E0;
            text-align: left;
        }
        .data-table td {
            font-size: 8px;
            padding: 4px 4px;
            border: 1px solid #E2E8F0;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background-color: #F8FAFC;
        }
        .dignity-badge {
            font-weight: bold;
            text-transform: capitalize;
        }
        .dignity-exalted { color: #22543D; font-weight: bold; }
        .dignity-own { color: #2B6CB0; font-weight: bold; }
        .dignity-debilitated { color: #9B2C2C; font-weight: bold; }
        .disclaimer-box {
            background-color: #FFFDF0;
            border: 1px solid #F6E05E;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 7.5px;
            color: #744210;
            line-height: 1.35;
            margin-top: 14px;
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
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <div class="footer">
        Astro Setu • {{ $locale === 'hi' ? 'वैदिक ज्योतिष विश्लेषण (पराशर प्रणाली)' : 'Vedic Astrological Analysis (Parashara System)' }} • True Chitra Paksha Lahiri Ayanamsha
    </div>

    <!-- Header Section -->
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
        <div class="section-title">{{ $locale === 'hi' ? 'जन्म विवरण (Birth Details)' : 'Birth Profile Details' }}</div>
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
                    {{ $kundli['profile']['birth_time'] ?? ($locale === 'hi' ? 'अनुमानित' : 'Approximate') }}
                    ({{ ucfirst($kundli['profile']['precision'] ?? 'verified') }})
                </td>
                <td class="meta-label">{{ $locale === 'hi' ? 'जन्म स्थान' : 'Birth City' }}:</td>
                <td class="meta-value">{{ $kundli['profile']['birth_city'] ?? '-' }}</td>
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
                        <strong>{{ $locale === 'hi' ? ($kundli['ascendant']['vedic_name'] ?? $kundli['ascendant']['name']) : $kundli['ascendant']['name'] }}</strong>
                    @else
                        <em>{{ $locale === 'hi' ? 'समय अनिश्चित' : 'Deferred (Approx Time)' }}</em>
                    @endif
                </td>
                <td class="meta-label">{{ $locale === 'hi' ? 'चंद्र राशि' : 'Moon Sign (Rashi)' }}:</td>
                <td class="meta-value">
                    <strong>{{ $locale === 'hi' ? ($kundli['planets']['moon']['rashi']['vedic_name'] ?? $kundli['planets']['moon']['rashi']['name']) : $kundli['planets']['moon']['rashi']['name'] }}</strong>
                </td>
            </tr>
        </table>
    </div>

    <!-- Chart Visualizations -->
    <div class="chart-container">
        @if($lagnaSvg && $chandraSvg)
            <div class="chart-col" style="margin-right: 3%;">
                <div class="chart-heading">{{ $locale === 'hi' ? 'लग्न कुंडली (Lagna D-1)' : 'Lagna Chart (D-1)' }}</div>
                {!! $lagnaSvg !!}
            </div>
            <div class="chart-col">
                <div class="chart-heading">{{ $locale === 'hi' ? 'चन्द्र कुंडली (Chandra Chart)' : 'Chandra Chart (Moon)' }}</div>
                {!! $chandraSvg !!}
            </div>
        @elseif($chandraSvg)
            <div style="text-align: center; width: 100%;">
                <div class="chart-heading">{{ $locale === 'hi' ? 'चन्द्र कुंडली (Chandra Kundli)' : 'Chandra Kundli (Moon Ascendant)' }}</div>
                {!! $chandraSvg !!}
            </div>
        @endif
    </div>

    <!-- Planetary Positions Table -->
    <div style="margin-top: 10px;">
        <div class="section-title">{{ $locale === 'hi' ? 'ग्रह स्थिति एवं बल (Planetary Positions)' : 'Planetary Positions & Dignities' }}</div>
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
                <tr>
                    <td><strong>{{ $locale === 'hi' ? ($p['name_hi'] ?? $p['name']) : $p['name'] }}</strong></td>
                    <td>{{ $locale === 'hi' ? ($p['rashi']['vedic_name'] ?? $p['rashi']['name']) : $p['rashi']['name'] }}</td>
                    <td>{{ $p['degree_dms'] ?? round((float)($p['longitude'] ?? 0), 2) . '°' }}</td>
                    <td>{{ $locale === 'hi' ? ($p['nakshatra']['name_hi'] ?? $p['nakshatra']['name']) : $p['nakshatra']['name'] }} ({{ $p['nakshatra']['pada'] ?? 1 }})</td>
                    <td>{{ $locale === 'hi' ? ($p['nakshatra']['lord_hi'] ?? $p['nakshatra']['lord']) : $p['nakshatra']['lord'] }}</td>
                    <td>
                        <span class="dignity-badge dignity-{{ $p['dignity'] ?? 'neutral' }}">
                            {{ ucfirst($p['dignity'] ?? 'neutral') }}
                        </span>
                    </td>
                    <td>
                        {{ !empty($p['is_retrograde']) ? ($locale === 'hi' ? 'वक्री ' : 'Retro ') : '' }}
                        {{ !empty($p['is_combust']) ? ($locale === 'hi' ? 'अस्त' : 'Combust') : '' }}
                        {{ (empty($p['is_retrograde']) && empty($p['is_combust'])) ? '-' : '' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="page-break"></div>

    <!-- Page 2: Houses & Dasha -->
    @if(!empty($kundli['houses']))
    <div style="margin-top: 10px;">
        <div class="section-title">{{ $locale === 'hi' ? 'द्वादश भाव विवरण (12 House Placements)' : '12 House (Bhava) Details' }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 8%;">{{ $locale === 'hi' ? 'भाव' : 'House' }}</th>
                    <th style="width: 18%;">{{ $locale === 'hi' ? 'संज्ञा' : 'Bhava Title' }}</th>
                    <th style="width: 15%;">{{ $locale === 'hi' ? 'राशि' : 'Sign' }}</th>
                    <th style="width: 12%;">{{ $locale === 'hi' ? 'भावेश' : 'Lord' }}</th>
                    <th style="width: 20%;">{{ $locale === 'hi' ? 'उपस्थित ग्रह' : 'Resident Planets' }}</th>
                    <th style="width: 27%;">{{ $locale === 'hi' ? 'महत्व' : 'Traditional Significance' }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $interpHouses = $kundli['interpretations'][$locale]['houses'] ?? ($kundli['interpretations']['en']['houses'] ?? []);
                @endphp
                @foreach($kundli['houses'] as $h)
                @php
                    $hNum = $h['house_number'];
                    $hInfo = $interpHouses[$hNum] ?? null;
                    $residents = [];
                    $rawResidents = $h['planets'] ?? ($h['resident_planets'] ?? []);
                    foreach($rawResidents as $rp) {
                        $residents[] = $locale === 'hi' ? ($rp['name_hi'] ?? $rp['name']) : $rp['name'];
                    }
                    $signName = $locale === 'hi'
                        ? ($h['sign_vedic_name'] ?? ($h['rashi']['vedic_name'] ?? ($h['sign_name'] ?? ($h['rashi']['name'] ?? '-'))))
                        : ($h['sign_name'] ?? ($h['rashi']['name'] ?? '-'));
                    $lordName = $locale === 'hi'
                        ? ($hInfo['lord_hi'] ?? ($h['sign_lord'] ?? '-'))
                        : ($hInfo['lord'] ?? ($h['sign_lord'] ?? '-'));
                @endphp
                <tr>
                    <td style="text-align: center; font-weight: bold;">{{ $hNum }}</td>
                    <td>{{ $hInfo['title'] ?? ($h['house_number'] . 'th House') }}</td>
                    <td>{{ $signName }}</td>
                    <td>{{ $lordName }}</td>
                    <td>{{ count($residents) > 0 ? implode(', ', $residents) : '-' }}</td>
                    <td>{{ $hInfo['significance'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Janma Nakshatra Details -->
    @php
        $nakshatraData = $kundli['interpretations'][$locale]['nakshatra'] ?? ($kundli['interpretations']['en']['nakshatra'] ?? null);
    @endphp
    @if($nakshatraData)
    <div class="section-box" style="margin-top: 10px;">
        <div class="section-title">{{ $locale === 'hi' ? 'जन्म नक्षत्र विवरण (Janma Nakshatra)' : 'Janma Nakshatra Details' }}</div>
        <table class="meta-grid">
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'नक्षत्र नाम' : 'Nakshatra' }}:</td>
                <td class="meta-value"><strong>{{ $locale === 'hi' ? $nakshatraData['name_hi'] : $nakshatraData['name'] }}</strong> ({{ $locale === 'hi' ? 'पाद' : 'Pada' }} {{ $nakshatraData['pada'] }})</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'नक्षत्र स्वामी' : 'Ruling Lord' }}:</td>
                <td class="meta-value">{{ $locale === 'hi' ? $nakshatraData['lord_hi'] : $nakshatraData['lord'] }}</td>
            </tr>
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'अधिष्ठाता देवता' : 'Presiding Deity' }}:</td>
                <td class="meta-value">{{ $nakshatraData['deity'] ?? '-' }}</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'प्रतीक चिन्ह' : 'Symbol' }}:</td>
                <td class="meta-value">{{ $nakshatraData['symbol'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">{{ $locale === 'hi' ? 'गण' : 'Gana' }}:</td>
                <td class="meta-value">{{ $nakshatraData['gana'] ?? '-' }}</td>
                <td class="meta-label">{{ $locale === 'hi' ? 'पारंपरिक महत्व' : 'Significance' }}:</td>
                <td class="meta-value">{{ $nakshatraData['significance'] ?? '-' }}</td>
            </tr>
        </table>
    </div>
    @endif

    <!-- Vimshottari Dasha Section -->
    @if(!empty($kundli['dasha']) && ($kundli['dasha']['status'] ?? '') === 'available')
    <div style="margin-top: 10px;">
        <div class="section-title">{{ $locale === 'hi' ? 'विंशोत्तरी दशा चक्र (Vimshottari Dasha - 120 Years)' : 'Vimshottari Dasha Timeline (120-Year Cycle)' }}</div>

        @if(!empty($kundli['dasha']['current_dasha']))
        @php
            $curr = $kundli['dasha']['current_dasha'];
        @endphp
        <div style="background-color: #EBF8FF; border-left: 3px solid #3182CE; padding: 6px 10px; margin-bottom: 8px; font-size: 8.5px;">
            <strong>{{ $locale === 'hi' ? 'वर्तमान दशा:' : 'Active Dasha:' }}</strong>
            {{ $locale === 'hi' ? $curr['mahadasha']['lord_hi'] : $curr['mahadasha']['lord'] }} - {{ $locale === 'hi' ? $curr['antardasha']['lord_hi'] : $curr['antardasha']['lord'] }}
            ({{ $locale === 'hi' ? 'समाप्ति:' : 'Until:' }} {{ \Carbon\Carbon::parse($curr['antardasha']['end_date'])->format('d M Y') }})
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
                @foreach($kundli['dasha']['mahadashas'] as $md)
                <tr style="{{ !empty($md['is_current']) ? 'background-color: #FEFCBF; font-weight: bold;' : '' }}">
                    <td>{{ $locale === 'hi' ? $md['lord_hi'] : $md['lord'] }}</td>
                    <td>{{ $md['years'] }} {{ $locale === 'hi' ? 'वर्ष' : 'Yrs' }}</td>
                    <td>{{ \Carbon\Carbon::parse($md['start_date'])->format('d M Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($md['end_date'])->format('d M Y') }}</td>
                    <td>{{ !empty($md['is_current']) ? ($locale === 'hi' ? 'सक्रिय (Current)' : 'Active') : '-' }}</td>
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
