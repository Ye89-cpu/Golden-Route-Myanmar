
<?php
require_once __DIR__ . '/../config.php';

if (!function_exists('about_asset_url')) {
    function about_asset_url(string $relativePath = '', string $fallback = 'assets/images/tourh.png'): string
    {
        $relativePath = ltrim($relativePath, '/');
        $fallback = ltrim($fallback, '/');

        if ($relativePath !== '') {
            $absolute = dirname(__DIR__) . '/' . $relativePath;
            if (is_file($absolute)) {
                return BASE_URL . $relativePath;
            }
        }

        return BASE_URL . $fallback;
    }
}

if (!function_exists('grm_about_page_data')) {
    function grm_about_page_data(): array
    {
        return [
            'hero_badge' => 'Our Story',
            'hero_title' => 'From a local travel counter to a modern digital travel platform',
            'hero_text' => 'Golden Route Myanmar began with simple offline ticketing and small group travel support. Over time, customer needs changed, travel patterns changed, and the business grew from in-person service into a platform designed to make bus booking, tours, payment and travel history easier for everyone.',
            'hero_image' => about_asset_url('assets/images/about/about-hero.jpg', 'assets/images/tourh.png'),
            'intro_title' => 'How we started',
            'intro_text' => 'At the beginning, the business focused on helping customers directly from a physical counter. People came to ask about routes, prices, departure times and travel suggestions. Later, many customers also wanted small curated trips, cultural journeys and group tours. That hands-on experience became the foundation of Golden Route Myanmar.',
            'stats' => [
                ['label' => 'Travel roots', 'value' => 'Offline service'],
                ['label' => 'Focus today', 'value' => 'Bus + Tour booking'],
                ['label' => 'Main goal', 'value' => 'Easy travel access'],
            ],
            'timeline' => [
                [
                    'year' => '2016',
                    'title' => 'Started from direct customer service',
                    'text' => 'The journey began with face-to-face support for bus tickets, local travel advice and personalized help for customers who preferred trusted human service.',
                ],
                [
                    'year' => '2018',
                    'title' => 'Expanded into curated tours',
                    'text' => 'As more travelers requested complete experiences, the business began organizing simple heritage, cultural and leisure trips with transport guidance and trip coordination.',
                ],
                [
                    'year' => '2020',
                    'title' => 'COVID changed the travel business',
                    'text' => 'The pandemic changed customer behavior and created a need for safer planning, faster communication and more flexible digital coordination.',
                ],
                [
                    'year' => '2021-2023',
                    'title' => 'Digital planning phase',
                    'text' => 'The team started planning a better system that could support online booking, package discovery, payment submission, notifications and travel records in one place.',
                ],
                [
                    'year' => 'Now',
                    'title' => 'Golden Route Myanmar online',
                    'text' => 'Today, the platform is being shaped into a smoother digital experience while still keeping the warmth and trust of the original service model.',
                ],
            ],
            'web_shift_title' => 'Why we moved toward the web platform',
            'web_shift_reasons' => [
                [
                    'icon' => 'bi-globe2',
                    'title' => 'Customers needed easier access',
                    'text' => 'Instead of visiting a shop or waiting for manual updates, customers can browse routes and tours online at any time.',
                ],
                [
                    'icon' => 'bi-shield-check',
                    'title' => 'Safer and more organized after COVID',
                    'text' => 'COVID pushed many services to become more contact-light, better documented and easier to manage from a distance.',
                ],
                [
                    'icon' => 'bi-phone',
                    'title' => 'Mobile-first travel behavior',
                    'text' => 'More people now search, compare and confirm travel plans from their phones, so the service also needs to be available there.',
                ],
                [
                    'icon' => 'bi-journal-check',
                    'title' => 'Better booking records',
                    'text' => 'A digital platform makes it easier to store booking history, payment progress, tickets, vouchers and notifications.',
                ],
            ],
            'values' => [
                [
                    'title' => 'Trust',
                    'text' => 'We want customers to feel the same confidence online that they felt when speaking to us in person.',
                ],
                [
                    'title' => 'Convenience',
                    'text' => 'Finding buses, tours and booking details should feel simple, clear and fast.',
                ],
                [
                    'title' => 'Local experience',
                    'text' => 'Our service is built around real travel routes, real local destinations and practical trip support.',
                ],
                [
                    'title' => 'Growth',
                    'text' => 'The platform is designed not just for today, but for future travel services, promotions and richer customer experiences.',
                ],
            ],
        ];
    }
}

if (!function_exists('grm_about_histories')) {
    function grm_about_histories(): array
    {
        return [
            [
                'slug' => 'bagan-sunrise-memory',
                'title' => 'Bagan Sunrise Heritage Journey',
                'year' => '2018',
                'location' => 'Bagan',
                'duration' => '3 Days / 2 Nights',
                'cover_image' => about_asset_url('assets/about/bagan_sun.png', 'assets/images/tourh.png'),
                'excerpt' => 'One of our memorable early cultural trips focused on temples, sunrise views and slow travel with meaningful local moments.',
                'summary' => 'This trip became special because many guests did not only want transport. They wanted a complete travel feeling: sunrise, local stories, photo moments and peaceful cultural stops.',
                'story_paragraphs' => [
                    'The Bagan journey was one of the trips that helped shape our idea of what a memorable package should feel like. Guests were not simply buying a seat or a route. They wanted atmosphere, timing, comfort and guidance.',
                    'We started early in the morning, planned around the best light for temple views and included practical support that made the trip feel smoother for families and first-time travelers.',
                    'The response from guests showed us that curated experiences could be just as important as transportation itself. That lesson later influenced how we planned tour packages for the website.',
                ],
                'highlights' => [
                    'Sunrise temple view experience',
                    'Cultural sightseeing with relaxed pacing',
                    'Memorable photo-friendly stops',
                    'Practical local guidance throughout the trip',
                ],
                'memories' => [
                    'Guests loved the early sunrise atmosphere and quiet temple moments.',
                    'The trip proved that storytelling and travel experience matter as much as the destination.',
                    'It became a model for later cultural and heritage packages.',
                ],
                'gallery' => [
                    [
                        'image' => about_asset_url('assets/images/about/bagan-gallery-1.jpg', 'assets/images/tourh.png'),
                        'caption' => 'Golden sunrise mood and heritage atmosphere',
                    ],
                    [
                        'image' => about_asset_url('assets/images/about/bagan-gallery-2.jpg', 'assets/images/bus.png'),
                        'caption' => 'Comfortable travel flow for guests',
                    ],
                ],
            ],
            [
                'slug' => 'inle-lake-discovery-memory',
                'title' => 'Inle Lake Discovery Experience',
                'year' => '2019',
                'location' => 'Inle Lake',
                'duration' => '2 Days / 1 Night',
                'cover_image' => about_asset_url('assets/about/inlay.jpg', 'assets/images/tour.png'),
                'excerpt' => 'A softer, slower trip designed around lake scenery, local lifestyle and a more peaceful travel rhythm.',
                'summary' => 'This tour reflected a different side of our service: calm travel, scenic pacing and a stronger emotional connection to the destination.',
                'story_paragraphs' => [
                    'The Inle trip was designed for travelers who wanted quiet beauty instead of a fast schedule. Boat moments, scenic views and local culture shaped the experience.',
                    'This journey taught us that some customers value calm pacing, soft service details and emotional travel memories over packed itineraries.',
                    'Because of this kind of feedback, our idea of online tours became broader. We wanted the website to show not just prices and routes, but also the feeling of each journey.',
                ],
                'highlights' => [
                    'Lake-side mood and scenic travel',
                    'Local lifestyle experience',
                    'Soft-paced itinerary suitable for relaxed travelers',
                    'Stronger emotional memory value',
                ],
                'memories' => [
                    'Travelers appreciated the calm and scenic pace.',
                    'The destination felt personal, not rushed.',
                    'It inspired a more experience-based way of presenting tours.',
                ],
                'gallery' => [
                    [
                        'image' => about_asset_url('assets/images/about/inle-gallery-1.jpg', 'assets/images/tour.png'),
                        'caption' => 'Relaxed destination experience',
                    ],
                    [
                        'image' => about_asset_url('assets/images/about/inle-gallery-2.jpg', 'assets/images/thin.jpg'),
                        'caption' => 'Simple moments that stayed memorable',
                    ],
                ],
            ],
            [
                'slug' => 'ngapali-relax-memory',
                'title' => 'Ngapali Beach Relax Package Memory',
                'year' => '2019',
                'location' => 'Ngapali',
                'duration' => '3 Days / 2 Nights',
                'cover_image' => about_asset_url('assets/about/ngapali.jpg', 'assets/images/thin.jpg'),
                'excerpt' => 'A leisure-focused package that showed us how strongly people value comfort, escape and simple well-managed holiday plans.',
                'summary' => 'This package highlighted the importance of convenience. Many travelers wanted a full trip arranged clearly in advance so they could simply enjoy the destination.',
                'story_paragraphs' => [
                    'Ngapali packages became memorable because they matched what many travelers wanted after busy routines: simple planning, beautiful surroundings and less stress.',
                    'This kind of tour pushed us to think more carefully about package presentation, batch comparison, pricing clarity and what information should appear before booking.',
                    'Those ideas now connect directly to the online platform, where we want users to compare packages more confidently and understand what each trip includes.',
                ],
                'highlights' => [
                    'Comfort-first holiday planning',
                    'Beach escape with simple coordination',
                    'Clear package value for customers',
                    'Stronger inspiration for digital comparison tools',
                ],
                'memories' => [
                    'Guests valued convenience and clarity before departure.',
                    'The package showed how important inclusion details are.',
                    'It helped shape the idea of cleaner online package display.',
                ],
                'gallery' => [
                    [
                        'image' => about_asset_url('assets/images/about/ngapali-gallery-1.jpg', 'assets/images/thin.jpg'),
                        'caption' => 'Relaxed holiday atmosphere',
                    ],
                    [
                        'image' => about_asset_url('assets/images/about/ngapali-gallery-2.jpg', 'assets/images/tourh.png'),
                        'caption' => 'A package built around ease and escape',
                    ],
                ],
            ],
        ];
    }
}

if (!function_exists('grm_about_story_by_slug')) {
    function grm_about_story_by_slug(string $slug): ?array
    {
        foreach (grm_about_histories() as $story) {
            if (($story['slug'] ?? '') === $slug) {
                return $story;
            }
        }

        return null;
    }
}