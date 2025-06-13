<?php
session_start();
require_once 'db.php';

// Debug: Log the requested URL
error_log("Requested URL: " . $_SERVER['REQUEST_URI']);

// List of cities (Pakistan and foreign)
$cities = [
    'Pakistan' => ['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Peshawar', 'Quetta', 'Multan'],
    'International' => ['Dubai', 'New York', 'London', 'Paris', 'Tokyo', 'Sydney', 'Toronto', 'Singapore']
];

// Flatten cities for dropdown and validation
$all_cities = array_merge($cities['Pakistan'], $cities['International']);

// Initialize variables
$search_results = [];
$search_performed = false;
$query_params = [];
$confirmation_message = isset($_GET['confirmation_message']) ? urldecode($_GET['confirmation_message']) : '';
$error_message = '';

// Debug: Check if cars exist in the database
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cars WHERE available = TRUE");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['count'] == 0) {
        $error_message = "No cars available in the database. Please ensure the cars table is populated.";
    }
} catch (PDOException $e) {
    error_log("Database Error in Index: " . $e->getMessage());
    $error_message = "Database error: Unable to fetch cars.";
}

// Handle search
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['pickup_location']) && isset($_GET['start_date']) && isset($_GET['return_date'])) {
    $pickup_location = trim($_GET['pickup_location']);
    $start_date = $_GET['start_date'];
    $return_date = $_GET['return_date'];
    $car_type = isset($_GET['car_type']) ? trim($_GET['car_type']) : '';
    $fuel_type = isset($_GET['fuel_type']) ? trim($_GET['fuel_type']) : '';
    $brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
    $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'price_asc';
    $search_performed = true;

    // Debug: Log search parameters
    error_log("Search Params: pickup_location=$pickup_location, start_date=$start_date, return_date=$return_date, car_type=$car_type, fuel_type=$fuel_type, brand=$brand, sort=$sort");

    // Validate inputs
    if (!in_array($pickup_location, $all_cities)) {
        $error_message = "Error: Invalid pickup location selected.";
    } elseif (strtotime($return_date) <= strtotime($start_date)) {
        $error_message = "Error: Return date must be after start date.";
    } else {
        // Store query parameters for redirection
        $query_params = [
            'pickup_location' => $pickup_location,
            'start_date' => $start_date,
            'return_date' => $return_date,
            'car_type' => $car_type,
            'fuel_type' => $fuel_type,
            'brand' => $brand,
            'sort' => $sort
        ];

        // Build search query
        $query = "SELECT * FROM cars WHERE available = TRUE";
        $params = [];
        if (!empty($car_type)) {
            $valid_car_types = ['Sedan', 'SUV', 'Truck', 'Van', 'Convertible'];
            if (in_array($car_type, $valid_car_types)) {
                $query .= " AND car_type = :car_type";
                $params[':car_type'] = $car_type;
            } else {
                $error_message = "Error: Invalid car type selected.";
            }
        }
        if (!empty($fuel_type)) {
            $valid_fuel_types = ['Petrol', 'Diesel', 'Electric', 'Hybrid'];
            if (in_array($fuel_type, $valid_fuel_types)) {
                $query .= " AND fuel_type = :fuel_type";
                $params[':fuel_type'] = $fuel_type;
            } else {
                $error_message = "Error: Invalid fuel type selected.";
            }
        }
        if (!empty($brand)) {
            $valid_brands = ['Toyota', 'Honda', 'Ford', 'BMW', 'Tesla'];
            if (in_array($brand, $valid_brands)) {
                $query .= " AND brand = :brand";
                $params[':brand'] = $brand;
            } else {
                $error_message = "Error: Invalid brand selected.";
            }
        }
        if ($sort == 'price_asc') {
            $query .= " ORDER BY price_per_day ASC";
        } elseif ($sort == 'price_desc') {
            $query .= " ORDER BY price_per_day DESC";
        }

        if (empty($error_message)) {
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($search_results)) {
                    $error_message = "No cars found matching your criteria. Try removing some filters or checking the database.";
                }
            } catch (PDOException $e) {
                error_log("Search Query Error: " . $e->getMessage());
                $error_message = "Error fetching cars: Unable to process search.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentACar Clone</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #2d3436;
        }

        header {
            background: linear-gradient(135deg, #1e90ff, #00c4ff);
            color: white;
            padding: 40px 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        header h1 {
            font-size: 2.8em;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        header p {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .search-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            max-width: 1000px;
            margin: 30px auto;
        }

        .search-container form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            align-items: center;
        }

        .search-container select, .search-container input {
            padding: 12px;
            border: 1px solid #dfe6e9;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .search-container select:focus, .search-container input:focus {
            border-color: #1e90ff;
            outline: none;
        }

        .search-container button {
            padding: 12px 25px;
            background: #1e90ff;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s, transform 0.2s;
        }

        .search-container button:hover {
            background: #0984e3;
            transform: translateY(-2px);
        }

        .car-list {
            max-width: 1200px;
            margin: 30px auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            padding: 0 20px;
        }

        .car-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .car-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .car-card img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-bottom: 1px solid #f1f1f1;
        }

        .car-card-content {
            padding: 20px;
        }

        .car-card-content h3 {
            font-size: 1.8em;
            color: #1e90ff;
            margin-bottom: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .car-card-content p {
            color: #636e72;
            font-size: 0.95em;
            margin-bottom: 8px;
        }

        .car-card-content .price {
            font-size: 1.4em;
            color: #2d3436;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .car-card-content button {
            width: 100%;
            padding: 14px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: bold;
            transition: background 0.3s, transform 0.2s;
            text-transform: uppercase;
        }

        .car-card-content button:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .confirmation, .error {
            max-width: 800px;
            margin: 20px auto;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 1.1em;
        }

        .confirmation {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .no-results {
            max-width: 800px;
            margin: 20px auto;
            text-align: center;
            color: #636e72;
            font-size: 1.2em;
        }

        @media (max-width: 768px) {
            .search-container form {
                grid-template-columns: 1fr;
            }

            .car-list {
                grid-template-columns: 1fr;
            }

            header h1 {
                font-size: 2em;
            }
        }

        @media (max-width: 480px) {
            .search-container {
                padding: 15px;
                margin: 15px;
            }

            .car-card img {
                height: 180px;
            }

            .car-card-content h3 {
                font-size: 1.5em;
            }

            .car-card-content button {
                font-size: 1em;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>RentACar Clone</h1>
        <p>Book your dream car today!</p>
    </header>

    <div class="search-container">
        <form id="searchForm" method="GET" action="index.php">
            <select name="pickup_location" required>
                <option value="" disabled <?php echo !isset($_GET['pickup_location']) ? 'selected' : ''; ?>>Select Pickup Location</option>
                <optgroup label="Pakistan">
                    <?php foreach ($cities['Pakistan'] as $city): ?>
                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo isset($_GET['pickup_location']) && $_GET['pickup_location'] == $city ? 'selected' : ''; ?>><?php echo htmlspecialchars($city); ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="International">
                    <?php foreach ($cities['International'] as $city): ?>
                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo isset($_GET['pickup_location']) && $_GET['pickup_location'] == $city ? 'selected' : ''; ?>><?php echo htmlspecialchars($city); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
            <input type="date" name="start_date" required value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
            <input type="date" name="return_date" required value="<?php echo isset($_GET['return_date']) ? htmlspecialchars($_GET['return_date']) : ''; ?>">
            <select name="car_type">
                <option value="">All Car Types</option>
                <option value="Sedan" <?php echo isset($_GET['car_type']) && $_GET['car_type'] == 'Sedan' ? 'selected' : ''; ?>>Sedan</option>
                <option value="SUV" <?php echo isset($_GET['car_type']) && $_GET['car_type'] == 'SUV' ? 'selected' : ''; ?>>SUV</option>
                <option value="Truck" <?php echo isset($_GET['car_type']) && $_GET['car_type'] == 'Truck' ? 'selected' : ''; ?>>Truck</option>
                <option value="Van" <?php echo isset($_GET['car_type']) && $_GET['car_type'] == 'Van' ? 'selected' : ''; ?>>Van</option>
                <option value="Convertible" <?php echo isset($_GET['car_type']) && $_GET['car_type'] == 'Convertible' ? 'selected' : ''; ?>>Convertible</option>
            </select>
            <select name="fuel_type">
                <option value="">All Fuel Types</option>
                <option value="Petrol" <?php echo isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                <option value="Diesel" <?php echo isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                <option value="Electric" <?php echo isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                <option value="Hybrid" <?php echo isset($_GET['fuel_type']) && $_GET['fuel_type'] == 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
            </select>
            <select name="brand">
                <option value="">All Brands</option>
                <option value="Toyota" <?php echo isset($_GET['brand']) && $_GET['brand'] == 'Toyota' ? 'selected' : ''; ?>>Toyota</option>
                <option value="Honda" <?php echo isset($_GET['brand']) && $_GET['brand'] == 'Honda' ? 'selected' : ''; ?>>Honda</option>
                <option value="Ford" <?php echo isset($_GET['brand']) && $_GET['brand'] == 'Ford' ? 'selected' : ''; ?>>Ford</option>
                <option value="BMW" <?php echo isset($_GET['brand']) && $_GET['brand'] == 'BMW' ? 'selected' : ''; ?>>BMW</option>
                <option value="Tesla" <?php echo isset($_GET['brand']) && $_GET['brand'] == 'Tesla' ? 'selected' : ''; ?>>Tesla</option>
            </select>
            <select name="sort">
                <option value="price_asc" <?php echo isset($_GET['sort']) && $_GET['sort'] == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                <option value="price_desc" <?php echo isset($_GET['sort']) && $_GET['sort'] == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
            </select>
            <button type="submit">Search Cars</button>
        </form>
    </div>

    <?php if ($error_message): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if ($confirmation_message): ?>
        <div class="confirmation <?php echo strpos($confirmation_message, 'Error') === false ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($confirmation_message); ?>
        </div>
    <?php endif; ?>

    <div class="car-list">
        <?php if ($search_performed && empty($search_results)): ?>
            <div class="no-results">No cars found matching your criteria. Try removing some filters or checking the database.</div>
        <?php elseif ($search_performed): ?>
            <?php foreach ($search_results as $car): ?>
                <div class="car-card">
                    <img src="<?php echo htmlspecialchars($car['image']); ?>" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                    <div class="car-card-content">
                        <h3><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h3>
                        <p><?php echo htmlspecialchars($car['description']); ?></p>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars($car['car_type']); ?></p>
                        <p><strong>Fuel:</strong> <?php echo htmlspecialchars($car['fuel_type']); ?></p>
                        <p class="price">$<?php echo number_format($car['price_per_day'], 2); ?>/day</p>
                        <button onclick="bookCar(<?php echo $car['car_id']; ?>, <?php echo htmlspecialchars($car['price_per_day']); ?>)">Book Now</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-results">Please use the search form to find available cars.</div>
        <?php endif; ?>
    </div>

    <script>
        function bookCar(carId, pricePerDay) {
            console.log('bookCar called with carId:', carId, 'pricePerDay:', pricePerDay);

            const pickupLocation = document.querySelector('select[name="pickup_location"]').value;
            const startDate = document.querySelector('input[name="start_date"]').value;
            const returnDate = document.querySelector('input[name="return_date"]').value;

            console.log('Form inputs:', { pickupLocation, startDate, returnDate });

            if (!pickupLocation || !startDate || !returnDate) {
                console.error('Missing required fields');
                alert('Please fill in all required fields (Pickup Location, Start Date, Return Date).');
                return;
            }

            const start = new Date(startDate);
            const end = new Date(returnDate);
            const days = (end - start) / (1000 * 60 * 60 * 24);
            if (days <= 0) {
                console.error('Invalid date range');
                alert('Return date must be after start date.');
                return;
            }

            const totalPrice = days * pricePerDay;
            console.log('Calculated totalPrice:', totalPrice);

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'book.php';

            const fields = [
                { name: 'book_car', value: '1' },
                { name: 'car_id', value: carId },
                { name: 'pickup_location', value: pickupLocation },
                { name: 'start_date', value: startDate },
                { name: 'return_date', value: returnDate },
                { name: 'total_price', value: totalPrice.toFixed(2) },
                // Preserve search query parameters
                <?php foreach ($query_params as $key => $value): ?>
                    { name: '<?php echo $key; ?>', value: '<?php echo htmlspecialchars($value); ?>' },
                <?php endforeach; ?>
            ];

            console.log('Form fields:', fields);

            fields.forEach(field => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = field.name;
                input.value = field.value;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            console.log('Submitting form to book.php');
            form.submit();
        }
    </script>
</body>
</html>
