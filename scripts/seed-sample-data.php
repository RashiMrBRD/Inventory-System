<?php
/**
 * High-Volume Database Seeding Script
 * -----------------------------------
 * Populates MongoDB with LARGE synthetic datasets (17,678 records per key collection)
 * so every module (inventory, quotations, invoicing, orders, projects, shipping,
 * chart of accounts, journal entries, financial reports) can be stress-tested with
 * realistic values that still respect Philippine accounting/BIR rules.
 *
 * Usage: php scripts/seed-sample-data.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Service\DatabaseService;
use MongoDB\BSON\UTCDateTime;

const SEED_COUNT_PER_COLLECTION = 10127;
const BATCH_SIZE = 500;
const PURGE_BEFORE_SEED = true;
const DATA_DIR = __DIR__ . '/data';
const REVENUE_MIN = 500000;
const REVENUE_MAX = 15000000;
const PURCHASE_MIN = 300000;
const PURCHASE_MAX = 9000000;
const QUOTATION_MIN = 250000;
const QUOTATION_MAX = 4000000;
const PROJECT_BUDGET_MIN = 2000000;
const PROJECT_BUDGET_MAX = 20000000;
const PROJECT_SPENT_MIN = 500000;
const PROJECT_SPENT_MAX = 15000000;
const JOURNAL_ENTRY_MIN = 250000;
const JOURNAL_ENTRY_MAX = 3000000;
const BIR_WITHHELD_MIN = 500000;
const BIR_WITHHELD_MAX = 4500000;
const TAX_CREDIT_MIN = 250000;
const TAX_CREDIT_MAX = 2000000;

$collectionsToSeed = [
    'inventory' => 'Inventory Items',
    'quotations' => 'Quotations',
    'invoices' => 'Invoices',
    'orders' => 'Orders',
    'projects' => 'Projects',
    'shipments' => 'Shipments',
    'chart_of_accounts' => 'Chart of Accounts',
    'journal_entries' => 'Journal Entries',
    'financial_reports' => 'Financial Reports',
    'fda_products' => 'FDA Products',
    'bir_forms' => 'BIR Forms',
    'payments' => 'Tax Payments',
];

$startTime = microtime(true);

echo "==========================================\n";
echo "  Massive Database Seeding Utility\n";
echo "  Target: " . SEED_COUNT_PER_COLLECTION . " records / collection\n";
echo "==========================================\n\n";

$db = DatabaseService::getInstance();

if (PURGE_BEFORE_SEED) {
    echo "Purging existing documents...\n";
    foreach (array_keys($collectionsToSeed) as $collectionName) {
        $collection = $db->getCollection($collectionName);
        $deleted = $collection->deleteMany([]);
        echo sprintf(" - %s: removed %d documents\n", $collectionName, $deleted->getDeletedCount());
    }
    echo "Purge completed.\n\n";
}

// ------------------------------------------------------------------
// Helper utilities
// ------------------------------------------------------------------
const RECENT_TIMELINE_PRESETS = [
    'this_month' => ['start' => 'first day of this month 00:00:00', 'end' => 'now', 'weight' => 20],
    'last_quarter' => ['start' => '-3 months', 'end' => 'now', 'weight' => 30],
    'recent_half' => ['start' => '-6 months', 'end' => '-3 months', 'weight' => 20],
    'one_to_three_years' => ['start' => '-3 years', 'end' => '-1 year', 'weight' => 30],
];

const TAX_CREDIT_MAX = 2000000;

function anchorFromTimeline(?string $preset = null): array
{
    $pool = RECENT_TIMELINE_PRESETS;
    if ($preset && isset($pool[$preset])) {
        $window = $pool[$preset];
        $window['key'] = $preset;
    } else {
        $total = array_sum(array_column($pool, 'weight'));
        $roll = mt_rand(1, max(1, $total));
        foreach ($pool as $key => $window) {
            $roll -= $window['weight'];
            if ($roll <= 0) {
                $window['key'] = $key;
                break;
            }
        }
        if (!isset($window)) {
            $key = array_key_first($pool);
            $window = $pool[$key];
            $window['key'] = $key;
        }
    }

    $startTs = strtotime($window['start']);
    $endTs = strtotime($window['end']);
    if ($startTs === false || $endTs === false) {
        $startTs = strtotime('-6 months');
        $endTs = time();
    }
    if ($startTs > $endTs) {
        [$startTs, $endTs] = [$endTs, $startTs];
    }

    return [
        'bucket' => $window['key'],
        'timestamp' => mt_rand($startTs, max($startTs + 86400, $endTs)),
    ];
}

function anchorUtcDate(array $anchor, string $modifier = 'now', bool $clampFuture = false): UTCDateTime
{
    $baseTs = $anchor['timestamp'] ?? time();
    $ts = strtotime($modifier, $baseTs) ?: $baseTs;
    if ($clampFuture && $ts > time()) {
        $ts = time();
    }
    return new UTCDateTime($ts * 1000);
}

function randomUtcDate(string $start = '-2 years', string $end = 'now + 30 days'): UTCDateTime
{
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    if ($startTs >= $endTs) {
        $startTs = strtotime('-2 years');
        $endTs = time();
    }
    $timestamp = mt_rand($startTs, $endTs);
    return new UTCDateTime($timestamp * 1000);
}

function randomMoney($min = 500, $max = 250000): float
{
    $minInt = (int)$min;
    $maxInt = (int)$max;
    return round(mt_rand($minInt * 100, $maxInt * 100) / 100, 2);
}

function pick(array $items)
{
    return $items[array_rand($items)];
}

function seedCollection(string $collectionName, string $label, callable $factory): void
{
    global $db;

    $collection = $db->getCollection($collectionName);
    $batch = [];
    $inserted = 0;

    echo sprintf("Seeding %-20s ... ", $label);

    for ($i = 1; $i <= SEED_COUNT_PER_COLLECTION; $i++) {
        $batch[] = $factory($i);

        if (count($batch) === BATCH_SIZE) {
            $collection->insertMany($batch);
            $inserted += count($batch);
            $batch = [];
        }

        if ($i % 1000 === 0) {
            echo ".";
        }
    }

    if (!empty($batch)) {
        $collection->insertMany($batch);
        $inserted += count($batch);
    }

    echo " done ({$inserted} docs)\n";
}

function randomAlphaNumeric(int $length = 6): string
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $result = '';
    $maxIndex = strlen($characters) - 1;
    for ($i = 0; $i < $length; $i++) {
        $result .= $characters[random_int(0, $maxIndex)];
    }
    return $result;
}

function randomNumericString(int $length = 6): string
{
    $digits = '';
    for ($i = 0; $i < $length; $i++) {
        $digits .= (string)random_int(0, 9);
    }
    return $digits;
}

function generateUniqueCode(string $prefix, int $length, array &$registry, bool $numeric = false): string
{
    do {
        $body = $numeric ? randomNumericString($length) : randomAlphaNumeric($length);
        $code = $prefix . $body;
    } while (isset($registry[$code]));

    $registry[$code] = true;
    return $code;
}

function uniqueProductLabel(string $base): string
{
    global $productDescriptors, $philippineLocations;
    $descriptor = pick($productDescriptors);
    $location = pick($philippineLocations);
    $suffix = randomAlphaNumeric(3);
    return sprintf('%s %s %s %s', $descriptor, $base, $location, $suffix);
}

function uniqueBusinessLabel(string $base, string $label = 'Branch'): string
{
    global $philippineLocations;
    $location = pick($philippineLocations);
    $suffix = randomAlphaNumeric(3);
    return sprintf('%s - %s %s %s', $base, $location, $label, $suffix);
}

function buildCatalog(array $bases, string $category, array $variantTags, array $sizeTags): array
{
    $catalog = [];
    foreach ($bases as $base) {
        foreach ($variantTags as $variant) {
            foreach ($sizeTags as $size) {
                $catalog[] = [
                    'name' => trim(sprintf('%s %s %s', $base, $variant, $size)),
                    'category' => $category
                ];
            }
        }
    }
    return $catalog;
}

function buildCatalogWithoutSizes(array $bases, string $category, array $variantTags): array
{
    $catalog = [];
    foreach ($bases as $base) {
        foreach ($variantTags as $variant) {
            $catalog[] = [
                'name' => trim(sprintf('%s %s', $base, $variant)),
                'category' => $category
            ];
        }
    }
    return $catalog;
}

// ------------------------------------------------------------------
// Dataset utilities (loads Kaggle CSVs when present, falls back to curated Filipino data)
// ------------------------------------------------------------------
function loadCsvColumn(string $filename, string $columnName, array $fallback): array
{
    $path = DATA_DIR . '/' . $filename;
    if (!is_readable($path)) {
        return $fallback;
    }

    $rows = [];
    if (($handle = fopen($path, 'r')) !== false) {
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return $fallback;
        }

        $index = array_search($columnName, $header, true);
        if ($index === false) {
            fclose($handle);
            return $fallback;
        }

        while (($data = fgetcsv($handle)) !== false) {
            $value = trim($data[$index] ?? '');
            if ($value !== '') {
                $rows[] = $value;
            }
        }
        fclose($handle);
    }

    return !empty($rows) ? array_values(array_unique($rows)) : $fallback;
}

function ensureValues(array $data, array $fallback): array
{
    return !empty($data) ? $data : $fallback;
}

// ------------------------------------------------------------------
// Static datasets used by factories (sourced from Kaggle when provided)
// ------------------------------------------------------------------
$filipinoFoods = loadCsvColumn(
    'filipino_foods.csv',
    'food_name',
    [
        'Adobo Flakes', 'Chicken Inasal', 'Pork Sisig', 'Bicol Express', 'Beef Pares',
        'Laing', 'Sinigang na Baboy', 'Kare-Kare', 'Pancit Malabon', 'Halo-Halo',
        'Taho', 'Bibingka', 'Putobumbong', 'Lumpiang Shanghai', 'Caldereta'
    ]
);

$filipinoBeverages = loadCsvColumn(
    'filipino_beverages.csv',
    'product_name',
    [
        'Barako Coffee Beans', 'Muscovado Iced Tea Concentrate', 'Dalandan Juice',
        'Sago\'t Gulaman Mix', 'Buko Pandan Drink', 'Guyabano Nectar', 'Calamansi Concentrate'
    ]
);

$filipinoAppliances = loadCsvColumn(
    'pinoy_appliance_prices.csv',
    'item_name',
    [
        'La Germania Gas Range', 'Kyowa Rice Cooker', 'Hanabishi Blender',
        'Imarflex Turbo Broiler', 'American Home Chest Freezer', 'Fujidenzo Chiller',
        'Sharp Refrigerator', 'Condura Upright Freezer', 'Dowell Food Warmer'
    ]
);

$filipinoRetailers = loadCsvColumn(
    'philippine_retailers.csv',
    'retailer_name',
    [
        'Rustan\'s Marketplace', 'Landers Superstore', 'S&R Membership Shopping',
        'Robinsons Appliances', 'Abenson', 'AllHome', 'WalterMart', 'Puregold Price Club'
    ]
);

$filipinoSuppliers = loadCsvColumn(
    'philippine_suppliers.csv',
    'supplier',
    [
        'Metro Manila Provisions', 'VisMin Culinary Goods', 'North Luzon Agro Traders',
        'Island Chef Supply', 'Palawan Seafood Partners', 'Bicolandia Spices Cooperative'
    ]
);

$customerNames = ensureValues(
    loadCsvColumn('philippine_business_registry.csv', 'business_name', []),
    [
        'Makati Food Ventures', 'Cebu Bistro Collective', 'Davao Catering Guild', 'BGC Grand Hotel',
        'QC Restaurant Chain', 'Mandaluyong Commissary', 'Pasig Food Hub', 'Ortigas Café Cooperative',
        'Laguna Agro Distribution', 'Taguig Catering Services', 'Manila Food Truck', 'Cebu Food Park',
        'Davao Food Hub', 'BGC Food Hall', 'Makati Food Court', 'Pasig Food Strip'
    ]
);

$productDescriptors = [
    'Heritage', 'Premier', 'Golden', 'Metro', 'Island', 'Sunrise',
    'Summit', 'Bayanihan', 'Harbor', 'Fusion', 'Vista', 'Lakeshore'
];

$philippineLocations = [
    'Manila', 'Cebu', 'Davao', 'Bacolod', 'Iloilo', 'Baguio',
    'Cagayan de Oro', 'Zamboanga', 'Dagupan', 'General Santos', 'Subic', 'Clark'
];

$stapleFoods = [
    'Sinandomeng Premium Rice', 'Jasmine White Rice', 'Dinorado Rice', 'Black Rice', 'Brown Rice', 'Milagrosa Rice',
    'Royal Umbrella Rice', 'Korean Sticky Rice', 'Adlai Grain', 'Whole Corn Grits', 'Organic Red Rice', 'Japanese Rice'
];

$snackBrands = [
    'Rebisco Sandwich', 'Fita Crackers', 'Hansel Mocha', 'SkyFlakes', 'Chippy', 'Piattos', 'Nova Multigrain',
    'Clover Chips', 'V-Cut', 'Mang Juan Chicharron', 'Oishi Prawn Crackers', 'Martys Cracklin', 'Cheez-It', 'Pretzels',
    'Moby Caramel Popcorn', 'Curly Tops', 'Flat Tops', 'Choc Nut', 'Cloud 9', 'Presto Peanut Butter', 'Cream-O',
    'Magic Flakes', 'Monde Butter Cookies', 'Goldilocks Polvoron', 'Red Ribbon Brownies'
];

$beverageBrands = [
    'Milo', 'Bear Brand Fortified Milk Powder', 'Alaska Evaporada', 'Alaska Condensada', 'Nescafe Original', 'Great Taste White',
    'Energen Cereal Drink', 'Quaker Oats', 'Coca-Cola', 'Royal Tru-Orange', 'Sprite', 'Mountain Dew', 'Pepsi', 'RC Cola',
    'Gatorade', 'Pocari Sweat', 'Zest-O Dalandan', 'C2 Green Tea', 'Lipton Iced Tea', 'Minute Maid', 'Del Monte Pineapple Juice',
    'Mogu Mogu', 'Calamansi Juice Concentrate', 'San Miguel Beer', 'Tanduay Ice', 'Emperador Light'
];

$condimentBases = [
    'UFC Banana Catsup', 'Silver Swan Soy Sauce', 'Datu Puti Cane Vinegar', 'Marca Piña Oyster Sauce', 'Knorr Liquid Seasoning',
    'Maggi Savor', 'Mama Sita Barbecue Marinade', 'Mang Tomas All Purpose Sauce', 'Lady\'s Choice Mayonnaise',
    'Best Foods Real Mayo', 'Lola Remedios Ginger Brew', 'Golden Fiesta Cooking Oil', 'Baguio Canola Oil', 'La Española Olive Oil',
    'McCormick Black Pepper', 'Himalayan Pink Salt', 'Ginebra San Miguel Gin', 'Kikkoman Soy Sauce', 'Tabasco Pepper Sauce'
];

$groceryEssentials = [
    'King Sue Ham', 'Purefoods Tender Juicy Hotdog', 'Magnolia Chicken', 'Monterey Pork Chops', 'Bounty Fresh Chicken', 'CDO Tocino',
    'Swift Corned Beef', 'Argentina Corned Beef', 'Delimondo Corned Beef', 'Century Tuna Flakes', '555 Sardines', 'Ligo Sardines',
    'Jolly Mushrooms', 'Del Monte Spaghetti Sauce', 'Clara Ole Carbonara Sauce', 'Ricoa Chocolate Chips', 'M.Y. San Grahams',
    'Angel Evaporated Milk', 'Arla Cheddar Cheese', 'Quickmelt Cheese', 'Gardenia Classic White Bread', 'Biscocho Haus Toast Bread',
    'Hopia Mongo', 'Pastillas de Leche'
];

$bakedGoods = [
    'Goldilocks Ensaymada', 'Goldilocks Mocha Roll', 'Red Ribbon Black Forest', 'Mary Grace Cheese Roll',
    'Kumori Hanjuku Cheese', 'Tous Les Jours Cream Bread', 'Wildflour Croissant', 'Conti\'s Mango Bravo',
    'Purple Oven Revel Bar', 'Bakery Fair Donuts', 'Pan de Manila Spanish Bread', 'Hizon\'s Cake'
];

$personalCareBases = [
    'Safeguard Soap', 'Belo Kojic Soap', 'Olay Body Wash', 'Johnson\'s Baby Soap', 'Dove Nourishing Soap', 'Palmolive Shampoo',
    'Cream Silk Conditioner', 'Head & Shoulders Shampoo', 'Pantene Detox', 'Colgate Total Toothpaste', 'Closeup Deep Action',
    'Hapee Toothpaste', 'Listerine Mouthwash', 'Belo Sunblock', 'Beach Hut Sunscreen', 'Human Nature Lotion', 'Myra-E Lotion',
    'Bioderm Germicidal Soap', 'Kojie San Cleanser', 'Bioré UV Aqua Rich'
];

$cleaningBases = [
    'Surf Detergent', 'Tide Ultra', 'Ariel Power Gel', 'Pride Powder', 'Champion Detergent', 'Calla Concentrate',
    'Downy Fabric Conditioner', 'Comfort Fabric Conditioner', 'Joy Dishwashing Liquid', 'Axion Paste', 'Domex Toilet Cleaner',
    'Zonrox Color Safe', 'Clorox Bleach', 'Mr Muscle Glass Cleaner', 'Lysol Disinfectant', 'Glade Aerosol', 'Ambi Pur Car Freshener'
];

$laptopBases = [
    'Asus ROG Strix', 'Acer Predator Helios', 'MSI Katana', 'MSI Stealth', 'Lenovo Legion', 'HP Victus',
    'HP Omen', 'Dell XPS 13', 'Dell Inspiron 16', 'Alienware m16', 'Gigabyte Aero', 'Razer Blade', 'Acer Swift', 'Huawei MateBook',
    'Surface Laptop', 'MacBook Air M3', 'MacBook Pro M3', 'Samsung Galaxy Book', 'Asus ZenBook', 'Framework Laptop'
];

$majorAppliances = [
    'Samsung Smart Refrigerator', 'LG InstaView Refrigerator', 'Panasonic Inverter Fridge', 'Sharp Chest Freezer',
    'Fujidenzo Chest Freezer', 'Condura Chiller', 'La Germania 5-Burner Gas Range', 'Tecnogas Cooking Range',
    'Electrolux Front Load Washer', 'Whirlpool Top Load Washer', 'Toshiba Washing Machine', 'American Home Aircon',
    'Carrier Optima Aircon', 'Kolin Split Type Aircon', 'Midea Window Type AC', 'Fujitsu Inverter AC', 'Gree Split Type Aircon'
];

$smallAppliances = [
    'Hanabishi Air Fryer', 'Imarflex Turbo Broiler', 'Dowell Stand Mixer', 'American Heritage Blender', 'Kyowa Rice Cooker',
    '3D Steamer', 'Asahi Electric Fan', 'Oster Toaster Oven', 'Philips Air Purifier', 'Instant Pot Duo', 'LocknLock Air Fryer',
    'Chef\'s Classics Food Processor', 'Imarflex Induction Cooker', 'Fuma Pressure Cooker', 'Camel Electric Kettle'
];

$frozenGoods = [
    'Magnolia Chicken Nuggets', 'Bounty Fresh Chicken Tapa', 'Monterey Beef Tapa', 'Purefoods Beef Burger Patty',
    'CDO Chicken Franks', 'San Miguel Chicken Nuggets', 'Holiday Ham Slices', 'King Sue Bacon', 'Aristocrat BBQ',
    'Chowking Siopao', 'Siomai House Pork', 'Kowloon Siopao', 'Dimsum Break Dumplings'
];

$cannedGoods = [
    'Del Monte Pineapple Chunks', 'Dole Fruit Cocktail', 'Heinz Baked Beans', 'Campbell Cream of Mushroom', 'Spam Luncheon Meat',
    'Maling Luncheon Meat', 'CDO Liver Spread', 'Lady\'s Choice Chicken Spread', 'Century Bangus Fillet', 'San Marino Mackerel',
    'Mega Sardines', 'Connetta Mackerel', 'Argentina Meat Loaf'
];

$beverageVariants = ['Classic', 'Zero Sugar', 'Lite', 'Dalandan', 'Calamansi', 'Pineapple', 'Mango', 'Lychee', 'Berry'];
$beverageSizes = ['250ml', '330ml', '500ml', '1L', '1.5L', '2L', '6s Bottles', '12s Bottles'];

$stapleVariants = ['Premium', 'Organic', 'Well-Milled', 'Glutinous', 'Fortified', 'Long Grain', 'Short Grain', 'Special'];
$stapleSizes = ['1kg', '2kg', '5kg', '10kg', '25kg', '50kg'];

$snackVariants = ['Original', 'Cheese', 'Barbecue', 'Sour Cream', 'Wasabi', 'Truffle', 'Honey Butter', 'Spicy Garlic'];
$snackSizes = ['40g', '80g', '120g', '200g', 'Party Pack', 'Family Pack'];

$condimentVariants = ['Original', 'Sweet', 'Spicy', 'Garlic', 'Calamansi', 'Smoky', 'Roasted', 'Zesty'];
$condimentSizes = ['150ml', '250ml', '500ml', '750ml', '1L', '1.8L', '5L'];

$groceryVariants = ['Classic', 'Garlic', 'Sweet Style', 'Original', 'Smoked', 'Herbed', 'Peppercorn', 'Honey Glazed'];
$grocerySizes = ['250g', '300g', '400g', '500g', '800g', '1kg', '1.3kg'];

$bakedVariants = ['Classic', 'Ube', 'Mocha', 'Chocolate', 'Caramel', 'Cream Cheese', 'Butternut', 'Pandan'];
$bakedSizes = ['Single', 'Mini', 'Party Box', 'Tray', '6s Pack', '12s Pack'];

$personalVariants = ['Fresh', 'Moisture Plus', 'Cooling', 'Antibac', 'Sensitive', 'Hydra', 'Ultra Clean', 'Detox'];
$personalSizes = ['90g', '135g', '200g', '400ml', '600ml', '1L', 'Refill Pack'];

$cleaningVariants = ['Original', 'Citrus', 'Floral', 'Lavender', 'Ocean Breeze', 'Mountain Fresh', 'Hypoallergenic', 'Antibac'];
$cleaningSizes = ['250g', '500g', '900g', '1.5kg', '2kg', '3kg', '500ml', '800ml', '1L', '3.6L'];

$laptopVariants = ['RTX 4050', 'RTX 4060', 'RTX 4070', 'Intel Evo', 'Ryzen Edition', 'OLED 120Hz', 'QHD 240Hz', 'Creator Series'];

$applianceVariants = ['Smart Series', 'Inverter', 'WiFi Ready', 'Energy Saver', 'Slim Type', 'Pro Kitchen', 'All-in-One'];

$smallApplianceVariants = ['Digital', 'Stainless', 'Glass Top', 'Compact', 'Heavy Duty', 'Programmable', 'Turbo Fan'];

$frozenVariants = ['Classic', 'Garlic Pepper', 'Teriyaki', 'Sweet Chili', 'Korean BBQ', 'Honey Garlic', 'Truffle Butter'];
$frozenSizes = ['300g', '500g', '700g', '1kg', '1.2kg', 'Party Tray'];

$cannedVariants = ['In Oil', 'In Water', 'Hot & Spicy', 'Tomato Sauce', 'Chili Garlic', 'Calamansi', 'BBQ Sauce', 'Light'];
$cannedSizes = ['155g', '180g', '200g', '250g', '400g', '850g'];

$inventoryCatalog = array_merge(
    buildCatalog($stapleFoods, 'Staple Foods', $stapleVariants, $stapleSizes),
    buildCatalog($snackBrands, 'Snacks', $snackVariants, $snackSizes),
    buildCatalog($beverageBrands, 'Beverages', $beverageVariants, $beverageSizes),
    buildCatalog($condimentBases, 'Condiments & Sauces', $condimentVariants, $condimentSizes),
    buildCatalog($groceryEssentials, 'Grocery Essentials', $groceryVariants, $grocerySizes),
    buildCatalog($bakedGoods, 'Bakery', $bakedVariants, $bakedSizes),
    buildCatalog($personalCareBases, 'Personal Care', $personalVariants, $personalSizes),
    buildCatalog($cleaningBases, 'Home Care', $cleaningVariants, $cleaningSizes),
    buildCatalogWithoutSizes($laptopBases, 'Computers & Laptops', $laptopVariants),
    buildCatalogWithoutSizes($majorAppliances, 'Major Appliances', $applianceVariants),
    buildCatalogWithoutSizes($smallAppliances, 'Small Appliances', $smallApplianceVariants),
    buildCatalog($frozenGoods, 'Frozen Goods', $frozenVariants, $frozenSizes),
    buildCatalog($cannedGoods, 'Canned & Processed', $cannedVariants, $cannedSizes)
);

$catalogMap = [];
foreach ($inventoryCatalog as $item) {
    $catalogMap[$item['name']] = $item;
}
$inventoryCatalog = array_values($catalogMap);
shuffle($inventoryCatalog);
if (count($inventoryCatalog) < SEED_COUNT_PER_COLLECTION) {
    throw new RuntimeException('Insufficient unique catalog entries for inventory seeding.');
}
$inventoryCatalogForInventory = array_slice($inventoryCatalog, 0, SEED_COUNT_PER_COLLECTION);

$inventoryCategories = array_unique(array_map(fn($item) => $item['category'], $inventoryCatalog));
$projectStatuses = ['planning', 'active', 'on_hold', 'completed'];
$orderStatuses = ['pending', 'processing', 'shipping', 'completed', 'cancelled'];
$quotationStatuses = ['draft', 'pending', 'approved', 'rejected', 'expired'];
$shipmentStatuses = ['pending', 'ready_pickup', 'in_transit', 'delivered', 'failed'];
$carriers = ['LBC Express', 'J&T Express', 'DHL Philippines', 'Ninja Van', '2GO Express'];
$taxTypes = ['Regular Corporate Income Tax', 'MCIT'];
$accountTypeMatrix = [
    ['code' => '1000', 'type' => 'asset', 'sub' => 'current_asset'],
    ['code' => '2000', 'type' => 'liability', 'sub' => 'current_liability'],
    ['code' => '3000', 'type' => 'equity', 'sub' => 'retained_earnings'],
    ['code' => '4000', 'type' => 'income', 'sub' => 'operating_income'],
    ['code' => '4900', 'type' => 'income', 'sub' => 'other_income'],
    ['code' => '5000', 'type' => 'expense', 'sub' => 'cost_of_goods_sold'],
    ['code' => '6000', 'type' => 'expense', 'sub' => 'operating_expense'],
];

$generatedOrderNumbers = [];
$generatedAccounts = [];
$skuRegistry = [];
$barcodeRegistry = [];
$lotRegistry = [];
$fdaRegistrationRegistry = [];

// ------------------------------------------------------------------
// Inventory
// ------------------------------------------------------------------
seedCollection('inventory', $collectionsToSeed['inventory'], function ($i) use ($inventoryCatalog, $inventoryCatalogForInventory, $filipinoSuppliers, $inventoryCategories) {
    global $skuRegistry, $barcodeRegistry;
    $quantity = mt_rand(0, 500);
    $cost = randomMoney(30, 800);
    $sell = $cost + randomMoney(5, 300);
    $catalogItem = $inventoryCatalogForInventory[$i - 1] ?? $inventoryCatalog[array_rand($inventoryCatalog)];
    $sku = generateUniqueCode('SKU-', 8, $skuRegistry);
    $barcode = generateUniqueCode('BR', 12, $barcodeRegistry, true);

    return [
        'sku' => $sku,
        'barcode' => $barcode,
        'name' => uniqueProductLabel($catalogItem['name']),
        'type' => $catalogItem['category'],
        'quantity' => $quantity,
        'min_stock' => mt_rand(5, 25),
        'max_stock' => mt_rand(100, 600),
        'cost_price' => $cost,
        'sell_price' => $sell,
        'price' => $sell,
        'lifespan' => mt_rand(3, 24) . ' months',
        'unit_of_measure' => 'pcs',
        'location' => 'Warehouse ' . mt_rand(1, 5),
        'supplier' => uniqueBusinessLabel(pick($filipinoSuppliers), 'Supply Depot'),
        'date_added' => randomUtcDate('-18 months', 'now'),
        'created_at' => randomUtcDate('-18 months', 'now'),
        'updated_at' => randomUtcDate('-18 months', 'now'),
    ];
});

// ------------------------------------------------------------------
// Quotations
// ------------------------------------------------------------------
seedCollection('quotations', $collectionsToSeed['quotations'], function ($i) use ($quotationStatuses, $customerNames, $inventoryCatalog) {
    $total = randomMoney(QUOTATION_MIN, QUOTATION_MAX);
    $date = randomUtcDate('-12 months', 'now');
    $validUntil = randomUtcDate('now', '+3 months');
    $lineItem = $inventoryCatalog[array_rand($inventoryCatalog)];
    return [
        'quote_number' => sprintf('QUO-%s-%05d', date('Ym'), $i),
        'customer' => uniqueBusinessLabel(pick($customerNames)),
        'date' => $date,
        'valid_until' => $validUntil,
        'total' => $total,
        'status' => pick($quotationStatuses),
        'items' => [
            [
                'description' => uniqueProductLabel($lineItem['name']),
                'quantity' => mt_rand(5, 50),
                'price' => randomMoney(QUOTATION_MIN / 40, QUOTATION_MAX / 10),
            ],
            [
                'description' => uniqueProductLabel($inventoryCatalog[array_rand($inventoryCatalog)]['name']),
                'quantity' => mt_rand(5, 50),
                'price' => randomMoney(QUOTATION_MIN / 40, QUOTATION_MAX / 10),
            ],
        ],
        'created_at' => $date,
        'updated_at' => randomUtcDate('-12 months', 'now'),
    ];
});

// ------------------------------------------------------------------
// Invoices
// ------------------------------------------------------------------
seedCollection('invoices', $collectionsToSeed['invoices'], function ($i) use ($customerNames, $taxTypes, $inventoryCatalog) {
    $issueDate = randomUtcDate('-18 months', 'now');
    $dueDate = randomUtcDate('now', '+2 months');
    $total = randomMoney(REVENUE_MIN, REVENUE_MAX);
    $paid = mt_rand(0, 1) ? $total : randomMoney(0, $total);
    $status = $paid >= $total ? 'paid' : (mt_rand(0, 1) ? 'pending' : 'overdue');
    $vatable = $total * 0.82;
    $zeroRated = $total * 0.08;
    $exempt = $total - $vatable - $zeroRated;
    $item = $inventoryCatalog[array_rand($inventoryCatalog)];

    return [
        'invoice_number' => sprintf('INV-%s-%05d', date('Ym'), $i),
        'customer_name' => uniqueBusinessLabel(pick($customerNames), 'Division'),
        'customer_email' => 'customer' . $i . '@example.com',
        'date' => $issueDate,
        'due' => $dueDate,
        'created_at' => $issueDate,
        'updated_at' => randomUtcDate('-18 months', 'now'),
        'total_amount' => $total,
        'total' => $total,
        'paid' => $paid,
        'status' => $status,
        'payment_currency' => 'PHP',
        'vatable_sales' => $vatable,
        'zero_rated_sales' => $zeroRated,
        'exempt_sales' => $exempt,
        'tax_type' => pick($taxTypes),
        'items' => [
            [
                'description' => uniqueProductLabel($item['name']),
                'quantity' => mt_rand(1, 20),
                'price' => randomMoney(REVENUE_MIN / 200, REVENUE_MAX / 40),
            ],
        ],
    ];
});

// ------------------------------------------------------------------
// Orders (sales & purchase)
// ------------------------------------------------------------------
seedCollection('orders', $collectionsToSeed['orders'], function ($i) use ($customerNames, $orderStatuses, &$generatedOrderNumbers, $inventoryCatalog) {
    $orderNumber = sprintf('ORD-%s-%05d', date('Ym'), $i);
    $generatedOrderNumbers[] = $orderNumber;
    $total = randomMoney(PURCHASE_MIN, PURCHASE_MAX);
    $paid = randomMoney(0, $total);
    $type = mt_rand(0, 1) ? 'Sales' : 'Purchase';
    $item = $inventoryCatalog[array_rand($inventoryCatalog)];

    return [
        'order_number' => $orderNumber,
        'type' => $type,
        'customer' => uniqueBusinessLabel(pick($customerNames), 'Outlet'),
        'date' => randomUtcDate('-18 months', 'now'),
        'created_at' => randomUtcDate('-18 months', 'now'),
        'total_amount' => $total,
        'total' => $total,
        'paid' => $paid,
        'status' => pick($orderStatuses),
        'items' => [
            [
                'description' => uniqueProductLabel($item['name']),
                'quantity' => mt_rand(5, 80),
                'price' => randomMoney(PURCHASE_MIN / 200, PURCHASE_MAX / 40),
            ],
        ],
    ];
});

// ------------------------------------------------------------------
// Projects
// ------------------------------------------------------------------
seedCollection('projects', $collectionsToSeed['projects'], function ($i) use ($projectStatuses, $customerNames, $filipinoFoods, $filipinoAppliances) {
    $startDate = randomUtcDate('-18 months', 'now');
    $endDate = randomUtcDate('now', '+18 months');
    $budget = randomMoney(PROJECT_BUDGET_MIN, PROJECT_BUDGET_MAX);
    $spent = randomMoney(PROJECT_SPENT_MIN, min($budget, PROJECT_SPENT_MAX));
    $focus = mt_rand(0, 1) ? pick($filipinoFoods) : pick($filipinoAppliances);

    return [
        'name' => uniqueProductLabel($focus . ' Program'),
        'client' => uniqueBusinessLabel(pick($customerNames), 'HQ'),
        'start' => $startDate,
        'end' => $endDate,
        'budget' => $budget,
        'spent' => $spent,
        'status' => pick($projectStatuses),
        'description' => 'Kaggle-aligned initiative covering ' . $focus . ' distribution.',
        'project_manager' => 'PM ' . mt_rand(1, 40),
        'created_at' => $startDate,
        'updated_at' => randomUtcDate('-18 months', 'now'),
    ];
});

// ------------------------------------------------------------------
// Shipments
// ------------------------------------------------------------------
seedCollection('shipments', $collectionsToSeed['shipments'], function ($i) use ($shipmentStatuses, $carriers, &$generatedOrderNumbers, $filipinoRetailers) {
    $orderNumber = $generatedOrderNumbers
        ? $generatedOrderNumbers[array_rand($generatedOrderNumbers)]
        : sprintf('ORD-%s-%05d', date('Ym'), $i);

    return [
        'shipment_number' => sprintf('SHP-%s-%05d', date('Ym'), $i),
        'order' => $orderNumber,
        'customer' => uniqueBusinessLabel(pick($filipinoRetailers), 'Storefront'),
        'carrier' => pick($carriers),
        'tracking' => strtoupper(substr(md5($i . microtime(true)), 0, 12)),
        'status' => pick($shipmentStatuses),
        'date' => randomUtcDate('-12 months', 'now'),
        'created_at' => randomUtcDate('-12 months', 'now'),
        'updated_at' => randomUtcDate('-12 months', 'now'),
    ];
});

// ------------------------------------------------------------------
// Chart of Accounts
// ------------------------------------------------------------------
seedCollection('chart_of_accounts', $collectionsToSeed['chart_of_accounts'], function ($i) use ($accountTypeMatrix, &$generatedAccounts) {
    $base = $accountTypeMatrix[$i % count($accountTypeMatrix)];
    $code = str_pad($base['code'] + $i, 5, '0', STR_PAD_LEFT);
    $account = [
        'account_code' => $code,
        'account_name' => ucfirst($base['type']) . ' Account ' . $i,
        'account_type' => $base['type'],
        'account_subtype' => $base['sub'],
        'is_active' => true,
        'is_system' => false,
        'balance' => randomMoney(-200000, 400000),
        'created_at' => randomUtcDate('-24 months', 'now'),
        'updated_at' => randomUtcDate('-24 months', 'now'),
    ];
    $generatedAccounts[] = $account;
    return $account;
});

// ------------------------------------------------------------------
// Journal Entries (double-entry)
// ------------------------------------------------------------------
$interestAccounts = ['Interest Income', 'Bank Interest'];
$dividendAccounts = ['Dividend Income', 'Investment Income'];

seedCollection('journal_entries', $collectionsToSeed['journal_entries'], function ($i) use (&$generatedAccounts, $interestAccounts, $dividendAccounts) {
    $amount = randomMoney(JOURNAL_ENTRY_MIN, JOURNAL_ENTRY_MAX);
    $entryDate = randomUtcDate('-18 months', 'now');
    $entryType = ['general', 'expense', 'income', 'debit'][mt_rand(0, 3)];
    $expenseAccountType = pick(['Operating Expense', 'Administrative Expense', 'Selling Expense', 'Interest Income', 'Dividend Income']);

    $lineItems = [
        [
            'account_code' => '6000',
            'account_name' => 'Expense Account ' . $i,
            'account_type' => $expenseAccountType,
            'debit' => $amount,
            'credit' => 0,
        ],
        [
            'account_code' => '4000',
            'account_name' => 'Sales Revenue',
            'account_type' => 'Sales Revenue',
            'debit' => 0,
            'credit' => $amount,
        ],
    ];

    // Occasionally add explicit interest/dividend income lines to satisfy Part III aggregation
    if ($i % 50 === 0) {
        $interestAmount = randomMoney(1000, 10000);
        $lineItems[] = [
            'account_code' => '4900',
            'account_name' => 'Interest Income',
            'account_type' => pick($interestAccounts),
            'debit' => 0,
            'credit' => $interestAmount,
        ];
    }
    if ($i % 75 === 0) {
        $dividendAmount = randomMoney(1000, 8000);
        $lineItems[] = [
            'account_code' => '4910',
            'account_name' => 'Dividend Income',
            'account_type' => pick($dividendAccounts),
            'debit' => 0,
            'credit' => $dividendAmount,
        ];
    }

    return [
        'entry_number' => sprintf('JE-%s-%05d', date('Ymd'), $i),
        'entry_date' => $entryDate,
        'entry_type' => $entryType,
        'description' => 'Auto-generated journal entry ' . $i,
        'amount' => $amount,
        'status' => 'posted',
        'line_items' => $lineItems,
        'created_at' => $entryDate,
        'updated_at' => randomUtcDate('-18 months', 'now'),
    ];
});

// ------------------------------------------------------------------
// Financial Reports
// ------------------------------------------------------------------
$reportTypes = ['income_statement', 'balance_sheet', 'cash_flow'];
seedCollection('financial_reports', $collectionsToSeed['financial_reports'], function ($i) use ($reportTypes) {
    $reportType = pick($reportTypes);
    $year = mt_rand(date('Y') - 3, date('Y'));
    $month = mt_rand(1, 12);

    $revenue = randomMoney(50000, 2000000);
    $cogs = randomMoney(20000, $revenue * 0.8);
    $grossProfit = max(0, $revenue - $cogs);
    $opex = randomMoney(10000, $grossProfit);
    $netIncome = $grossProfit - $opex;

    return [
        'report_type' => $reportType,
        'period' => sprintf('%04d-%02d', $year, $month),
        'year' => $year,
        'month' => $month,
        'prepared_at' => randomUtcDate('-6 months', 'now'),
        'data' => [
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $opex,
            'net_income' => $netIncome,
            'total_assets' => randomMoney(100000, 8000000),
            'total_liabilities' => randomMoney(50000, 5000000),
            'cash_flow_from_operations' => randomMoney(-200000, 2000000),
        ],
    ];
});

// ------------------------------------------------------------------
// FDA Products with random expirations
// ------------------------------------------------------------------
seedCollection('fda_products', $collectionsToSeed['fda_products'], function ($i) use ($inventoryCatalog, $filipinoSuppliers) {
    global $lotRegistry, $fdaRegistrationRegistry;
    $product = $inventoryCatalog[$i % count($inventoryCatalog)];

    $lotNumber = generateUniqueCode('LOT-', 10, $lotRegistry);
    $registration = generateUniqueCode('FDA-', 10, $fdaRegistrationRegistry);

    $now = time();
    $manufacturedTs = $now - mt_rand(90, 720) * 86400;
    $isExpired = mt_rand(0, 100) < 25;
    $expiryTs = $manufacturedTs + mt_rand(60, 540) * 86400;

    if ($isExpired) {
        if ($expiryTs > $now) {
            $expiryTs = $now - mt_rand(1, 60) * 86400;
        }
    } else {
        if ($expiryTs <= $now) {
            $expiryTs = $now + mt_rand(60, 720) * 86400;
        }
    }

    return [
        'name' => $product['name'],
        'category' => $product['category'],
        'batch' => $lotNumber,
        'fda_registration' => $registration,
        'manufacturer' => uniqueBusinessLabel(pick($filipinoSuppliers), 'Manufacturing Plant'),
        'quantity' => mt_rand(50, 5000),
        'expiry' => new UTCDateTime($expiryTs * 1000),
        'manufactured_at' => new UTCDateTime($manufacturedTs * 1000),
        'last_inspected_at' => randomUtcDate('-90 days', 'now'),
        'active' => !$isExpired,
        'status' => $isExpired ? 'expired' : 'active',
        'storage_location' => 'FDA Warehouse ' . mt_rand(1, 10),
        'notes' => $isExpired ? 'Flagged for disposal' : 'Cleared for distribution',
        'created_at' => randomUtcDate('-12 months', 'now'),
        'updated_at' => randomUtcDate('-3 months', 'now'),
    ];
});

// ------------------------------------------------------------------
// BIR Forms (monthly/quarterly filings with million-level amounts)
// ------------------------------------------------------------------
$birFormTypes = ['1601C', '2550M', '2550Q', '1702-EX', 'RAMSAY 307'];
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
seedCollection('bir_forms', $collectionsToSeed['bir_forms'], function ($i) use ($birFormTypes, $months) {
    $formType = pick($birFormTypes);
    $year = mt_rand(date('Y') - 2, date('Y'));
    $periodMonth = pick($months);

    return [
        'form_type' => $formType,
        'period' => $periodMonth . ' ' . $year,
        'period_year' => $year,
        'status' => pick(['filed', 'pending', 'for_review']),
        'amount' => randomMoney(BIR_WITHHELD_MIN, BIR_WITHHELD_MAX),
        'total_withheld' => randomMoney(BIR_WITHHELD_MIN, BIR_WITHHELD_MAX),
        'total_duties' => randomMoney(BIR_WITHHELD_MIN / 2, BIR_WITHHELD_MAX / 2),
        'import_vat' => randomMoney(BIR_WITHHELD_MIN / 3, BIR_WITHHELD_MAX / 3),
        'date' => randomUtcDate('-18 months', 'now'),
        'due_date' => randomUtcDate('-12 months', 'now'),
        'created_at' => randomUtcDate('-18 months', 'now'),
        'updated_at' => randomUtcDate('-6 months', 'now'),
    ];
});

// ------------------------------------------------------------------
// Tax Payments / Credits
// ------------------------------------------------------------------
seedCollection('payments', $collectionsToSeed['payments'], function ($i) {
    $paymentDate = randomUtcDate('-18 months', 'now');
    return [
        'payment_reference' => sprintf('TAXPAY-%s-%05d', date('Ymd'), $i),
        'payment_type' => 'tax_credit',
        'amount' => randomMoney(TAX_CREDIT_MIN, TAX_CREDIT_MAX),
        'payment_method' => pick(['bank_transfer', 'gcash', 'paymaya', 'landbank_linkbiz']),
        'payment_date' => $paymentDate,
        'created_at' => $paymentDate,
        'updated_at' => randomUtcDate('-6 months', 'now'),
    ];
});

echo "\n==========================================\n";
echo "  High-volume seeding finished\n";
echo "  Collections seeded: " . count($collectionsToSeed) . "\n";
echo "  Total docs inserted: " . (count($collectionsToSeed) * SEED_COUNT_PER_COLLECTION) . "\n";
echo "  Duration: " . round(microtime(true) - $startTime, 2) . "s\n";
echo "==========================================\n\n";
echo "Next steps:\n";
echo " - Run: php scripts/seed-sample-data.php\n";
echo " - Log in as usual and open inventory, quotations, invoicing, orders, projects,\n";
echo "   shipping, chart-of-accounts, journal entries, and financial reports pages to\n";
echo "   validate that all tables, analytics, and BIR Form 1702 modal now show live data.\n";
echo " - Remember: this script inserts large volumes (≈ " . number_format(SEED_COUNT_PER_COLLECTION) . " per collection),\n";
echo "   so expect initial load times while MongoDB builds indexes.\n\n";
