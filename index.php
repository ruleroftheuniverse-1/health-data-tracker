<?php
// Database setup
$db_file = 'app.db';
$db = new PDO('sqlite:' . $db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Auto-create table if it doesn't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        category TEXT,
        subtype TEXT,
        data TEXT
    )
");

// Handle form submissions
session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['category'])) {
        $_SESSION['category'] = $_POST['category'];
        header('Location: ?step=2');
        exit;
    } elseif (isset($_POST['subtype']) && isset($_SESSION['category'])) {
        $category = $_SESSION['category'];
        $subtype = $_POST['subtype'];
        
        // Handle data based on category
        $data = '';
        if ($category === 'food' && isset($_POST['items'])) {
            $data = json_encode($_POST['items']);
        } elseif ($category === 'unfood') {
            $bristol = $_POST['bristol'] ?? '';
            $volume = $_POST['volume'] ?? '';
            $data = json_encode(['bristol' => $bristol, 'volume' => $volume]);
        }
        
        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO entries (category, subtype, data)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$category, $subtype, $data]);
        
        unset($_SESSION['category']);
        header('Location: ?step=3');
        exit;
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $db->query("SELECT * FROM entries ORDER BY timestamp DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="export.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['id', 'timestamp', 'category', 'subtype', 'data']);
    
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Health Tracker</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; }
        button { padding: 10px 20px; margin: 5px; font-size: 16px; cursor: pointer; }
        button:hover { background-color: #ddd; }
        input[type="checkbox"] { margin-right: 10px; }
        label { display: block; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .container { margin: 20px 0; }
    </style>
</head>
<body>
    <?php if ($step === 1): ?>
        <h1>Health Tracker - Step 1</h1>
        <p>Select a category:</p>
        <form method="POST">
            <button type="submit" name="category" value="med">Med</button>
            <button type="submit" name="category" value="sleep">Sleep</button>
            <button type="submit" name="category" value="food">Food</button>
            <button type="submit" name="category" value="move">Move</button>
            <button type="submit" name="category" value="unfood">Unfood</button>
        </form>

    <?php elseif ($step === 2): ?>
        <h1>Health Tracker - Step 2</h1>
        <form method="POST">
            <?php $category = $_SESSION['category'] ?? ''; ?>
            
            <?php if ($category === 'med'): ?>
                <p>Select time:</p>
                <button type="submit" name="subtype" value="morning">Morning</button>
                <button type="submit" name="subtype" value="afternoon">Afternoon</button>
                <button type="submit" name="subtype" value="night">Night</button>

            <?php elseif ($category === 'sleep'): ?>
                <p>Sleep quality:</p>
                <button type="submit" name="subtype" value="down">Down</button>
                <button type="submit" name="subtype" value="up">Up</button>

            <?php elseif ($category === 'food'): ?>
                <p>What did you eat?</p>
                <label><input type="checkbox" name="items[]" value="carb"> Carb</label>
                <label><input type="checkbox" name="items[]" value="protein"> Protein</label>
                <label><input type="checkbox" name="items[]" value="fat"> Fat</label>
                <label><input type="checkbox" name="items[]" value="micros"> Micros</label>
                <label><input type="checkbox" name="items[]" value="water"> Water</label>
                <label><input type="checkbox" name="items[]" value="electros"> Electros</label>
                <button type="submit" name="subtype" value="food_entry">Submit</button>

            <?php elseif ($category === 'move'): ?>
                <p>Type of movement:</p>
                <button type="submit" name="subtype" value="mobility">Mobility</button>
                <button type="submit" name="subtype" value="stretch">Stretch</button>
                <button type="submit" name="subtype" value="cardio">Cardio</button>
                <button type="submit" name="subtype" value="strength">Strength</button>

            <?php elseif ($category === 'unfood'): ?>
                <p>Bristol scale (1-7):</p>
                <div>
                    <label><input type="radio" name="bristol" value="1" required> 1</label>
                    <label><input type="radio" name="bristol" value="2"> 2</label>
                    <label><input type="radio" name="bristol" value="3"> 3</label>
                    <label><input type="radio" name="bristol" value="4"> 4</label>
                    <label><input type="radio" name="bristol" value="5"> 5</label>
                    <label><input type="radio" name="bristol" value="6"> 6</label>
                    <label><input type="radio" name="bristol" value="7"> 7</label>
                </div>
                
                <p>Volume:</p>
                <div>
                    <label><input type="radio" name="volume" value="4oz" required> 4oz</label>
                    <label><input type="radio" name="volume" value="8oz"> 8oz</label>
                    <label><input type="radio" name="volume" value="16oz"> 16oz</label>
                    <label><input type="radio" name="volume" value="32oz"> 32oz</label>
                </div>
                <button type="submit" name="subtype" value="unfood_entry">Submit</button>
            <?php endif; ?>
        </form>
        <button onclick="history.back()" style="background-color: #ccc;">Back</button>

    <?php elseif ($step === 3): ?>
        <h1>Health Tracker - Recent Entries</h1>
        <?php
            $stmt = $db->query("SELECT * FROM entries ORDER BY timestamp DESC LIMIT 20");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Category</th>
                    <th>Subtype</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['timestamp']) ?></td>
                        <td><?= htmlspecialchars($row['category']) ?></td>
                        <td><?= htmlspecialchars($row['subtype']) ?></td>
                        <td><?= htmlspecialchars($row['data']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="container">
            <button onclick="window.location='?step=1'">New Entry</button>
            <button onclick="window.location='?export=csv'">Export CSV</button>
        </div>
    <?php endif; ?>
</body>
</html>