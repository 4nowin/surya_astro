<?php

declare(strict_types=1);

namespace App\Services\Geo;

class LocalGazetteerGeocodingProvider implements GeocodingServiceInterface
{
    /**
     * Verified canonical gazetteer for Vedic astrology calculations.
     * Contains accurate topocentric coordinates (7 decimals) and canonical IANA timezones.
     *
     * @var array<int, array{name: string, state: ?string, country: string, latitude: float, longitude: float, timezone: string, aliases?: array<string>}>
     */
    protected static array $cities = [
        // India - Major Metros & Capitals
        ['name' => 'New Delhi', 'state' => 'Delhi', 'country' => 'India', 'latitude' => 28.6139391, 'longitude' => 77.2090212, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Delhi', 'Dilli']],
        ['name' => 'Mumbai', 'state' => 'Maharashtra', 'country' => 'India', 'latitude' => 19.0760900, 'longitude' => 72.8774260, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Bombay']],
        ['name' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India', 'latitude' => 12.9715987, 'longitude' => 77.5945627, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Bangalore']],
        ['name' => 'Kolkata', 'state' => 'West Bengal', 'country' => 'India', 'latitude' => 22.5726460, 'longitude' => 88.3638950, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Calcutta']],
        ['name' => 'Chennai', 'state' => 'Tamil Nadu', 'country' => 'India', 'latitude' => 13.0826802, 'longitude' => 80.2707184, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Madras']],
        ['name' => 'Hyderabad', 'state' => 'Telangana', 'country' => 'India', 'latitude' => 17.3850440, 'longitude' => 78.4866710, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Ahmedabad', 'state' => 'Gujarat', 'country' => 'India', 'latitude' => 23.0225050, 'longitude' => 72.5713621, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Amdavad']],
        ['name' => 'Pune', 'state' => 'Maharashtra', 'country' => 'India', 'latitude' => 18.5204303, 'longitude' => 73.8567437, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Poona']],
        ['name' => 'Jaipur', 'state' => 'Rajasthan', 'country' => 'India', 'latitude' => 26.9124336, 'longitude' => 75.7872709, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Lucknow', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 26.8467088, 'longitude' => 80.9461592, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Kanpur', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 26.4499230, 'longitude' => 80.3318745, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Nagpur', 'state' => 'Maharashtra', 'country' => 'India', 'latitude' => 21.1458004, 'longitude' => 79.0881546, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Indore', 'state' => 'Madhya Pradesh', 'country' => 'India', 'latitude' => 22.7195687, 'longitude' => 75.8577258, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Bhopal', 'state' => 'Madhya Pradesh', 'country' => 'India', 'latitude' => 23.2599333, 'longitude' => 77.4126150, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Patna', 'state' => 'Bihar', 'country' => 'India', 'latitude' => 25.5940947, 'longitude' => 85.1375645, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Vadodara', 'state' => 'Gujarat', 'country' => 'India', 'latitude' => 22.3071588, 'longitude' => 73.1812187, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Baroda']],
        ['name' => 'Surat', 'state' => 'Gujarat', 'country' => 'India', 'latitude' => 21.1702401, 'longitude' => 72.8310607, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Chandigarh', 'state' => 'Chandigarh', 'country' => 'India', 'latitude' => 30.7333148, 'longitude' => 76.7794179, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Dehradun', 'state' => 'Uttarakhand', 'country' => 'India', 'latitude' => 30.3164945, 'longitude' => 78.0321918, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Shimla', 'state' => 'Himachal Pradesh', 'country' => 'India', 'latitude' => 31.1048145, 'longitude' => 77.1734033, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Srinagar', 'state' => 'Jammu and Kashmir', 'country' => 'India', 'latitude' => 34.0836560, 'longitude' => 74.7973710, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Ranchi', 'state' => 'Jharkhand', 'country' => 'India', 'latitude' => 23.3440997, 'longitude' => 85.3095620, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Bhubaneswar', 'state' => 'Odisha', 'country' => 'India', 'latitude' => 20.2960587, 'longitude' => 85.8245398, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Raipur', 'state' => 'Chhattisgarh', 'country' => 'India', 'latitude' => 21.2513844, 'longitude' => 81.6296413, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Guwahati', 'state' => 'Assam', 'country' => 'India', 'latitude' => 26.1445169, 'longitude' => 91.7362365, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Thiruvananthapuram', 'state' => 'Kerala', 'country' => 'India', 'latitude' => 8.5241391, 'longitude' => 76.9366376, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Trivandrum']],
        ['name' => 'Kochi', 'state' => 'Kerala', 'country' => 'India', 'latitude' => 9.9312328, 'longitude' => 76.2673041, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Cochin']],
        ['name' => 'Coimbatore', 'state' => 'Tamil Nadu', 'country' => 'India', 'latitude' => 11.0168445, 'longitude' => 76.9558321, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Madurai', 'state' => 'Tamil Nadu', 'country' => 'India', 'latitude' => 9.9252007, 'longitude' => 78.1197754, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Mysore', 'state' => 'Karnataka', 'country' => 'India', 'latitude' => 12.2958104, 'longitude' => 76.6393805, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Mysuru']],
        ['name' => 'Visakhapatnam', 'state' => 'Andhra Pradesh', 'country' => 'India', 'latitude' => 17.6868159, 'longitude' => 83.2184815, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Vizag']],
        ['name' => 'Vijayawada', 'state' => 'Andhra Pradesh', 'country' => 'India', 'latitude' => 16.5061743, 'longitude' => 80.6480153, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Varanasi', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 25.3176452, 'longitude' => 82.9739144, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Kashi', 'Banaras']],
        ['name' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 25.4358011, 'longitude' => 81.8463110, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Allahabad']],
        ['name' => 'Ayodhya', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 26.7921600, 'longitude' => 82.1998000, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Mathura', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 27.4924134, 'longitude' => 77.6736730, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Haridwar', 'state' => 'Uttarakhand', 'country' => 'India', 'latitude' => 29.9456906, 'longitude' => 78.1642478, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Rishikesh', 'state' => 'Uttarakhand', 'country' => 'India', 'latitude' => 30.0869281, 'longitude' => 78.2676116, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Ujjain', 'state' => 'Madhya Pradesh', 'country' => 'India', 'latitude' => 23.1764665, 'longitude' => 75.7885163, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Puri', 'state' => 'Odisha', 'country' => 'India', 'latitude' => 19.8134554, 'longitude' => 85.8312359, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Tirupati', 'state' => 'Andhra Pradesh', 'country' => 'India', 'latitude' => 13.6287557, 'longitude' => 79.4191795, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Shirdi', 'state' => 'Maharashtra', 'country' => 'India', 'latitude' => 19.7668000, 'longitude' => 74.4762000, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Amritsar', 'state' => 'Punjab', 'country' => 'India', 'latitude' => 31.6339793, 'longitude' => 74.8722642, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Ludhiana', 'state' => 'Punjab', 'country' => 'India', 'latitude' => 30.9009650, 'longitude' => 75.8572758, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Gurugram', 'state' => 'Haryana', 'country' => 'India', 'latitude' => 28.4594965, 'longitude' => 77.0266383, 'timezone' => 'Asia/Kolkata', 'aliases' => ['Gurgaon']],
        ['name' => 'Noida', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 28.5355161, 'longitude' => 77.3910265, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Ghaziabad', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 28.6691565, 'longitude' => 77.4537578, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Faridabad', 'state' => 'Haryana', 'country' => 'India', 'latitude' => 28.4089123, 'longitude' => 77.3177894, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Agra', 'state' => 'Uttar Pradesh', 'country' => 'India', 'latitude' => 27.1766701, 'longitude' => 78.0080745, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Jodhpur', 'state' => 'Rajasthan', 'country' => 'India', 'latitude' => 26.2389469, 'longitude' => 73.0243094, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Udaipur', 'state' => 'Rajasthan', 'country' => 'India', 'latitude' => 24.5854450, 'longitude' => 73.7124790, 'timezone' => 'Asia/Kolkata'],
        ['name' => 'Kota', 'state' => 'Rajasthan', 'country' => 'India', 'latitude' => 25.2138156, 'longitude' => 75.8647527, 'timezone' => 'Asia/Kolkata'],

        // Global Key Spiritual and Diaspora Locations
        ['name' => 'Kathmandu', 'state' => 'Bagmati', 'country' => 'Nepal', 'latitude' => 27.7172453, 'longitude' => 85.3239605, 'timezone' => 'Asia/Kathmandu'],
        ['name' => 'Pokhara', 'state' => 'Gandaki', 'country' => 'Nepal', 'latitude' => 28.2095831, 'longitude' => 83.9855670, 'timezone' => 'Asia/Kathmandu'],
        ['name' => 'Colombo', 'state' => 'Western Province', 'country' => 'Sri Lanka', 'latitude' => 6.9270786, 'longitude' => 79.8612430, 'timezone' => 'Asia/Colombo'],
        ['name' => 'London', 'state' => 'England', 'country' => 'United Kingdom', 'latitude' => 51.5073509, 'longitude' => -0.1277583, 'timezone' => 'Europe/London'],
        ['name' => 'New York', 'state' => 'New York', 'country' => 'United States', 'latitude' => 40.7127753, 'longitude' => -74.0059728, 'timezone' => 'America/New_York', 'aliases' => ['NYC']],
        ['name' => 'San Francisco', 'state' => 'California', 'country' => 'United States', 'latitude' => 37.7749295, 'longitude' => -122.4194155, 'timezone' => 'America/Los_Angeles', 'aliases' => ['SF', 'Bay Area']],
        ['name' => 'Los Angeles', 'state' => 'California', 'country' => 'United States', 'latitude' => 34.0522342, 'longitude' => -118.2436849, 'timezone' => 'America/Los_Angeles', 'aliases' => ['LA']],
        ['name' => 'Chicago', 'state' => 'Illinois', 'country' => 'United States', 'latitude' => 41.8781136, 'longitude' => -87.6297982, 'timezone' => 'America/Chicago'],
        ['name' => 'Houston', 'state' => 'Texas', 'country' => 'United States', 'latitude' => 29.7604267, 'longitude' => -95.3698028, 'timezone' => 'America/Chicago'],
        ['name' => 'Dallas', 'state' => 'Texas', 'country' => 'United States', 'latitude' => 32.7766642, 'longitude' => -96.7969879, 'timezone' => 'America/Chicago'],
        ['name' => 'Toronto', 'state' => 'Ontario', 'country' => 'Canada', 'latitude' => 43.6532260, 'longitude' => -79.3831843, 'timezone' => 'America/Toronto'],
        ['name' => 'Vancouver', 'state' => 'British Columbia', 'country' => 'Canada', 'latitude' => 49.2827291, 'longitude' => -123.1207375, 'timezone' => 'America/Vancouver'],
        ['name' => 'Dubai', 'state' => 'Dubai', 'country' => 'United Arab Emirates', 'latitude' => 25.2048493, 'longitude' => 55.2707828, 'timezone' => 'Asia/Dubai', 'aliases' => ['UAE']],
        ['name' => 'Abu Dhabi', 'state' => 'Abu Dhabi', 'country' => 'United Arab Emirates', 'latitude' => 24.4538840, 'longitude' => 54.3773438, 'timezone' => 'Asia/Dubai'],
        ['name' => 'Singapore', 'state' => null, 'country' => 'Singapore', 'latitude' => 1.3520830, 'longitude' => 103.8198360, 'timezone' => 'Asia/Singapore'],
        ['name' => 'Kuala Lumpur', 'state' => 'Federal Territory', 'country' => 'Malaysia', 'latitude' => 3.1390030, 'longitude' => 101.6868550, 'timezone' => 'Asia/Kuala_Lumpur', 'aliases' => ['KL']],
        ['name' => 'Sydney', 'state' => 'New South Wales', 'country' => 'Australia', 'latitude' => -33.8688197, 'longitude' => 151.2092955, 'timezone' => 'Australia/Sydney'],
        ['name' => 'Melbourne', 'state' => 'Victoria', 'country' => 'Australia', 'latitude' => -37.8136276, 'longitude' => 144.9630576, 'timezone' => 'Australia/Melbourne'],
        ['name' => 'Port Louis', 'state' => 'Port Louis', 'country' => 'Mauritius', 'latitude' => -20.1608912, 'longitude' => 57.5012222, 'timezone' => 'Indian/Mauritius'],
        ['name' => 'Bangkok', 'state' => 'Bangkok', 'country' => 'Thailand', 'latitude' => 13.7563309, 'longitude' => 100.5017651, 'timezone' => 'Asia/Bangkok'],
        ['name' => 'Tokyo', 'state' => 'Tokyo', 'country' => 'Japan', 'latitude' => 35.6761919, 'longitude' => 139.6503106, 'timezone' => 'Asia/Tokyo'],
        ['name' => 'Paris', 'state' => 'Ile-de-France', 'country' => 'France', 'latitude' => 48.8566140, 'longitude' => 2.3522219, 'timezone' => 'Europe/Paris'],
        ['name' => 'Berlin', 'state' => 'Berlin', 'country' => 'Germany', 'latitude' => 52.5200066, 'longitude' => 13.4049540, 'timezone' => 'Europe/Berlin'],
        ['name' => 'Johannesburg', 'state' => 'Gauteng', 'country' => 'South Africa', 'latitude' => -26.2041028, 'longitude' => 28.0473051, 'timezone' => 'Africa/Johannesburg'],
    ];

    public function searchCities(string $query, int $limit = 10): array
    {
        $normalized = mb_strtolower(trim($query));

        // When query is empty, return top featured cities up to limit
        if ($normalized === '') {
            return array_map(
                fn (array $city) => $this->formatCityResult($city),
                array_slice(self::$cities, 0, $limit)
            );
        }

        $results = [];
        foreach (self::$cities as $city) {
            $name = mb_strtolower($city['name']);
            $state = $city['state'] ? mb_strtolower($city['state']) : '';
            $country = mb_strtolower($city['country']);

            $match = false;
            // Exact prefix or substring on city name
            if (str_starts_with($name, $normalized) || str_contains($name, $normalized)) {
                $match = true;
            } elseif ($state !== '' && (str_starts_with($state, $normalized) || str_contains($state, $normalized))) {
                $match = true;
            } elseif (str_starts_with($country, $normalized) || str_contains($country, $normalized)) {
                $match = true;
            } elseif (!empty($city['aliases'])) {
                foreach ($city['aliases'] as $alias) {
                    if (str_contains(mb_strtolower($alias), $normalized)) {
                        $match = true;
                        break;
                    }
                }
            }

            if ($match) {
                $results[] = $this->formatCityResult($city);
            }

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param array{name: string, state: ?string, country: string, latitude: float, longitude: float, timezone: string, aliases?: array<string>} $city
     * @return array{city: string, name: string, state: ?string, country: string, latitude: float, longitude: float, timezone: string, label: string}
     */
    protected function formatCityResult(array $city): array
    {
        $parts = array_filter([$city['name'], $city['state'], $city['country']]);
        return [
            'city'      => $city['name'],
            'name'      => $city['name'],
            'state'     => $city['state'],
            'country'   => $city['country'],
            'latitude'  => (float) $city['latitude'],
            'longitude' => (float) $city['longitude'],
            'timezone'  => $city['timezone'],
            'label'     => implode(', ', $parts),
        ];
    }
}
