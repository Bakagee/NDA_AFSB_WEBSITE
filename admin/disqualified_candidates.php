<?php
require_once '../database_connection.php';

$conn = connectDB();

// Documentation disqualified
$doc_sql = "SELECT s.state_name, c.chest_number, c.nda_application_number, CONCAT(c.first_name, ' ', c.surname, IF(c.other_name IS NOT NULL, CONCAT(' ', c.other_name), '')) as full_name, dv.verification_details
FROM candidates c
JOIN states s ON c.state_id = s.id
JOIN document_verifications dv ON c.candidate_id = dv.candidate_id
WHERE dv.verification_status = 'rejected'";
$doc_result = $conn->query($doc_sql);
$doc_disqualified = [];
while ($row = $doc_result->fetch_assoc()) {
    $details = json_decode($row['verification_details'], true);
    $reason = isset($details['disqualification_reason']) ? $details['disqualification_reason'] : '';
    $row['reason'] = $reason;
    $doc_disqualified[$row['state_name']][] = $row;
}

// Medical disqualified
$med_sql = "SELECT s.state_name, c.chest_number, c.nda_application_number, CONCAT(c.first_name, ' ', c.surname, IF(c.other_name IS NOT NULL, CONCAT(' ', c.other_name), '')) as full_name, ms.overall_fitness
FROM candidates c
JOIN states s ON c.state_id = s.id
JOIN medical_screening ms ON c.candidate_id = ms.candidate_id";
$med_result = $conn->query($med_sql);
$med_disqualified = [];
while ($row = $med_result->fetch_assoc()) {
    $fitness = json_decode($row['overall_fitness'], true);
    if (isset($fitness['status']) && $fitness['status'] === 'not_fit') {
        $row['reason'] = isset($fitness['reason']) ? $fitness['reason'] : '';
        $med_disqualified[$row['state_name']][] = $row;
    }
}
$conn->close();
ksort($doc_disqualified);
ksort($med_disqualified);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disqualified Candidates - NDA AFSB</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .state-section { margin-bottom: 2.5rem; }
        .state-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 1rem; color: #C8102E; }
        .table-candidates { background: #fff; border-radius: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .table th, .table td { vertical-align: middle !important; }
        .search-box { max-width: 350px; margin-bottom: 2rem; }
        .print-btn { float: right; margin-bottom: 1.5rem; }
        @media (max-width: 600px) {
            .state-title { font-size: 1.1rem; }
            .table-candidates { font-size: 0.95rem; }
        }
        @media print {
            body { background: #fff !important; }
            .no-print, .search-box, .print-btn { display: none !important; }
            .letterhead { display: block !important; }
            .state-section { page-break-inside: avoid; }
        }
        .letterhead {
            display: none;
            text-align: center;
            margin-bottom: 2rem;
        }
        .letterhead img {
            height: 70px;
            margin-bottom: 0.5rem;
        }
        .letterhead h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
            color: #007A33;
        }
        .letterhead h4 {
            font-size: 1.1rem;
            font-weight: 500;
            color: #222;
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
        <div class="no-print">
            <h2 class="mb-4">Disqualified Candidates</h2>
            <input type="text" class="form-control search-box" id="searchInput" placeholder="Search by name, chest number, or application number...">
        </div>
        <!-- Documentation Section -->
        <div id="doc-section">
            <div class="letterhead">
                <img src="../img/nda-logo.png" alt="NDA Logo">
                <h2>NIGERIAN DEFENCE ACADEMY</h2>
                <h4>List of Disqualified Candidates (Documentation)</h4>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="state-title">Documentation Disqualified</h3>
                <button class="btn btn-danger print-btn no-print" onclick="printSection('doc-section')">Print</button>
            </div>
            <?php foreach ($doc_disqualified as $state => $candidates): ?>
                <div class="state-section">
                    <div class="state-title"><?php echo htmlspecialchars($state); ?></div>
                    <div class="table-responsive">
                        <table class="table table-striped table-candidates">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Chest Number</th>
                                    <th>NDA Application Number</th>
                                    <th>Reason for Disqualification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['chest_number']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['nda_application_number']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['reason']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Medicals Section -->
        <div id="med-section">
            <div class="letterhead">
                <img src="../img/nda-logo.png" alt="NDA Logo">
                <h2>NIGERIAN DEFENCE ACADEMY</h2>
                <h4>List of Disqualified Candidates (Medicals)</h4>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="state-title">Medicals Disqualified</h3>
                <button class="btn btn-danger print-btn no-print" onclick="printSection('med-section')">Print</button>
            </div>
            <?php foreach ($med_disqualified as $state => $candidates): ?>
                <div class="state-section">
                    <div class="state-title"><?php echo htmlspecialchars($state); ?></div>
                    <div class="table-responsive">
                        <table class="table table-striped table-candidates">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Chest Number</th>
                                    <th>NDA Application Number</th>
                                    <th>Reason for Disqualification</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($candidate['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['chest_number']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['nda_application_number']); ?></td>
                                    <td><?php echo htmlspecialchars($candidate['reason']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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
    function printSection(sectionId) {
        // Hide all letterheads, then show only the one in the section
        $('.letterhead').hide();
        $('#' + sectionId + ' .letterhead').show();
        // Print only the section
        var printContents = document.getElementById(sectionId).innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
    </script>
</body>
</html> 