<?php
session_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log');

try {
    if (!file_exists('db.php')) {
        error_log("book.php: db.php file not found");
        throw new Exception("Database configuration file missing.");
    }
    require_once 'db.php';

    $cities = [
        'Pakistan' => ['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Peshawar', 'Quetta', 'Multan'],
        'International' => ['Dubai', 'New York', 'London', 'Paris', 'Tokyo', 'Sydney', 'Toronto', 'Singapore']
    ];
    $all_cities = array_merge($cities['Pakistan'], $cities['International']);

    $confirmation_message = '';

    $query_params = [
        'pickup_location' => isset($_POST['pickup_location']) ? trim($_POST['pickup_location']) : '',
        'start_date' => isset($_POST['start_date']) ? $_POST['start_date'] : '',
        'return_date' => isset($_POST['return_date']) ? $_POST['return_date'] : '',
        'car_type' => isset($_POST['car_type']) ? trim($_POST['car_type']) : '',
        'fuel_type' => isset($_POST['fuel_type']) ? trim($_POST['fuel_type']) : '',
        'brand' => isset($_POST['brand']) ? trim($_POST['brand']) : '',
        'sort' => isset($_POST['sort']) ? trim($_POST['sort']) : 'price_asc'
    ];

    error_log("book.php: Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("book.php: POST Data: " . print_r($_POST, true));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_car'])) {
        $car_id = isset($_POST['car_id']) ? (int)$_POST['car_id'] : 0;
        $pickup_location = isset($_POST['pickup_location']) ? trim($_POST['pickup_location']) : '';
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
        $return_date = isset($_POST['return_date']) ? $_POST['return_date'] : '';
        $total_price = isset($_POST['total_price']) ? (float)$_POST['total_price'] : 0;

        if (empty($car_id) || empty($pickup_location) || empty($start_date) || empty($return_date) || $total_price <= 0) {
            $confirmation_message = "Error: All fields are required for booking.";
            error_log("book.php: Validation failed - missing required fields");
        } elseif (!in_array($pickup_location, $all_cities)) {
            $confirmation_message = "Error: Invalid pickup location selected.";
            error_log("book.php: Invalid pickup location: $pickup_location");
        } elseif (strtotime($return_date) <= strtotime($start_date)) {
            $confirmation_message = "Error: Return date must be after start date.";
            error_log("book.php: Invalid date range: start_date=$start_date, return_date=$return_date");
        } else {
            $user_id = 1; // Demo user

            try {
                $stmt = $pdo->prepare("SELECT * FROM cars WHERE car_id = ? AND available = TRUE");
                $stmt->execute([$car_id]);
                $car = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$car) {
                    $confirmation_message = "Error: Selected car is not available.";
                    error_log("book.php: Car not available, car_id=$car_id");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, car_id, pickup_location, start_date, return_date, total_price, booking_status) VALUES (?, ?, ?, ?, ?, ?, 'Confirmed')");
                    $stmt->execute([$user_id, $car_id, $pickup_location, $start_date, $return_date, $total_price]);
                    $confirmation_message = "Booking confirmed! Your car is reserved in $pickup_location.";
                    error_log("book.php: Booking successful, car_id=$car_id, pickup_location=$pickup_location");
                }
            } catch (PDOException $e) {
                error_log("book.php: Database Error: " . $e->getMessage());
                $confirmation_message = "Error: Database error during booking.";
            }
        }
    } else {
        error_log("book.php: Direct access or invalid POST data");
        $confirmation_message = "Error: Invalid request. Please book through the search form.";
    }

    $query_params['confirmation_message'] = urlencode($confirmation_message);
    $query_string = http_build_query($query_params);
    error_log("book.php: Redirecting to index.php?$query_string");
    header("Location: index.php?$query_string");
    exit;

} catch (Exception $e) {
    error_log("book.php: Fatal Error: " . $e->getMessage());
    http_response_code(500);
    die("An error occurred. Please try again later.");
}
?>
