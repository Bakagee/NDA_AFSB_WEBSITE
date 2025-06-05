<?php
require_once '../database_connection.php';

// Fetch all candidates grouped by state
$conn = connectDB();
$sql = "SELECT s.state_name, c.chest_number, c.nda_application_number, CONCAT(c.first_name, ' ', c.surname, IF(c.other_name IS NOT NULL, CONCAT(' ', c.other_name), '')) as full_name
        FROM candidates c
        JOIN states s ON c.state_id = s.id
        ORDER BY s.state_name, c.chest_number";
$result = $conn->query($sql);

// Group candidates by state
$candidates_by_state = [];
while ($row = $result->fetch_assoc()) {
    $candidates_by_state[$row['state_name']][] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates - NDA AFSB</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .state-section { margin-bottom: 2.5rem; }
        .state-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 1rem; color: #007A33; }
        .table-candidates { background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .table th, .table td { vertical-align: middle !important; }
        .search-box { max-width: 350px; margin-bottom: 2rem; }
        @media (max-width: 600px) {
            .state-title { font-size: 1.1rem; }
            .table-candidates { font-size: 0.95rem; }
        }
        .back-button {
            position: fixed;
            top: 80px;
            left: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .back-button:hover {
            transform: translateX(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        @media (max-width: 768px) {
            .back-button {
                top: 70px;
                left: 15px;
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <button class="back-button" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i>
    </button>

    <div class="container py-4">
        <h2 class="mb-4">Candidates by State</h2>
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search by name, chest number, or application number...">
        <?php foreach ($candidates_by_state as $state => $candidates): ?>
            <div class="state-section">
                <div class="state-title"><?php echo htmlspecialchars($state); ?></div>
                <div class="table-responsive">
                    <table class="table table-striped table-candidates">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Chest Number</th>
                                <th>NDA Application Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['chest_number']); ?></td>
                                <td><?php echo htmlspecialchars($candidate['nda_application_number']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#searchInput').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('.state-section').each(function() {
                var section = $(this);
                var found = false;
                section.find('tbody tr').each(function() {
                    var row = $(this);
                    var text = row.text().toLowerCase();
                    if (text.indexOf(value) > -1) {
                        row.show();
                        found = true;
                    } else {
                        row.hide();
                    }
                });
                section.toggle(found || value === '');
            });
        });
    });
    </script>
</body>
</html> 